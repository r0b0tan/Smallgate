<?php

/**
 * The navigation rail: what each role is offered. The speech bubble leads to
 * the archive of answers, not to a chat.
 */

use App\Models\Customer;

it('offers a customer the overview, the message archive and their access data', function () {
    $customer = Customer::factory()->create(['name' => 'Baufirma Schmidt GmbH']);

    $this->actingAs($this->customerUser($customer))
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Baufirma Schmidt GmbH')
        ->assertSee(route('portal.dashboard'), escape: false)
        ->assertSee(route('portal.feedback.index'), escape: false)
        ->assertSee(route('profile.edit'), escape: false)
        ->assertDontSee(route('admin.customers.index'), escape: false);
});

it('offers an administrator dashboard, customers and projects', function () {
    $this->actingAs($this->admin())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.customers.index'), escape: false)
        ->assertSee(route('admin.projects.index'), escape: false)
        ->assertDontSee(route('portal.feedback.index'), escape: false);
});
