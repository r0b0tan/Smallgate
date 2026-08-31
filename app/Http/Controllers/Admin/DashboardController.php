<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'customerCount' => Customer::query()->count(),
            'activeCustomerCount' => Customer::query()->active()->count(),
            'projectCount' => Project::query()->count(),
            'activeProjectCount' => Project::query()->status(ProjectStatus::Active)->count(),
            'previewCount' => Preview::query()->count(),
            'pendingInvitations' => Invitation::query()->pending()->with('customer')->latest()->take(5)->get(),
            'recentProjects' => Project::query()->with('customer')->latest()->take(5)->get(),
        ]);
    }
}
