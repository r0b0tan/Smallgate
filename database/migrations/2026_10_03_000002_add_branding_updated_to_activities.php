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
    ];

    public function up(): void
    {
        $this->replaceCheck([...self::ACTIONS, 'branding_updated']);
    }

    public function down(): void
    {
        DB::table('activities')->where('action', 'branding_updated')->delete();

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
