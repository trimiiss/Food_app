<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // RESTRICT: orders are financial records and must outlive casual deletes.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('order_number', 32)->unique();
            // Cast to App\Enums\OrderStatus; transitions are enforced there.
            $table->string('status', 32)->default('pending')->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('delivery_address', 500);
            $table->string('contact_phone', 30);
            $table->text('notes')->nullable();
            $table->timestamps();

            // "My orders, newest first".
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
