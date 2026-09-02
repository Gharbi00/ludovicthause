<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->string('code_insee', 10)->nullable()->index();
            $table->string('nom')->index();               // autocomplétion
            $table->string('code_postal', 10)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Table de référence importée (BAN/INSEE) : pas d'horodatage.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
