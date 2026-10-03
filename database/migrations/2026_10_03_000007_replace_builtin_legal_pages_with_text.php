<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The built-in imprint and privacy pages (filled from LEGAL_*) give way to a
 * text the administrator pastes in. Without a link or a text, a page is not
 * shown -- which is where installations that used the built-in page land.
 */
return new class extends Migration
{
    private const PAGES = ['imprint', 'privacy'];

    public function up(): void
    {
        Schema::table('branding', function (Blueprint $table) {
            foreach (self::PAGES as $page) {
                $table->text("{$page}_text")->nullable();
            }
        });

        foreach (self::PAGES as $page) {
            DB::statement("ALTER TABLE branding DROP CONSTRAINT branding_{$page}_mode_check");
            DB::table('branding')->where("{$page}_mode", 'builtin')->update(["{$page}_mode" => 'hidden']);
            DB::statement("ALTER TABLE branding ALTER COLUMN {$page}_mode SET DEFAULT 'hidden'");

            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_{$page}_mode_check
                CHECK ({$page}_mode IN ('link', 'text', 'hidden'))");

            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_{$page}_text_check
                CHECK ({$page}_mode <> 'text' OR ({$page}_text IS NOT NULL AND btrim({$page}_text) <> ''))");

            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_{$page}_text_length_check
                CHECK ({$page}_text IS NULL OR char_length({$page}_text) <= 100000)");
        }
    }

    public function down(): void
    {
        foreach (self::PAGES as $page) {
            DB::statement("ALTER TABLE branding DROP CONSTRAINT branding_{$page}_mode_check");
            DB::statement("ALTER TABLE branding DROP CONSTRAINT branding_{$page}_text_check");
            DB::statement("ALTER TABLE branding DROP CONSTRAINT branding_{$page}_text_length_check");

            DB::table('branding')->where("{$page}_mode", 'text')->update(["{$page}_mode" => 'builtin']);
            DB::statement("ALTER TABLE branding ALTER COLUMN {$page}_mode SET DEFAULT 'builtin'");

            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_{$page}_mode_check
                CHECK ({$page}_mode IN ('builtin', 'link', 'hidden'))");
        }

        Schema::table('branding', function (Blueprint $table) {
            foreach (self::PAGES as $page) {
                $table->dropColumn("{$page}_text");
            }
        });
    }
};
