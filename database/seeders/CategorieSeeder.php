<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Vehicule;
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
            ['libelle' => 'Minibus 8 places', 'capacite' => 8, 'gabarit' => 'minibus', 'ordre' => 10],
            ['libelle' => 'Minibus 19 places', 'capacite' => 19, 'gabarit' => 'minibus', 'ordre' => 20],
            ['libelle' => 'Autocar Grand Tourisme 47 places', 'capacite' => 47, 'gabarit' => 'standard', 'ordre' => 30],
            ['libelle' => 'Autocar grand tourisme 53 places', 'capacite' => 53, 'gabarit' => 'grand_tourisme', 'ordre' => 40],
            ['libelle' => 'Autocar grand tourisme 61 places', 'capacite' => 61, 'gabarit' => 'grand_tourisme', 'ordre' => 50],
            ['libelle' => 'Autocar double étage 89 places', 'capacite' => 89, 'gabarit' => 'double_etage', 'ordre' => 60],
        ];

        foreach ($categories as $c) {
            Categorie::updateOrCreate(['libelle' => $c['libelle']], $c + ['actif' => true]);
        }

        // Les véhicules réels suivent automatiquement la catégorie active la plus juste.
        foreach (Vehicule::all() as $vehicule) {
            $categorie = Categorie::where('actif', true)
                ->where('capacite', '>=', $vehicule->nb_places)
                ->orderBy('capacite')
                ->first();
            if ($categorie) {
                $vehicule->update(['categorie_id' => $categorie->id]);
            }
        }
    }
}
