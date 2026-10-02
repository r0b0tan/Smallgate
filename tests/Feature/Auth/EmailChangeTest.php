<?php

/**
 * The email address is where a password reset goes, so changing it is guarded
 * like changing the password: a hijacked session alone must not be enough.
 */

use App\Notifications\EmailChangedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('requires the current password to change the email address', function () {
    Notification::fake();

    $user = $this->customerUser();
    $before = $user->email;

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'angreifer@example.test',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('email_password');

    expect($user->fresh()->email)->toBe($before);
    Notification::assertNothingSent();
});

it('refuses a wrong password for an email change', function () {
    $user = $this->customerUser();
    $before = $user->email;

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'angreifer@example.test',
            'email_password' => 'nicht-das-aktuelle-passwort',
        ])
        ->assertSessionHasErrors('email_password');

    expect($user->fresh()->email)->toBe($before);
});

it('changes the email address with the current password and tells the previous one', function () {
    Notification::fake();

    $user = $this->customerUser();
    $before = $user->email;

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'Neu@Example.test',
            'email_password' => self::PASSWORD,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->email)->toBe('neu@example.test')
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentOnDemand(
        EmailChangedNotification::class,
        fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === $before,
    );
});

it('needs no password when only the name changes', function () {
    Notification::fake();

    $user = $this->customerUser();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Neuer Name',
            'email' => strtoupper($user->email),
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Neuer Name');
    Notification::assertNothingSent();
});

it('guards an administrator\'s email address the same way', function () {
    $admin = $this->admin();
    $before = $admin->email;

    $this->actingAs($admin)
        ->patch(route('profile.update'), [
            'name' => $admin->name,
            'email' => 'angreifer@example.test',
        ])
        ->assertSessionHasErrors('email_password');

    expect($admin->fresh()->email)->toBe($before);
});

it('keeps the notification free of the new address', function () {
    config(['smallgate.contact_email' => '']);

    $mail = (new EmailChangedNotification('Marion Holzmann'))->toMail(new AnonymousNotifiable);

    expect(implode(' ', $mail->introLines))->not->toContain('@')
        ->and($mail->greeting)->toBe('Hallo Marion Holzmann,');
});
