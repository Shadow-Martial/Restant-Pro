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
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('kds_finished')->default(0);
        });
        Schema::table('order_has_items', function (Blueprint $table) {
            $table->integer('kds_finished')->default(0);
        });
        Schema::table('cart_storage', function (Blueprint $table) {
            $table->integer('kds_finished')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
