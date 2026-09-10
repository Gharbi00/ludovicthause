<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Devis;
use Barryvdh\DomPDF\Facade\Pdf;

class DevisController extends Controller
{
    /** Carte du trajet (dépôt → étapes → dépôt), aller-retour inclus. */
    public function carte(Devis $devis)
    {
        $devis->load('demande.etapes.commune');
        $depot = Commune::where('code_insee', '58303')->first(); // Varennes-Vauzelles

        $points = [];
        $ajouter = function (?float $lat, ?float $lng, string $nom, string $type) use (&$points) {
            if ($lat === null || $lng === null) {
                return;
            }
            $last = end($points);
            if ($last && (float) $last['lat'] === (float) $lat && (float) $last['lng'] === (float) $lng) {
                return; // dédoublonnage des points consécutifs identiques
            }
            $points[] = ['lat' => (float) $lat, 'lng' => (float) $lng, 'nom' => $nom, 'type' => $type];
        };

        if ($depot) {
            $ajouter((float) $depot->latitude, (float) $depot->longitude, $depot->nom, 'depot');
        }
        foreach ($devis->demande->etapes as $etape) {
            $lat = $etape->latitude ?? $etape->commune?->latitude;
            $lng = $etape->longitude ?? $etape->commune?->longitude;
            $ajouter($lat !== null ? (float) $lat : null, $lng !== null ? (float) $lng : null, $etape->libelle(), 'etape');
        }
        if ($depot) {
            $ajouter((float) $depot->latitude, (float) $depot->longitude, $depot->nom, 'depot');
        }

        // Tracé routier exact + tronçons à péage (présents si le devis a été calculé via ORS).
        $itineraire = data_get($devis->calcul_payload, 'itineraire', []);

        return view('carte.trajet', [
            'devis' => $devis,
            'points' => $points,
            'geometry' => data_get($itineraire, 'geometry'),
            'tollways' => data_get($itineraire, 'tollways', []),
        ]);
    }

    /** Génère et affiche le PDF A4 du devis (commercial : prix de vente uniquement). */
    public function pdf(Devis $devis)
    {
        abort_if($devis->cout_revient_ht <= 0, 404, 'Devis non calculé.');
        abort_if($devis->statut === 'brouillon', 403, 'Veuillez valider le devis avant de l’éditer en PDF.');

        $devis->load(['demande.etapes.commune', 'vehicule']);

        $itineraire = [];
        $precedent = null;
        foreach ($devis->demande->etapes as $etape) {
            if ($etape->commune_id === $precedent) {
                continue;
            }
            $itineraire[] = $etape;
            $precedent = $etape->commune_id;
        }

        $pdf = Pdf::loadView('pdf.devis', [
            'devis' => $devis,
            'itineraire' => $itineraire,
        ])->setPaper('a4');

        return $pdf->stream('devis-'.$devis->reference.'.pdf');
    }

    /** Génère et affiche le PDF interne du devis (détails coût, RSE, scénarios). */
    public function interne(Devis $devis)
    {
        abort_if($devis->cout_revient_ht <= 0, 404, 'Devis non calculé.');

        $devis->load(['demande.etapes.commune', 'demande.categorie', 'vehicule']);

        $pdf = Pdf::loadView('pdf.devis-interne', [
            'devis' => $devis,
        ])->setPaper('a4');

        return $pdf->stream('interne-'.$devis->reference.'.pdf');
    }

    /** Export CSV des devis pour une demande. */
    public function csv(Devis $devis)
    {
        abort_if($devis->cout_revient_ht <= 0, 404, 'Devis non calculé.');

        $devis->load(['demande.etapes.commune', 'vehicule']);

        $nom = 'devis-'.$devis->reference.'.csv';
        $enTetes = ['Poste', 'Valeur'];
        $lignes = [
            ['Référence', $devis->reference],
            ['Client', $devis->demande->client_nom],
            ['Véhicule', $devis->vehicule->immatriculation ?? ''],
            ['Distance (km)', $devis->distance_km],
            ['Dont chargés (km)', $devis->distance_km_charge],
            ['Dont à vide (km)', $devis->distance_km_vide],
            ['Durée conduite (min)', $devis->duree_conduite_minutes],
            ['Temps attente (min)', $devis->temps_attente_minutes],
            ['Nb chauffeurs', $devis->nb_chauffeurs],
            ['Nb nuitées', $devis->nb_nuitees],
            ['Carburant (€)', $devis->cout_carburant],
            ['Péage (€)', $devis->cout_peage],
            ['Vignettes (€)', $devis->cout_vignettes],
            ['Chauffeur (€)', $devis->cout_chauffeur],
            ['Charges fixes (€)', $devis->cout_charges_fixes],
            ['Charges variables (€)', $devis->cout_charges_variables],
            ['Coût revient HT (€)', $devis->cout_revient_ht],
            ['Marge (%)', $devis->marge_taux],
            ['Montant HT (€)', $devis->montant_ht],
            ['TVA (%)', $devis->taux_tva],
            ['Montant TVA (€)', $devis->montant_tva],
            ['Montant TTC (€)', $devis->montant_ttc],
            ['Marge (€)', $devis->marge_montant],
            ['Statut', $devis->statut],
        ];

        $callback = function () use ($enTetes, $lignes) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $enTetes, ';');
            foreach ($lignes as $ligne) {
                fputcsv($f, $ligne, ';');
            }
            fclose($f);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }
}
