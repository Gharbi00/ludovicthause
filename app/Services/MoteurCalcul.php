<?php

namespace App\Services;

use App\Models\Commune;
use App\Models\Demande;
use App\Models\Parametre;
use App\Models\Vehicule;
use App\Services\Itineraire\ItineraireProvider;
use App\Services\Rse\RseCalculator;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Cœur du chiffrage : itinéraire (boucle dépôt → étapes → dépôt) + RSE + cascade de coûts
 * (carburant, péage, chauffeur, charges fixes réallouées, charges variables) + TVA + marge.
 *
 * Valeurs dérivées TOUJOURS recalculées, jamais stockées en dur (leçon de l'Excel LTT).
 */
class MoteurCalcul
{
    public function __construct(
        private readonly ItineraireProvider $itineraireProvider,
        private readonly RseCalculator $rse,
    ) {
    }

    /**
     * @return array{devis: array<string,mixed>, itineraire: mixed, rse: mixed}
     */
    public function calculer(Demande $demande, Vehicule $vehicule, ?float $margeTaux = null): array
    {
        $demande->loadMissing('etapes.commune');
        $etapes = $demande->etapes;

        if ($etapes->count() < 2) {
            throw new RuntimeException('La demande doit comporter au moins deux étapes.');
        }

        $depot = $this->depot();

        // Liste brute : dépôt (à vide) → étapes (passagers à bord) → dépôt (à vide).
        // On géolocalise à l'adresse précise de l'étape (BAN) ; repli sur la commune si besoin.
        $brut = [];
        $ptDepot = $this->pointCoord((float) $depot->latitude, (float) $depot->longitude, $depot->nom);
        $brut[] = ['pt' => $ptDepot, 'etape' => false, 'id' => $this->idCoord($ptDepot)];

        foreach ($etapes as $etape) {
            $lat = $etape->latitude ?? $etape->commune?->latitude;
            $lng = $etape->longitude ?? $etape->commune?->longitude;
            if ($lat === null || $lng === null) {
                throw new RuntimeException("Coordonnées manquantes pour l'étape « {$etape->libelle()} ».");
            }
            $pt = $this->pointCoord((float) $lat, (float) $lng, $etape->libelle());
            $brut[] = ['pt' => $pt, 'etape' => true, 'id' => $this->idCoord($pt)];
        }

        $brut[] = ['pt' => $ptDepot, 'etape' => false, 'id' => $this->idCoord($ptDepot)];

        // Dédoublonner les points identiques consécutifs (point de retournement en double,
        // départ = dépôt…) : sinon le comptage des segments est décalé (km chargés/à vide faux).
        $fusion = [];
        foreach ($brut as $p) {
            $n = count($fusion);
            if ($n > 0 && $fusion[$n - 1]['id'] === $p['id']) {
                $fusion[$n - 1]['etape'] = $fusion[$n - 1]['etape'] || $p['etape'];
                continue;
            }
            $fusion[] = $p;
        }

        $points = array_map(fn ($p) => $p['pt'], $fusion);
        $flags  = array_values(array_map(fn ($p) => $p['etape'], $fusion));

        // Prise en charge = 1er point « étape » ; dépose = dernier point « étape ».
        $indexPriseEnCharge = array_search(true, $flags, true);
        $indexDepose = 0;
        foreach ($flags as $i => $estEtape) {
            if ($estEtape) {
                $indexDepose = $i;
            }
        }

        $itineraire = $this->itineraireProvider->calculer($points, (int) $indexPriseEnCharge, $indexDepose, $vehicule);

        // Fenêtre du déplacement (jours) → RSE.
        $dates = $etapes->pluck('date')->filter();
        $dateDebut = Carbon::parse($dates->min());
        $dateFin = Carbon::parse($dates->max());
        $config = $this->rse->calculer($itineraire->dureeConduiteMinutes, $dateDebut, $dateFin);

        // Paramètres.
        $prixGasoil    = (float) Parametre::get('prix_gasoil_litre', 1.70);
        $tauxChauffeur = (float) Parametre::get('taux_horaire_chauffeur', 0);
        $coefSaison    = (float) Parametre::get('coef_saisonnalite', 1.0);
        $tauxTva       = (float) Parametre::get('taux_tva', 10);
        $fraisNuitee   = (float) Parametre::get('frais_nuitee_chauffeur', 90);
        $margeTaux     = $margeTaux ?? (float) Parametre::get('marge_cible', 15);

        $attenteMinutes = (int) $etapes->sum(fn ($e) => (int) $e->temps_attente_minutes);

        // --- Cascade de coûts ---
        $coutCarburant = ($vehicule->conso_l_100km / 100) * $itineraire->distanceKm * $prixGasoil;

        $dureeChauffeurMin = $itineraire->dureeConduiteMinutes + $attenteMinutes;
        $coutChauffeur = $tauxChauffeur * ($dureeChauffeurMin / 60) * $config->nbChauffeurs
            + $fraisNuitee * $config->nbNuitees * $config->nbChauffeurs;

        $chargesFixesAnnuelles = ($vehicule->loyer_credit_bail_mensuel * 12)
            + $vehicule->assurance_annuelle
            + $vehicule->quote_part_loyers_annuelle
            + $vehicule->autres_charges_fixes_annuelles;
        $coutChargesFixes = ($chargesFixesAnnuelles / max(1, $vehicule->jours_exploitation_an))
            * $config->nbJours * $coefSaison;

        $coutVariableKm = $vehicule->cout_entretien_km + $vehicule->cout_pneus_km
            + $vehicule->cout_adblue_km + $vehicule->autres_variables_km;
        $coutChargesVariables = $coutVariableKm * $itineraire->distanceKm;

        $coutRevient = $coutCarburant + $itineraire->coutPeage + $itineraire->coutVignettes
            + $coutChauffeur + $coutChargesFixes + $coutChargesVariables;

        // --- Marge, TVA, totaux ---
        $montantHt   = $coutRevient * (1 + $margeTaux / 100);
        $montantTva  = $montantHt * $tauxTva / 100;
        $montantTtc  = $montantHt + $montantTva;
        $margeMontant = $montantHt - $coutRevient;

        $devis = [
            'vehicule_id'            => $vehicule->id,
            'distance_km'            => round($itineraire->distanceKm, 2),
            'distance_km_charge'     => round($itineraire->distanceKmCharge, 2),
            'distance_km_vide'       => round($itineraire->distanceKmVide, 2),
            'duree_conduite_minutes' => $itineraire->dureeConduiteMinutes,
            'temps_attente_minutes'  => $attenteMinutes,
            'nb_chauffeurs'          => $config->nbChauffeurs,
            'nb_nuitees'             => $config->nbNuitees,
            'cout_carburant'         => round($coutCarburant, 2),
            'cout_peage'             => round($itineraire->coutPeage, 2),
            'cout_vignettes'         => round($itineraire->coutVignettes, 2),
            'cout_chauffeur'         => round($coutChauffeur, 2),
            'cout_charges_fixes'     => round($coutChargesFixes, 2),
            'cout_charges_variables' => round($coutChargesVariables, 2),
            'cout_revient_ht'        => round($coutRevient, 2),
            'marge_taux'             => round($margeTaux, 2),
            'montant_ht'             => round($montantHt, 2),
            'taux_tva'               => round($tauxTva, 2),
            'montant_tva'            => round($montantTva, 2),
            'montant_ttc'            => round($montantTtc, 2),
            'marge_montant'          => round($margeMontant, 2),
            'calcul_payload'         => [
                'source_itineraire' => $itineraire->source,
                'itineraire'        => $itineraire->payload,
                'rse'               => $config->details,
                'parametres'        => compact('prixGasoil', 'tauxChauffeur', 'coefSaison', 'tauxTva', 'fraisNuitee'),
                'calcule_le'        => $dateDebut->toDateString(),
            ],
        ];

        return ['devis' => $devis, 'itineraire' => $itineraire, 'rse' => $config];
    }

    private function depot(): Commune
    {
        $id = Parametre::get('depot_commune_id');
        $depot = $id
            ? Commune::find($id)
            : Commune::where('code_insee', '58303')->first(); // Varennes-Vauzelles

        if (! $depot) {
            throw new RuntimeException("Dépôt introuvable : renseignez le paramètre 'depot_commune_id' ou importez la commune de Varennes-Vauzelles.");
        }

        return $depot;
    }

    /** @return array{lat: float, lng: float, nom: string} */
    private function pointCoord(float $lat, float $lng, string $nom): array
    {
        return ['lat' => $lat, 'lng' => $lng, 'nom' => $nom];
    }

    /** Identifiant d'un point pour le dédoublonnage (coordonnées arrondies). */
    private function idCoord(array $pt): string
    {
        return round($pt['lat'], 5) . ',' . round($pt['lng'], 5);
    }
}
