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
        Schema::table('items', function (Blueprint $table) {
            $table->integer('qty')->default(1);
            $table->integer('qty_management')->default(1);
        });
        //Variants already have qty field
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
