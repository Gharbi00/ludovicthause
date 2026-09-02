<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

class VehiculeDemoSeeder extends Seeder
{
    /**
     * Parc de DÉMONSTRATION (valeurs de charges plausibles, à remplacer par
     * l'import réel de GRILLE TARIFAIRE LTT 2025.xlsm en Phase 1).
     */
    public function run(): void
    {
        $capacites = Categorie::pluck('id', 'capacite'); // [19 => id, 35 => id, ...]

        $vehicules = [
            // immatriculation, parc, capacité cible, places, essieux, conso, loyer/mois, assurance/an, entretien/km
            ['DA-101-LT', 'P01', 19, 19, 2, 18.0,  900, 3200, 0.09],
            ['DB-220-LT', 'P02', 35, 33, 2, 24.0, 1500, 4200, 0.12],
            ['DC-330-LT', 'P03', 49, 49, 2, 28.0, 2100, 5200, 0.15],
            ['DC-331-LT', 'P04', 49, 49, 2, 28.5, 2100, 5200, 0.15],
            ['DE-440-LT', 'P05', 59, 57, 3, 32.0, 2600, 6100, 0.18],
            ['DE-441-LT', 'P06', 59, 59, 3, 32.5, 2600, 6100, 0.18],
            ['DF-550-LT', 'P07', 63, 63, 3, 34.0, 2900, 6500, 0.19],
        ];

        foreach ($vehicules as [$immat, $parc, $cap, $places, $essieux, $conso, $loyer, $assur, $entretienKm]) {
            Vehicule::updateOrCreate(
                ['immatriculation' => $immat],
                [
                    'numero_parc'                    => $parc,
                    'categorie_id'                   => $capacites[$cap] ?? Categorie::value('id'),
                    'nb_places'                      => $places,
                    'nb_essieux'                     => $essieux,
                    'type_energie'                   => 'gazole',
                    'conso_l_100km'                  => $conso,
                    'loyer_credit_bail_mensuel'      => $loyer,
                    'assurance_annuelle'             => $assur,
                    'quote_part_loyers_annuelle'     => 1200,
                    'autres_charges_fixes_annuelles' => 800,
                    'cout_entretien_km'              => $entretienKm,
                    'cout_pneus_km'                  => 0.03,
                    'cout_adblue_km'                 => 0.02,
                    'autres_variables_km'            => 0.01,
                    'jours_exploitation_an'          => 220,
                    'actif'                          => true,
                ]
            );
        }
    }
}
