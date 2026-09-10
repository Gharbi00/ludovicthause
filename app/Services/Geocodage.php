<?php

namespace App\Services;

use App\Models\Commune;
use App\Models\Lieu;
use Illuminate\Support\Facades\Http;

/**
 * Autocomplétion d'adresses combinée :
 *   - France : Base Adresse Nationale (api-adresse.data.gouv.fr) — officielle, précise.
 *   - Europe / étranger : Photon (photon.komoot.io, OpenStreetMap) — gratuit, sans clé.
 * Les deux sans clé d'API.
 */
class Geocodage
{
    private const URL_BAN = 'https://api-adresse.data.gouv.fr/search/';

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
     * Recherche de VILLES (communes locales + Europe Photon).
     *
     * @return array<int, array{label:string, lat:float, lng:float, citycode:?string, contexte:?string}>
     */
    public function rechercherVilles(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return [];
        }

        $normalisee = Commune::normaliser($q);
        $arrondissements = $this->arrondissementsVille($normalisee);

        // France : communes importées depuis database/data/communes.csv.
        $fr = $arrondissements ?: Commune::query()
            ->where('nom_normalise', 'like', $normalisee.'%')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('nom')
            ->limit(5)
            ->get()
            ->map(fn (Commune $commune) => [
                'label' => $commune->nom.($commune->code_postal ? " ({$commune->code_postal})" : ''),
                'lat' => (float) $commune->latitude,
                'lng' => (float) $commune->longitude,
                'citycode' => $commune->code_insee,
                'contexte' => 'France',
                'source' => 'communes',
                'provider_id' => $commune->code_insee,
            ])
            ->all();

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
                    $label = trim(($p['name'] ?? '').(isset($p['postcode']) ? ' '.$p['postcode'] : '').', '.($p['country'] ?? ''));
                    if (isset($vus[$label])) {
                        continue;
                    }
                    $vus[$label] = true;
                    $etr[] = [
                        'label' => $label,
                        'lat' => (float) $c[1],
                        'lng' => (float) $c[0],
                        'citycode' => null,
                        'contexte' => $p['country'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        return array_merge(array_slice($fr, 0, $arrondissements ? count($arrondissements) : 5), array_slice($etr, 0, 4));
    }

    /** Arrondissements municipaux absents du CSV des communes. */
    protected function arrondissementsVille(string $q): array
    {
        $villes = [
            'paris' => ['Paris', 20, '751', 48.8566, 2.3522, 75000],
            'lyon' => ['Lyon', 9, '6938', 45.758, 4.8351, 69000],
            'marseille' => ['Marseille', 16, '132', 43.2965, 5.3698, 13000],
        ];
        $ville = $villes[$q] ?? null;
        if (! $ville) {
            return [];
        }

        [$nom, $total, $prefixe, $lat, $lng, $cpBase] = $ville;
        $out = [];
        if ($nom === 'Paris') {
            $out[] = ['label' => 'Paris (toute la ville)', 'lat' => $lat, 'lng' => $lng, 'citycode' => '75056', 'contexte' => 'France', 'source' => 'local', 'provider_id' => 'paris-ville'];
        }
        for ($numero = 1; $numero <= $total; $numero++) {
            $suffixe = $numero === 1 ? '1er' : $numero.'e';
            if ($nom === 'Paris') {
                $code = $prefixe.str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
                $codePostal = '750'.str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
            } elseif ($nom === 'Lyon') {
                $code = $prefixe.$numero;
                $codePostal = '6900'.$numero;
            } else {
                $code = $prefixe.str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
                $codePostal = '130'.str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
            }

            $out[] = [
                'label' => $nom.' '.$suffixe.' arrondissement ('.$codePostal.')',
                'lat' => $lat,
                'lng' => $lng,
                'citycode' => $code,
                'contexte' => 'France',
                'source' => 'local',
                'provider_id' => $code,
            ];
        }

        return $out;
    }

    /**
     * Recherche d'ADRESSES (rue/n°) dans une ville donnée.
     *
     * @param  array{citycode:?string, ville:?string, lat:?float, lng:?float}  $contexte
     * @return array<int, array{label:string, lat:float, lng:float}>
     */
    public function rechercherAdresses(string $q, array $contexte): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return [];
        }

        $carnet = Lieu::query()
            ->where(function ($query) use ($q) {
                $query->where('libelle', 'like', '%'.$q.'%')
                    ->orWhere('adresse_normalisee', 'like', '%'.$q.'%')
                    ->orWhere('ville', 'like', '%'.$q.'%');
            })
            ->orderByDesc('utilisations')
            ->limit(10)
            ->get()
            ->map(fn (Lieu $lieu) => [
                'label' => '★ Carnet LTT · '.$lieu->libelle.($lieu->adresse_normalisee ? ' — '.$lieu->adresse_normalisee : ''),
                'lat' => (float) $lieu->latitude,
                'lng' => (float) $lieu->longitude,
                'source' => 'carnet',
                'provider_id' => (string) $lieu->id,
                'contexte' => $lieu->ville,
            ])->all();

