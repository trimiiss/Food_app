<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The code is snapshotted as a string rather than a foreign key: deleting a
     * promo code later must not erase what a past order was charged, and the
     * order only ever needs the code that was used.
     *
     * total = subtotal + delivery_fee - discount_total
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('promo_code', 40)->nullable()->after('delivery_fee');
            $table->decimal('discount_total', 10, 2)->default(0)->after('promo_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['promo_code', 'discount_total']);
        });
    }
};
