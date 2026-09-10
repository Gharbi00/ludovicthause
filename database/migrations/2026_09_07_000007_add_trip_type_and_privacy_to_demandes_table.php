<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->string('type_trajet')->default('simple')->after('mode');
            $table->boolean('consentement_rgpd')->default(false)->after('ip_soumission');
            $table->timestamp('consentement_rgpd_at')->nullable()->after('consentement_rgpd');
        });
    }

    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropColumn(['type_trajet', 'consentement_rgpd', 'consentement_rgpd_at']);
        });
    }
};
