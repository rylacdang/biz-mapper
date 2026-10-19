<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store optional catalog details independently of business registration.
     */
    public function up(): void
    {
        Schema::create('business_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('price_max', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('price_unit', 50)->nullable();
            $table->timestamps();
            $table->index(['business_id', 'type', 'id']);
        });
    }

    /**
     * Remove catalog storage.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_offerings');
    }
};
