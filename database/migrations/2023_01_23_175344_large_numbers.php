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
        Schema::table('coupons', function (Blueprint $table) {
            $table->float('price', 16, 2)->change();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->float('amount', 16, 2)->change();
        });
        Schema::table('plan', function (Blueprint $table) {
            $table->float('price', 16, 2)->change();
        });

        Schema::table('order_has_items', function (Blueprint $table) {
            $table->float('variant_price', 16, 2)->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->float('delivery_price', 16, 2)->change();
            $table->float('order_price', 16, 2)->change();
            $table->float('fee_value', 16, 2)->change();
            $table->float('static_fee', 16, 2)->change();
            $table->float('vatvalue', 16, 2)->change();
            $table->float('discount', 16, 2)->change();
        });
        Schema::table('variants', function (Blueprint $table) {
            $table->float('price', 16, 2)->change();
        });
        Schema::table('items', function (Blueprint $table) {
            $table->float('price', 16, 2)->change();
            $table->float('discounted_price', 16, 2)->change();
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
