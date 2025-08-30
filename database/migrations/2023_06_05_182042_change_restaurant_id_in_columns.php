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

        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('restorant_id', 'company_id');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->renameColumn('restaurant_id', 'company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
