<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS = [
        'login', 'login_failed', 'password_reset', 'password_changed', 'profile_updated',
        'invitation_sent', 'invitation_resent', 'invitation_revoked', 'invitation_accepted',
        'user_blocked', 'user_unblocked',
        'customer_created', 'customer_updated', 'customer_activated', 'customer_deactivated',
        'project_created', 'project_updated',
        'preview_created', 'preview_updated', 'preview_deleted', 'preview_provisioned',
        'preview_provision_failed', 'preview_disabled',
        'feedback_approved', 'feedback_changes_requested',
        'branding_updated',
    ];

    private const ADDED = ['project_directory_created', 'project_directory_failed'];

    public function up(): void
    {
        $this->replaceCheck([...self::ACTIONS, ...self::ADDED]);
    }

    public function down(): void
    {
        DB::table('activities')->whereIn('action', self::ADDED)->delete();

        $this->replaceCheck(self::ACTIONS);
    }

    /**
     * @param  list<string>  $actions
     */
    private function replaceCheck(array $actions): void
    {
        $list = implode(', ', array_map(fn (string $action) => "'{$action}'", $actions));

        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_action_check');
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_action_check CHECK (action IN ({$list}))");
    }
};
