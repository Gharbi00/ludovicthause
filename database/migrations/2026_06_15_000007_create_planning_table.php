<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->cascadeOnDelete();
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            // disponible / occupe / maintenance / conge
            $table->string('statut')->default('occupe');
            $table->string('motif')->nullable();
            $table->foreignId('devis_id')->nullable()->constrained('devis')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicule_id', 'date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning');
    }
};
