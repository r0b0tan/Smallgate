<?php

/**
 * Requirements 2 and 8: an administrator can create a customer, and a customer
 * user can reach no administrative endpoint at all.
 */

use App\Models\Customer;
use App\Models\Preview;
use App\Models\Project;

/* ----------------------------------------------------------- requirement 2 */

it('lets an administrator create a customer', function () {
    $admin = $this->admin();

    $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
        'name' => 'Holzmann Bau GmbH',
        'slug' => 'holzmann',
        'contact_email' => 'Kontakt@Holzmann.test',
        'is_active' => '1',
    ]);

    $customer = Customer::sole();

    $response->assertRedirect(route('admin.customers.show', $customer));

    expect($customer->name)->toBe('Holzmann Bau GmbH')
        ->and($customer->slug)->toBe('holzmann')
        ->and($customer->contact_email)->toBe('kontakt@holzmann.test')
        ->and($customer->is_active)->toBeTrue();
});

it('derives a missing slug from the name', function () {
    $this->actingAs($this->admin())->post(route('admin.customers.store'), [
        'name' => 'Hotel Bergblick & Söhne',
        'slug' => '',
        'is_active' => '1',
    ]);

    expect(Customer::sole()->slug)->toBe('hotel-bergblick-sohne');
});

it('validates customer input on the server', function () {
    $this->actingAs($this->admin())
        ->from(route('admin.customers.create'))
        ->post(route('admin.customers.store'), [
            'name' => '',
            'slug' => 'Nicht Erlaubt!',
            'contact_email' => 'keine-email',
        ])
        ->assertSessionHasErrors(['name', 'contact_email']);

    expect(Customer::count())->toBe(0);
});

it('rejects a duplicate customer slug', function () {
    Customer::factory()->create(['slug' => 'holzmann']);

    $this->actingAs($this->admin())
        ->from(route('admin.customers.create'))
        ->post(route('admin.customers.store'), [
            'name' => 'Zweiter Holzmann',
            'slug' => 'holzmann',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('slug');

    expect(Customer::count())->toBe(1);
});

it('lets an administrator create a project and a preview', function () {
    $admin = $this->admin();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)->post(route('admin.projects.store'), [
        'customer_id' => $customer->id,
        'name' => 'Website-Relaunch',
        'slug' => 'website-relaunch',
        'description' => 'Kompletter Relaunch.',
        'status' => 'active',
    ])->assertRedirect();

    $project = Project::sole();
    expect($project->customer_id)->toBe($customer->id);

    $this->actingAs($admin)->post(route('admin.projects.previews.store', $project), [
        'name' => 'Stand KW12',
        'slug' => 'kw12',
        'hostname' => 'holzmann.'.config('previews.base_domain'),
        'target_type' => 'static_directory',
        'target' => '/srv/previews/holzmann/kw12',
        'status' => 'available',
    ])->assertRedirect(route('admin.projects.show', $project));

    $preview = Preview::sole();
    expect($preview->project_id)->toBe($project->id)
        ->and($preview->hostname)->toBe('holzmann.'.config('previews.base_domain'));
});

/* ----------------------------------------------------------- requirement 8 */

dataset('admin endpoints', function () {
    return [
        'dashboard' => ['get', fn () => route('admin.dashboard')],
        'customer list' => ['get', fn () => route('admin.customers.index')],
        'customer form' => ['get', fn () => route('admin.customers.create')],
        'create customer' => ['post', fn () => route('admin.customers.store')],
        'customer detail' => ['get', fn () => route('admin.customers.show', Customer::factory()->create())],
        'edit customer' => ['get', fn () => route('admin.customers.edit', Customer::factory()->create())],
        'update customer' => ['patch', fn () => route('admin.customers.update', Customer::factory()->create())],
        'customer users' => ['get', fn () => route('admin.customers.users.index', Customer::factory()->create())],
        'invite user' => ['post', fn () => route('admin.customers.invitations.store', Customer::factory()->create())],
        'project list' => ['get', fn () => route('admin.projects.index')],
        'project form' => ['get', fn () => route('admin.projects.create')],
        'create project' => ['post', fn () => route('admin.projects.store')],
        'project detail' => ['get', fn () => route('admin.projects.show', Project::factory()->create())],
        'update project' => ['patch', fn () => route('admin.projects.update', Project::factory()->create())],
        'preview form' => ['get', fn () => route('admin.projects.previews.create', Project::factory()->create())],
        'create preview' => ['post', fn () => route('admin.projects.previews.store', Project::factory()->create())],
    ];
});

it('keeps customer users out of every administrative endpoint', function (string $method, Closure $url) {
    $user = $this->customerUser();

    $response = $this->actingAs($user)->{$method}($url());

    // 404, not 403: the admin area does not confirm its own existence to a
    // customer user.
    $response->assertNotFound();
})->with('admin endpoints');

it('keeps guests out of every administrative endpoint', function (string $method, Closure $url) {
    $this->{$method}($url())->assertRedirect(route('login'));
})->with('admin endpoints');

it('does not link to the admin area from the customer navigation', function () {
    $user = $this->customerUser();

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.dashboard'))
        ->assertDontSee(route('admin.customers.index'));
});

it('gives an administrator access to every customer', function () {
    $admin = $this->admin();
    $first = Customer::factory()->create(['name' => 'Erster Kunde']);
    $second = Customer::factory()->create(['name' => 'Zweiter Kunde']);

    $this->actingAs($admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertSee('Erster Kunde')
        ->assertSee('Zweiter Kunde');
});

it('blocks a blocked administrator too', function () {
    $admin = $this->admin(['is_active' => false]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('login'));
});
