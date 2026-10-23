<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('collection_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_business_id')->constrained()->cascadeOnDelete();
            $table->unique(['place_collection_id', 'saved_business_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_places');
    }
};
