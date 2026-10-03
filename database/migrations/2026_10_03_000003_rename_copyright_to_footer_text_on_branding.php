<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The footer shows free text rather than "© <year> <holder>".
    public function up(): void
    {
        Schema::table('branding', function (Blueprint $table) {
            $table->renameColumn('copyright', 'footer_text');
        });
    }

    public function down(): void
    {
        Schema::table('branding', function (Blueprint $table) {
            $table->renameColumn('footer_text', 'copyright');
        });
    }
};
