<?php

/**
 * Requirement 14: mass assignment cannot change a role or a customer link.
 *
 * These are the two attributes that decide what somebody may see, so they are
 * deliberately absent from every $fillable list and are only ever set by
 * explicit, administrator-only code.
 */

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\Preview;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;

/* -------------------------------------------------- the model level itself */

it('does not allow filling role, customer_id or is_active on a user', function () {
    $customer = Customer::factory()->create();
    $foreign = Customer::factory()->create();

    $user = new User;

    foreach (['role', 'customer_id', 'is_active'] as $attribute) {
        expect($user->isFillable($attribute))->toBeFalse(
            "[{$attribute}] darf nicht mass assignable sein."
        );
    }

    expect($user->isFillable('name'))->toBeTrue()
        ->and($user->isFillable('email'))->toBeTrue();
});

it('does not allow filling customer_id on a project or project_id on a preview', function () {
    expect((new Project)->isFillable('customer_id'))->toBeFalse()
        ->and((new Preview)->isFillable('project_id'))->toBeFalse()
        ->and((new Preview)->isFillable('provisioned_at'))->toBeFalse();
});

it('guards the invitation model entirely', function () {
    $invitation = new Invitation;

    // Tokens, expiry and role are never accepted from request input.
    foreach (['token_hash', 'expires_at', 'accepted_at', 'role', 'customer_id', 'email'] as $attribute) {
        expect($invitation->isFillable($attribute))->toBeFalse(
            "[{$attribute}] darf nicht mass assignable sein."
        );
    }
});

/* ---------------------------------------------------- through real requests */

it('ignores a role smuggled into the profile form', function () {
    $user = $this->customerUser();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Neuer Name',
        'email' => $user->email,
        // The escalation attempt.
        'role' => 'admin',
        'is_active' => true,
        'customer_id' => Customer::factory()->create()->id,
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Neuer Name')
        ->and($user->role)->toBe(UserRole::Customer)
        ->and($user->isAdmin())->toBeFalse();

    // And the escalation did not take effect at the routing level either.
    $this->actingAs($user)->get(route('admin.dashboard'))->assertNotFound();
});

it('ignores a customer reassignment smuggled into the profile form', function () {
    $mine = Customer::factory()->create();
    $theirs = Customer::factory()->create();

    $user = $this->customerUser($mine);
    $foreignProject = Project::factory()->for_customer($theirs)->create(['name' => 'Fremdes Projekt']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'customer_id' => $theirs->id,
    ]);

    expect($user->fresh()->customer_id)->toBe($mine->id);

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertDontSee('Fremdes Projekt');

    $this->actingAs($user)->get(route('portal.projects.show', $foreignProject))->assertNotFound();
});

it('ignores a role smuggled into the invitation form', function () {
    $admin = $this->admin();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.customers.users.index', $customer))
        ->post(route('admin.customers.invitations.store', $customer), [
            'name' => 'Möchtegern-Admin',
            'email' => 'moechtegern@holzmann.test',
            // Neither of these is part of the form or the validated data.
            'role' => 'admin',
            'customer_id' => Customer::factory()->create()->id,
        ]);

    $invitation = Invitation::sole();

    expect($invitation->role)->toBe(UserRole::Customer)
        ->and($invitation->customer_id)->toBe($customer->id);
});

it('ignores a role smuggled into the invitation redemption', function () {
    $customer = Customer::factory()->create();
    $token = Str::random(64);

    Invitation::factory()->withToken($token)
        ->create(['customer_id' => $customer->id, 'email' => 'neu@holzmann.test']);

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Neuer Zugang',
        'password' => self::PASSWORD,
        'password_confirmation' => self::PASSWORD,
        'role' => 'admin',
        'is_active' => true,
        'email' => 'ganz-andere@adresse.test',
        'customer_id' => Customer::factory()->create()->id,
    ])->assertRedirect(route('portal.dashboard'));

    $user = User::whereEmail('neu@holzmann.test')->sole();

    // Role, customer and email all come from the invitation, not the form.
    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->customer_id)->toBe($customer->id)
        ->and($user->email)->toBe('neu@holzmann.test');

    expect(User::whereEmail('ganz-andere@adresse.test')->exists())->toBeFalse();
});

it('ignores a customer reassignment smuggled into the preview form', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($admin)->post(route('admin.projects.previews.store', $project), [
        'name' => 'Vorschau',
        'slug' => 'vorschau',
        'target_type' => 'static_directory',
        'status' => 'draft',
        // The preview must belong to the project in the URL, not this one.
        'project_id' => $otherProject->id,
        'provisioned_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    $preview = Preview::sole();

    expect($preview->project_id)->toBe($project->id)
        ->and($preview->provisioned_at)->toBeNull();
});

/* ---------------------------------------------- database level backstop */

it('refuses a customer user without a customer at the database level', function () {
    // Even bypassing the application entirely, the schema will not accept it.
    expect(fn () => DB::table('users')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'Kaputt',
        'email' => 'kaputt@example.test',
        'password' => 'x',
        'role' => 'customer',
        'customer_id' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('refuses an administrator bound to a customer at the database level', function () {
    $customer = Customer::factory()->create();

    expect(fn () => DB::table('users')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'Kaputt',
        'email' => 'kaputt2@example.test',
        'password' => 'x',
        'role' => 'admin',
        'customer_id' => $customer->id,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('refuses an unknown role at the database level', function () {
    $customer = Customer::factory()->create();

    expect(fn () => DB::table('users')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'Kaputt',
        'email' => 'kaputt3@example.test',
        'password' => 'x',
        'role' => 'superadmin',
        'customer_id' => $customer->id,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
