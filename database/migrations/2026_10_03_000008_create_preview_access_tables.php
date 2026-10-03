<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two steps of opening a preview on its own host (ADR 0003): a handoff
 * token the portal hands out for a few seconds, and the session the preview
 * host exchanges it for. Both store only the SHA-256 hash of their token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_handoffs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('preview_id')->constrained('previews')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');

            $table->index('expires_at');
        });

        Schema::create('preview_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('preview_id')->constrained('previews')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at');

            $table->index('expires_at');
        });

        foreach (['preview_handoffs', 'preview_sessions'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_token_hash_check
                CHECK (token_hash ~ '^[0-9a-f]{64}$')");

            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_expiry_check
                CHECK (expires_at > created_at)");
        }

        // A handoff is redeemed once, and only while it is still valid.
        DB::statement('ALTER TABLE preview_handoffs ADD CONSTRAINT preview_handoffs_used_check
            CHECK (used_at IS NULL OR (used_at >= created_at AND used_at <= expires_at))');
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_sessions');
        Schema::dropIfExists('preview_handoffs');
    }
};
