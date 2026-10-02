<?php

/**
 * Upstream previews are opened at their own URL, path included -- e.g.
 * https://customer.example.com/zimmerei-holzmann -- after the portal has
 * checked access. They need no subdomain; static previews still do.
 */

use App\Enums\PreviewStatus;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Database\QueryException;

function upstreamHost(): string
{
    return ((array) config('previews.allowed_upstream_hosts'))[0];
}

it('releases an upstream preview without a subdomain and sends the customer to its path', function () {
    $customer = Customer::factory()->create();
    $project = Project::factory()->for_customer($customer)->create();
    $url = 'https://'.upstreamHost().'/zimmerei-holzmann';

    $this->actingAs($this->admin())
        ->post(route('admin.projects.previews.store', $project), [
            'name' => 'Entwurf 1',
            'slug' => 'entwurf-1',
            'hostname' => '',
            'target_type' => 'upstream_url',
            'target' => $url,
        ])
        ->assertSessionHasNoErrors();

    $preview = Preview::sole();

    $this->post(route('admin.projects.previews.provision', [$project, $preview]))
        ->assertSessionHas('status');

    expect($preview->fresh()->status)->toBe(PreviewStatus::Available)
        ->and($preview->fresh()->hostname)->toBeNull();

    $this->flushSession();
    $user = $this->customerUser($customer);

    // The card shows the address the customer will actually land on.
    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee(upstreamHost().'/zimmerei-holzmann');

    $this->actingAs($user)
        ->get(route('portal.previews.show', [$project, $preview]))
        ->assertRedirect($url);
});

it('keeps a live upstream preview editable without a subdomain', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->upstream()->create();

    $this->actingAs($this->admin())
        ->patch(route('admin.projects.previews.update', [$project, $preview]), [
            'name' => 'Neuer Name',
            'slug' => $preview->slug,
            'hostname' => '',
            'target_type' => 'upstream_url',
            'target' => $preview->target,
        ])
        ->assertSessionHasNoErrors();

    expect($preview->fresh()->name)->toBe('Neuer Name');
});

it('still requires a subdomain to release a static preview', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->create([
        'target' => '/srv/previews/holzmann',
    ]);

    $this->actingAs($this->admin())
        ->post(route('admin.projects.previews.provision', [$project, $preview]))
        ->assertSessionHas('error');

    expect($preview->fresh()->status)->not->toBe(PreviewStatus::Available);
});

it('does not redirect once the host is no longer allow-listed', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();
    $preview = Preview::factory()->for_project($project)->upstream()->create();

    config(['previews.allowed_upstream_hosts' => ['other.example.test']]);

    $this->actingAs($user)
        ->get(route('portal.previews.show', [$project, $preview]))
        ->assertOk()
        ->assertSee('derzeit nicht erreichbar');
});

it('does not turn a tampered target into an open redirect', function (string $target) {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();
    $preview = Preview::factory()->for_project($project)->upstream()->create();

    // Changed in the database, bypassing every form.
    $preview->forceFill(['target' => $target])->saveQuietly();

    $this->actingAs($user)
        ->get(route('portal.previews.show', [$project, $preview]))
        ->assertOk()
        ->assertSee('derzeit nicht erreichbar');
})->with([
    'foreign host' => 'https://evil.example/zimmerei-holzmann',
    'plain http' => 'http://staging.clickit-digital.test/zimmerei-holzmann',
    'credentials' => 'https://user:pass@staging.clickit-digital.test/x',
    'lookalike host' => 'https://staging.clickit-digital.test.evil.example/x',
]);

it('answers 404 for another customer\'s upstream preview', function () {
    $user = $this->customerUser();
    $preview = Preview::factory()->upstream()->create();

    $this->actingAs($user)
        ->get(route('portal.previews.show', [$preview->project_id, $preview]))
        ->assertNotFound();
});

it('enforces the subdomain rule for static previews in the database as well', function () {
    $upstream = Preview::factory()->upstream()->create();
    expect($upstream->hostname)->toBeNull();

    expect(fn () => Preview::factory()->available()->create(['hostname' => null]))
        ->toThrow(QueryException::class);
});
