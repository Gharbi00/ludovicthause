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

        // Sécurité : une date aberrante (ex. année 0020 au lieu de 2026) ou un écart
        // démesuré ferait exploser la grille journalière (des centaines de milliers de jours).
        if ($dateDebut->year < 2000 || $dateDebut->year > 2100 || $dateFin->year < 2000 || $dateFin->year > 2100) {
            throw new \RuntimeException('Date(s) d’étape invalide(s) (année hors 2000-2100) : corrigez les dates de l’itinéraire.');
        }
        if ($nbJours > 370) {
            throw new \RuntimeException('Écart de dates trop grand ('.$nbJours.' jours) : vérifiez les dates de l’itinéraire.');
        }

        $nuitees = max(0, $nbJours - 1);

        $conduiteMaxJour = (int) Parametre::get('rse_conduite_journaliere_max_min', 540); // 9h
        $conduiteParJour = (int) round($dureeConduiteMinutes / $nbJours);

        $conduiteParJour = (int) round($dureeConduiteMinutes / $nbJours);
        $nombreChauffeurs = $conduiteParJour > 600 ? 2 : 1;
        $grille = $this->grilleJournaliere($dureeConduiteMinutes, $dateDebut, $nbJours, $postes, $conduiteMaxJour, $nombreChauffeurs > 1);
        $relaisNecessaire = collect($grille)->contains(fn (array $jour) => $jour['relais_necessaire']);
        $nbChauffeurs = $relaisNecessaire || $nombreChauffeurs > 1 ? 2 : 1;
        if ($nbChauffeurs > $nombreChauffeurs) {
            $grille = $this->grilleJournaliere($dureeConduiteMinutes, $dateDebut, $nbJours, $postes, $conduiteMaxJour, true);
        }

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

    private function grilleJournaliere(int $dureeConduiteMinutes, Carbon $dateDebut, int $nbJours, iterable $postes, int $conduiteMaxJour, bool $equipeDeuxChauffeurs): array
    {
        $conduiteBase = intdiv($dureeConduiteMinutes, $nbJours);
        $reste = $dureeConduiteMinutes % $nbJours;
        $seuilAmplitude = (int) Parametre::get('rse_amplitude_max_min', 780);
        $seuilTte = min(660, (int) Parametre::get('rse_tte_max_min', 660));
        $tauxDisposition = (int) Parametre::get('rse_taux_temps_disposition', 50);
        $conduiteMaxJourEtendue = 600;
        $conduiteMaxSemaine = 56 * 60;
        $conduiteMaxQuinzaine = 90 * 60;

        $postesCollection = collect($postes);
        $aDesPostes = $postesCollection->isNotEmpty();

        $jours = [];
        for ($i = 0; $i < $nbJours; $i++) {
            $date = $dateDebut->copy()->addDays($i)->toDateString();
            $postesJour = $postesCollection->filter(fn (Poste $p) => Carbon::parse($p->date)->toDateString() === $date);
            $postesActifs = $postesJour->whereIn('type', ['conduite', 'attente', 'pause']);
            $posteDebutActif = $postesActifs
                ->sortBy(fn (Poste $poste) => $this->heurePoste($poste->heure_debut))
                ->first();
            $posteFinActif = $postesActifs
                ->sortByDesc(fn (Poste $poste) => $this->heurePoste($poste->heure_fin))
                ->first();
            $debut = $postesJour->where('type', 'prise_service')->first()?->heure_debut
                ?? $posteDebutActif?->heure_debut;
            $fin = $postesJour->where('type', 'fin_service')->last()?->heure_fin
                ?? $posteFinActif?->heure_fin;
            $reposAvantMin = null;
            if ($i > 0 && $debut) {
                $dateFinPrecedente = null;
                $finPrecedente = null;
                $datesPrecedentes = $postesCollection
                    ->map(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString())
                    ->unique()
                    ->filter(fn (string $datePoste) => $datePoste < $date)
                    ->sortDesc();
                foreach ($datesPrecedentes as $dateCandidate) {
                    $postesPrecedents = $postesCollection->filter(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString() === $dateCandidate);
                    $finPrecedente = $postesPrecedents->where('type', 'fin_service')->last()?->heure_fin;
                    $finPrecedente ??= $postesPrecedents
                        ->whereIn('type', ['conduite', 'attente', 'pause'])
                        ->sortByDesc(fn (Poste $poste) => $this->heurePoste($poste->heure_fin))
                        ->first()?->heure_fin;
                    if ($finPrecedente) {
                        $dateFinPrecedente = $dateCandidate;
                        break;
                    }
                }
                if ($finPrecedente && $dateFinPrecedente) {
                    $heureDebut = $debut instanceof \DateTimeInterface ? $debut->format('H:i') : substr((string) $debut, 0, 5);
                    $heureFinPrecedente = $finPrecedente instanceof \DateTimeInterface
                        ? $finPrecedente->format('H:i')
                        : substr((string) $finPrecedente, 0, 5);
                    $debutRepos = Carbon::parse($dateFinPrecedente.' '.$heureFinPrecedente);
                    $finRepos = Carbon::parse($date.' '.$heureDebut);
                    $reposAvantMin = (int) floor(($finRepos->timestamp - $debutRepos->timestamp) / 60);
                }
            }
            $amplitude = null;
            if ($debut && $fin) {
                $debutStr = $this->heurePoste($debut);
                $finStr = $this->heurePoste($fin);
                $amplitude = Carbon::createFromFormat('H:i', $debutStr)->diffInMinutes(Carbon::createFromFormat('H:i', $finStr));
                if ($amplitude < 0) {
                    $amplitude += 1440;
                }
            }
            $attente = (int) $postesJour->where('type', 'attente')->sum('duree_min');
            $pauseInsuffisante = ! $equipeDeuxChauffeurs && $this->pauseConduiteInsuffisante($postesActifs);

            if ($aDesPostes) {
                $conduite = (int) $postesJour->where('type', 'conduite')->sum('duree_min');
            } else {
                $conduite = $conduiteBase + ($i < $reste ? 1 : 0);
            }

            $tte = $conduite + (int) round($attente * $tauxDisposition / 100);
            $conduiteParChauffeur = $equipeDeuxChauffeurs ? (int) ceil($conduite / 2) : $conduite;
            $tteParChauffeur = $conduiteParChauffeur + (int) round($attente * $tauxDisposition / 100);
            $seuilAmplitudeJour = $equipeDeuxChauffeurs ? 1260 : $seuilAmplitude;
            $jours[] = [
                'date' => $date,
                'conduite_min' => $conduite,
                'attente_min' => $attente,
                'tte_min' => $tte,
                'amplitude_min' => $amplitude,
                'heures_100_min' => $conduite,
                'heures_50_min' => $attente,
                'repos_precedent_min' => $reposAvantMin,
                'pause_reglementaire_manquante' => $pauseInsuffisante,
                'conduite_jour_etendue' => $conduiteParChauffeur > $conduiteMaxJour,
                'heure_debut_activite' => $debut ? $this->heurePoste($debut) : null,
                'heure_fin_activite' => $fin ? $this->heurePoste($fin) : null,
                'relais_necessaire' => ($amplitude !== null && $amplitude > $seuilAmplitudeJour)
                    || $conduiteParChauffeur > $conduiteMaxJourEtendue
                    || $tteParChauffeur > $seuilTte,
                'statut' => $postesActifs->isEmpty() ? 'Repos' : ($amplitude === null ? 'Horaires incomplets' : 'Contrôle effectué'),
            ];
        }

        $joursParSemaine = collect($jours)->groupBy(fn (array $jour) => Carbon::parse($jour['date'])->format('o-W'));
        $derniereActivite = null;
        $joursTravailConsecutifs = 0;
        foreach ($jours as $index => &$jour) {
            $semaine = Carbon::parse($jour['date'])->format('o-W');
            $semainePrecedente = Carbon::parse($jour['date'])->subWeek()->format('o-W');
            $conduiteSemaine = (int) $joursParSemaine->get($semaine, collect())->sum('conduite_min');
            $conduiteDeuxSemaines = $conduiteSemaine + (int) $joursParSemaine->get($semainePrecedente, collect())->sum('conduite_min');
            $extensionsSemaine = $joursParSemaine->get($semaine, collect())->where('conduite_jour_etendue', true)->count();
            $travailJour = (int) $jour['conduite_min'] + (int) $jour['attente_min'];
            $reposHebdomadaireAvantMin = null;
            if ($travailJour > 0) {
                if ($derniereActivite && $jour['heure_debut_activite']) {
                    $debutActivite = Carbon::parse($jour['date'].' '.$jour['heure_debut_activite']);
                    $reposHebdomadaireAvantMin = (int) floor(($debutActivite->timestamp - $derniereActivite->timestamp) / 60);
                    $joursTravailConsecutifs = $reposHebdomadaireAvantMin >= 2700 ? 1 : $joursTravailConsecutifs + 1;
                } else {
                    $joursTravailConsecutifs = max(1, $joursTravailConsecutifs + 1);
                }
                if ($jour['heure_fin_activite']) {
                    $derniereActivite = Carbon::parse($jour['date'].' '.$jour['heure_fin_activite']);
                }
            }
            $jour['conduite_semaine_min'] = $conduiteSemaine;
            $jour['conduite_deux_semaines_min'] = $conduiteDeuxSemaines;
            $jour['depassement_semaine'] = $conduiteSemaine > $conduiteMaxSemaine;
            $jour['depassement_deux_semaines'] = $conduiteDeuxSemaines > $conduiteMaxQuinzaine;
            $jour['depassement_extensions_journalieres'] = $extensionsSemaine > 2;
            $jour['repos_hebdomadaire_precedent_min'] = $reposHebdomadaireAvantMin;
            $jour['jours_travail_consecutifs'] = $joursTravailConsecutifs;
        }
        unset($jour);

        return $jours;
    }

    private function heurePoste(mixed $heure): string
    {
        if ($heure instanceof \DateTimeInterface) {
            return $heure->format('H:i');
        }

        return substr((string) $heure, 0, 5);
    }

    private function pauseConduiteInsuffisante(iterable $postes): bool
    {
        $postes = collect($postes)
            ->filter(fn (Poste $poste) => in_array($poste->type, ['conduite', 'pause'], true))
            ->sortBy(fn (Poste $poste) => $this->heurePoste($poste->heure_debut));
        $conduiteContinue = 0;
        $pauseFractionnee = 0;

        foreach ($postes as $poste) {
            if ($poste->type === 'pause') {
                    $dureePause = (int) $poste->duree_min;
                    if ($dureePause >= 45 || ($pauseFractionnee >= 15 && $dureePause >= 30)) {
                    $conduiteContinue = 0;
                    $pauseFractionnee = 0;
                    } elseif ($conduiteContinue >= 270 && $dureePause >= 15) {
                        $conduiteContinue = 0;
                        $pauseFractionnee = $dureePause;
                }

                continue;
            }

            if ($conduiteContinue >= 270 && $pauseFractionnee < 45) {
                return true;
            }
            if ($pauseFractionnee > 0 && $pauseFractionnee < 45) {
                $pauseFractionnee = 0;
            }

            $conduiteContinue += (int) $poste->duree_min;
            if ($conduiteContinue > 270) {
                return true;
            }
        }

        return false;
    }

}
