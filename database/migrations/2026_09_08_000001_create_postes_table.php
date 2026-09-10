<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('ordre');
            $table->string('type'); // prise_service | conduite | attente | fin_service
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->integer('duree_min')->nullable();
            $table->integer('taux'); // 100 ou 50 (ou paramétré)
            $table->string('origine'); // calculee | saisie_manuelle | surchargee
            $table->string('auteur')->nullable();
            $table->timestamp('date_modif')->nullable();
            $table->timestamps();

            $table->index(['devis_id', 'date', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postes');
    }
};
