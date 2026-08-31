<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamps();

            // Slugs only have to be unique within a customer, which keeps
            // readable slugs like "website-relaunch" available to everyone.
            $table->unique(['customer_id', 'slug']);
            $table->index(['customer_id', 'status']);
        });

        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_status_check
            CHECK (status IN ('draft', 'active', 'waiting_for_feedback', 'completed', 'archived'))");

        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_slug_format_check
            CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
