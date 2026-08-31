<?php

/**
 * Requirements 9, 10 and 12: blocked users, deactivated customers and login
 * rate limiting -- plus the generic error message that ties them together.
 */

use App\Models\Customer;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('');
});

it('lets an active customer user sign in', function () {
    $user = $this->customerUser();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => self::PASSWORD,
    ]);

    $response->assertRedirect(route('portal.dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('sends an administrator to the admin dashboard', function () {
    $admin = $this->admin();

    $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD])
        ->assertRedirect(route('admin.dashboard'));
});

it('records the login time', function () {
    $user = $this->customerUser();
    expect($user->last_login_at)->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('regenerates the session id on login', function () {
    $user = $this->customerUser();

    // Establish a session as a guest first, then capture its id.
    $this->get('/login');
    $idBefore = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

    expect(session()->getId())->not->toBe($idBefore);
});

/* ------------------------------------------------------------------ blocked */

it('refuses a blocked user', function () {
    $user = $this->customerUser(attributes: ['is_active' => false]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => self::PASSWORD,
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('refuses a user whose customer is deactivated', function () {
    $customer = Customer::factory()->inactive()->create();
    $user = $this->customerUser($customer);

    $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('gives the same message for wrong password, unknown address and blocked account', function () {
    $blocked = $this->customerUser(attributes: ['is_active' => false]);
    $active = $this->customerUser();
    $inactiveCustomer = $this->customerUser(Customer::factory()->inactive()->create());

    // The one message all four failure modes must produce. If any branch ever
    // gets its own wording, the login form becomes an account enumeration
    // oracle -- and this test fails.
    $generic = __('auth.failed');

    $cases = [
        'wrong password' => ['email' => $active->email, 'password' => 'definitely-the-wrong-password'],
        'unknown address' => ['email' => 'nobody-here@example.test', 'password' => self::PASSWORD],
        'blocked user' => ['email' => $blocked->email, 'password' => self::PASSWORD],
        'deactivated customer' => ['email' => $inactiveCustomer->email, 'password' => self::PASSWORD],
    ];

    foreach ($cases as $label => $credentials) {
        $this->flushSession();

        $this->from('/login')
            ->post('/login', $credentials)
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => $generic], errorBag: 'default');

        expect(auth()->check())->toBeFalse("Anmeldung bei \"{$label}\" haette scheitern muessen.");
    }
});

/* ------------------------------------------------------------ rate limiting */

it('throttles repeated failed logins', function () {
    $user = $this->customerUser();
    $max = (int) config('smallgate.login.max_attempts');

    for ($attempt = 0; $attempt < $max; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password-'.$attempt,
        ])->assertSessionHasErrors('email');

        $this->flushSession();
    }

    // The next attempt is refused before the credentials are even checked --
    // note it uses the *correct* password and still fails.
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => self::PASSWORD,
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Sekunden');
    $this->assertGuest();
});

it('throttles per email and ip, so one account cannot lock out another', function () {
    $victim = $this->customerUser();
    $other = $this->customerUser();
    $max = (int) config('smallgate.login.max_attempts');

    for ($attempt = 0; $attempt <= $max; $attempt++) {
        $this->post('/login', ['email' => $victim->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->flushSession();
    }

    // The unrelated account is unaffected by the attack on the first one.
    $this->post('/login', ['email' => $other->email, 'password' => self::PASSWORD])
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($other);
});

it('clears the throttle counter after a successful login', function () {
    $user = $this->customerUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
        ->assertRedirect(route('portal.dashboard'));

    expect(RateLimiter::attempts(strtolower($user->email).'|127.0.0.1'))->toBe(0);
});

/* -------------------------------------------------------------- mid-session */

it('logs out a user who is blocked while signed in', function () {
    $user = $this->customerUser();
    $this->actingAs($user);

    $this->get(route('portal.dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    // Blocking takes effect on the very next request, not at the next login.
    $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('logs out a user whose customer is deactivated while signed in', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $this->actingAs($user);

    $this->get(route('portal.dashboard'))->assertOk();

    $customer->forceFill(['is_active' => false])->save();

    $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('logs out and invalidates the session', function () {
    $user = $this->customerUser();
    $this->actingAs($user);

    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});
