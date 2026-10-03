<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding', function (Blueprint $table) {
            // A single row, never shown in a URL -- so a fixed integer key
            // rather than a ULID.
            $table->unsignedSmallInteger('id')->primary();

            // Every column is optional: an empty one falls back to the
            // built-in look.
            $table->string('name', 60)->nullable();
            $table->string('copyright', 120)->nullable();
            $table->char('brand_color', 7)->nullable();
            $table->char('accent_color', 7)->nullable();

            // Files on the private disk, served by BrandingAssetController.
            $table->string('logo_light_path')->nullable();
            $table->string('logo_light_mime', 32)->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('logo_dark_mime', 32)->nullable();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE branding ADD CONSTRAINT branding_single_row_check CHECK (id = 1)');

        // The colours end up verbatim in a stylesheet; nothing but a lower case
        // hex triplet may get there.
        DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_brand_color_check
            CHECK (brand_color IS NULL OR brand_color ~ '^#[0-9a-f]{6}$')");
        DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_accent_color_check
            CHECK (accent_color IS NULL OR accent_color ~ '^#[0-9a-f]{6}$')");

        // A logo is a path and its type together, and only a raster type the
        // browser cannot execute. The explicit IS NOT NULL matters: NULL IN
        // (...) is NULL, and a CHECK lets NULL through.
        foreach (['light', 'dark'] as $variant) {
            DB::statement("ALTER TABLE branding ADD CONSTRAINT branding_logo_{$variant}_check
                CHECK ((logo_{$variant}_path IS NULL AND logo_{$variant}_mime IS NULL)
                    OR (logo_{$variant}_path IS NOT NULL AND logo_{$variant}_mime IS NOT NULL
                        AND logo_{$variant}_mime IN ('image/png', 'image/webp')))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branding');
    }
};
