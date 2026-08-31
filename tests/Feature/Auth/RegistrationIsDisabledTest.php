<?php

/**
 * Requirement 1: there is no public registration.
 *
 * Rather than asserting that a handful of guessed URLs 404, this walks the
 * actual route table -- so a registration route added later fails the test even
 * if nobody thought to add its URL here.
 */

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('registers no route that creates an account publicly', function () {
    $suspicious = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => ['name' => $route->getName(), 'uri' => $route->uri()])
        ->filter(function (array $route) {
            $haystack = strtolower(($route['name'] ?? '').' '.$route['uri']);

            return str_contains($haystack, 'register')
                || str_contains($haystack, 'registr')
                || str_contains($haystack, 'signup')
                || str_contains($haystack, 'sign-up');
        });

    expect($suspicious)->toBeEmpty();
});

it('has no named register route', function () {
    expect(Route::has('register'))->toBeFalse();
});

it('answers 404 on the conventional registration urls', function (string $url) {
    $this->get($url)->assertNotFound();
    $this->post($url, [
        'name' => 'Eindringling',
        'email' => 'eindringling@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0);
})->with(['/register', '/registrieren', '/signup', '/sign-up']);

it('offers no registration link on the login page', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertDontSee('Registrieren', escape: false);
    $response->assertSee('Eine Registrierung ist nicht vorgesehen.', escape: false);
});
