<?php

namespace App\Services\Rse;

use App\Models\Parametre;
use Illuminate\Support\Carbon;

/**
 * Configuration chauffeur selon le règlement (CE) 561/2006.
 *
 * ⚠️ Modèle de BASE (à durcir en intégrant les dérogations « occasionnel » UE 2024/1258 :
 *   fractionnement de pause, report de repos hebdomadaire, seuils exacts).
 * La précision par jour s'améliorera avec les durées réelles par segment fournies par HERE.
 */
class RseCalculator
{
    public function calculer(int $dureeConduiteMinutes, Carbon $dateDebut, Carbon $dateFin): ConfigurationChauffeur
    {
        $nbJours = (int) $dateDebut->copy()->startOfDay()->diffInDays($dateFin->copy()->startOfDay()) + 1;
        $nbJours = max(1, $nbJours);

        $nuitees = max(0, $nbJours - 1);

        $conduiteMaxJour = (int) Parametre::get('rse_conduite_journaliere_max_min', 540); // 9h
        $conduiteParJour = (int) round($dureeConduiteMinutes / $nbJours);

        // Si la conduite moyenne journalière dépasse le maximum réglementaire,
        // il faut un second chauffeur (double équipage).
        $nbChauffeurs = $conduiteParJour > $conduiteMaxJour ? 2 : 1;

        return new ConfigurationChauffeur(
            nbChauffeurs: $nbChauffeurs,
            nbNuitees: $nuitees,
            nbJours: $nbJours,
            conduiteParJourMinutes: $conduiteParJour,
            details: [
                'conduite_totale_min'     => $dureeConduiteMinutes,
                'conduite_max_jour_min'   => $conduiteMaxJour,
                'declencheur_2e_chauffeur' => $nbChauffeurs === 2,
                'modele'                  => 'base 561/2006 (dérogations occasionnel à intégrer)',
            ],
        );
    }
}
