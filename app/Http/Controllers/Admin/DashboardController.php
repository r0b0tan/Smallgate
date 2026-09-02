<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PreviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * A work list, not a statistics page: with a handful of customers, counts say
 * nothing an administrator can act on. What is open does.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'openPreviews' => $this->openPreviews(),
            'pendingInvitations' => Invitation::query()->pending()->with('customer')->latest()->take(5)->get(),
            'recentProjects' => Project::query()->with('customer')->latest()->take(5)->get(),
        ]);
    }

    /**
     * Previews that still need a decision: never provisioned, failed, or edited
     * since they were last put live.
     *
     * @return Collection<int, Preview>
     */
    private function openPreviews(): Collection
    {
        return Preview::query()
            ->with('project.customer')
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [PreviewStatus::Draft, PreviewStatus::Failed])
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', PreviewStatus::Available)
                    ->whereColumn('updated_at', '>', 'provisioned_at')))
            ->orderBy('updated_at')
            ->take(8)
            ->get();
    }
}
