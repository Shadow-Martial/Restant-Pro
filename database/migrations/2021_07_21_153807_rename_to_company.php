<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $oldName = 'restorants';
        $newName = 'companies';

        //Rename the table
        Schema::rename($oldName, $newName);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $newName = 'restorants';
        $oldName = 'companies';

        //Rename the table
        Schema::rename($oldName, $newName);
    }
};
