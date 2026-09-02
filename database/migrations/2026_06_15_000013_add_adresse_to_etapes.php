<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etapes', function (Blueprint $table) {
            $table->string('adresse')->nullable()->after('commune_id');   // libellé BAN précis
            $table->decimal('latitude', 10, 7)->nullable()->after('adresse');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // La commune devient facultative (on géolocalise à l'adresse).
            $table->unsignedBigInteger('commune_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('etapes', function (Blueprint $table) {
            $table->dropColumn(['adresse', 'latitude', 'longitude']);
        });
    }
};
