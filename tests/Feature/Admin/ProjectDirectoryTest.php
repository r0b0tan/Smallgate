<?php

/**
 * "Ordner anlegen": the request fixes the name and queues the job, the job
 * creates <customer>/<project> below the configured root and nowhere else.
 */

use App\Enums\ActivityAction;
use App\Enums\DirectoryStatus;
use App\Jobs\CreateProjectDirectory;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/smallgate-directories-'.Str::lower(Str::random(10));
    mkdir($this->root);
    config(['smallgate.project_directories.root' => $this->root]);

    $this->project = Project::factory()
        ->for_customer(Customer::factory()->create(['slug' => 'holzmann']))
        ->create(['slug' => 'relaunch']);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('creates the project folder below the root and records it', function () {
    $admin = $this->admin();

    $this->actingAs($admin)
        ->post(route('admin.projects.directory.store', $this->project))
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHas('status');

    $project = $this->project->fresh();

    expect($project->directory)->toBe('holzmann/relaunch')
        ->and($project->directory_status)->toBe(DirectoryStatus::Created)
        ->and($project->directory_created_at)->not->toBeNull()
        ->and(is_dir($this->root.'/holzmann/relaunch'))->toBeTrue();

    $activity = Activity::query()->where('action', ActivityAction::ProjectDirectoryCreated)->sole();
    expect($activity->actor_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($this->project->id);
});

it('queues the job and shows the progress while it runs', function () {
    Queue::fake();

    $this->actingAs($this->admin())
        ->post(route('admin.projects.directory.store', $this->project));

    Queue::assertPushed(CreateProjectDirectory::class, fn ($job) => $job->projectId === $this->project->id);
    expect($this->project->fresh()->directory_status)->toBe(DirectoryStatus::Pending)
        ->and(is_dir($this->root.'/holzmann'))->toBeFalse();

    $this->get(route('admin.projects.show', $this->project))
        ->assertOk()
        ->assertSee('data-poll', false)
        ->assertSee('Ordner wird angelegt');
});

it('stops polling once the folder exists', function () {
    $this->actingAs($this->admin())
        ->post(route('admin.projects.directory.store', $this->project));

    $this->get(route('admin.projects.show', $this->project))
        ->assertOk()
        ->assertDontSee('data-poll', false)
        ->assertSee($this->root.'/holzmann/relaunch');
});

it('changes nothing on a second click', function () {
    $admin = $this->admin();
    $this->actingAs($admin)->post(route('admin.projects.directory.store', $this->project));
    $createdAt = $this->project->fresh()->directory_created_at;

    Queue::fake();

    $this->actingAs($admin)
        ->post(route('admin.projects.directory.store', $this->project))
        ->assertSessionHas('status', 'Der Projektordner existiert bereits.');

    Queue::assertNothingPushed();
    expect($this->project->fresh()->directory_created_at->equalTo($createdAt))->toBeTrue()
        ->and(Activity::query()->where('action', ActivityAction::ProjectDirectoryCreated)->count())->toBe(1);
});

it('keeps the folder name once it is fixed, even after a rename', function () {
    $admin = $this->admin();
    $this->actingAs($admin)->post(route('admin.projects.directory.store', $this->project));

    $this->project->update(['slug' => 'neuer-name']);

    expect($this->project->fresh()->directory)->toBe('holzmann/relaunch');
});

it('refuses a name another project already uses', function () {
    $other = Project::factory()->for_customer($this->project->customer)->create(['slug' => 'anderes']);
    DB::table('projects')->where('id', $other->id)->update(['directory' => 'holzmann/relaunch']);

    $this->actingAs($this->admin())
        ->post(route('admin.projects.directory.store', $this->project))
        ->assertSessionHas('error');

    expect($this->project->fresh()->directory_status)->toBeNull();
});

it('accepts a folder that already exists', function () {
    mkdir($this->root.'/holzmann/relaunch', 0775, true);

    $this->actingAs($this->admin())->post(route('admin.projects.directory.store', $this->project));

    expect($this->project->fresh()->directory_status)->toBe(DirectoryStatus::Created);
});

it('fails visibly when the root is missing and can be retried', function () {
    config(['smallgate.project_directories.root' => $this->root.'/fehlt']);
    $admin = $this->admin();

    $this->actingAs($admin)->post(route('admin.projects.directory.store', $this->project));

    $project = $this->project->fresh();
    expect($project->directory_status)->toBe(DirectoryStatus::Failed)
        ->and($project->directory_error)->toContain('PROJECT_DIRECTORY_ROOT')
        ->and(file_exists($this->root.'/fehlt'))->toBeFalse()
        ->and(Activity::query()->where('action', ActivityAction::ProjectDirectoryFailed)->count())->toBe(1);

    $this->get(route('admin.projects.show', $this->project))->assertSee('Erneut versuchen');

    config(['smallgate.project_directories.root' => $this->root]);
    $this->actingAs($admin)->post(route('admin.projects.directory.store', $this->project));

    expect($this->project->fresh()->directory_status)->toBe(DirectoryStatus::Created)
        ->and($this->project->fresh()->directory_error)->toBeNull();
});

it('never follows a symlink out of the root', function () {
    $outside = $this->root.'-outside';
    mkdir($outside);
    symlink($outside, $this->root.'/holzmann');

    try {
        $this->actingAs($this->admin())->post(route('admin.projects.directory.store', $this->project));

        expect($this->project->fresh()->directory_status)->toBe(DirectoryStatus::Failed)
            ->and(is_dir($outside.'/relaunch'))->toBeFalse();
    } finally {
        File::deleteDirectory($outside);
    }
});

it('does nothing when the project is no longer waiting', function () {
    (new CreateProjectDirectory($this->project->id))->handle();

    expect(is_dir($this->root.'/holzmann'))->toBeFalse()
        ->and($this->project->fresh()->directory_status)->toBeNull();
});

it('hides the action from customer users', function () {
    $user = $this->customerUser($this->project->customer);

    $this->actingAs($user)
        ->post(route('admin.projects.directory.store', $this->project))
        ->assertNotFound();

    expect($this->project->fresh()->directory_status)->toBeNull();
});

it('does not allow filling the folder columns', function () {
    foreach (['directory', 'directory_status', 'directory_error', 'directory_created_at'] as $attribute) {
        expect((new Project)->isFillable($attribute))->toBeFalse("[{$attribute}] darf nicht mass assignable sein.");
    }
});

it('rejects a folder name that could leave the root at the database level', function (string $directory) {
    expect(fn () => DB::table('projects')->where('id', $this->project->id)->update(['directory' => $directory]))
        ->toThrow(QueryException::class);
})->with(['../etc', '/etc/passwd', 'holzmann', 'holzmann/relaunch/tief', 'Holzmann/relaunch']);

it('offers a created folder as the target of a new preview', function () {
    config(['previews.allowed_roots' => [$this->root]]);
    $this->actingAs($this->admin())->post(route('admin.projects.directory.store', $this->project));

    $this->get(route('admin.projects.previews.create', $this->project))
        ->assertOk()
        ->assertSee($this->root.'/holzmann/relaunch');
});

it('does not offer the folder when static previews are switched off', function () {
    config(['previews.allowed_roots' => [$this->root], 'previews.target_types' => ['upstream_url']]);
    $this->actingAs($this->admin())->post(route('admin.projects.directory.store', $this->project));

    $this->get(route('admin.projects.previews.create', $this->project))
        ->assertOk()
        ->assertDontSee($this->root.'/holzmann/relaunch');
});
