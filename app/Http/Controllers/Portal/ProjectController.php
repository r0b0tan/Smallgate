<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\ResolvesPortalPreviews;
use App\Http\Controllers\Controller;
use App\Models\PreviewFeedback;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The customer's read-only view of their own projects.
 *
 * Projects are resolved through the visibility scope by id rather than through
 * route model binding, so a foreign or unknown id produces an identical 404.
 * A "403 Forbidden" would confirm that the id exists and belongs to someone
 * else, which is exactly the leak the spec asks to avoid.
 */
class ProjectController extends Controller
{
    use ResolvesPortalPreviews;

    /**
     * The same page as the overview, narrowed to one project. Links to it live
     * in older mails and bookmarks.
     */
    public function show(Request $request, string $project): View
    {
        $model = Project::query()
            ->visibleTo($request->user())
            ->withOfferedPreviews()
            ->whereKey($project)
            ->firstOrFail();

        // Belt and braces: the policy has to agree with the scope.
        $this->authorize('view', $model);

        return view('portal.dashboard', [
            'projects' => collect([$model]),
            'recentFeedback' => PreviewFeedback::query()
                ->visibleTo($request->user())
                ->whereRelation('preview', 'project_id', $model->id)
                ->with('preview.project')
                ->latest()
                ->orderByDesc('id')
                ->take(3)
                ->get(),
        ]);
    }

    /**
     * The preview itself is the destination, so this sends the customer
     * straight there instead of showing a page whose only content is a button.
     *
     * The route stays because links to it live in mails and bookmarks, and
     * because a preview that is not up needs somewhere to say so.
     */
    public function showPreview(Request $request, string $project, string $preview): RedirectResponse|View
    {
        [$projectModel, $previewModel] = $this->resolvePreview($request->user(), $project, $preview);

        // url() is gated on the status and re-checks the address on every
        // call -- the subdomain against the base domain, an upstream URL against
        // the allowlist -- so this only ever leaves for a configured host.
        if (($url = $previewModel->url()) !== null) {
            return redirect()->away($url);
        }

        return view('portal.previews.show', [
            'project' => $projectModel,
            'preview' => $previewModel,
        ]);
    }

    /**
     * The card picture. Protected exactly like the preview it shows, and only
     * ever the picture of the version that is live -- a stale one is a 404 and
     * the card shows its placeholder instead.
     */
    public function thumbnail(Request $request, string $project, string $preview): StreamedResponse
    {
        [, $previewModel] = $this->resolvePreview($request->user(), $project, $preview);

        $disk = Storage::disk(config('previews.thumbnails.disk'));

        abort_unless(
            $previewModel->status->isVisitable()
                && $previewModel->hasCurrentThumbnail()
                && $disk->exists($previewModel->thumbnail_path),
            404
        );

        return $disk->response($previewModel->thumbnail_path, 'vorschau.jpg', [
            'Content-Type' => 'image/jpeg',
            // Private: never kept by a shared cache. The URL changes with every
            // new picture, so a day in the browser cache is safe.
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
