<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->decimal('hauteur_m', 5, 2)->nullable()->after('nb_essieux');
            $table->decimal('largeur_m', 5, 2)->nullable()->after('hauteur_m');
            $table->decimal('longueur_m', 5, 2)->nullable()->after('largeur_m');
            $table->decimal('poids_t', 6, 3)->nullable()->after('longueur_m');
        });
    }

    public function down(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn(['hauteur_m', 'largeur_m', 'longueur_m', 'poids_t']);
        });
    }
};
