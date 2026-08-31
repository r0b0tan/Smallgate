<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('previews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');

            // The future subdomain, e.g. holzmann.preview.clickit-digital.de.
            // Globally unique because a host header maps to exactly one preview.
            $table->string('hostname')->nullable()->unique();

            // Where the preview is served from. Only administrators may set
            // these, and only to values allowed by config/previews.php.
            $table->string('target_type', 32)->default('static_directory');
            $table->text('target')->nullable();

            $table->string('status', 32)->default('draft');
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->index(['project_id', 'status']);
        });

        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_status_check
            CHECK (status IN ('draft', 'provisioning', 'available', 'disabled', 'failed'))");

        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_target_type_check
            CHECK (target_type IN ('static_directory', 'upstream_url'))");

        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_slug_format_check
            CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");

        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_hostname_format_check
            CHECK (hostname IS NULL OR hostname ~ '^[a-z0-9]+(-[a-z0-9]+)*(\.[a-z0-9]+(-[a-z0-9]+)*)+$')");

        // A preview can only be reachable once it actually has a target.
        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_available_needs_target_check
            CHECK (status <> 'available' OR (target IS NOT NULL AND hostname IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('previews');
    }
};