        // France : BAN restreinte à la commune (citycode).
        if (! empty($contexte['citycode'])) {
            try {
                $resp = $this->http()->get(self::URL_BAN, [
                    'q' => $q,
                    'citycode' => $contexte['citycode'],
                    'limit' => 7,
                ]);
                if ($resp->ok()) {
                    $out = [];
                    foreach ($resp->json('features', []) as $f) {
                        $c = data_get($f, 'geometry.coordinates');
                        if (! is_array($c) || count($c) < 2) {
                            continue;
                        }
                        $out[] = $this->resultatAdresse($f, $c, 'ban');
                    }

                    $lieux = $this->photonAdresses($q, $contexte);
                    $vus = [];
                    $fusion = [];
                    foreach (array_merge($lieux, $out) as $adresse) {
                        if (isset($vus[$adresse['label']])) {
                            continue;
                        }
                        $vus[$adresse['label']] = true;
                        $fusion[] = $adresse;
                    }
                    if ($fusion !== []) {
                        return array_slice(array_merge($carnet, $fusion), 0, 10);
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }

            // Secours OpenStreetMap lorsque la BAN est indisponible ou sans résultat.
            return array_slice(array_merge($carnet, $this->photonAdresses($q, $contexte)), 0, 10);
        }

        // Étranger : Photon biaisé autour de la ville.
        try {
            $params = ['q' => $q.' '.($contexte['ville'] ?? ''), 'limit' => 7, 'lang' => 'fr'];
            if (! empty($contexte['lat']) && ! empty($contexte['lng'])) {
                $params['lat'] = $contexte['lat'];
                $params['lon'] = $contexte['lng'];
            }
            $resp = $this->http()->get(self::URL_PHOTON, $params);
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
                $out[] = [
                    'label' => $label,
                    'adresse_normalisee' => $label,
                    'lat' => (float) $c[1],
                    'lng' => (float) $c[0],
                    'source' => 'photon',
                    'provider_id' => (string) (($p['osm_type'] ?? '').':'.($p['osm_id'] ?? '')),
                    'contexte' => $p['city'] ?? ($contexte['ville'] ?? null),
                ];
            }

            return array_slice(array_merge($carnet, $out), 0, 10);
        } catch (\Throwable $e) {
            report($e);

            return array_slice($carnet, 0, 10);
        }
    }

    /** Normalise un résultat BAN en lieu exploitable par le formulaire et le carnet. */
    protected function resultatAdresse(array $feature, array $coordinates, string $source): array
    {
        $properties = data_get($feature, 'properties', []);

        return [
            'label' => (string) data_get($properties, 'label'),
            'adresse_normalisee' => (string) data_get($properties, 'label'),
            'lat' => (float) $coordinates[1],
            'lng' => (float) $coordinates[0],
            'source' => $source,
            'provider_id' => (string) (data_get($properties, 'id') ?: data_get($properties, 'banId', '')),
            'contexte' => data_get($properties, 'city'),
        ];
    }

    /** Recherche d'adresses via Photon, utilisée comme secours pour la France et l'Europe. */
    protected function photonAdresses(string $q, array $contexte): array
    {
        try {
            $params = [
                'q' => $q.' '.($contexte['ville'] ?? ''),
                'limit' => 7,
                'lang' => 'fr',
            ];
            if ($contexte['lat'] !== null && $contexte['lng'] !== null) {
                $params['lat'] = $contexte['lat'];
                $params['lon'] = $contexte['lng'];
            }

            $resp = $this->http()->get(self::URL_PHOTON, $params);
            if (! $resp->ok()) {
                return [];
            }

            $out = [];
            $vus = [];
            foreach ($resp->json('features', []) as $f) {
                $p = data_get($f, 'properties', []);
                $c = data_get($f, 'geometry.coordinates');
                $label = $this->labelPhoton($p);
                if (! is_array($c) || count($c) < 2 || $label === '' || isset($vus[$label])) {
                    continue;
                }
                $vus[$label] = true;
                $out[] = [
                    'label' => $label,
                    'adresse_normalisee' => $label,
                    'lat' => (float) $c[1],
                    'lng' => (float) $c[0],
                    'source' => 'photon',
                    'provider_id' => (string) (($p['osm_type'] ?? '').':'.($p['osm_id'] ?? '')),
                    'contexte' => $p['city'] ?? ($contexte['ville'] ?? null),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Client HTTP externe ; le mode local tolère le certificat PHP manquant. */
    protected function http()
    {
        $client = Http::withHeaders(['User-Agent' => 'ThauseDevis/1.0 (contact@doliexpert.fr)'])
            ->timeout(8);
        $caBundle = env('GEOCODAGE_CA_BUNDLE') ?: ini_get('curl.cainfo') ?: ini_get('openssl.cafile');

        if ($caBundle && is_file($caBundle)) {
            $client = $client->withOptions(['verify' => $caBundle]);
        }

        return app()->environment('local') && ! (bool) env('GEOCODAGE_VERIFY_SSL', true)
            ? $client->withoutVerifying()
            : $client;
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
                'label' => (string) data_get($f, 'properties.label'),
                'lat' => (float) $c[1],
                'lng' => (float) $c[0],
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
                    'q' => $q,
                    'limit' => 8,
                    'lang' => 'fr',
                    'bbox' => '-11,35,25,58', // Europe de l'Ouest / centrale
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
                'label' => $label,
                'lat' => (float) $c[1],
                'lng' => (float) $c[0],
                'citycode' => null,
                'contexte' => $pays,
            ];
        }

        return $out;
    }

    /** Construit un libellé lisible depuis un résultat Photon. */
    protected function labelPhoton(array $p): string
    {
        $rue = trim((($p['housenumber'] ?? '').' '.($p['street'] ?? '')));
        $l1 = $rue !== '' ? $rue : ($p['name'] ?? '');
        $ville = trim((($p['postcode'] ?? '').' '.($p['city'] ?? '')));

        $parties = array_filter([
            $l1 ?: null,
            ($ville !== '' && $ville !== $l1) ? $ville : null,
            $p['country'] ?? null,
        ]);

        return implode(', ', $parties);
    }
}
