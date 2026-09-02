<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('demande_id')->constrained('demandes');
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules');

            // Itinéraire
            $table->decimal('distance_km', 10, 2)->default(0);
            $table->decimal('distance_km_charge', 10, 2)->default(0);
            $table->decimal('distance_km_vide', 10, 2)->default(0);
            $table->unsignedInteger('duree_conduite_minutes')->default(0);
            $table->unsignedInteger('temps_attente_minutes')->default(0);

            // Configuration chauffeur (déduite RSE)
            $table->unsignedTinyInteger('nb_chauffeurs')->default(1);
            $table->unsignedInteger('nb_nuitees')->default(0);

            // Postes de coût
            $table->decimal('cout_carburant', 10, 2)->default(0);
            $table->decimal('cout_peage', 10, 2)->default(0);
            $table->decimal('cout_vignettes', 10, 2)->default(0);
            $table->decimal('cout_chauffeur', 10, 2)->default(0);
            $table->decimal('cout_charges_fixes', 10, 2)->default(0);
            $table->decimal('cout_charges_variables', 10, 2)->default(0);
            $table->decimal('cout_revient_ht', 10, 2)->default(0);

            // Marge & totaux
            $table->decimal('marge_taux', 5, 2)->default(0);
            $table->decimal('montant_ht', 10, 2)->default(0);
            $table->decimal('taux_tva', 5, 2)->default(10);
            $table->decimal('montant_tva', 10, 2)->default(0);
            $table->decimal('montant_ttc', 10, 2)->default(0);
            $table->decimal('marge_montant', 10, 2)->default(0);

            // brouillon → valide → envoye → accepte / refuse
            $table->string('statut')->default('brouillon')->index();
            $table->string('pdf_path')->nullable();
            $table->json('calcul_payload')->nullable();   // réponses API brutes (traçabilité)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis');
    }
};
