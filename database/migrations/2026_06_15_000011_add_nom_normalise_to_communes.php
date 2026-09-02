<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communes', function (Blueprint $table) {
            // Version normalisée du nom (sans accents/tirets/espaces) pour la recherche.
            $table->string('nom_normalise')->nullable()->after('nom')->index();
        });
    }

    public function down(): void
    {
        Schema::table('communes', function (Blueprint $table) {
            $table->dropColumn('nom_normalise');
        });
    }
};
