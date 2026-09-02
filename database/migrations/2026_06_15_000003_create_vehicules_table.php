<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('immatriculation')->unique();
            $table->string('numero_parc')->nullable();
            $table->foreignId('categorie_id')->constrained('categories');
            $table->unsignedInteger('nb_places');
            $table->unsignedTinyInteger('nb_essieux')->default(2);   // 2 => classe 3, 3+ => classe 4
            $table->string('type_energie')->default('gazole');

            // Charges variables & conso
            $table->decimal('conso_l_100km', 6, 2)->default(0);

            // Charges fixes (annuelles / mensuelles)
            $table->decimal('loyer_credit_bail_mensuel', 10, 2)->default(0);
            $table->decimal('assurance_annuelle', 10, 2)->default(0);
            $table->decimal('quote_part_loyers_annuelle', 10, 2)->default(0);
            $table->decimal('autres_charges_fixes_annuelles', 10, 2)->default(0);

            // Charges variables au km
            $table->decimal('cout_entretien_km', 8, 4)->default(0);
            $table->decimal('cout_pneus_km', 8, 4)->default(0);
            $table->decimal('cout_adblue_km', 8, 4)->default(0);
            $table->decimal('autres_variables_km', 8, 4)->default(0);

            $table->unsignedInteger('jours_exploitation_an')->default(220);
            $table->boolean('actif')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
