<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Autocomplétion / géocodage d'adresses via la Base Adresse Nationale
 * (api-adresse.data.gouv.fr) : officielle, gratuite, sans clé, au niveau rue/numéro.
 */
class GeocodageBan
{
    private const URL = 'https://api-adresse.data.gouv.fr/search/';

    /**
     * @return array<int, array{label:string, lat:float, lng:float, citycode:?string, contexte:?string}>
     */
    public function rechercher(string $recherche, int $limit = 6): array
    {
        $recherche = trim($recherche);
        if (mb_strlen($recherche) < 3) {
            return [];
        }

        try {
            $resp = Http::timeout(8)->get(self::URL, ['q' => $recherche, 'limit' => $limit]);
        } catch (\Throwable $e) {
            return [];
        }

        if (! $resp->ok()) {
            return [];
        }

        $out = [];
        foreach ($resp->json('features', []) as $f) {
            $coords = data_get($f, 'geometry.coordinates');
            if (! is_array($coords) || count($coords) < 2) {
                continue;
            }
            $out[] = [
                'label' => (string) data_get($f, 'properties.label'),
                'lat' => (float) $coords[1],
                'lng' => (float) $coords[0],
                'citycode' => data_get($f, 'properties.citycode'),
                'contexte' => data_get($f, 'properties.context'),
            ];
        }

        return $out;
    }
}
