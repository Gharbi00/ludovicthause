<?php

namespace App\Services\Itineraire;

use App\Models\Vehicule;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Itinéraire réel via HERE Routing v8 : distance, durée, péages et vignettes (en €),
 * profil autocar, classe de péage selon le nombre d'essieux.
 *
 * Doc : https://www.here.com/docs/bundle/routing-api-v8-api-reference/
 */
class HereItineraireProvider implements ItineraireProvider
{
    private const URL = 'https://router.hereapi.com/v8/routes';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function calculer(array $points, int $indexPriseEnCharge, int $indexDepose, Vehicule $vehicule): ResultatItineraire
    {
        $origine     = $points[0];
        $destination = $points[count($points) - 1];
        $vias        = array_slice($points, 1, -1);

        $params = [
            'transportMode'      => 'bus',
            'origin'             => $origine['lat'] . ',' . $origine['lng'],
            'destination'        => $destination['lat'] . ',' . $destination['lng'],
            'return'             => 'summary,tolls',
            'currency'           => 'EUR',
            'vehicle[axleCount]' => max(2, (int) $vehicule->nb_essieux),
            'apiKey'             => $this->apiKey,
        ];

        $query = http_build_query($params);
        foreach ($vias as $v) {
            $query .= '&via=' . urlencode($v['lat'] . ',' . $v['lng']);
        }

        $resp = Http::timeout(30)->get(self::URL . '?' . $query);

        if (! $resp->ok()) {
            throw new RuntimeException('HERE a renvoyé une erreur (' . $resp->status() . ') : ' . $resp->body());
        }

        $route = data_get($resp->json(), 'routes.0');
        if (! $route) {
            throw new RuntimeException('Aucun itinéraire renvoyé par HERE.');
        }

        $distanceTotaleM = 0;
        $distanceChargeM = 0;
        $distanceVideM   = 0;
        $dureeSecondes   = 0;
        $peage           = 0.0;
        $vignettes       = 0.0;

        foreach (data_get($route, 'sections', []) as $i => $section) {
            $lenM = (int) data_get($section, 'summary.length', 0);
            $dur  = (int) data_get($section, 'summary.duration', 0);
            $distanceTotaleM += $lenM;
            $dureeSecondes   += $dur;

            if ($i >= $indexPriseEnCharge && $i < $indexDepose) {
                $distanceChargeM += $lenM;
            } else {
                $distanceVideM += $lenM;
            }

            // Péages et vignettes de la section.
            foreach (data_get($section, 'tolls', []) as $toll) {
                foreach (data_get($toll, 'fares', []) as $fare) {
                    $montant = (float) data_get($fare, 'price.value', 0);
                    $estVignette = str_contains(mb_strtolower((string) data_get($fare, 'name', '')), 'vignette');
                    if ($estVignette) {
                        $vignettes += $montant;
                    } else {
                        $peage += $montant;
                    }
                }
            }
        }

        return new ResultatItineraire(
            distanceKm: round($distanceTotaleM / 1000, 2),
            distanceKmCharge: round($distanceChargeM / 1000, 2),
            distanceKmVide: round($distanceVideM / 1000, 2),
            dureeConduiteMinutes: (int) round($dureeSecondes / 60),
            coutPeage: round($peage, 2),
            coutVignettes: round($vignettes, 2),
            source: 'here',
            payload: [
                'classe_essieux' => max(2, (int) $vehicule->nb_essieux),
                'sections'       => count(data_get($route, 'sections', [])),
            ],
        );
    }
}
