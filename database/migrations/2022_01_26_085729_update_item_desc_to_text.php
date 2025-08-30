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
        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                $table->text('description')->change();
                $table->text('name')->change();
            });

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
