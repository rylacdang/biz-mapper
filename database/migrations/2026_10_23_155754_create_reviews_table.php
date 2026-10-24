<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store one review per consumer and business.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('status', 20)->default('published');
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
            $table->index(['business_id', 'status', 'id']);
        });
    }

    /**
     * Remove review storage.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
