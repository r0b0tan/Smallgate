<?php

/**
 * "Passt so" / "Änderung wünschen": a customer answers their own drafts, the
 * answer is pinned to the version they saw, and nothing about who or what is
 * taken from the form.
 */

use App\Enums\FeedbackDecision;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\PreviewFeedback;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;

function offeredPreview(?Customer $customer = null): Preview
{
    $customer ??= Customer::factory()->create();
    $project = Project::factory()->for_customer($customer)->create(['name' => 'Gasthaus zur Post']);

    return Preview::factory()->for_project($project)->available()->create(['name' => 'Entwurf 2']);
}

function feedbackRoute(Preview $preview): string
{
    return route('portal.previews.feedback.store', [$preview->project_id, $preview]);
}

it('records "Passt so" for the version the customer saw and confirms it', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $preview = offeredPreview($customer);

    $this->actingAs($user)
        ->post(feedbackRoute($preview), ['decision' => 'approved', 'version' => 1, 'comment' => '  Nur das Datum fehlt.  '])
        ->assertRedirect(route('portal.dashboard').'#entwurf-'.$preview->id)
        ->assertSessionHas('feedback_sent', $preview->id);

    $feedback = PreviewFeedback::sole();

    expect($feedback->decision)->toBe(FeedbackDecision::Approved)
        ->and($feedback->preview_id)->toBe($preview->id)
        ->and($feedback->user_id)->toBe($user->id)
        ->and($feedback->preview_version)->toBe(1)
        // The note field sits under both buttons and goes with either answer.
        ->and($feedback->comment)->toBe('Nur das Datum fehlt.');

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Sie haben den Entwurf am')
        ->assertSee('Nur das Datum fehlt.')
        ->assertSee('Alles erledigt')
        ->assertDontSee('Wartet auf Ihre Meinung')
        ->assertDontSee('wartet auf Ihre Meinung');

    // Right after sending, the thanks appears on the card itself.
    $this->actingAs($user)->withSession(['feedback_sent' => $preview->id])
        ->get(route('portal.dashboard'))
        ->assertSee('Vielen Dank!');
});

it('records a change request with an optional comment', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $preview = offeredPreview($customer);

    $this->actingAs($user)
        ->get(route('portal.previews.feedback.create', [$preview->project_id, $preview]))
        ->assertOk()
        ->assertSee('Was sollen wir ändern?');

    $this->actingAs($user)
        ->post(feedbackRoute($preview), [
            'decision' => 'changes_requested',
            'version' => 1,
            'comment' => '  Telefonnummer fehlt.  ',
        ])
        ->assertRedirect()
        ->assertSessionHas('feedback_sent', $preview->id);

    $this->actingAs($user)
        ->post(feedbackRoute($preview), ['decision' => 'changes_requested', 'version' => 1])
        ->assertRedirect();

    expect(PreviewFeedback::query()->pluck('comment')->all())
        ->toContain('Telefonnummer fehlt.')
        ->toContain(null);
});

it('rejects an unknown decision and an overlong comment', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $preview = offeredPreview($customer);

    $this->actingAs($user)
        ->post(feedbackRoute($preview), ['decision' => 'maybe', 'version' => 1])
        ->assertSessionHasErrors('decision');

    $this->actingAs($user)
        ->post(feedbackRoute($preview), [
            'decision' => 'changes_requested',
            'version' => 1,
            'comment' => str_repeat('x', 2001),
        ])
        ->assertSessionHasErrors('comment');

    expect(PreviewFeedback::count())->toBe(0);
});

it('takes preview, user and version from the server, never from the form', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $someoneElse = $this->customerUser($customer);
    $preview = offeredPreview($customer);
    $other = offeredPreview();

    $this->actingAs($user)->post(feedbackRoute($preview), [
        'decision' => 'approved',
        'version' => 1,
        'user_id' => $someoneElse->id,
        'preview_id' => $other->id,
        'preview_version' => 99,
    ]);

    $feedback = PreviewFeedback::sole();

    expect($feedback->user_id)->toBe($user->id)
        ->and($feedback->preview_id)->toBe($preview->id)
        ->and($feedback->preview_version)->toBe(1);
});

it('does not attach an answer to a newer version the customer has not seen', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $preview = offeredPreview($customer);

    $preview->forceFill(['version' => 2])->save();

    $this->actingAs($user)
        ->post(feedbackRoute($preview), ['decision' => 'approved', 'version' => 1])
        ->assertRedirect()
        ->assertSessionHas('notice');

    expect(PreviewFeedback::count())->toBe(0);
});

