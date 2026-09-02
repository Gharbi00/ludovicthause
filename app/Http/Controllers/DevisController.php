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
            'devis'    => $devis,
            'points'   => $points,
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

        // Itinéraire client : on masque le point de retournement dupliqué (villes consécutives identiques).
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
            'devis'      => $devis,
            'itineraire' => $itineraire,
        ])->setPaper('a4');

        return $pdf->stream('devis-' . $devis->reference . '.pdf');
    }
}
