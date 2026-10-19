<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep social links optional for existing listings.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('social_links')->nullable();
        });
    }

    /**
     * Remove optional social links.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
