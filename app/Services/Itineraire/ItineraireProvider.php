<?php

namespace App\Services\Itineraire;

use App\Models\Vehicule;

interface ItineraireProvider
{
    /**
     * Calcule l'itinéraire en boucle : dépôt → prise en charge → étapes → retour dépôt.
     *
     * @param array<int, array{lat: float, lng: float, nom: string}> $points
     *        Points ordonnés, dépôt inclus en première ET dernière position.
     * @param int $indexPriseEnCharge  Index du point de prise en charge des passagers (1re étape client).
     * @param int $indexDepose         Index du point de dépose (dernière étape client).
     */
    public function calculer(array $points, int $indexPriseEnCharge, int $indexDepose, Vehicule $vehicule): ResultatItineraire;
}
