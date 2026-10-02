<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Preview;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The activity log: read only, newest first, filterable by customer and topic.
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $group = (string) $request->string('group');
        $group = array_key_exists($group, ActivityAction::groupOptions()) ? $group : null;

        $activities = Activity::query()
            ->with(['actor', 'customer', 'subject' => fn (MorphTo $subject) => $subject->morphWith([
                Preview::class => ['project'],
            ])])
            // Expired entries are deleted on the next write; until then they
            // are already out of sight.
            ->where('created_at', '>=', Activity::retentionStart())
            ->when($request->filled('customer'), fn ($q) => $q->where('customer_id', $request->string('customer')))
            ->when($group, fn ($q) => $q->whereIn('action', ActivityAction::inGroup($group)))
            ->latest()
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.activities.index', [
            'activities' => $activities,
            'customers' => Customer::query()->orderBy('name')->get(),
            'groups' => ActivityAction::groupOptions(),
            'filtered' => $request->filled('customer') || $group !== null,
            'retentionDays' => (int) config('smallgate.activity.retention_days', 90),
        ]);
    }
}
