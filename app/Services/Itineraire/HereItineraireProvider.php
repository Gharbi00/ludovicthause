<?php

namespace App\Services\Itineraire;

use App\Models\Parametre;
use App\Models\Vehicule;
use App\Services\ApiTracker;
use Illuminate\Support\Facades\Cache;
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

    public function __construct(private readonly string $apiKey, private readonly ApiTracker $tracker) {}

    public function calculer(array $points, int $indexPriseEnCharge, int $indexDepose, Vehicule $vehicule): ResultatItineraire
    {
        $origine = $points[0];
        $destination = $points[count($points) - 1];
        $vias = array_slice($points, 1, -1);

        $params = [
            'transportMode' => 'bus',
            'origin' => $origine['lat'].','.$origine['lng'],
            'destination' => $destination['lat'].','.$destination['lng'],
            'return' => 'summary,tolls,geometry',
            'currency' => 'EUR',
            'vehicle[axleCount]' => max(2, (int) $vehicule->nb_essieux),
            'apiKey' => $this->apiKey,
        ];

        if (Parametre::get('itineraire_eviter_peage', false)) {
            $params['avoid[features]'] = 'tollRoad';
        }
        if (Parametre::get('itineraire_eviter_autoroute', false)) {
            $params['avoid[features]'] = ($params['avoid[features]'] ?? '').($params['avoid[features]'] ?? '' ? ',' : '').'highway';
        }

        $query = http_build_query($params);
        foreach ($vias as $v) {
            $query .= '&via='.urlencode($v['lat'].','.$v['lng']);
        }

        $debut = microtime(true);
        $resp = Http::timeout(30)->get(self::URL.'?'.$query);
        $duree = (int) round((microtime(true) - $debut) * 1000);

        if (! $resp->ok()) {
            $this->tracker->log('here', self::URL, null, false, $duree, $resp->status());
            throw new RuntimeException('HERE a renvoyé une erreur ('.$resp->status().') : '.$resp->body());
        }

        $route = data_get($resp->json(), 'routes.0');
        if (! $route) {
            throw new RuntimeException('Aucun itinéraire renvoyé par HERE.');
        }

        $distanceTotaleM = 0;
        $distanceChargeM = 0;
        $distanceVideM = 0;
        $dureeSecondes = 0;
        $peage = 0.0;
        $vignettes = 0.0;

        foreach (data_get($route, 'sections', []) as $i => $section) {
            $lenM = (int) data_get($section, 'summary.length', 0);
            $dur = (int) data_get($section, 'summary.duration', 0);
            $distanceTotaleM += $lenM;
            $dureeSecondes += $dur;

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
                'sections' => count(data_get($route, 'sections', [])),
                'geometry' => data_get($route, 'sections.0.polyline'),
                'tollways' => [],
            ],
        );
    }

    private function calculerAvecCache(array $points, int $indexPriseEnCharge, int $indexDepose, Vehicule $vehicule): ResultatItineraire
    {
        $cacheKey = 'itineraire:'.md5(implode('>', array_map(fn ($p) => round($p['lat'], 4).','.round($p['lng'], 4), $points)).':'.$vehicule->id.':here');
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $this->tracker->log('here', self::URL, $cacheKey, true, 0, 200);

            return new ResultatItineraire(
                distanceKm: (float) ($cached['distanceKm'] ?? 0),
                distanceKmCharge: (float) ($cached['distanceKmCharge'] ?? 0),
                distanceKmVide: (float) ($cached['distanceKmVide'] ?? 0),
                dureeConduiteMinutes: (int) ($cached['dureeConduiteMinutes'] ?? 0),
                coutPeage: (float) ($cached['coutPeage'] ?? 0),
                coutVignettes: (float) ($cached['coutVignettes'] ?? 0),
                source: 'here',
                payload: $cached,
            );
        }

        $resultat = $this->calculer($points, $indexPriseEnCharge, $indexDepose, $vehicule);
        $this->tracker->log('here', self::URL, $cacheKey, false, null, 200);

        return $resultat;
    }
}