it('asks again once a new version is published', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $preview = offeredPreview($customer);

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertSee('Ein Entwurf wartet auf Ihre Meinung')
        ->assertSee('Wartet auf Ihre Meinung');

    PreviewFeedback::factory()->create(['preview_id' => $preview->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertDontSee('Ein Entwurf wartet auf Ihre Meinung');

    $preview->forceFill(['version' => 2])->save();

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertSee('Ein Entwurf wartet auf Ihre Meinung')
        ->assertSee('Wartet auf Ihre Meinung');
});

it('answers 404 for feedback on a foreign, unknown or unreleased preview', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $mine = offeredPreview($customer);
    $foreign = offeredPreview();
    $draft = Preview::factory()->for_project(Project::find($mine->project_id))->create();

    $payload = ['decision' => 'approved', 'version' => 1];

    $this->actingAs($user)->post(feedbackRoute($foreign), $payload)->assertNotFound();

    // A foreign preview smuggled under the customer's own project.
    $this->actingAs($user)
        ->post(route('portal.previews.feedback.store', [$mine->project_id, $foreign]), $payload)
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('portal.previews.feedback.store', [$mine->project_id, Str::ulid()]), $payload)
        ->assertNotFound();

    // Not released yet: as far as the customer is concerned it does not exist.
    $this->actingAs($user)->post(feedbackRoute($draft), $payload)->assertNotFound();
    $this->actingAs($user)
        ->get(route('portal.previews.feedback.create', [$draft->project_id, $draft]))
        ->assertNotFound();

    expect(PreviewFeedback::count())->toBe(0);
});

it('lists the last three answers of the customer beside the drafts', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $colleague = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();

    foreach (['Startseite', 'Kontaktseite', 'Impressum', 'Leistungen'] as $i => $name) {
        $preview = Preview::factory()->for_project($project)->available()->create(['name' => $name]);
        PreviewFeedback::factory()->create([
            'preview_id' => $preview->id,
            'user_id' => $i % 2 === 0 ? $user->id : $colleague->id,
            'created_at' => now()->subDays(10 - $i),
        ]);
    }

    $response = $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Letzte Rückmeldungen', 'Leistungen', 'Impressum', 'Kontaktseite'])
        ->assertSee('Passt so!');

    // Newest first, and only three: the oldest answer is not in the list.
    expect(Str::after($response->getContent(), 'Letzte Rückmeldungen'))->not->toContain('Startseite');
});

it('does not show one customer the feedback of another', function () {
    $mine = Customer::factory()->create();
    $user = $this->customerUser($mine);
    offeredPreview($mine);

    $foreign = offeredPreview();
    PreviewFeedback::factory()
        ->changesRequested('Geheimer Hinweis eines anderen Kunden')
        ->create(['preview_id' => $foreign->id, 'user_id' => User::factory()]);

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertDontSee('Geheimer Hinweis eines anderen Kunden');
});

it('lets administrators read feedback but not give it', function () {
    $admin = $this->admin();
    $customer = Customer::factory()->create();
    $preview = offeredPreview($customer);

    PreviewFeedback::factory()->changesRequested('Bitte Logo größer')->create([
        'preview_id' => $preview->id,
        'user_id' => $this->customerUser($customer)->id,
    ]);

    $this->actingAs($admin)
        ->post(feedbackRoute($preview), ['decision' => 'approved', 'version' => 1])
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.projects.show', $preview->project_id))
        ->assertOk()
        ->assertSee('Bitte Logo größer');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Bitte Logo größer');
});

it('sends guests to the login page', function () {
    $preview = offeredPreview();

    $this->post(feedbackRoute($preview), ['decision' => 'approved', 'version' => 1])
        ->assertRedirect(route('login'));

    expect(PreviewFeedback::count())->toBe(0);
});

it('enforces the comment length in the database as well', function () {
    $preview = offeredPreview();

    $feedback = new PreviewFeedback;
    $feedback->decision = FeedbackDecision::Approved;
    $feedback->comment = str_repeat('x', 2001);
    $feedback->preview_id = $preview->id;
    $feedback->user_id = User::factory()->create()->id;
    $feedback->preview_version = 1;

    expect(fn () => $feedback->save())->toThrow(QueryException::class);
});
