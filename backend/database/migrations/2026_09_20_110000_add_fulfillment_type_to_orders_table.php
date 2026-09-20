<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cast to App\Enums\FulfillmentType. Every existing order was a
            // delivery, which is also what a client that omits the field means.
            $table->string('fulfillment_type', 16)->default('delivery')->after('status')->index();
        });

        // Pickup orders are collected from the shop, so they have no address.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_address', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // The column goes back to NOT NULL, so pickup orders need something.
        DB::table('orders')->whereNull('delivery_address')->update([
            'delivery_address' => 'Collected in store',
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_address', 500)->nullable(false)->change();
        });
    }
};
