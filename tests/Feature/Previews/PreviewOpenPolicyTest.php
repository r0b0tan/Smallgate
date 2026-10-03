<?php

/**
 * PreviewPolicy::open decides who may see a preview on its own host
 * (ADR 0003). It runs when the portal hands out a token and again on every
 * request to the preview host.
 */

use App\Enums\PreviewStatus;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function openablePreview(Customer $customer, array $attributes = []): Preview
{
    $project = Project::factory()->for_customer($customer)->create();

    return Preview::factory()->for_project($project)->available()->create($attributes);
}

function canOpen($user, Preview $preview): bool
{
    return Gate::forUser($user)->allows('open', $preview->fresh());
}

it('lets a customer open a live static preview of their own customer', function () {
    $customer = Customer::factory()->create();

    expect(canOpen($this->customerUser($customer), openablePreview($customer)))->toBeTrue();
});

it('keeps a customer out of another customer\'s preview', function () {
    $preview = openablePreview(Customer::factory()->create());

    expect(canOpen($this->customerUser(), $preview))->toBeFalse();
});

it('keeps a customer out of a preview that is not live', function (PreviewStatus $status) {
    $customer = Customer::factory()->create();
    $preview = openablePreview($customer);
    $preview->status = $status;
    $preview->save();

    expect(canOpen($this->customerUser($customer), $preview))->toBeFalse();
})->with([PreviewStatus::Draft, PreviewStatus::Provisioning, PreviewStatus::Disabled, PreviewStatus::Failed]);

it('lets an administrator open a preview before release', function () {
    $preview = openablePreview(Customer::factory()->create());
    $preview->status = PreviewStatus::Draft;
    $preview->save();

    expect(canOpen($this->admin(), $preview))->toBeTrue();
});

it('locks out a blocked user and the users of a deactivated customer', function () {
    $customer = Customer::factory()->create();
    $preview = openablePreview($customer);

    expect(canOpen($this->customerUser($customer, ['is_active' => false]), $preview))->toBeFalse()
        ->and(canOpen($this->admin(['is_active' => false]), $preview))->toBeFalse();

    $user = $this->customerUser($customer);
    $customer->is_active = false;
    $customer->save();

    expect(canOpen($user, $preview))->toBeFalse();
});

it('never opens an upstream preview on a preview host', function () {
    $customer = Customer::factory()->create();
    $project = Project::factory()->for_customer($customer)->create();
    $preview = Preview::factory()->for_project($project)->upstream()->create();

    expect(canOpen($this->customerUser($customer), $preview))->toBeFalse()
        ->and(canOpen($this->admin(), $preview))->toBeFalse();
});

it('refuses a target the allowlist no longer accepts', function () {
    $customer = Customer::factory()->create();
    $preview = openablePreview($customer);

    // Changed outside the application, past every form and the provisioner.
    DB::table('previews')->where('id', $preview->id)->update(['target' => '/etc']);

    expect(canOpen($this->customerUser($customer), $preview))->toBeFalse()
        ->and(canOpen($this->admin(), $preview))->toBeFalse();
});

it('refuses a hostname outside the preview domain', function () {
    $customer = Customer::factory()->create();
    $preview = openablePreview($customer);

    DB::table('previews')->where('id', $preview->id)->update(['hostname' => 'evil.example.com']);

    expect(canOpen($this->customerUser($customer), $preview))->toBeFalse();
});

it('refuses static previews while the type is switched off', function () {
    $customer = Customer::factory()->create();
    $preview = openablePreview($customer);

    config(['previews.target_types' => ['upstream_url']]);

    expect(canOpen($this->customerUser($customer), $preview))->toBeFalse()
        ->and(canOpen($this->admin(), $preview))->toBeFalse();
});
