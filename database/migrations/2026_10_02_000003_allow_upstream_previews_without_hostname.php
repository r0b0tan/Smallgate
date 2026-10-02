<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * An upstream preview is opened at its own URL (path included), so only a
     * static preview needs a hostname to be available.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE previews DROP CONSTRAINT previews_available_needs_target_check');

        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_available_needs_target_check
            CHECK (status <> 'available'
                OR (target IS NOT NULL AND (hostname IS NOT NULL OR target_type = 'upstream_url')))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE previews DROP CONSTRAINT previews_available_needs_target_check');

        DB::statement("ALTER TABLE previews ADD CONSTRAINT previews_available_needs_target_check
            CHECK (status <> 'available' OR (target IS NOT NULL AND hostname IS NOT NULL))");
    }
};
