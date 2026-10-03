<?php

namespace App\Jobs;

use App\Enums\ActivityAction;
use App\Enums\DirectoryStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates the folder of one project below the configured root.
 *
 * Queued by the "Ordner anlegen" button, never run inside the request. The
 * folder name was fixed when the button was pressed; this job only ever
 * creates that name, one level at a time, below a root that must already
 * exist. It does not create the root, follow symlinks, change ownership or
 * run any command, and it never touches anything outside the root.
 *
 * An existing folder of that name counts as success, so a retry after a crash
 * between mkdir and the status update does not fail.
 */
class CreateProjectDirectory implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    // A lost job must not block the button forever.
    public int $uniqueFor = 600;

    private const MESSAGES = [
        'root_missing' => 'Das Wurzelverzeichnis für Projektordner fehlt oder ist kein Verzeichnis. Es wird in der .env unter PROJECT_DIRECTORY_ROOT eingestellt.',
        'name_invalid' => 'Der Ordnername ist ungültig.',
        'symlink' => 'Unter diesem Namen liegt ein symbolischer Link. Er wird aus Sicherheitsgründen nicht verwendet.',
        'not_a_directory' => 'Unter diesem Namen liegt bereits eine Datei.',
        'mkdir_failed' => 'Der Ordner konnte nicht angelegt werden. Darf der Queue-Worker in das Wurzelverzeichnis schreiben?',
        'escaped_root' => 'Der Ordner liegt nicht im Wurzelverzeichnis.',
        'job_failed' => 'Das Anlegen wurde abgebrochen.',
    ];

    public function __construct(
        public readonly string $projectId,
        public readonly ?string $actorId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->projectId;
    }

    public function handle(): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null || $project->directory_status !== DirectoryStatus::Pending) {
            return;
        }

        $reason = $this->create((string) $project->directory);

        if ($reason !== null) {
            $this->markFailed($reason);

            return;
        }

        $stored = $this->stillPending()->update([
            'directory_status' => DirectoryStatus::Created->value,
            'directory_error' => null,
            'directory_created_at' => Carbon::now(),
        ]);

        if ($stored > 0) {
            Activity::record(ActivityAction::ProjectDirectoryCreated, $project, $this->actor());
        }
    }

    /**
     * Called by the worker when the job dies outside handle(), e.g. on timeout.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed('job_failed');
    }

    /**
     * @return string|null a reason code, or null once the folder exists
     */
    private function create(string $directory): ?string
    {
        $root = realpath((string) config('smallgate.project_directories.root'));

        if ($root === false || ! is_dir($root)) {
            return 'root_missing';
        }

        // The database enforces the same pattern; this is the last line before
        // the filesystem, so it does not rely on that.
        if (preg_match('#^[a-z0-9]+(-[a-z0-9]+)*/[a-z0-9]+(-[a-z0-9]+)*$#', $directory) !== 1) {
            return 'name_invalid';
        }

        $path = $root;

        foreach (explode('/', $directory) as $segment) {
            $path .= '/'.$segment;

            if (is_link($path)) {
                return 'symlink';
            }

            if (file_exists($path)) {
                if (! is_dir($path)) {
                    return 'not_a_directory';
                }

                continue;
            }

            // Not recursive: every level is checked above before it is used.
            if (! @mkdir($path, 0775) && ! is_dir($path)) {
                return 'mkdir_failed';
            }
        }

        // Catches a level swapped for a symlink between the check and mkdir.
        if (realpath($path) !== $path) {
            return 'escaped_root';
        }

        return null;
    }

    private function markFailed(string $reason): void
    {
        // Ids and a reason code only: the path carries the customer's slug.
        Log::warning('Project directory could not be created.', [
            'project_id' => $this->projectId,
            'reason' => $reason,
        ]);

        $stored = $this->stillPending()->update([
            'directory_status' => DirectoryStatus::Failed->value,
            'directory_error' => self::MESSAGES[$reason] ?? self::MESSAGES['job_failed'],
        ]);

        $project = Project::query()->find($this->projectId);

        if ($stored > 0 && $project !== null) {
            Activity::record(ActivityAction::ProjectDirectoryFailed, $project, $this->actor());
        }
    }

    private function actor(): ?User
    {
        return $this->actorId === null ? null : User::query()->find($this->actorId);
    }

    /**
     * The project row while it still waits for this job. A plain query builder,
     * like the thumbnail job: folder state is not an edit of the project and
     * must not move its updated_at.
     */
    private function stillPending(): Builder
    {
        return Project::query()
            ->whereKey($this->projectId)
            ->where('directory_status', DirectoryStatus::Pending)
            ->toBase();
    }
}
