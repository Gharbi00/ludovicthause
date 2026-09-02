<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Autocomplétion d'adresses combinée :
 *   - France : Base Adresse Nationale (api-adresse.data.gouv.fr) — officielle, précise.
 *   - Europe / étranger : Photon (photon.komoot.io, OpenStreetMap) — gratuit, sans clé.
 * Les deux sans clé d'API.
 */
class Geocodage
{
    private const URL_BAN    = 'https://api-adresse.data.gouv.fr/search/';
    private const URL_PHOTON = 'https://photon.komoot.io/api/';

    /**
     * @return array<int, array{label:string, lat:float, lng:float, citycode:?string, contexte:?string}>
     */
    public function rechercher(string $recherche): array
    {
        $recherche = trim($recherche);
        if (mb_strlen($recherche) < 3) {
            return [];
        }

        // France (BAN) d'abord, puis l'étranger (Photon) — on interroge toujours les deux,
        // car des noms comme « Milan » ou « Genève » existent aussi en France.
        return array_merge(
            array_slice($this->ban($recherche), 0, 5),
            array_slice($this->photonEtranger($recherche), 0, 4),
        );
    }

    /**
     * Recherche de VILLES (France BAN + Europe Photon).
     * @return array<int, array{label:string, lat:float, lng:float, citycode:?string, contexte:?string}>
     */
    public function rechercherVilles(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        // France : communes (BAN, type=municipality)
        $fr = [];
        try {
            $resp = Http::timeout(8)->get(self::URL_BAN, ['q' => $q, 'type' => 'municipality', 'limit' => 6]);
            if ($resp->ok()) {
                foreach ($resp->json('features', []) as $f) {
                    $c = data_get($f, 'geometry.coordinates');
                    $p = data_get($f, 'properties', []);
                    if (! is_array($c) || count($c) < 2) {
                        continue;
                    }
                    $cp = $p['postcode'] ?? '';
                    $fr[] = [
                        'label'    => trim(($p['city'] ?? $p['name'] ?? '') . ($cp ? " ($cp)" : '')),
                        'lat'      => (float) $c[1],
                        'lng'      => (float) $c[0],
                        'citycode' => $p['citycode'] ?? null,
                        'contexte' => 'France',
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        // Europe : villes (Photon, type ville)
        $etr = [];
        static $europe = ['ES', 'IT', 'DE', 'CH', 'BE', 'NL', 'LU', 'GB', 'PT', 'AT', 'IE', 'DK', 'CZ', 'SK', 'SI', 'PL', 'HR', 'HU', 'AD', 'MC', 'LI'];
        try {
            $resp = Http::withHeaders(['User-Agent' => 'ThauseDevis/1.0 (contact@doliexpert.fr)'])
                ->timeout(8)
                ->get(self::URL_PHOTON, ['q' => $q, 'limit' => 8, 'lang' => 'fr', 'bbox' => '-11,35,25,58']);
            if ($resp->ok()) {
                $vus = [];
                foreach ($resp->json('features', []) as $f) {
                    $p = data_get($f, 'properties', []);
                    $type = $p['type'] ?? '';
                    $code = strtoupper((string) ($p['countrycode'] ?? ''));
                    if (! in_array($type, ['city', 'town', 'village'], true) || ! in_array($code, $europe, true)) {
                        continue;
                    }
                    $c = data_get($f, 'geometry.coordinates');
                    if (! is_array($c) || count($c) < 2) {
                        continue;
                    }
                    $label = trim(($p['name'] ?? '') . (isset($p['postcode']) ? ' ' . $p['postcode'] : '') . ', ' . ($p['country'] ?? ''));
                    if (isset($vus[$label])) {
                        continue;
                    }
                    $vus[$label] = true;
                    $etr[] = [
                        'label'    => $label,
                        'lat'      => (float) $c[1],
                        'lng'      => (float) $c[0],
                        'citycode' => null,
                        'contexte' => $p['country'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        return array_merge(array_slice($fr, 0, 5), array_slice($etr, 0, 4));
    }

    /**
     * Recherche d'ADRESSES (rue/n°) dans une ville donnée.
     * @param array{citycode:?string, ville:?string, lat:?float, lng:?float} $contexte
     * @return array<int, array{label:string, lat:float, lng:float}>
     */
    public function rechercherAdresses(string $q, array $contexte): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        // France : BAN restreinte à la commune (citycode).
        if (! empty($contexte['citycode'])) {
            try {
                $resp = Http::timeout(8)->get(self::URL_BAN, [
                    'q'        => $q,
                    'citycode' => $contexte['citycode'],
                    'limit'    => 7,
                ]);
                if ($resp->ok()) {
                    $out = [];
                    foreach ($resp->json('features', []) as $f) {
                        $c = data_get($f, 'geometry.coordinates');
                        if (! is_array($c) || count($c) < 2) {
                            continue;
                        }
                        $out[] = [
                            'label' => (string) data_get($f, 'properties.label'),
                            'lat'   => (float) $c[1],
                            'lng'   => (float) $c[0],
                        ];
                    }

                    return $out;
                }
            } catch (\Throwable $e) {
            }

            return [];
        }

        // Étranger : Photon biaisé autour de la ville.
        try {
            $params = ['q' => $q . ' ' . ($contexte['ville'] ?? ''), 'limit' => 7, 'lang' => 'fr'];
            if (! empty($contexte['lat']) && ! empty($contexte['lng'])) {
                $params['lat'] = $contexte['lat'];
                $params['lon'] = $contexte['lng'];
            }
            $resp = Http::withHeaders(['User-Agent' => 'ThauseDevis/1.0 (contact@doliexpert.fr)'])
                ->timeout(8)->get(self::URL_PHOTON, $params);
            if (! $resp->ok()) {
                return [];
            }
            $out = [];
            $vus = [];
            foreach ($resp->json('features', []) as $f) {
                $p = data_get($f, 'properties', []);
                $c = data_get($f, 'geometry.coordinates');
                if (! is_array($c) || count($c) < 2) {
                    continue;
                }
                $label = $this->labelPhoton($p);
                if ($label === '' || isset($vus[$label])) {
                    continue;
                }
                $vus[$label] = true;
                $out[] = ['label' => $label, 'lat' => (float) $c[1], 'lng' => (float) $c[0]];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Adresses françaises (BAN). */
    protected function ban(string $q): array
    {
        try {
            $resp = Http::timeout(8)->get(self::URL_BAN, ['q' => $q, 'limit' => 6]);
        } catch (\Throwable $e) {
            return [];
        }
        if (! $resp->ok()) {
            return [];
        }

        $out = [];
        foreach ($resp->json('features', []) as $f) {
            $c = data_get($f, 'geometry.coordinates');
            if (! is_array($c) || count($c) < 2) {
                continue;
            }
            $out[] = [
                'label'    => (string) data_get($f, 'properties.label'),
                'lat'      => (float) $c[1],
                'lng'      => (float) $c[0],
                'citycode' => data_get($f, 'properties.citycode'),
                'contexte' => data_get($f, 'properties.context'),
            ];
        }

        return $out;
    }

    /** Villes / adresses hors de France, restreintes à l'Europe (Photon). */
    protected function photonEtranger(string $q): array
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => 'ThauseDevis/1.0 (contact@doliexpert.fr)'])
                ->timeout(8)
                ->get(self::URL_PHOTON, [
                    'q'     => $q,
                    'limit' => 8,
                    'lang'  => 'fr',
                    'bbox'  => '-11,35,25,58', // Europe de l'Ouest / centrale
                ]);
        } catch (\Throwable $e) {
            return [];
        }
        if (! $resp->ok()) {
            return [];
        }

        // Pays européens pertinents pour LTT (la France est gérée par la BAN).
        static $europe = ['ES', 'IT', 'DE', 'CH', 'BE', 'NL', 'LU', 'GB', 'PT', 'AT', 'IE', 'DK', 'CZ', 'SK', 'SI', 'PL', 'HR', 'HU', 'AD', 'MC', 'LI'];

        $out = [];
        $vus = [];
        foreach ($resp->json('features', []) as $f) {
            $p = data_get($f, 'properties', []);
            $pays = (string) ($p['country'] ?? '');
            $code = strtoupper((string) ($p['countrycode'] ?? ''));
            if (! in_array($code, $europe, true)) {
                continue; // hors périmètre européen (ou France, gérée par la BAN)
            }
            $c = data_get($f, 'geometry.coordinates');
            if (! is_array($c) || count($c) < 2) {
                continue;
            }
            $label = $this->labelPhoton($p);
            if ($label === '' || isset($vus[$label])) {
                continue; // doublon
            }
            $vus[$label] = true;
            $out[] = [
                'label'    => $label,
                'lat'      => (float) $c[1],
                'lng'      => (float) $c[0],
                'citycode' => null,
                'contexte' => $pays,
            ];
        }

        return $out;
    }

    /** Construit un libellé lisible depuis un résultat Photon. */
    protected function labelPhoton(array $p): string
    {
        $rue   = trim((($p['housenumber'] ?? '') . ' ' . ($p['street'] ?? '')));
        $l1    = $rue !== '' ? $rue : ($p['name'] ?? '');
        $ville = trim((($p['postcode'] ?? '') . ' ' . ($p['city'] ?? '')));

        $parties = array_filter([
            $l1 ?: null,
            ($ville !== '' && $ville !== $l1) ? $ville : null,
            $p['country'] ?? null,
        ]);

        return implode(', ', $parties);
    }
}
