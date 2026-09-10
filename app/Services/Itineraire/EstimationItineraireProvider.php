<?php

namespace App\Services\Itineraire;

use App\Models\Vehicule;

/**
 * Estimation d'itinéraire SANS API : distance à vol d'oiseau × facteur routier,
 * durée via une vitesse moyenne autocar, péage estimé au km selon la classe.
 *
 * ⚠️ Valeurs approximatives — destinées à faire tourner le moteur avant l'intégration
 * HERE (qui fournira distance/durée/péage réels). À NE PAS utiliser pour un devis ferme.
 */
class EstimationItineraireProvider implements ItineraireProvider
{
    private const FACTEUR_ROUTIER = 1.25;     // majoration vol d'oiseau → route

    private const VITESSE_MOY_KMH = 65.0;     // vitesse moyenne autocar (mixte)

    public function calculer(array $points, int $indexPriseEnCharge, int $indexDepose, Vehicule $vehicule): ResultatItineraire
    {
        $distanceTotale = 0.0;
        $distanceCharge = 0.0;
        $distanceVide = 0.0;
        $segments = [];

        for ($i = 0; $i < count($points) - 1; $i++) {
            $a = $points[$i];
            $b = $points[$i + 1];
            $km = round($this->haversine($a['lat'], $a['lng'], $b['lat'], $b['lng']) * self::FACTEUR_ROUTIER, 2);

            // Chargé entre la prise en charge et la dépose ; à vide avant/après.
            $chargeSegment = ($i >= $indexPriseEnCharge && $i < $indexDepose);
            if ($chargeSegment) {
                $distanceCharge += $km;
            } else {
                $distanceVide += $km;
            }
            $distanceTotale += $km;
            $segments[] = ['de' => $a['nom'], 'vers' => $b['nom'], 'km' => $km, 'charge' => $chargeSegment];
        }

        $dureeMinutes = (int) round(($distanceTotale / self::VITESSE_MOY_KMH) * 60);

        // Péage estimé au km selon la classe autocar (3 = 2 essieux, 4 = 3 essieux et +).
        // Estimation grossière — HERE fournit le péage exact.
        $tarifKm = $vehicule->classe_peage >= 4 ? 0.14 : 0.10;
        $coutPeage = round($distanceTotale * $tarifKm, 2);

        return new ResultatItineraire(
            distanceKm: round($distanceTotale, 2),
            distanceKmCharge: round($distanceCharge, 2),
            distanceKmVide: round($distanceVide, 2),
            dureeConduiteMinutes: $dureeMinutes,
            coutPeage: $coutPeage,
            coutVignettes: 0.0,
            source: 'estimation',
            payload: [
                'facteur_routier' => self::FACTEUR_ROUTIER,
                'vitesse_moy_kmh' => self::VITESSE_MOY_KMH,
                'tarif_peage_km' => $tarifKm,
                'segments' => $segments,
            ],
        );
    }

    /** Distance à vol d'oiseau (km) entre deux points GPS. */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
