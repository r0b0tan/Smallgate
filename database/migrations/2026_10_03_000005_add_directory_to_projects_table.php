<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // "<customer-slug>/<project-slug>" below the configured root, fixed
            // when the folder is first requested: renaming the project later
            // must not point it at a different folder.
            $table->string('directory')->nullable()->unique();
            $table->string('directory_status', 16)->nullable();
            // A fixed German message for the administrator, never a path.
            $table->string('directory_error')->nullable();
            $table->timestamp('directory_created_at')->nullable();
        });

        // Two levels of slugs, nothing else: no "..", no absolute path.
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_directory_format_check
            CHECK (directory IS NULL OR directory ~ '^[a-z0-9]+(-[a-z0-9]+)*/[a-z0-9]+(-[a-z0-9]+)*$')");

        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_directory_status_check
            CHECK (directory_status IS NULL OR directory_status IN ('pending', 'created', 'failed'))");

        // Every status belongs to a fixed name, and a created folder has a date.
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_directory_named_check
            CHECK (directory_status IS NULL OR directory IS NOT NULL)');

        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_directory_created_check
            CHECK (directory_status IS DISTINCT FROM 'created' OR directory_created_at IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_directory_created_check');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_directory_named_check');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_directory_status_check');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_directory_format_check');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['directory']);
            $table->dropColumn(['directory', 'directory_status', 'directory_error', 'directory_created_at']);
        });
    }
};
