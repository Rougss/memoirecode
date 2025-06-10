<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->foreignId('id_formateur')->nullable()->constrained('formateurs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->dropForeign(['id_formateur']);
            $table->dropColumn('id_formateur');
        });
    }
};
