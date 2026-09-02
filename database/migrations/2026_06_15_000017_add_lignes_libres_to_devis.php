<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            // Prestations supplémentaires libres : [{libelle, montant}, …] facturées en plus du transport.
            $table->json('lignes_libres')->nullable()->after('calcul_payload');
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropColumn('lignes_libres');
        });
    }
};
