<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->decimal('original_distance_km', 12, 2)->nullable()->after('calcul_payload');
            $table->decimal('original_distance_km_charge', 12, 2)->nullable()->after('original_distance_km');
            $table->decimal('original_distance_km_vide', 12, 2)->nullable()->after('original_distance_km_charge');
            $table->integer('original_duree_conduite_minutes')->nullable()->after('original_distance_km_vide');
            $table->integer('original_temps_attente_minutes')->nullable()->after('original_duree_conduite_minutes');
            $table->string('override_author')->nullable()->after('original_temps_attente_minutes');
            $table->timestamp('override_at')->nullable()->after('override_author');
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropColumn([
                'original_distance_km',
                'original_distance_km_charge',
                'original_distance_km_vide',
                'original_duree_conduite_minutes',
                'original_temps_attente_minutes',
                'override_author',
                'override_at',
            ]);
        });
    }
};
