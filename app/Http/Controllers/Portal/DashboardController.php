<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        // The scope -- not a where clause written out here -- decides what is
        // visible. Same scope everywhere, so there is one place to get right.
        $projects = Project::query()
            ->visibleTo($user)
            ->withCount(['previews' => fn ($q) => $q->where('status', 'available')])
            ->orderBy('name')
            ->get();

        return view('portal.dashboard', [
            'projects' => $projects,
            'customer' => $user->customer,
        ]);
    }
}
