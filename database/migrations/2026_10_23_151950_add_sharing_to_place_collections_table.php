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
        Schema::table('place_collections', function (Blueprint $table) {
            $table->boolean('is_public')->default(false);
            $table->uuid('share_token')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('place_collections', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['is_public', 'share_token']);
        });
    }
};
