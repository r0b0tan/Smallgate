<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->with('customer')
            ->withCount('previews')
            ->when($request->filled('customer'), fn ($q) => $q->where('customer_id', $request->string('customer')))
            ->when(
                $request->filled('status') && ProjectStatus::tryFrom((string) $request->string('status')),
                fn ($q) => $q->where('status', $request->string('status'))
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'customers' => Customer::query()->orderBy('name')->get(),
            'statuses' => ProjectStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);

        $project = new Project(['status' => ProjectStatus::Draft->value]);
        $project->customer_id = (string) $request->string('customer') ?: null;

        return view('admin.projects.create', [
            'project' => $project,
            'customers' => Customer::query()->orderBy('name')->get(),
            'statuses' => ProjectStatus::options(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validated();

        $project = new Project;
        // customer_id is excluded from fill() because it is not mass assignable
        // -- it decides who may see the project, so it is assigned explicitly.
        $project->fill(Arr::except($validated, ['customer_id']));
        $project->customer_id = $validated['customer_id'];
        $project->save();

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Projekt wurde angelegt.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        return view('admin.projects.show', [
            'project' => $project->load('customer'),
            'previews' => $project->previews()->orderBy('name')->get(),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('admin.projects.edit', [
            'project' => $project,
            'customers' => Customer::query()->orderBy('name')->get(),
            'statuses' => ProjectStatus::options(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->fill(Arr::except($validated, ['customer_id']));
        $project->customer_id = $validated['customer_id'];
        $project->save();

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Projekt wurde gespeichert.');
    }
}
