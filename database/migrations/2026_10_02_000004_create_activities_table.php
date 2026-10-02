<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('action', 64);

            // Who did it. Null for a failed sign-in, where nobody is signed in.
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // Which customer it concerns, for the filter. Null for an
            // administrator's own sign-in and the like.
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->nullableUlidMorphs('subject');

            // The subject's name at the time, for subjects that are deleted
            // later (a preview) -- never a person's name or address.
            $table->string('subject_label')->nullable();

            // Small, non-personal details, e.g. the version a provisioning
            // published. Never free text or an email address.
            $table->jsonb('properties')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['customer_id', 'created_at']);
        });

        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_action_check CHECK (action IN (
            'login', 'login_failed', 'password_reset', 'password_changed', 'profile_updated',
            'invitation_sent', 'invitation_resent', 'invitation_revoked', 'invitation_accepted',
            'user_blocked', 'user_unblocked',
            'customer_created', 'customer_updated', 'customer_activated', 'customer_deactivated',
            'project_created', 'project_updated',
            'preview_created', 'preview_updated', 'preview_deleted', 'preview_provisioned',
            'preview_provision_failed', 'preview_disabled',
            'feedback_approved', 'feedback_changes_requested'
        ))");
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
