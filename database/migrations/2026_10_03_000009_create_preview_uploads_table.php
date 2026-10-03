<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts uploaded as a ZIP (ADR 0004). Every row stands for one folder the
 * queue worker unpacked; its path is derived from the ids, never stored, and
 * these rows are the only folders Smallgate may ever delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_uploads', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // No cascade: a preview's folders have to be removed from disk
            // before the rows that name them can go.
            $table->foreignUlid('preview_id')->constrained('previews')->restrictOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 16)->default('pending');
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('file_count')->nullable();

            // A fixed German message for the administrator, never a path or a
            // name taken from the archive.
            $table->string('error')->nullable();

            $table->timestamp('created_at');
            $table->timestamp('finished_at')->nullable();

            $table->index(['preview_id', 'created_at']);
        });

        DB::statement("ALTER TABLE preview_uploads ADD CONSTRAINT preview_uploads_status_check
            CHECK (status IN ('pending', 'extracting', 'ready', 'failed', 'removed'))");

        DB::statement('ALTER TABLE preview_uploads ADD CONSTRAINT preview_uploads_counts_check
            CHECK ((size_bytes IS NULL OR size_bytes >= 0) AND (file_count IS NULL OR file_count >= 0))');

        // An unpacked draft knows what it holds and when it was done.
        DB::statement("ALTER TABLE preview_uploads ADD CONSTRAINT preview_uploads_ready_check
            CHECK (status <> 'ready'
                OR (size_bytes IS NOT NULL AND file_count IS NOT NULL AND finished_at IS NOT NULL))");

        DB::statement("ALTER TABLE preview_uploads ADD CONSTRAINT preview_uploads_failed_check
            CHECK (status <> 'failed' OR (error IS NOT NULL AND finished_at IS NOT NULL))");

        // One upload at a time per preview: a second one could switch the
        // preview to a folder that is older than the one already live.
        DB::statement("CREATE UNIQUE INDEX preview_uploads_one_running
            ON preview_uploads (preview_id) WHERE status IN ('pending', 'extracting')");
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_uploads');
    }
};
