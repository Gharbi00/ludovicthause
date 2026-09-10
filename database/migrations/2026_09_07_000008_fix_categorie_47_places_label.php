<?php

use App\Models\Categorie;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Categorie::where('libelle', 'Autocar grand tourisme 47 places')
            ->update(['libelle' => 'Autocar Grand Tourisme 47 places']);
    }

    public function down(): void
    {
        Categorie::where('libelle', 'Autocar Grand Tourisme 47 places')
            ->update(['libelle' => 'Autocar grand tourisme 47 places']);
    }
};
