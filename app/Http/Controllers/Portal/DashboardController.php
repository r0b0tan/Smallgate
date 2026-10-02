<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PreviewFeedback;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's one page: the status of each project with every draft they
 * are offered, unanswered first, each with its own "view" and "answer"
 * buttons, and the last few answers beside it. No second level -- a customer
 * with several projects sees them on the same page.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        // The scope -- not a where clause written out here -- decides what is
        // visible. Same scope everywhere, so there is one place to get right.
        $projects = Project::query()
            ->visibleTo($request->user())
            ->withOfferedPreviews()
            ->orderBy('name')
            ->get();

        return view('portal.dashboard', [
            'projects' => $projects,
            'recentFeedback' => PreviewFeedback::query()
                ->visibleTo($request->user())
                ->with('preview.project')
                ->latest()
                ->orderByDesc('id')
                ->take(3)
                ->get(),
        ]);
    }
}
