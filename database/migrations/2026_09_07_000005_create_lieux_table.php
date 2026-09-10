<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lieux', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('adresse_normalisee')->nullable();
            $table->string('ville')->nullable();
            $table->string('code_postal', 10)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('source')->default('manuel');
            $table->string('provider_id')->nullable();
            $table->text('acces')->nullable();
            $table->string('contact')->nullable();
            $table->text('commentaire')->nullable();
            $table->unsignedInteger('utilisations')->default(0);
            $table->timestamps();
            $table->index(['source', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieux');
    }
};
