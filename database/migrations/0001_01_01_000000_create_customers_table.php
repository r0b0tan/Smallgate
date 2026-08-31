<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            // ULID primary keys: customer identifiers appear in URLs, and
            // sequential integers would let anyone count or enumerate clients.
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // Database level guarantees next to the application validation, so a
        // stray tinker session or seeder cannot write malformed data either.
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_slug_format_check
            CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");

        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_contact_email_lowercase_check
            CHECK (contact_email IS NULL OR contact_email = lower(contact_email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
