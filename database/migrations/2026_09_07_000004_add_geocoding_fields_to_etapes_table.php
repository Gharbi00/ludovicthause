<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etapes', function (Blueprint $table) {
            $table->string('lieu_libelle')->nullable()->after('adresse');
            $table->string('adresse_normalisee')->nullable()->after('lieu_libelle');
            $table->string('geocodage_source')->nullable()->after('adresse_normalisee');
            $table->string('geocodage_provider_id')->nullable()->after('geocodage_source');
            $table->text('lieu_acces')->nullable()->after('geocodage_provider_id');
            $table->string('lieu_contact')->nullable()->after('lieu_acces');
            $table->text('lieu_commentaire')->nullable()->after('lieu_contact');
        });
    }

    public function down(): void
    {
        Schema::table('etapes', function (Blueprint $table) {
            $table->dropColumn([
                'lieu_libelle', 'adresse_normalisee', 'geocodage_source',
                'geocodage_provider_id', 'lieu_acces', 'lieu_contact', 'lieu_commentaire',
            ]);
        });
    }
};
