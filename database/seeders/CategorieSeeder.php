<?php

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Seeder;

class CategorieSeeder extends Seeder
{
    /**
     * Catégories réelles du parc LTT (déduites du planning 2026).
     * Seeder « autoritaire » : désactive tout puis (ré)active la liste réelle,
     * pour que les anciennes catégories de démo disparaissent des formulaires.
     */
    public function run(): void
    {
        Categorie::query()->update(['actif' => false]);

        $categories = [
            ['libelle' => 'Minibus 8 places',                  'capacite' => 8,  'gabarit' => 'minibus'],
            ['libelle' => 'Minibus 19 places',                 'capacite' => 19, 'gabarit' => 'minibus'],
            ['libelle' => 'Autocar 47 places',                 'capacite' => 47, 'gabarit' => 'standard'],
            ['libelle' => 'Autocar grand tourisme 53 places',  'capacite' => 53, 'gabarit' => 'grand_tourisme'],
            ['libelle' => 'Autocar grand tourisme 61 places',  'capacite' => 61, 'gabarit' => 'grand_tourisme'],
            ['libelle' => 'Autocar double étage 89 places',    'capacite' => 89, 'gabarit' => 'double_etage'],
        ];

        foreach ($categories as $c) {
            Categorie::updateOrCreate(['libelle' => $c['libelle']], $c + ['actif' => true]);
        }
    }
}
