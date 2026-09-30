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

        // Contexte de la ville choisie : sert à écarter les adresses incohérentes.
        $cible = $this->cibleVille($contexte);

        $carnet = Lieu::query()
            ->where(function ($query) use ($q) {
                $query->where('libelle', 'like', '%'.$q.'%')
                    ->orWhere('adresse_normalisee', 'like', '%'.$q.'%')
                    ->orWhere('ville', 'like', '%'.$q.'%');
            })
            ->orderByDesc('utilisations')
            ->limit(40)
            ->get()
            ->map(fn (Lieu $lieu) => [
                'label' => '★ Carnet LTT · '.$lieu->libelle.($lieu->adresse_normalisee ? ' — '.$lieu->adresse_normalisee : ''),
                'lat' => (float) $lieu->latitude,
                'lng' => (float) $lieu->longitude,
                'source' => 'carnet',
                'provider_id' => (string) $lieu->id,
                'contexte' => $lieu->ville,
                'ville_nom' => (string) $lieu->ville,
                'code_postal' => $lieu->code_postal,
            ])
            ->filter(fn (array $adresse) => $this->adresseCoherente($adresse, $cible))
            ->take(10)
            ->values()
            ->all();

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
                        $adresse = $this->resultatAdresse($f, $c, 'ban');
                        if ($this->adresseCoherente($adresse, $cible)) {
                            $out[] = $adresse;
                        }
                    }

                    $lieux = $this->photonAdresses($q, $cible);
                    $vus = [];
                    $fusion = [];
                    foreach (array_merge($lieux, $out) as $adresse) {
                        if (isset($vus[$adresse['label']]) || ! $this->adresseCoherente($adresse, $cible)) {
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
            return array_slice(array_merge($carnet, $this->filtrerCoherentes($this->photonAdresses($q, $cible), $cible)), 0, 10);
        }

        // Étranger : Photon biaisé autour de la ville, recoupé avec la ville choisie.
        return array_slice(array_merge($carnet, $this->filtrerCoherentes($this->photonAdresses($q, $cible), $cible)), 0, 10);
    }

    // ------------------------------------------------------------------
    // Cohérence ville / adresse : une suggestion doit appartenir à la ville choisie.
    // ------------------------------------------------------------------

    /** Décompose le contexte ville d'une étape en informations comparables. */
    protected function cibleVille(array $contexte): array
    {
        $label = trim((string) ($contexte['ville'] ?? ''));

        // « Lyon 5e arrondissement (69005) » -> « Lyon » ; « Milan, Italie » -> « Milan ».
        $nom = trim((string) preg_replace('/\(.*?\)/u', ' ', $label));
        $parties = preg_split('/\s*,\s*/u', $nom) ?: [$nom];
        $nom = trim((string) ($parties[0] ?? ''));
        $nom = trim((string) preg_replace('/\b\d+\s*(?:er|e|ème|eme)\b/iu', ' ', $nom));
        $nom = trim((string) preg_replace('/\barrondissements?\b/iu', ' ', $nom));
        // Codes postaux éventuellement accolés au nom (« Milano 20121 »).
        $nom = trim((string) preg_replace('/\b\d{4,5}\b/u', ' ', $nom));
        $nom = trim((string) preg_replace('/\s+/u', ' ', $nom));

        $lat = (isset($contexte['lat']) && is_numeric($contexte['lat'])) ? (float) $contexte['lat'] : null;
        $lng = (isset($contexte['lng']) && is_numeric($contexte['lng'])) ? (float) $contexte['lng'] : null;
        if ($lat === 0.0 && $lng === 0.0) { // coordonnées absentes : on ne les utilise pas.
            $lat = null;
            $lng = null;
        }

        return [
            'citycode' => ($contexte['citycode'] ?? null) ?: null,
            'code_postal' => $this->extraireCodePostal($label),
            'arrondissement' => $this->numeroArrondissement($label),
            'ville_libelle' => $nom,
            'ville_simple' => Commune::normaliser($nom),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /** Numéro d'arrondissement d'un libellé (« Lyon 5e arrondissement » -> 5). */
    protected function numeroArrondissement(?string $label): ?int
    {
        if ($label === null || $label === '') {
            return null;
        }

        return preg_match('/\b(\d{1,2})\s*(?:er|e|ème|eme)\b/iu', $label, $m) === 1 ? (int) $m[1] : null;
    }

    /** Décompose une suggestion d'adresse en informations comparables. */
    protected function cibleAdresse(array $adresse): array
    {
        $label = (string) ($adresse['label'] ?? '');
        $normalisee = (string) ($adresse['adresse_normalisee'] ?? '');
        $ville = (string) ($adresse['ville_nom'] ?? $adresse['contexte'] ?? '');

        $codePostal = $this->extraireCodePostal((string) ($adresse['code_postal'] ?? ''));
        if ($codePostal === null) {
            $codePostal = $this->extraireCodePostal($normalisee) ?? $this->extraireCodePostal($label);
        }

        $lat = (isset($adresse['lat']) && is_numeric($adresse['lat'])) ? (float) $adresse['lat'] : null;
        $lng = (isset($adresse['lng']) && is_numeric($adresse['lng'])) ? (float) $adresse['lng'] : null;
        if ($lat === 0.0 && $lng === 0.0) {
            $lat = null;
            $lng = null;
        }

        return [
            'code_postal' => $codePostal,
            'arrondissement' => $this->numeroArrondissement($ville),
            'citycode' => ($adresse['citycode'] ?? null) ?: null,
            'ville_simple' => Commune::normaliser($ville),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /** Premier code postal (5 chiffres) trouvé dans un texte. */
    protected function extraireCodePostal(?string $texte): ?string
    {
        if ($texte === null || $texte === '') {
            return null;
        }

        return preg_match('/\b(\d{5})\b/', $texte, $m) === 1 ? $m[1] : null;
    }

    /**
     * Une suggestion est cohérente lorsqu'elle se situe dans la ville choisie.
     * Le code postal tranche en priorité (indispensable pour les grandes villes à
     * arrondissements comme Lyon / Paris / Marseille), puis le nom de ville, et
     * enfin la proximité géographique autour du centre-ville.
     */
    protected function adresseCoherente(array $adresse, array $cible): bool
    {
        $aContexte = ($cible['code_postal'] ?? null) !== null
            || ($cible['ville_simple'] ?? '') !== ''
            || ($cible['lat'] ?? null) !== null
            || ($cible['lng'] ?? null) !== null;
        if (! $aContexte) {
            return true; // aucune ville sélectionnée : aucune restriction.
        }

        $candidat = $this->cibleAdresse($adresse);

        // 1) Code INSEE (citycode) : identifiant officiel le plus fiable en France.
        if (($cible['citycode'] ?? null) !== null && $candidat['citycode'] !== null) {
            return $candidat['citycode'] === $cible['citycode'];
        }

        // 2) Code postal : décisif sauf pour les codes « …000 » qui couvrent toute la commune.
        $cpCible = $cible['code_postal'] ?? null;
        $cpCandidat = $candidat['code_postal'];
        if ($cpCible !== null && $cpCandidat !== null) {
            if ($cpCandidat === $cpCible) {
                return true;
            }
            if (! str_ends_with($cpCible, '000')) {
                return false;
            }
        }

        // 4) Nom de ville : deux villes différentes ne sont pas cohérentes.
        $nomCible = (string) ($cible['ville_simple'] ?? '');
        $nomCandidat = (string) $candidat['ville_simple'];
        if ($nomCible !== '' && $nomCandidat !== '') {
            return $nomCandidat === $nomCible;
        }

        // 5) Dernier recours (information de ville manquante d'un côté) : proximité.
        return $this->procheDuContexte($candidat, $cible);
    }

    /** La suggestion reste-t-elle dans un rayon raisonnable autour de la ville ? */
    protected function procheDuContexte(array $candidat, array $cible): bool
    {
        if ($candidat['lat'] === null || $candidat['lng'] === null
            || ($cible['lat'] ?? null) === null || ($cible['lng'] ?? null) === null) {
            return true; // impossible de trancher : on conserve la suggestion.
        }

        return $this->distanceKm($candidat['lat'], $candidat['lng'], $cible['lat'], $cible['lng']) <= 30.0;
    }

    /** Filtre une liste de suggestions : cohérence avec la ville + suppression des doublons. */
    protected function filtrerCoherentes(array $adresses, array $cible): array
    {
        $out = [];
        $vus = [];
        foreach ($adresses as $adresse) {
            $label = (string) ($adresse['label'] ?? '');
            if ($label === '' || isset($vus[$label]) || ! $this->adresseCoherente($adresse, $cible)) {
                continue;
            }
            $vus[$label] = true;
            $out[] = $adresse;
        }

        return $out;
    }

    /** Distance approximative (km) entre deux points géographiques. */
    protected function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rayon = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = (sin($dLat / 2) ** 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);

        return 2 * $rayon * asin(min(1.0, sqrt($a)));
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
            'ville_nom' => data_get($properties, 'city'),
            'code_postal' => data_get($properties, 'postcode'),
            'citycode' => data_get($properties, 'citycode'),
        ];
    }

    /** Recherche d'adresses via Photon, utilisée comme secours pour la France et l'Europe. */
    protected function photonAdresses(string $q, array $cible): array
    {
        try {
            $params = [
                'q' => trim($q.' '.($cible['ville_libelle'] ?? '')),
                'limit' => 7,
                'lang' => 'fr',
            ];
            if (! empty($cible['lat']) && ! empty($cible['lng'])) {
                $params['lat'] = $cible['lat'];
                $params['lon'] = $cible['lng'];
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
                    'contexte' => $p['city'] ?? ($cible['ville_libelle'] ?? null),
                    'ville_nom' => (string) ($p['city'] ?? ''),
                    'code_postal' => $p['postcode'] ?? null,
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
