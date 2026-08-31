<?php

/**
 * Requirement 13: changing the password invalidates existing sessions.
 * Also covers the password reset flow and its non-enumeration guarantee.
 */

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

const NEW_PASSWORD = 'ein-vollkommen-neues-passwort';

/**
 * Write a session row as if the same account were signed in on another device.
 */
function otherSessionFor(User $user): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->getKey(),
        'ip_address' => '203.0.113.7',
        'user_agent' => 'anderes-geraet',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    return $id;
}

/* ---------------------------------------------------------- requirement 13 */

it('revokes other sessions when the password is changed', function () {
    $user = $this->customerUser();
    $otherSession = otherSessionFor($user);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.password'), [
            'current_password' => self::PASSWORD,
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status');

    // The other device's session row is gone.
    expect(DB::table('sessions')->where('id', $otherSession)->exists())->toBeFalse();

    // Remember-me cookies issued under the old password are dead too.
    expect($user->fresh()->remember_token)->toBeNull();

    // And the new password is what actually works now.
    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
        ->assertSessionHasErrors('email');
    $this->flushSession();
    $this->post('/login', ['email' => $user->email, 'password' => NEW_PASSWORD])
        ->assertRedirect(route('portal.dashboard'));
});

it('keeps the current session signed in after a password change', function () {
    $user = $this->customerUser();

    $this->actingAs($user)->put(route('profile.password'), [
        'current_password' => self::PASSWORD,
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ]);

    // AuthenticateSession re-binds the current session to the new hash, so the
    // person who just changed their password is not thrown out.
    $this->get(route('portal.dashboard'))->assertOk();
});

it('requires the current password to change it', function () {
    $user = $this->customerUser();
    $otherSession = otherSessionFor($user);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.password'), [
            'current_password' => 'nicht-das-aktuelle-passwort',
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ])
        ->assertSessionHasErrors('current_password');

    // Nothing was revoked and nothing was changed.
    expect(DB::table('sessions')->where('id', $otherSession)->exists())->toBeTrue();
    expect(Hash::check(self::PASSWORD, $user->fresh()->getAuthPassword()))->toBeTrue();
});

it('rejects a password shorter than the minimum', function () {
    $user = $this->customerUser();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.password'), [
            'current_password' => self::PASSWORD,
            'password' => 'kurz',
            'password_confirmation' => 'kurz',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check(self::PASSWORD, $user->fresh()->getAuthPassword()))->toBeTrue();
});

/* ------------------------------------------------------------ reset by mail */

it('revokes every session when the password is reset', function () {
    Notification::fake();

    $user = $this->customerUser();
    $otherSession = otherSessionFor($user);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class,
        function ($notification) use (&$token) {
            $token = (new ReflectionProperty($notification, 'token'))->getValue($notification);

            return true;
        });

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ])->assertRedirect(route('login'));

    // A reset is a recovery action: every session dies, including an
    // attacker's.
    expect(DB::table('sessions')->where('id', $otherSession)->exists())->toBeFalse();
    expect(Hash::check(NEW_PASSWORD, $user->fresh()->getAuthPassword()))->toBeTrue();
});

it('does not reveal whether an email address exists', function () {
    Notification::fake();

    $known = $this->customerUser();

    $first = $this->post(route('password.email'), ['email' => $known->email]);
    $this->flushSession();
    $second = $this->post(route('password.email'), ['email' => 'gibt-es-nicht@example.test']);

    // Identical status message for both, and no validation error either way.
    expect($first->getSession()->get('status'))->toBe($second->getSession()->get('status'));
    $second->assertSessionHasNoErrors();
});

it('sends no reset mail to a blocked account', function () {
    Notification::fake();

    $blocked = $this->customerUser(attributes: ['is_active' => false]);

    $this->post(route('password.email'), ['email' => $blocked->email])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('refuses an invalid reset token generically', function () {
    $user = $this->customerUser();

    $this->from(route('password.reset', ['token' => 'x']))
        ->post(route('password.store'), [
            'token' => 'ein-erfundener-token',
            'email' => $user->email,
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ])
        ->assertSessionHasErrors('email');

    expect(Hash::check(self::PASSWORD, $user->fresh()->getAuthPassword()))->toBeTrue();
});

/* ------------------------------------------------------------------ hashing */

it('stores passwords as argon2id hashes', function () {
    $user = $this->customerUser();

    expect(config('hashing.driver'))->toBe('argon2id')
        ->and($user->getAuthPassword())->toStartWith('$argon2id$')
        ->and($user->getAuthPassword())->not->toContain(self::PASSWORD);

    // Same password, different hash: the salt is per-record, so identical
    // passwords are not detectable by comparing stored values.
    $other = $this->customerUser();
    expect($other->getAuthPassword())->not->toBe($user->getAuthPassword());
});
