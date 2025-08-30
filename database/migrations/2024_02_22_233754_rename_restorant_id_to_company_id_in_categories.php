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
        try {
            Schema::table('categories', function (Blueprint $table) {
                $table->renameColumn('restorant_id', 'category_id');
            });
        } catch (\Throwable $th) {
        }
       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('category_id', 'restorant_id');
        });
    }
};
         