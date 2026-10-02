<?php

/**
 * "Nachrichten": every answer given at the customer, newest first, page by
 * page -- and never an answer from another customer.
 */

use App\Models\Customer;
use App\Models\Preview;
use App\Models\PreviewFeedback;
use App\Models\Project;
use App\Models\User;

function archivePreview(Customer $customer, string $name = 'Startseite'): Preview
{
    $project = Project::factory()->for_customer($customer)->create(['name' => 'Webseiten-Relaunch']);

    return Preview::factory()->for_project($project)->available()->create(['name' => $name]);
}

it('lists the answers of everybody at the customer with their notes', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $colleague = $this->customerUser($customer, attributes: ['name' => 'Petra Schmidt']);
    $preview = archivePreview($customer);

    PreviewFeedback::factory()->changesRequested('Telefonnummer fehlt.')->create([
        'preview_id' => $preview->id,
        'user_id' => $colleague->id,
        'created_at' => now()->subDay(),
    ]);
    PreviewFeedback::factory()->create(['preview_id' => $preview->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('portal.feedback.index'))
        ->assertOk()
        ->assertSeeInOrder(['Passt so!', 'von Ihnen', 'Änderung gewünscht', 'von Petra Schmidt', 'Telefonnummer fehlt.'])
        ->assertSee('Startseite')
        ->assertSee('Webseiten-Relaunch');
});

it('pages through a long archive', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $preview = archivePreview($customer);

    foreach (range(1, 16) as $i) {
        PreviewFeedback::factory()->changesRequested('Hinweis Nummer '.$i.'.')->create([
            'preview_id' => $preview->id,
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(100 - $i),
        ]);
    }

    $this->actingAs($user)->get(route('portal.feedback.index'))
        ->assertOk()
        ->assertSee('Hinweis Nummer 16.')
        ->assertSee('Hinweis Nummer 2.')
        ->assertDontSee('Hinweis Nummer 1.')
        ->assertSee('Seite 1 von 2')
        ->assertSee(route('portal.feedback.index', ['page' => 2]), escape: false);

    $this->actingAs($user)->get(route('portal.feedback.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('Hinweis Nummer 1.')
        ->assertDontSee('Hinweis Nummer 2.');
});

it('never shows the answers of another customer', function () {
    $user = $this->customerUser(Customer::factory()->create());

    PreviewFeedback::factory()->changesRequested('Geheimer Hinweis eines anderen Kunden')->create([
        'preview_id' => archivePreview(Customer::factory()->create())->id,
        'user_id' => User::factory(),
    ]);

    $this->actingAs($user)->get(route('portal.feedback.index'))
        ->assertOk()
        ->assertSee('Noch keine Nachrichten.')
        ->assertDontSee('Geheimer Hinweis eines anderen Kunden');
});

it('offers a mail to the agency only when an address is configured', function () {
    $customer = Customer::factory()->create(['name' => 'Baufirma Schmidt GmbH']);
    $user = $this->customerUser($customer);

    config(['smallgate.contact_email' => 'hallo@agentur.test']);

    $this->actingAs($user)->get(route('portal.feedback.index'))
        ->assertSee('mailto:hallo@agentur.test?subject='.rawurlencode('Kundenportal – Baufirma Schmidt GmbH'), escape: false);

    config(['smallgate.contact_email' => '']);

    $this->actingAs($user)->get(route('portal.feedback.index'))
        ->assertDontSee('mailto:', escape: false);
});

it('sends guests to the login page', function () {
    $this->get(route('portal.feedback.index'))->assertRedirect(route('login'));
});
