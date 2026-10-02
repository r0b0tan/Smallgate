<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_feedback', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('preview_id')->constrained('previews')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            // The version the customer was looking at when answering.
            $table->unsignedInteger('preview_version');

            $table->string('decision', 32);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['preview_id', 'preview_version', 'created_at']);
        });

        DB::statement("ALTER TABLE preview_feedback ADD CONSTRAINT preview_feedback_decision_check
            CHECK (decision IN ('approved', 'changes_requested'))");

        // A comment is optional with either answer, and stays short enough to be
        // read rather than archived.
        DB::statement('ALTER TABLE preview_feedback ADD CONSTRAINT preview_feedback_comment_check
            CHECK (comment IS NULL OR char_length(comment) <= 2000)');

        DB::statement('ALTER TABLE preview_feedback ADD CONSTRAINT preview_feedback_version_check
            CHECK (preview_version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_feedback');
    }
};
