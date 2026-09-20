<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A discount is stored on the product rather than in a separate offers
     * table: one dish has at most one running offer, and an expired
     * discount_ends_at switches it off without anyone having to remember.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('discount_price', 10, 2)->nullable()->after('price');
            // NULL = runs until an admin removes it.
            $table->timestamp('discount_ends_at')->nullable()->after('discount_price');

            // The storefront's "Deals" listing filters on these.
            $table->index(['discount_price', 'discount_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['discount_price', 'discount_ends_at']);
            $table->dropColumn(['discount_price', 'discount_ends_at']);
        });
    }
};
