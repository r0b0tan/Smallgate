<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ActivityAction;
use App\Enums\FeedbackDecision;
use App\Http\Controllers\Concerns\ResolvesPortalPreviews;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Preview;
use App\Models\PreviewFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Passt so" or "Änderung wünschen" -- the only thing a customer ever writes.
 *
 * The preview, the user and the version are taken from the authorised preview
 * and the session, never from the form. The version the form carries is only
 * compared: if a newer version went live while the page was open, the answer
 * is not silently attached to something the customer has not seen.
 */
class FeedbackController extends Controller
{
    use ResolvesPortalPreviews;

    /**
     * Every answer anybody at this customer has given, newest first -- the
     * archive behind "Letzte Rückmeldungen". Read only.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PreviewFeedback::class);

        return view('portal.feedback.index', [
            'feedback' => PreviewFeedback::query()
                ->visibleTo($request->user())
                ->with(['preview.project', 'user'])
                ->latest()
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    /**
     * The change request form on its own page: one optional text field and one
     * button. Reached from an already answered card, to revise the answer.
     */
    public function create(Request $request, string $project, string $preview): View
    {
        [$projectModel, $previewModel] = $this->resolvePreview($request->user(), $project, $preview);

        $this->ensureOffered($previewModel);
        $this->authorize('create', [PreviewFeedback::class, $previewModel]);

        return view('portal.feedback.create', [
            'project' => $projectModel,
            'preview' => $previewModel,
        ]);
    }

    public function store(Request $request, string $project, string $preview): RedirectResponse
    {
        [, $previewModel] = $this->resolvePreview($request->user(), $project, $preview);

        $this->ensureOffered($previewModel);
        $this->authorize('create', [PreviewFeedback::class, $previewModel]);

        $validated = $request->validate([
            'decision' => ['required', Rule::enum(FeedbackDecision::class)],
            'version' => ['required', 'integer'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'decision' => 'Rückmeldung',
            'comment' => 'Ihr Hinweis',
        ]);

        $back = redirect()->to(route('portal.dashboard').'#entwurf-'.$previewModel->id);

        if ((int) $validated['version'] !== $previewModel->version) {
            return $back->with('notice',
                'Inzwischen gibt es eine neuere Fassung dieses Entwurfs. Bitte sehen Sie sie sich kurz an, bevor Sie antworten.');
        }

        $decision = FeedbackDecision::from($validated['decision']);

        $feedback = new PreviewFeedback;
        $feedback->decision = $decision;
        // Optional with either answer: "Passt so, nur das Datum stimmt nicht".
        $feedback->comment = $validated['comment'] ?? null;
        $feedback->preview_id = $previewModel->id;
        $feedback->user_id = $request->user()->id;
        $feedback->preview_version = $previewModel->version;
        $feedback->save();

        // Only the decision -- the comment stays in the feedback itself.
        Activity::record(
            $decision === FeedbackDecision::Approved ? ActivityAction::FeedbackApproved : ActivityAction::FeedbackChangesRequested,
            $previewModel,
            properties: ['version' => $previewModel->version],
        );

        // The thanks is shown on the card itself, where the buttons were --
        // not in a banner the customer has to connect to the right draft.
        return $back->with('feedback_sent', $previewModel->id);
    }

    /**
     * A preview the customer is not offered is not there as far as they are
     * concerned: 404, like the rest of the portal, rather than a 403 that
     * would confirm an unreleased draft exists.
     */
    private function ensureOffered(Preview $preview): void
    {
        abort_unless($preview->status->isVisitable() && $preview->version >= 1, 404);
    }
}
