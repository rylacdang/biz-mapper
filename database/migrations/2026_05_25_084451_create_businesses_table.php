<?php

use App\Enums\BusinessStatus;
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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('address');
            $table->decimal('lat', 10, 7)->index();
            $table->decimal('lng', 10, 7)->index();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('cover_photo'); // Required cover image
            $table->string('logo')->nullable();
            $table->json('hours'); // Day-to-day schedule JSON
            $table->string('status')->default(BusinessStatus::PENDING->value);
            $table->unsignedInteger('view_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
