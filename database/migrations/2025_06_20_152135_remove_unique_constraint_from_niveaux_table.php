<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('niveaux', function (Blueprint $table) {
            // Supprimer l'ancienne contrainte d'unicité globale
            $table->dropUnique(['intitule']); // ou $table->dropUnique('niveaux_intitule_unique');
            
            // Ajouter la nouvelle contrainte d'unicité composée
            $table->unique(['intitule', 'type_formation_id'], 'niveaux_intitule_type_unique');
        });
    }

    public function down()
    {
        Schema::table('niveaux', function (Blueprint $table) {
            // Restaurer l'ancienne contrainte pour le rollback
            $table->dropUnique('niveaux_intitule_type_unique');
            $table->unique('intitule');
        });
    }
};