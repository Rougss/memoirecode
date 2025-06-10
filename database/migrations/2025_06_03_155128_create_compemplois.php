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
        Schema::create('compemplois', function (Blueprint $table) {
            $table->id();
             $table->foreignId('competence_id')->constrained('competences');
            $table->foreignId('emploi_du_temps_id')->constrained('emploi_du_temps');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compemplois');
    }
};
