<?php

/**
 * Flash messages are shown as toasts in both layouts. Validation errors are
 * not: they stay next to the form.
 */
it('shows the sign-out message as a toast on the login page', function () {
    $this->actingAs($this->customerUser());

    $this->followingRedirects()
        ->post('/logout')
        ->assertSee('class="sg-toast', false)
        ->assertSee('Sie wurden abgemeldet.');
});

it('shows status, notice and error toasts in the portal, the error as an alert', function () {
    $this->actingAs($this->customerUser())
        ->withSession([
            'status' => 'Gespeichert.',
            'notice' => 'Bitte beachten.',
            'error' => 'Das ging schief.',
        ])
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Gespeichert.', 'Bitte beachten.', 'Das ging schief.'])
        ->assertSee('role="alert"', false);
});

it('renders no toast container without a flash message', function () {
    $this->actingAs($this->customerUser())
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertDontSee('sg-toast', false);
});

it('keeps validation errors inline instead of in a toast', function () {
    $this->from(route('password.request'))
        ->followingRedirects()
        ->post(route('password.email'), ['email' => 'keine-adresse'])
        ->assertDontSee('class="sg-toast', false)
        ->assertSee('sg-alert-error', false);
});
