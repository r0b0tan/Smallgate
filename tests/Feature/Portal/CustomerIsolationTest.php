<?php

/**
 * Requirements 6, 7, 10 and 11: a customer sees their own projects, never
 * another customer's, loses access when deactivated, and gets a 404 -- never a
 * 403 -- for foreign ids.
 */

use App\Models\Customer;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Support\Carbon;

/* ----------------------------------------------------------- requirement 6 */

it('shows a customer their own projects', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);

    $mine = Project::factory()->for_customer($customer)->create(['name' => 'Website-Relaunch']);
    Project::factory()->for_customer($customer)->create(['name' => 'Zweites Projekt']);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Website-Relaunch');

    $this->actingAs($user)
        ->get(route('portal.projects.show', $mine))
        ->assertOk()
        ->assertSee('Website-Relaunch');
});

it('shows a customer with a single project everything on the overview', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);

    $only = Project::factory()->for_customer($customer)->create(['name' => 'Einziges Projekt']);
    Preview::factory()->for_project($only)->available()->create(['name' => 'Entwurf 1']);

    // One page with the drafts on it -- no list to click through first.
    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Einziges Projekt')
        ->assertSee('Entwurf 1')
        ->assertSee('Entwurf ansehen');
});

it('shows a customer the available previews of their own project', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);

    $project = Project::factory()->for_customer($customer)->create();
    $available = Preview::factory()->for_project($project)->available()->create(['name' => 'Stand KW12']);
    $draft = Preview::factory()->for_project($project)->create(['name' => 'Interner Entwurf']);

    $response = $this->actingAs($user)->get(route('portal.projects.show', $project));

    $response->assertOk()->assertSee('Stand KW12');

    // A preview that is not available is not offered to the customer at all.
    $response->assertDontSee('Interner Entwurf');

    // The button goes through the portal, which checks access again at the
    // moment of the click and then sends the customer straight on.
    $response->assertSee(route('portal.previews.show', [$project, $available]), escape: false);

    $this->actingAs($user)
        ->get(route('portal.previews.show', [$project, $available]))
        ->assertRedirect('https://'.$available->hostname);

    expect($draft->status->isVisitable())->toBeFalse();
});

it('explains rather than redirects when a preview is not reachable', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);

    $project = Project::factory()->for_customer($customer)->create();
    $preview = Preview::factory()->for_project($project)->create(['name' => 'Noch nicht bereit']);

    $this->actingAs($user)
        ->get(route('portal.previews.show', [$project, $preview]))
        ->assertOk()
        ->assertSee('derzeit nicht erreichbar');
});

it('never shows the customer the preview target', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);

    $project = Project::factory()->for_customer($customer)->create();
    $preview = Preview::factory()->for_project($project)->available()->create();

    // The target is a server path -- customers are only ever handed the URL.
    $this->actingAs($user)
        ->get(route('portal.projects.show', $project))
        ->assertOk()
        ->assertDontSee($preview->target);
});

/* -------------------------------------------------------- requirements 7+11 */

it('does not show a customer the projects of another customer', function () {
    $mine = Customer::factory()->create();
    $theirs = Customer::factory()->create();

    $user = $this->customerUser($mine);
    Project::factory()->for_customer($mine)->create(['name' => 'Mein Projekt']);
    Project::factory()->for_customer($mine)->create(['name' => 'Mein zweites Projekt']);
    $foreign = Project::factory()->for_customer($theirs)->create(['name' => 'Fremdes Projekt']);

    $response = $this->actingAs($user)->get(route('portal.dashboard'));

    $response->assertOk()->assertSee('Mein Projekt')->assertDontSee('Fremdes Projekt');
});

it('answers 404 -- not 403 -- for a foreign project id', function () {
    $user = $this->customerUser();
    $foreign = Project::factory()->create();

    $response = $this->actingAs($user)->get(route('portal.projects.show', $foreign));

    // 403 would confirm the id exists and belongs to someone else.
    $response->assertNotFound();
    expect($response->status())->toBe(404)->not->toBe(403);
});

it('answers 404 for a foreign preview id', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $mine = Project::factory()->for_customer($customer)->create();

    $foreignProject = Project::factory()->create();
    $foreignPreview = Preview::factory()->for_project($foreignProject)->available()->create();

    // Foreign preview under a foreign project.
    $this->actingAs($user)
        ->get(route('portal.previews.show', [$foreignProject, $foreignPreview]))
        ->assertNotFound();

    // And the same preview smuggled under the user's own project id, which is
    // the more interesting attempt: the nesting must be genuine.
    $this->actingAs($user)
        ->get(route('portal.previews.show', [$mine, $foreignPreview]))
        ->assertNotFound();
});

it('answers 404 for a completely unknown id, exactly like a foreign one', function () {
    $user = $this->customerUser();
    $foreign = Project::factory()->create();

    $unknown = $this->actingAs($user)->get(route('portal.projects.show', Str::ulid()));
    $foreignResponse = $this->actingAs($user)->get(route('portal.projects.show', $foreign));

    // Indistinguishable from the outside.
    expect($unknown->status())->toBe($foreignResponse->status())->toBe(404);
});

it('uses non-sequential ulids for publicly visible resources', function () {
    $project = Project::factory()->create();

    // Ids appear in URLs, so they must not be guessable or countable.
    expect($project->getKey())->toHaveLength(26)
        ->and($project->getKeyType())->toBe('string')
        ->and($project->getIncrementing())->toBeFalse();
});

/* ---------------------------------------------------------- requirement 10 */

it('takes access away when the customer is deactivated', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();
    $preview = Preview::factory()->for_project($project)->available()->create();

    $this->actingAs($user)->get(route('portal.projects.show', $project))->assertOk();

    $customer->forceFill(['is_active' => false])->save();

    // Deactivating the customer ends the session outright.
    $this->actingAs($user)->get(route('portal.projects.show', $project))
        ->assertRedirect(route('login'));

    $this->actingAs($user)->get(route('portal.previews.show', [$project, $preview]))
        ->assertRedirect(route('login'));

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertRedirect(route('login'));
});

it('takes access away when the user is blocked', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();

    $this->actingAs($user)->get(route('portal.projects.show', $project))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->actingAs($user->fresh())->get(route('portal.projects.show', $project))
        ->assertRedirect(route('login'));
});

/* -------------------------------------------------------------- guest access */

it('sends guests to the login page instead of leaking anything', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create();

    $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
    $this->get(route('portal.projects.show', $project))->assertRedirect(route('login'));
    $this->get(route('portal.previews.show', [$project, $preview]))->assertRedirect(route('login'));
});

/* ---------------------------------------------------------------- greeting */

it('greets by time of day in the display time zone, without a name', function (string $berlinTime, string $greeting) {
    $user = $this->customerUser(attributes: ['name' => 'Max Schneider']);

    Carbon::setTestNow(
        Carbon::parse('2026-10-02 '.$berlinTime, 'Europe/Berlin')
    );

    $html = $this->actingAs($user)->get(route('portal.dashboard'))->assertOk()->getContent();

    expect($html)->toMatch('#<h1[^>]*>\s*'.$greeting.'\s*</h1>#');

    Carbon::setTestNow();
})->with([
    ['08:00', 'Guten Morgen'],
    ['14:00', 'Guten Tag'],
    ['21:30', 'Guten Abend'],
]);
