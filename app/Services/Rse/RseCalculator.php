<?php

namespace App\Services\Rse;

use App\Models\Parametre;
use App\Models\Poste;
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
    public function calculer(int $dureeConduiteMinutes, Carbon $dateDebut, Carbon $dateFin, iterable $postes = []): ConfigurationChauffeur
    {
        $nbJours = (int) $dateDebut->copy()->startOfDay()->diffInDays($dateFin->copy()->startOfDay()) + 1;
        $nbJours = max(1, $nbJours);

        $nuitees = max(0, $nbJours - 1);

        $conduiteMaxJour = (int) Parametre::get('rse_conduite_journaliere_max_min', 540); // 9h
        $conduiteParJour = (int) round($dureeConduiteMinutes / $nbJours);

        $grille = $this->grilleJournaliere($dureeConduiteMinutes, $dateDebut, $nbJours, $postes, $conduiteMaxJour);
        $relaisNecessaire = collect($grille)->contains(fn (array $jour) => $jour['relais_necessaire']);
        $nbChauffeurs = $relaisNecessaire || $conduiteParJour > $conduiteMaxJour ? 2 : 1;

        return new ConfigurationChauffeur(
            nbChauffeurs: $nbChauffeurs,
            nbNuitees: $nuitees,
            nbJours: $nbJours,
            conduiteParJourMinutes: $conduiteParJour,
            details: [
                'conduite_totale_min' => $dureeConduiteMinutes,
                'conduite_max_jour_min' => $conduiteMaxJour,
                'declencheur_2e_chauffeur' => $nbChauffeurs === 2,
                'relais_necessaire' => $relaisNecessaire,
                'grille_journaliere' => $grille,
                'modele' => 'base 561/2006 (dérogations occasionnel à intégrer)',
            ],
        );
    }

    private function grilleJournaliere(int $dureeConduiteMinutes, Carbon $dateDebut, int $nbJours, iterable $postes, int $conduiteMaxJour): array
    {
        $conduiteBase = intdiv($dureeConduiteMinutes, $nbJours);
        $reste = $dureeConduiteMinutes % $nbJours;
        $seuilAmplitude = (int) Parametre::get('rse_amplitude_max_min', 780);
        $seuilTte = (int) Parametre::get('rse_tte_max_min', 900);
        $tauxDisposition = (int) Parametre::get('rse_taux_temps_disposition', 50);

        $postesCollection = collect($postes);
        $aDesPostes = $postesCollection->isNotEmpty();

        $jours = [];
        for ($i = 0; $i < $nbJours; $i++) {
            $date = $dateDebut->copy()->addDays($i)->toDateString();
            $postesJour = $postesCollection->filter(fn (Poste $p) => $p->date->format('Y-m-d') === $date);
            $debut = $postesJour->where('type', 'prise_service')->first()?->heure_debut;
            $fin = $postesJour->where('type', 'fin_service')->first()?->heure_fin;
            $amplitude = null;
            if ($debut && $fin) {
                $debutStr = $debut instanceof \Carbon\Carbon ? $debut->format('H:i') : (string) $debut;
                $finStr = $fin instanceof \Carbon\Carbon ? $fin->format('H:i') : (string) $fin;
                $amplitude = Carbon::createFromFormat('H:i', $debutStr)->diffInMinutes(Carbon::createFromFormat('H:i', $finStr));
                if ($amplitude < 0) {
                    $amplitude += 1440;
                }
            }
            $attente = (int) $postesJour->where('type', 'attente')->sum('duree_min');

            if ($aDesPostes) {
                $conduite = (int) $postesJour->where('type', 'conduite')->sum('duree_min');
                if ($conduite === 0 && $postesJour->isEmpty()) {
                    $conduite = $conduiteBase + ($i < $reste ? 1 : 0);
                }
            } else {
                $conduite = $conduiteBase + ($i < $reste ? 1 : 0);
            }

            $tte = $conduite + (int) round($attente * $tauxDisposition / 100);
            $jours[] = [
                'date' => $date,
                'conduite_min' => $conduite,
                'attente_min' => $attente,
                'tte_min' => $tte,
                'amplitude_min' => $amplitude,
                'heures_100_min' => $conduite,
                'heures_50_min' => $attente,
                'relais_necessaire' => ($amplitude !== null && $amplitude > $seuilAmplitude) || $conduite > $conduiteMaxJour || ($tte > $seuilTte),
                'statut' => $amplitude === null ? 'Horaires incomplets' : 'Contrôle effectué',
            ];
        }

        return $jours;
    }
}
