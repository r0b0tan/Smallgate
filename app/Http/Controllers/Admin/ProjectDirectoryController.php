<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DirectoryStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CreateProjectDirectory;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The "Ordner anlegen" button. The request only fixes the folder name and
 * queues the job; the folder itself is created by the queue worker, and the
 * project page shows the progress until it is done.
 */
class ProjectDirectoryController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('createDirectory', $project);

        $back = redirect()->route('admin.projects.show', $project);

        if ($project->directory_status === DirectoryStatus::Created) {
            return $back->with('status', 'Der Projektordner existiert bereits.');
        }

        // Pending already: queue it again in case the first job got lost. The
        // job is unique per project, so a double click never runs it twice.
        if ($project->directory_status === DirectoryStatus::Pending) {
            CreateProjectDirectory::dispatch($project->id, $request->user()->id);

            return $back->with('status', 'Der Projektordner wird bereits angelegt.');
        }

        // Fixed on the first request and kept from then on, even across a
        // rename of the project or a failed attempt.
        $directory = $project->directory ?? $project->load('customer')->proposedDirectory();

        if (Project::query()->where('directory', $directory)->whereKeyNot($project->id)->exists()) {
            return $back->with('error', 'Ein anderes Projekt nutzt den Ordner „'.$directory.'“ bereits.');
        }

        // Claimed in one statement, so two simultaneous clicks queue one job.
        $claimed = Project::query()
            ->whereKey($project->id)
            ->where(fn ($query) => $query
                ->whereNull('directory_status')
                ->orWhere('directory_status', DirectoryStatus::Failed))
            ->toBase()
            ->update([
                'directory' => $directory,
                'directory_status' => DirectoryStatus::Pending->value,
                'directory_error' => null,
            ]);

        if ($claimed > 0) {
            CreateProjectDirectory::dispatch($project->id, $request->user()->id);
        }

        return $back->with('status', 'Der Projektordner wird angelegt.');
    }
}
