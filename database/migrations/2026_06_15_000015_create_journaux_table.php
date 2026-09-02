<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journaux', function (Blueprint $table) {
            $table->id();
            $table->string('utilisateur')->nullable(); // nom/e-mail au moment de l'action
            $table->string('action');
            $table->string('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journaux');
    }
};
