<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            // Stored upper-cased; lookups are case-insensitive.
            $table->string('code', 40)->unique();
            $table->string('description', 255)->nullable();
            // percent | fixed | free_delivery — cast to App\Enums\PromoCodeType.
            $table->string('type', 20);
            // Percentage (0-100) or amount off; ignored for free_delivery.
            $table->decimal('value', 8, 2)->default(0);
            $table->decimal('min_subtotal', 10, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // NULL = unlimited redemptions.
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true);
            // Public codes are advertised on the Deals page; private ones are
            // only usable by someone who was given the code.
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
