<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the latest summary and its source version for each business.
     */
    public function up(): void
    {
        Schema::create('review_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('insufficient_reviews');
            $table->text('text')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->unsignedTinyInteger('generation_attempts')->default(0);
            $table->string('source_fingerprint', 64)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('sampled_review_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('next_generation_at')->nullable();
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_generation_at']);
        });
    }

    /**
     * Remove summary storage without changing reviews.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_summaries');
    }
};
