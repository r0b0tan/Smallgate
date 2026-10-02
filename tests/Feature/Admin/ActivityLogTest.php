<?php

/**
 * The activity log in the administration: what gets recorded, who may read
 * it, and that it copies no personal data and forgets old entries.
 */

use App\Enums\ActivityAction;
use App\Enums\PreviewStatus;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('');
});

// Customer users get a 404 here like everywhere else in the admin area; that
// is covered by the 'admin endpoints' dataset in AdminAccessTest.
it('shows the log to administrators', function () {
    $this->actingAs($this->admin())
        ->get(route('admin.activities.index'))
        ->assertOk()
        ->assertSee('Protokoll');
});

it('records a sign-in with the user as actor', function () {
    $user = $this->customerUser();

    $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::Login)
        ->and($activity->actor_id)->toBe($user->id)
        ->and($activity->customer_id)->toBe($user->customer_id);
});

it('records a failed sign-in against an existing account without an actor', function () {
    $user = $this->customerUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'falsch-falsch-falsch']);

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::LoginFailed)
        ->and($activity->actor_id)->toBeNull()
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->subject_label)->toBeNull();
});

it('records nothing for a sign-in attempt on an unknown address', function () {
    $this->post('/login', ['email' => 'niemand@example.test', 'password' => 'falsch-falsch-falsch']);

    expect(Activity::count())->toBe(0);
});

it('records a failed sign-in of a deactivated customer without an actor', function () {
    $user = $this->customerUser(Customer::factory()->create(['is_active' => false]));

    $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::LoginFailed)
        ->and($activity->actor_id)->toBeNull();
});

it('records deactivating a customer as such, not as a plain edit', function () {
    $admin = $this->admin();
    $customer = Customer::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
        'name' => $customer->name,
        'slug' => $customer->slug,
        'contact_email' => $customer->contact_email,
        'is_active' => '0',
    ]);

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::CustomerDeactivated)
        ->and($activity->actor_id)->toBe($admin->id)
        ->and($activity->customer_id)->toBe($customer->id);
});

it('records an invitation without copying the invited address', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin())->post(route('admin.customers.invitations.store', $customer), [
        'name' => 'Erika Muster',
        'email' => 'erika@example.test',
    ]);

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::InvitationSent)
        ->and($activity->customer_id)->toBe($customer->id)
        ->and($activity->subject_label)->toBeNull()
        ->and(json_encode($activity->getAttributes()))->not->toContain('erika');
});

it('records a provisioning with the version it published', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create(['status' => PreviewStatus::Draft]);

    $this->actingAs($this->admin())
        ->post(route('admin.projects.previews.provision', [$project, $preview]));

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::PreviewProvisioned)
        ->and($activity->customer_id)->toBe($project->customer_id)
        ->and($activity->properties)->toBe(['version' => $preview->fresh()->version]);
});

it('keeps the name of a deleted preview', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->create(['name' => 'Entwurf Startseite']);

    $this->actingAs($this->admin())
        ->delete(route('admin.projects.previews.destroy', [$project, $preview]));

    $this->get(route('admin.activities.index'))
        ->assertSee('Vorschau gelöscht')
        ->assertSee('Entwurf Startseite');
});

it('records a customer\'s answer without the comment', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();
    $preview = Preview::factory()->for_project($project)->available()->create();

    $this->actingAs($user)->post(route('portal.previews.feedback.store', [$project, $preview]), [
        'decision' => 'changes_requested',
        'version' => $preview->version,
        'comment' => 'Bitte das Logo größer',
    ]);

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::FeedbackChangesRequested)
        ->and($activity->actor_id)->toBe($user->id)
        ->and($activity->customer_id)->toBe($customer->id)
        ->and(json_encode($activity->getAttributes()))->not->toContain('Logo');
});

it('filters the log by customer and by topic', function () {
    $admin = $this->admin();
    $mine = Project::factory()->create(['name' => 'Projekt Eins']);
    $other = Project::factory()->create(['name' => 'Projekt Zwei']);

    $this->actingAs($admin);
    Activity::record(ActivityAction::ProjectCreated, $mine);
    Activity::record(ActivityAction::ProjectCreated, $other);
    Activity::record(ActivityAction::CustomerUpdated, $mine->customer);

    $this->get(route('admin.activities.index', ['customer' => $mine->customer_id]))
        ->assertSee('Projekt Eins')
        ->assertDontSee('Projekt Zwei');

    $this->get(route('admin.activities.index', ['group' => 'customers']))
        ->assertSee('Kunde geändert')
        ->assertDontSee('Projekt angelegt');
});

it('hides entries older than the retention period and deletes them with the next one', function () {
    config(['smallgate.activity.retention_days' => 90]);
    $admin = $this->admin();
    $project = Project::factory()->create(['name' => 'Altes Projekt']);

    $this->actingAs($admin);
    $old = Activity::record(ActivityAction::ProjectCreated, $project);
    $old->created_at = Carbon::now()->subDays(91);
    $old->save();

    $this->get(route('admin.activities.index'))
        ->assertDontSee('Projekt angelegt');
    expect(Activity::count())->toBe(1);

    Activity::record(ActivityAction::ProjectUpdated, $project);

    expect(Activity::pluck('action')->all())->toBe([ActivityAction::ProjectUpdated]);
});
