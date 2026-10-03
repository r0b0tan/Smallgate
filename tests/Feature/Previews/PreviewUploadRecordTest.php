<?php

/**
 * Who may upload a draft, and what the database itself refuses (ADR 0004).
 * The rows of preview_uploads name the only folders Smallgate ever deletes,
 * so their constraints are part of what is under test.
 */

use App\Enums\PreviewUploadStatus;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\PreviewUpload;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function uploadFor(Preview $preview, PreviewUploadStatus $status = PreviewUploadStatus::Pending, array $columns = []): PreviewUpload
{
    $upload = new PreviewUpload;
    $upload->preview_id = $preview->id;
    $upload->status = $status;
    $upload->created_at = now();

    foreach ($columns as $column => $value) {
        $upload->{$column} = $value;
    }

    $upload->save();

    return $upload;
}

function finishedColumns(): array
{
    return ['size_bytes' => 1024, 'file_count' => 3, 'finished_at' => now()];
}

/* ------------------------------------------------------------------ policy */

it('lets an administrator upload a draft for a static preview', function () {
    expect(Gate::forUser($this->admin())->allows('upload', Preview::factory()->create()))->toBeTrue();
});

it('lets nobody upload for an upstream preview', function () {
    expect(Gate::forUser($this->admin())->allows('upload', Preview::factory()->upstream()->create()))->toBeFalse();
});

it('never lets a customer upload, not even to their own preview', function () {
    $customer = Customer::factory()->create();
    $preview = Preview::factory()
        ->for_project(Project::factory()->for_customer($customer)->create())
        ->available()
        ->create();

    expect(Gate::forUser($this->customerUser($customer))->allows('upload', $preview))->toBeFalse();
});

it('lets nobody upload while static previews are switched off', function () {
    $preview = Preview::factory()->create();

    config(['previews.target_types' => ['upstream_url']]);

    expect(Gate::forUser($this->admin())->allows('upload', $preview))->toBeFalse();
});

/* ---------------------------------------------------------------- database */

it('allows only one running upload per preview', function (PreviewUploadStatus $running) {
    $preview = Preview::factory()->create();
    uploadFor($preview, $running);

    uploadFor($preview);
})->with([PreviewUploadStatus::Pending, PreviewUploadStatus::Extracting])
    ->throws(QueryException::class, 'preview_uploads_one_running');

it('accepts a new upload once the last one has finished', function (PreviewUploadStatus $finished, array $columns) {
    $preview = Preview::factory()->create();
    uploadFor($preview, $finished, $columns);

    uploadFor($preview);

    expect($preview->uploads()->count())->toBe(2);
})->with([
    'ready' => [PreviewUploadStatus::Ready, fn () => finishedColumns()],
    'failed' => [PreviewUploadStatus::Failed, fn () => ['error' => 'Kaputt.', 'finished_at' => now()]],
    'removed' => [PreviewUploadStatus::Removed, fn () => finishedColumns()],
]);

it('lets another preview upload at the same time', function () {
    uploadFor(Preview::factory()->create());
    uploadFor(Preview::factory()->create());

    expect(PreviewUpload::query()->count())->toBe(2);
});

it('refuses an unpacked upload without size, file count or end', function (string $missing) {
    $columns = finishedColumns();
    unset($columns[$missing]);

    uploadFor(Preview::factory()->create(), PreviewUploadStatus::Ready, $columns);
})->with(['size_bytes', 'file_count', 'finished_at'])
    ->throws(QueryException::class, 'preview_uploads_ready_check');

it('refuses a failed upload without a message', function () {
    uploadFor(Preview::factory()->create(), PreviewUploadStatus::Failed, ['finished_at' => now()]);
})->throws(QueryException::class, 'preview_uploads_failed_check');

it('refuses an unknown status', function () {
    $upload = uploadFor(Preview::factory()->create());

    DB::table('preview_uploads')->where('id', $upload->id)->update(['status' => 'live']);
})->throws(QueryException::class, 'preview_uploads_status_check');

it('refuses negative counts', function () {
    $upload = uploadFor(Preview::factory()->create());

    DB::table('preview_uploads')->where('id', $upload->id)->update(['size_bytes' => -1]);
})->throws(QueryException::class, 'preview_uploads_counts_check');

it('keeps a preview with uploads from being deleted before its folders are gone', function () {
    $preview = Preview::factory()->create();
    uploadFor($preview);

    $preview->delete();
})->throws(QueryException::class, 'preview_uploads_preview_id_foreign');

it('keeps the upload when the uploading user is deleted', function () {
    $admin = $this->admin();
    $upload = uploadFor(Preview::factory()->create(), columns: ['user_id' => $admin->id]);

    User::query()->whereKey($admin->id)->delete();

    expect($upload->fresh())->not->toBeNull()
        ->and($upload->fresh()->user_id)->toBeNull();
});

it('assigns nothing in bulk', function () {
    expect(fn () => new PreviewUpload(['status' => 'ready', 'preview_id' => 'x']))
        ->toThrow(MassAssignmentException::class);
});
