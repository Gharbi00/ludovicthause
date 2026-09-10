<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

/**
 * Parc RÉEL LTT.
 *   - Identité (immatriculation, modèle, nb de places) : PLANNING 2026 (fait foi).
 *   - Charges (conso, entretien/km, pneus/km, assurance/an, taxes/an, valeur) :
 *     « GRILLE TARIFAIRE LTT 2025.xlsm » (une feuille de coût par type).
 *
 * Seeder « autoritaire » : désactive tout puis (ré)active la liste réelle.
 *
 * ⚠️ À CONFIRMER AVEC LUDOVIC :
 *   - nb_essieux (classe de péage) : SUPPOSÉ (89 pl double étage = 3 ; autres = 2).
 *   - appariement charges ↔ véhicule pour les deux Neoplan 53 (même profil « mixte » ici).
 *   - charges des 2 minibus 8 places (absents de la grille) : estimées, à affiner.
 *   - amortissement de la « valeur de remplacement » (reporté en note, non chiffré).
 */
class VehiculeReelSeeder extends Seeder
{
    public function run(): void
    {
        Vehicule::query()->update(['actif' => false]);

        $cat = Categorie::pluck('id', 'capacite');

        // immat, parc/modèle, capacité→cat, places, essieux, conso, entretien/km, pneus/km,
        //   assurance/an, taxes/an, valeur_remplacement, charges_estimees(bool)
        $vehicules = [
            ['GJ-510-AS', 'VanHool',            89, 89, 3, 30.0, 0.0806, 0.0161, 2600, 4038, 73716, false],
            ['GW-830-FH', 'VanHool',            61, 61, 3, 25.0, 0.0806, 0.0161, 2200, 4038, 38964, false],
            ['GH-216-ZW', 'Volvo 9900',         53, 53, 2, 25.0, 0.0806, 0.0161, 2500, 4038, 73716, false],
            ['GX-648-QC', 'Neoplan Tourliner',  53, 53, 2, 25.0, 0.0806, 0.0161, 2000, 4038, 73716, false],
            ['GX-762-QC', 'Neoplan Tourliner',  53, 53, 2, 25.0, 0.0806, 0.0161, 2000, 4038, 73716, true],  // profil à confirmer
            ['FA-634-KQ', 'Volvo 9900',         47, 47, 2, 26.0, 0.0800, 0.0161, 2500, 4038, 45600, false],
            ['HE-284-XE', 'Sprinter Mercedes',  19, 19, 2, 18.0, 0.0806, 0.0161, 1000, 2600, 16392, false],
            ['GQ-852-WP', 'Minibus',             8,  8, 2, 10.0, 0.0500, 0.0100,  600, 1000,     0, true],  // charges estimées
            ['GD-956-HN', 'Minibus',             8,  8, 2, 10.0, 0.0500, 0.0100,  600, 1000,     0, true],  // charges estimées
        ];

        foreach ($vehicules as [$immat, $modele, $capCat, $places, $essieux, $conso, $entretien, $pneus, $assur, $taxes, $valeur, $estime]) {
            $note = $modele
                .($valeur ? ' — valeur remplacement : '.number_format($valeur, 0, ',', ' ').' € (amortissement à définir)' : '')
                .($estime ? ' — ⚠ charges/profil à confirmer' : '');

            Vehicule::updateOrCreate(
                ['immatriculation' => $immat],
                [
                    'numero_parc' => null,
                    'categorie_id' => $cat[$capCat] ?? Categorie::value('id'),
                    'nb_places' => $places,
                    'nb_essieux' => $essieux,
                    'type_energie' => 'gazole',
                    'conso_l_100km' => $conso,
                    'loyer_credit_bail_mensuel' => 0,
                    'assurance_annuelle' => $assur,
                    'quote_part_loyers_annuelle' => 0,      // amortissement à définir
                    'autres_charges_fixes_annuelles' => $taxes,
                    'cout_entretien_km' => $entretien,
                    'cout_pneus_km' => $pneus,
                    'cout_adblue_km' => 0,
                    'autres_variables_km' => 0,
                    'jours_exploitation_an' => 236,
                    'actif' => true,
                    'notes' => $note,
                ]
            );
        }
    }
}
