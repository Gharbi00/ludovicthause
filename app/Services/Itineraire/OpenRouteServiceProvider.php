<?php

namespace App\Services\Itineraire;

use App\Models\Parametre;
use App\Models\Vehicule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Itinéraire réel via OpenRouteService (profil poids-lourd « driving-hgv »).
 * Distances et durées RÉELLES, sans carte bancaire. Les péages ne sont PAS fournis
 * par ORS : ils restent estimés au km selon la classe (HERE requis pour l'exact).
 *
 * Doc : https://openrouteservice.org/dev/#/api-docs
 */
class OpenRouteServiceProvider implements ItineraireProvider
{
    private const URL = 'https://api.openrouteservice.org/v2/directions/driving-hgv';

    /** Nombre total de tentatives (budget de temps volontairement court, cf. calculer()). */
    private const TENTATIVES = 2;

    public function __construct(private readonly string $apiKey)
    {
    }

    /** Message clair pour la secrétaire (jamais le HTML brut renvoyé par le serveur). */
    protected function messageErreur(Response $resp): string
    {
        $statut = $resp->status();

        if ($resp->serverError()) {
            return "OpenRouteService est momentanément indisponible (erreur $statut) après " . self::TENTATIVES . ' tentatives. '
                . 'Ce n’est pas un problème de votre demande : réessayez dans quelques minutes.';
        }

        if (in_array($statut, [401, 403], true)) {
            return 'Clé API OpenRouteService invalide ou expirée (Réglages → Clés d’API).';
        }

        if ($statut === 429) {
            return 'Quota OpenRouteService atteint (trop de calculs sur la période). Réessayez plus tard.';
        }

        // Erreur métier : on remonte le message d'ORS s'il existe, sinon un extrait nettoyé.
        $detail = data_get($resp->json(), 'error.message')
            ?? trim(preg_replace('/\s+/', ' ', strip_tags((string) $resp->body())));

        return "OpenRouteService a refusé le calcul (erreur $statut) : " . mb_strimwidth((string) $detail, 0, 200, '…');
    }

    public function calculer(array $points, int $indexPriseEnCharge, int $indexDepose, Vehicule $vehicule): ResultatItineraire
    {
        // ORS attend les coordonnées en [longitude, latitude].
        $coordinates = array_map(fn ($p) => [$p['lng'], $p['lat']], $points);

        // Pannes passagères d'ORS (502/503/504, coupures) : on réessaie une fois.
        // ⚠️ Le budget total doit rester COURT : au-delà, PHP tue la requête et
        // l'écran resterait bloqué sans résultat ni erreur.
        // Pire cas ≈ 2 × (5 s connexion + 20 s réponse) + 0,5 s ≈ 45 s.
        try {
            $resp = Http::withHeaders(['Authorization' => $this->apiKey])
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(self::TENTATIVES, 500, function ($exception) {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError());
                }, throw: false)
                ->post(self::URL, [
                    'coordinates' => $coordinates,
                    'extra_info'  => ['tollways'],   // segments d'autoroute à péage
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'OpenRouteService ne répond pas (délai dépassé, ' . self::TENTATIVES . ' tentatives). '
                . 'Leur service est probablement en panne : réessayez dans quelques minutes.'
            );
        }

        if (! $resp->ok()) {
            throw new RuntimeException($this->messageErreur($resp));
        }

        $json = $resp->json();
        $segments = data_get($json, 'routes.0.segments');
        if (! $segments) {
            throw new RuntimeException('Aucun itinéraire renvoyé par OpenRouteService.');
        }

        $distanceTotaleM = 0.0;
        $distanceChargeM = 0.0;
        $distanceVideM   = 0.0;
        $dureeSecondes   = 0.0;

        foreach ($segments as $i => $segment) {
            $lenM = (float) data_get($segment, 'distance', 0);
            $dur  = (float) data_get($segment, 'duration', 0);
            $distanceTotaleM += $lenM;
            $dureeSecondes   += $dur;

            if ($i >= $indexPriseEnCharge && $i < $indexDepose) {
                $distanceChargeM += $lenM;
            } else {
                $distanceVideM += $lenM;
            }
        }

        $distanceKm = round($distanceTotaleM / 1000, 2);

        // Kilomètres réellement sur autoroute à péage (extra_info tollways : value=1).
        $peageM = 0.0;
        foreach (data_get($json, 'routes.0.extras.tollways.summary', []) as $s) {
            if ((int) ($s['value'] ?? 0) === 1) {
                $peageM += (float) ($s['distance'] ?? 0);
            }
        }
        $kmPeage = round($peageM / 1000, 2);

        // Tracé exact + index des tronçons à péage, pour l'affichage sur la carte.
        // tollways.values = [[indexDebut, indexFin, valeur], …] (valeur 1 = autoroute à péage),
        // les index renvoient aux coordonnées de la géométrie encodée.
        $geometry = data_get($json, 'routes.0.geometry');
        $tollways = array_map(
            fn ($v) => [(int) ($v[0] ?? 0), (int) ($v[1] ?? 0), (int) ($v[2] ?? 0)],
            data_get($json, 'routes.0.extras.tollways.values', []) ?: [],
        );

        // Tarif au km selon la classe autocar (3 = 2 essieux, 4 = 3 essieux et +), paramétrable.
        $tarifKm = $vehicule->classe_peage >= 4
            ? (float) Parametre::get('peage_tarif_km_classe4', 0.29)
            : (float) Parametre::get('peage_tarif_km_classe3', 0.20);
        $peage = round($kmPeage * $tarifKm, 2);

        return new ResultatItineraire(
            distanceKm: $distanceKm,
            distanceKmCharge: round($distanceChargeM / 1000, 2),
            distanceKmVide: round($distanceVideM / 1000, 2),
            dureeConduiteMinutes: (int) round($dureeSecondes / 60),
            coutPeage: $peage,
            coutVignettes: 0.0,
            source: 'ors',
            payload: [
                'profil'         => 'driving-hgv',
                'km_a_peage'     => $kmPeage,
                'classe_peage'   => $vehicule->classe_peage,
                'tarif_peage_km' => $tarifKm,
                'geometry'       => $geometry,   // polyline encodée (lat,lng)
                'tollways'       => $tollways,   // tronçons : [début, fin, 1=péage]
            ],
        );
    }
}
