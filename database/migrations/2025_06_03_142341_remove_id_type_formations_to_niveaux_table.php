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
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropForeign(['id_type_formations']); 
            $table->dropColumn('id_type_formations'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->foreignId('id_type_formations')->constrained('type_formations');
        });
    }
};
