<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // RESTRICT: deleting a category that still has products is refused
            // (surfaced as a 409) rather than silently wiping the menu.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            // The public listing filters on both of these.
            $table->index(['category_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
