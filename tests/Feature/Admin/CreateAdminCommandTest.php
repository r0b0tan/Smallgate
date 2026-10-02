<?php

/**
 * The first administrator of a fresh deployment comes from `admin:create`.
 * It must set the protected attributes explicitly, validate like the web
 * forms, and never accept the password other than through a hidden prompt.
 */

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function createAdmin(object $test, string $name, string $email, string $password, ?string $confirmation = null): object
{
    return $test->artisan('admin:create')
        ->expectsQuestion('Name', $name)
        ->expectsQuestion('E-Mail-Adresse', $email)
        ->expectsQuestion('Passwort (mindestens 12 Zeichen)', $password)
        ->expectsQuestion('Passwort wiederholen', $confirmation ?? $password);
}

it('creates an active administrator without a customer', function () {
    createAdmin($this, 'Admin', ' Admin@Example.TEST ', 'ein-langes-passwort')
        ->expectsOutput('Administrator admin@example.test angelegt.')
        ->assertExitCode(0);

    $user = User::sole();

    expect($user->email)->toBe('admin@example.test')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and($user->customer_id)->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::info($user->password)['algoName'])->toBe('argon2id')
        ->and(Hash::check('ein-langes-passwort', $user->password))->toBeTrue();
});

it('lets the new administrator sign in', function () {
    createAdmin($this, 'Admin', 'admin@example.test', 'ein-langes-passwort')->assertExitCode(0);

    $this->post(route('login'), [
        'email' => 'admin@example.test',
        'password' => 'ein-langes-passwort',
    ])->assertRedirect(route('admin.dashboard'));
});

it('refuses a password shorter than the policy', function () {
    createAdmin($this, 'Admin', 'admin@example.test', 'zu-kurz')
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});

it('refuses a password that does not match its confirmation', function () {
    createAdmin($this, 'Admin', 'admin@example.test', 'ein-langes-passwort', 'ein-anderes-passwort')
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});

it('refuses an address that already has an account, regardless of case', function () {
    $this->admin(['email' => 'admin@example.test']);

    createAdmin($this, 'Admin', 'ADMIN@example.test', 'ein-langes-passwort')
        ->expectsOutput('E-Mail-Adresse ist bereits vergeben.')
        ->assertExitCode(1);

    expect(User::count())->toBe(1);
});

it('runs only interactively, so the password never travels as an argument', function () {
    $this->artisan('admin:create', ['--no-interaction' => true])
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});
