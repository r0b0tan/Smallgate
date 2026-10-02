<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('previews', function (Blueprint $table) {
            // Every successful provisioning publishes a new version to the
            // customer. Feedback and thumbnails are pinned to a version, so a
            // re-provisioned preview asks for feedback again and never shows
            // the picture of an older state.
            $table->unsignedInteger('version')->default(0);

            // The screenshot shown on the customer's card. The file lives on the
            // private disk and is only ever served through an authorised route.
            $table->string('thumbnail_status', 16)->nullable();
            $table->unsignedInteger('thumbnail_version')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->timestamp('thumbnail_generated_at')->nullable();
        });

        // Previews that are already live count as their first version.
        DB::table('previews')->whereNotNull('provisioned_at')->update(['version' => 1]);

        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_thumbnail_status_check
            CHECK (thumbnail_status IS NULL OR thumbnail_status IN ('pending', 'ready', 'failed'))");

        // A ready thumbnail always has a file and the version it depicts.
        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_thumbnail_ready_check
            CHECK (thumbnail_status IS DISTINCT FROM 'ready'
                OR (thumbnail_path IS NOT NULL AND thumbnail_version IS NOT NULL))");

        DB::statement('ALTER TABLE previews ADD CONSTRAINT previews_thumbnail_version_check
            CHECK (thumbnail_version IS NULL OR thumbnail_version <= version)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE previews DROP CONSTRAINT IF EXISTS previews_thumbnail_version_check');
        DB::statement('ALTER TABLE previews DROP CONSTRAINT IF EXISTS previews_thumbnail_ready_check');
        DB::statement('ALTER TABLE previews DROP CONSTRAINT IF EXISTS previews_thumbnail_status_check');

        Schema::table('previews', function (Blueprint $table) {
            $table->dropColumn([
                'version',
                'thumbnail_status',
                'thumbnail_version',
                'thumbnail_path',
                'thumbnail_generated_at',
            ]);
        });
    }
};
