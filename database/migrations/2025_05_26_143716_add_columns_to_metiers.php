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
        Schema::table('metiers', function (Blueprint $table) {
           $table->foreignId('niveau_id')->constrained('niveaux')->onDelete('cascade');
              $table->foreignId('departement_id')->constrained('departements')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metiers', function (Blueprint $table) {
            //
        });
    }
};
