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
        Schema::create('emploi_du_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_id')->constrained('annees');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->foreignId('salle_id')->constrained('salles');
            $table->foreignId('competence_id')->constrained('competences');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emploi_du_temps');
    }
};
