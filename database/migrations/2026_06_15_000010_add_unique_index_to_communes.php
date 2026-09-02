<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communes', function (Blueprint $table) {
            // Le code INSEE identifie une commune de façon unique (NULL autorisé pour les villes étrangères).
            $table->unique('code_insee');
        });
    }

    public function down(): void
    {
        Schema::table('communes', function (Blueprint $table) {
            $table->dropUnique(['code_insee']);
        });
    }
};
