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
        Schema::table('competences', function (Blueprint $table) {
           $table->dropForeign(['id_salle']); // Supprime la contrainte de clé étrangère
            $table->dropColumn('id_salle');    // Supprime la colonne
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competences', function (Blueprint $table) {
      $table->foreignId('id_salle')->constrained('salles');

        });
    }
};
