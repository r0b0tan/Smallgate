<?php

/**
 * Requirements 3, 4 and 5: an administrator can invite a user, a token works
 * exactly once, and an expired token is refused.
 */

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Notification;

it('lets an administrator invite a customer user', function () {
    Notification::fake();

    $admin = $this->admin();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.customers.users.index', $customer))
        ->post(route('admin.customers.invitations.store', $customer), [
            'name' => 'Marion Holzmann',
            'email' => 'Marion@Holzmann.test',
        ])
        ->assertRedirect(route('admin.customers.users.index', $customer))
        ->assertSessionHas('status');

    $invitation = Invitation::sole();

    expect($invitation->customer_id)->toBe($customer->id)
        ->and($invitation->email)->toBe('marion@holzmann.test')
        ->and($invitation->role)->toBe(UserRole::Customer)
        ->and($invitation->invited_by_user_id)->toBe($admin->id)
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->expires_at->isFuture())->toBeTrue();

    Notification::assertSentTo($invitation, InvitationNotification::class);
});

it('never stores the invitation token in plaintext', function () {
    $admin = $this->admin();
    $customer = Customer::factory()->create();

    $token = app(InvitationService::class)
        ->invite($customer, 'Marion', 'marion@holzmann.test', $admin)['token'];

    $stored = DB::table('invitations')->sole();

    // The database holds only a SHA-256 hash. A database leak therefore does
    // not hand out working invitation links.
    expect($stored->token_hash)
        ->not->toBe($token)
        ->toBe(hash('sha256', $token))
        ->and(strlen($stored->token_hash))->toBe(64);

    // And the plaintext appears in no column of the row.
    foreach ((array) $stored as $value) {
        expect((string) $value)->not->toContain($token);
    }
});

it('lets an invited person set their own password and sign in', function () {
    $customer = Customer::factory()->create();
    $token = Str::random(64);

    $invitation = Invitation::factory()
        ->withToken($token)
        ->create(['customer_id' => $customer->id, 'email' => 'marion@holzmann.test']);

    $this->get(route('invitations.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('marion@holzmann.test');

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Marion Holzmann',
        'password' => self::PASSWORD,
        'password_confirmation' => self::PASSWORD,
    ])->assertRedirect(route('portal.dashboard'));

    $user = User::whereEmail('marion@holzmann.test')->sole();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->customer_id)->toBe($customer->id)
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    // Stored as an Argon2id hash, never in plaintext or reversible form.
    expect($user->getAuthPassword())
        ->toStartWith('$argon2id$')
        ->not->toContain(self::PASSWORD);

    expect(Hash::check(self::PASSWORD, $user->getAuthPassword()))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

/* ---------------------------------------------------------- requirement 4 */

it('accepts an invitation token only once', function () {
    $customer = Customer::factory()->create();
    $token = Str::random(64);

    Invitation::factory()->withToken($token)
        ->create(['customer_id' => $customer->id, 'email' => 'einmal@holzmann.test']);

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Erster Zugriff',
        'password' => self::PASSWORD,
        'password_confirmation' => self::PASSWORD,
    ])->assertRedirect(route('portal.dashboard'));

    expect(User::whereEmail('einmal@holzmann.test')->count())->toBe(1);

    $this->post('/logout');

    // Replaying the very same link must not create a second account.
    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Zweiter Zugriff',
        'password' => 'ein-ganz-anderes-passwort',
        'password_confirmation' => 'ein-ganz-anderes-passwort',
    ])->assertRedirect(route('invitations.show', ['token' => $token]));

    expect(User::whereEmail('einmal@holzmann.test')->count())->toBe(1)
        ->and(User::whereEmail('einmal@holzmann.test')->sole()->name)->toBe('Erster Zugriff');

    $this->assertGuest();

    // The used link now shows the generic "no longer valid" page.
    $this->get(route('invitations.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('Einladung nicht mehr gültig', escape: false);
});

/* ---------------------------------------------------------- requirement 5 */

it('refuses an expired invitation token', function () {
    $customer = Customer::factory()->create();
    $token = Str::random(64);

    Invitation::factory()->withToken($token)->expired()
        ->create(['customer_id' => $customer->id, 'email' => 'abgelaufen@holzmann.test']);

    $this->get(route('invitations.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('Einladung nicht mehr gültig', escape: false);

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Zu spät',
        'password' => self::PASSWORD,
        'password_confirmation' => self::PASSWORD,
    ])->assertRedirect(route('invitations.show', ['token' => $token]));

    expect(User::whereEmail('abgelaufen@holzmann.test')->exists())->toBeFalse();
    $this->assertGuest();
});

it('refuses an unknown token with the same page as an expired one', function () {
    $this->get(route('invitations.show', ['token' => Str::random(64)]))
        ->assertOk()
        ->assertSee('Einladung nicht mehr gültig', escape: false);
});

it('refuses an invitation of a deactivated customer', function () {
    $customer = Customer::factory()->inactive()->create();
    $token = Str::random(64);

    Invitation::factory()->withToken($token)
        ->create(['customer_id' => $customer->id, 'email' => 'gesperrt@holzmann.test']);

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Kein Zugang',
        'password' => self::PASSWORD,
        'password_confirmation' => self::PASSWORD,
    ])->assertRedirect(route('invitations.show', ['token' => $token]));

    expect(User::whereEmail('gesperrt@holzmann.test')->exists())->toBeFalse();
});

/* ------------------------------------------------------------- resend flow */

it('invalidates the previous link when an invitation is resent', function () {
    Notification::fake();

    $admin = $this->admin();
    $customer = Customer::factory()->create();
    $oldToken = Str::random(64);

    $invitation = Invitation::factory()->withToken($oldToken)
        ->create(['customer_id' => $customer->id, 'email' => 'neu@holzmann.test']);

    $this->actingAs($admin)
        ->from(route('admin.customers.users.index', $customer))
        ->post(route('admin.customers.invitations.resend', [$customer, $invitation]))
        ->assertSessionHas('status');

    // The invitation pages are guest-only, so sign the administrator out first.
    $this->post('/logout');

    // The old link is dead the moment a new one is issued.
    $this->get(route('invitations.show', ['token' => $oldToken]))
        ->assertSee('Einladung nicht mehr gültig', escape: false);

    Notification::assertSentTo($invitation->fresh(), InvitationNotification::class);
});

it('refuses to invite an address that already has an account', function () {
    $admin = $this->admin();
    $customer = Customer::factory()->create();
    $existing = $this->customerUser($customer);

    $this->actingAs($admin)
        ->from(route('admin.customers.users.index', $customer))
        ->post(route('admin.customers.invitations.store', $customer), [
            'name' => 'Doppelt',
            'email' => $existing->email,
        ])
        ->assertSessionHasErrors('email');

    expect(Invitation::count())->toBe(0);
});
