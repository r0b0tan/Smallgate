<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PAGES = ['imprint', 'privacy'];

    public function up(): void
    {
        Schema::table('branding', function (Blueprint $table) {
            foreach (self::PAGES as $page) {
                // The built-in page stays the default, so nothing changes for an
                // installation until somebody decides otherwise.
                $table->string("{$page}_mode", 16)->default('builtin');
                // Kept when another mode is chosen, so switching back is easy.
                $table->string("{$page}_url", 2048)->nullable();
            }
        });

        foreach (self::PAGES as $page) {
            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_{$page}_mode_check
                CHECK ({$page}_mode IN ('builtin', 'link', 'hidden'))");

            // The URL lands in an href: https only, never javascript: or data:.
            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_{$page}_url_check
                CHECK ({$page}_url IS NULL OR {$page}_url ~ '^https://[^[:space:]]+$')");

            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_{$page}_link_check
                CHECK ({$page}_mode <> 'link' OR {$page}_url IS NOT NULL)");
        }
    }

    public function down(): void
    {
        Schema::table('branding', function (Blueprint $table) {
            foreach (self::PAGES as $page) {
                $table->dropColumn(["{$page}_mode", "{$page}_url"]);
            }
        });
    }
};
