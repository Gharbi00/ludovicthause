<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usages', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // here, ors, tollguru, ban, photon
            $table->string('endpoint')->nullable();
            $table->string('cache_key')->nullable();
            $table->boolean('cached')->default(false);
            $table->integer('response_time_ms')->nullable();
            $table->integer('http_status')->nullable();
            $table->string('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->unsignedBigInteger('devis_id')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->foreign('devis_id')->references('id')->on('devis')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usages');
    }
};
