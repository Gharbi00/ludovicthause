<?php

namespace App\Services\Itineraire;

/**
 * Résultat d'un calcul d'itinéraire (indépendant du fournisseur : estimation ou HERE).
 */
class ResultatItineraire
{
    public function __construct(
        public readonly float $distanceKm,
        public readonly float $distanceKmCharge,
        public readonly float $distanceKmVide,
        public readonly int $dureeConduiteMinutes,
        public readonly float $coutPeage,
        public readonly float $coutVignettes,
        public readonly string $source,           // 'estimation' | 'here' | ...
        public readonly array $payload = [],       // trace brute (réponses API, segments…)
    ) {}
}
