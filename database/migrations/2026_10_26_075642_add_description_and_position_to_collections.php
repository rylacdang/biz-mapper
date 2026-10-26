<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend existing collections without replacing their saved-place memberships.
     */
    public function up(): void
    {
        Schema::table('place_collections', function (Blueprint $table) {
            $table->text('description')->nullable();
        });

        Schema::table('collection_places', function (Blueprint $table) {
            $table->bigInteger('position')->nullable();
        });
    }

    /**
     * Retain collections and saves when removing discovery metadata.
     */
    public function down(): void
    {
        Schema::table('collection_places', fn (Blueprint $table) => $table->dropColumn('position'));
        Schema::table('place_collections', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
