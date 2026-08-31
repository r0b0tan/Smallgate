<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // MVP: a user belongs to exactly one customer. Administrators have
            // no customer at all. Should multiple assignments ever be needed,
            // only App\Models\User::accessibleCustomerIds() and this column
            // have to change -- every query funnels through that method.
            $table->foreignUlid('customer_id')->nullable()
                ->constrained('customers')->restrictOnDelete();

            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 20)->default('customer');
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
            $table->index('role');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('admin', 'customer'))");

        // Emails are stored normalised, which makes the plain unique index above
        // a genuinely case-insensitive uniqueness guarantee.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_lowercase_check
            CHECK (email = lower(email))');

        // The core tenancy invariant, enforced by the database rather than by
        // convention: a customer user always has a customer, an admin never has.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_customer_check CHECK (
            (role = 'customer' AND customer_id IS NOT NULL)
            OR (role = 'admin' AND customer_id IS NULL)
        )");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
