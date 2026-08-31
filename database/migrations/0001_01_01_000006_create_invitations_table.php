<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->nullable()
                ->constrained('customers')->cascadeOnDelete();

            $table->string('name');
            $table->string('email');
            $table->string('role', 20)->default('customer');

            // Only the SHA-256 hash of the token is stored. The plaintext token
            // exists exactly once, inside the invitation mail. A leaked database
            // therefore does not yield usable invitation links.
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();

            $table->foreignUlid('invited_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignUlid('accepted_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['email', 'accepted_at']);
            $table->index('expires_at');
        });

        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check
            CHECK (role IN ('admin', 'customer'))");

        DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_email_lowercase_check
            CHECK (email = lower(email))');

        // Mirrors the users table invariant so an invitation can never create a
        // customer user without a customer.
        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_customer_check CHECK (
            (role = 'customer' AND customer_id IS NOT NULL)
            OR (role = 'admin' AND customer_id IS NULL)
        )");

        // An accepted invitation must record which user it created.
        DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_accepted_consistency_check CHECK (
            (accepted_at IS NULL AND accepted_user_id IS NULL)
            OR (accepted_at IS NOT NULL AND accepted_user_id IS NOT NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
