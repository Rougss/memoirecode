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
        Schema::create('compesemestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competence_id')->constrained('competences');
            $table->foreignId('semestre_id')->constrained('semestres');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compesemestres');
    }
};
