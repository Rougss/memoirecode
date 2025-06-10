<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropForeign(['salle_id']); // Supprime la contrainte de clé étrangère
            $table->dropColumn('salle_id');    // Supprime la colonne
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->foreignId('salle_id')->constrained('salles');
        });
    }
};

