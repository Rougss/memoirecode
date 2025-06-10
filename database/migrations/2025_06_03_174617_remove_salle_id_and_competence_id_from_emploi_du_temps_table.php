<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('emploi_du_temps', function (Blueprint $table) {
            // D'abord, supprimer les contraintes de clés étrangères si elles existent
            $table->dropForeign(['salle_id']);
            $table->dropForeign(['competence_id']);

            // Ensuite, supprimer les colonnes
            $table->dropColumn(['salle_id', 'competence_id']);
        });
    }

    public function down(): void
    {
        Schema::table('emploi_du_temps', function (Blueprint $table) {
            // Recréer les colonnes avec les contraintes
            $table->foreignId('salle_id')->constrained('salles');
            $table->foreignId('competence_id')->constrained('competences');
        });
    }
};
