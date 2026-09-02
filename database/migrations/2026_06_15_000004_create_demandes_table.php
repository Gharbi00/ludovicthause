<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('categorie_id')->constrained('categories');
            $table->unsignedInteger('nb_passagers');
            $table->string('client_nom');
            $table->string('client_email');
            $table->string('client_telephone')->nullable();
            // nouvelle → en_traitement → devis_edite → close
            $table->string('statut')->default('nouvelle')->index();
            $table->text('commentaire')->nullable();
            $table->string('ip_soumission', 45)->nullable();   // traçabilité anti-spam
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
