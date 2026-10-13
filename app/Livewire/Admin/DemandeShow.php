<?php

namespace App\Livewire\Admin;

use App\Models\Commune;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Etape;
use App\Models\Journal;
use App\Models\Lieu;
use App\Models\Parametre;
use App\Models\Planning;
use App\Models\Poste;
use App\Models\Vehicule;
use App\Services\Geocodage;
use App\Services\MoteurCalcul;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class DemandeShow extends Component
{
    public Demande $demande;

    public ?int $vehicule_id = null;

    public ?string $flash = null;

    public ?string $erreur = null;

    public ?float $marge_taux = null;

    public array $lieuAcces = [];

    public array $lieuContact = [];

    public array $lieuCommentaire = [];

    public array $lieuLibelle = [];

    public array $lieuAdresse = [];

    // Édition de la date d'une étape (Itinéraire) : « changer » → saisie → enregistrer.
    public array $editionDate = [];

    public array $dateEdition = [];

    /** @var array<int, bool> */
    public array $editionHoraires = [];

    /** @var array<int, array{heure_arrivee: ?string, heure_depart: ?string}> */
    public array $horairesEdition = [];

    // Suggestions ville / adresse lors de la modification d'un lieu (Itinéraire).
    public array $suggestionsVille = [];

    public array $suggestionsAdresse = [];

    // Saisie en cours de recherche (par étape).
    public array $villeRecherche = [];

    public array $adresseRecherche = [];

    // Affichage du champ de recherche (vs. libellé figé + bouton « changer »).
    public array $editionVille = [];

    public array $editionAdresse = [];

    // Valeurs sélectionnées (ville / adresse) en attente d'enregistrement.
    public array $lieuVille = [];

    public array $lieuVilleLat = [];

    public array $lieuVilleLng = [];

    public array $lieuCitycode = [];

    public array $lieuLat = [];

    public array $lieuLng = [];

    public array $lieuGeocodageSource = [];

    public array $lieuGeocodageProviderId = [];

    // Saisie d'une prestation supplémentaire (ligne libre).
    public string $ligneLibelle = '';

    public ?string $ligneMontant = null;

    // Surcharges manuelles km / durées (REQ-S-06, REQ-S-10).
    public ?float $edit_distance_km = null;

    public ?float $edit_distance_km_charge = null;

    public ?float $edit_distance_km_vide = null;

    public ?int $edit_duree_conduite_minutes = null;

    public ?int $edit_temps_attente_minutes = null;

    public string $override_raison = '';

    // Surcharges manuelles coûts (REQ-S-10).
    public ?float $edit_cout_carburant = null;

    public ?float $edit_cout_peage = null;

    public ?float $edit_cout_vignettes = null;

    public ?float $edit_cout_chauffeur = null;

    public ?float $edit_cout_charges_fixes = null;

    public ?float $edit_cout_charges_variables = null;

    // Édition en ligne des postes dans le tableau RSE.
    public ?string $editingPosteDate = null;

    public ?int $editingPosteId = null;

    public string $poste_type = 'conduite';

    public ?string $poste_heure_debut = null;

    public ?string $poste_heure_fin = null;

    public int $poste_taux = 100;

    public function mount(Demande $demande): void
    {
        $this->demande = $demande->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $devis = $this->devisActuel();
        $this->vehicule_id = $devis?->vehicule_id;
        $this->marge_taux = $devis && $devis->cout_revient_ht > 0
            ? (float) $devis->marge_taux
            : (float) Parametre::get('marge_cible', 15);
        foreach ($this->demande->etapes as $etape) {
            $this->lieuAcces[$etape->id] = (string) $etape->lieu_acces;
            $this->lieuContact[$etape->id] = (string) $etape->lieu_contact;
            $this->lieuCommentaire[$etape->id] = (string) $etape->lieu_commentaire;
            $this->lieuLibelle[$etape->id] = (string) ($etape->lieu_libelle ?: ($etape->adresse ?: ($etape->ville ?: ($etape->commune?->nom ?? ''))));
            $this->lieuAdresse[$etape->id] = (string) ($etape->adresse_normalisee ?: $etape->adresse);
            $this->lieuVille[$etape->id] = (string) ($etape->ville ?: $etape->commune?->nom);
            // Contexte ville pour restreindre la recherche d'adresse.
            $this->lieuVilleLat[$etape->id] = $etape->commune?->latitude ?? $etape->latitude;
            $this->lieuVilleLng[$etape->id] = $etape->commune?->longitude ?? $etape->longitude;
            $this->lieuCitycode[$etape->id] = $etape->commune?->code_insee;
            // Point effectif (adresse si dispo, sinon ville).
            $this->lieuLat[$etape->id] = $etape->latitude;
            $this->lieuLng[$etape->id] = $etape->longitude;
            $this->lieuGeocodageSource[$etape->id] = $etape->geocodage_source;
            $this->lieuGeocodageProviderId[$etape->id] = $etape->geocodage_provider_id;
        }
    }
    
    public function modifierHorairesEtape(int $etapeId): void
    {
        $etape = $this->demande->etapes->firstWhere('id', $etapeId);
        if (! $etape) {
            return;
        }
        
        $this->editionHoraires[$etapeId] = true;
        $this->horairesEdition[$etapeId] = [
            'heure_arrivee' => $etape->heure_arrivee ? substr((string) $etape->heure_arrivee, 0, 5) : null,
            'heure_depart' => $etape->heure_depart ? substr((string) $etape->heure_depart, 0, 5) : null,
        ];
        $this->resetErrorBag("horairesEdition.$etapeId");
    }
    
    public function annulerHorairesEtape(int $etapeId): void
    {
        unset($this->editionHoraires[$etapeId], $this->horairesEdition[$etapeId]);
        $this->resetErrorBag("horairesEdition.$etapeId");
    }
    
    public function enregistrerHorairesEtape(int $etapeId): void
    {
        $etape = $this->demande->etapes->firstWhere('id', $etapeId);
        if (! $etape) {
            return;
        }
        
        $this->validate([
            "horairesEdition.$etapeId.heure_arrivee" => ['nullable', 'date_format:H:i'],
            "horairesEdition.$etapeId.heure_depart" => ['nullable', 'date_format:H:i'],
        ], [
            'horairesEdition.*.date_format' => 'Saisissez une heure au format HH:MM.',
        ]);
        
        $arrivee = $this->horairesEdition[$etapeId]['heure_arrivee'] ?? null;
        $depart = $this->horairesEdition[$etapeId]['heure_depart'] ?? null;
        if ($arrivee && $depart) {
            $minutesArrivee = (int) Carbon::createFromFormat('H:i', $arrivee)->format('H') * 60
                + (int) Carbon::createFromFormat('H:i', $arrivee)->format('i');
            $minutesDepart = (int) Carbon::createFromFormat('H:i', $depart)->format('H') * 60
                + (int) Carbon::createFromFormat('H:i', $depart)->format('i');
            if ($minutesDepart < $minutesArrivee) {
                $this->addError("horairesEdition.$etapeId.heure_depart", 'Le départ ne peut pas précéder l’arrivée sur cette étape.');
                return;
            }
        }
        
        $etape->update([
            'heure_arrivee' => $arrivee ?: null,
            'heure_depart' => $depart ?: null,
        ]);
        $this->annulerHorairesEtape($etapeId);
        
        $devis = $this->devisActuel();
        if ($devis) {
            $posteService = $etape->ordre === $this->demande->etapes->min('ordre')
                ? Poste::query()->where('devis_id', $devis->id)->where('type', 'prise_service')->first()
                : null;
            $posteFin = $etape->ordre === $this->demande->etapes->max('ordre')
                ? Poste::query()->where('devis_id', $devis->id)->where('type', 'fin_service')->first()
                : null;
            if ($posteService && $depart) {
                $posteService->update(['heure_debut' => $depart, 'heure_fin' => $depart]);
            }
            if ($posteFin && $arrivee) {
                $posteFin->update(['heure_debut' => $arrivee, 'heure_fin' => $arrivee]);
            }
        }

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        if ($devis) {
            $this->synchroniserPlanningApresModificationEtape($devis);
        }
        $this->flash = 'Horaires de l’étape enregistrés.';
        $this->erreur = null;
    }

    protected function synchroniserPlanningApresModificationEtape(Devis $devis): void
    {
        $itineraireCalcule = filled($devis->calcul_payload['source_itineraire'] ?? null)
            || filled($devis->calcul_payload['itineraire']['segments'] ?? null);
        if (! $itineraireCalcule) {
            app(MoteurCalcul::class)->recalculerRsePourDevis($devis);

            return;
        }

        if (($devis->calcul_payload['rse']['grille_journaliere'] ?? []) === []) {
            app(MoteurCalcul::class)->recalculerRsePourDevis($devis);
        }

        $this->corrigerReglementation();
    }

    /** Le devis en cours (affectation) pour cette demande, s'il existe. */
    protected function devisActuel(): ?Devis
    {
        return $this->demande->devis->sortByDesc('id')->first();
    }

    /** Contexte ville d'une étape, pour restreindre la recherche d'adresse. */
    protected function contexteVilleEtape(int $etapeId): array
    {
        return [
            'citycode' => $this->lieuCitycode[$etapeId] ?? null,
            'ville' => $this->lieuVille[$etapeId] ?? null,
            'lat' => $this->lieuVilleLat[$etapeId] ?? null,
            'lng' => $this->lieuVilleLng[$etapeId] ?? null,
        ];
    }

    /** Recherche ville/adresse dès que le texte change (comme dans le formulaire de création). */
    public function updated(string $name, $value): void
    {
        $geo = app(Geocodage::class);

        if (preg_match('/^villeRecherche\\.(\\d+)$/', $name, $m)) {
            $this->suggestionsVille[(int) $m[1]] = $geo->rechercherVilles((string) $value);
        } elseif (preg_match('/^adresseRecherche\\.(\\d+)$/', $name, $m)) {
            $i = (int) $m[1];
            $this->suggestionsAdresse[$i] = $geo->rechercherAdresses((string) $value, $this->contexteVilleEtape($i));
        }
    }

    /** Affiche le champ de recherche de la ville pour une étape. */
    public function changerVilleEtape(int $etapeId): void
    {
        $this->editionVille[$etapeId] = true;
        $this->villeRecherche[$etapeId] = '';
        $this->suggestionsVille[$etapeId] = [];
    }

    /** Affiche le champ de recherche de l'adresse pour une étape. */
    public function changerAdresseEtape(int $etapeId): void
    {
        $this->editionAdresse[$etapeId] = true;
        $this->adresseRecherche[$etapeId] = '';
        $this->suggestionsAdresse[$etapeId] = [];
    }

    /** Annule la recherche de ville et revient au libellé figé. */
    public function annulerVilleEtape(int $etapeId): void
    {
        $this->editionVille[$etapeId] = false;
        $this->villeRecherche[$etapeId] = '';
        $this->suggestionsVille[$etapeId] = [];
    }

    /** Annule la recherche d'adresse et revient au libellé figé. */
    public function annulerAdresseEtape(int $etapeId): void
    {
        $this->editionAdresse[$etapeId] = false;
        $this->adresseRecherche[$etapeId] = '';
        $this->suggestionsAdresse[$etapeId] = [];
    }

    /** Passe la date d'une étape en mode édition. */
    public function changerDateEtape(int $etapeId): void
    {
        $etape = $this->demande->etapes->firstWhere('id', $etapeId);
        if (! $etape) {
            return;
        }

        $this->editionDate[$etapeId] = true;
        $this->dateEdition[$etapeId] = $etape->date ? $etape->date->format('Y-m-d') : '';
    }

    /** Annule la modification de la date d'une étape. */
    public function annulerDateEtape(int $etapeId): void
    {
        unset($this->editionDate[$etapeId], $this->dateEdition[$etapeId]);
    }

    /** Enregistre la nouvelle date d'une étape (année 2000-2100). */
    public function appliquerDateEtape(int $etapeId): void
    {
        $etape = $this->demande->etapes->firstWhere('id', $etapeId);
        if (! $etape) {
            return;
        }

        $this->validate([
            "dateEdition.$etapeId" => ['required', 'string', 'max:10'],
        ]);

        $date = $this->parserDateEdition($this->dateEdition[$etapeId] ?? '');
        if (! $date) {
            $this->erreur = 'Date invalide : choisissez une date valide.';
            $this->flash = null;

            return;
        }
        if ($date->year < 2000 || $date->year > 2100) {
            $this->erreur = 'Date invalide (année '.$date->year.') : saisissez une année entre 2000 et 2100.';
            $this->flash = null;

            return;
        }

        $etape->update(['date' => $date->toDateString()]);
        unset($this->editionDate[$etapeId], $this->dateEdition[$etapeId]);

        // Le calcul RSE stocké ne correspond plus à la nouvelle fenêtre de dates :
        // invalider la grille pour forcer un nouveau calcul avant toute confirmation.
        $devis = $this->devisActuel();
        if ($devis) {
            $payload = $devis->calcul_payload ?? [];
            $payload['rse'] = [];
            $devis->update(['calcul_payload' => $payload]);
        }

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $this->flash = 'Date de l’étape mise à jour ('.$date->format('d/m/Y').') : relancez le calcul du devis pour la prendre en compte.';
        $this->erreur = null;
    }

    /** Saisie tolérante : JJ/MM/AAAA, AAAA-MM-JJ puis repli sur le parsing Carbon. */
    protected function parserDateEdition(?string $brut): ?Carbon
    {
        $brut = trim((string) $brut);
        if ($brut === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd/m/y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $brut)->startOfDay();
            } catch (\Throwable) {
                // format suivant
            }
        }

        try {
            return Carbon::parse($brut)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Applique la ville sélectionnée à l'étape. */
    public function appliquerVilleEtape(int $etapeId, int $index): void
    {
        $s = ($this->suggestionsVille[$etapeId] ?? [])[$index] ?? null;
        if (! $s) {
            return;
        }

        // La ville change -> l'adresse précédente n'est plus valable.
        $ancienLibelle = trim((string) ($this->lieuLibelle[$etapeId] ?? ''));
        $ancienneAdresse = trim((string) ($this->lieuAdresse[$etapeId] ?? ''));
        $ancienneVille = trim((string) ($this->lieuVille[$etapeId] ?? ''));
        $this->lieuVille[$etapeId] = (string) $s['label'];
        $this->lieuVilleLat[$etapeId] = (float) $s['lat'];
        $this->lieuVilleLng[$etapeId] = (float) $s['lng'];
        $this->lieuCitycode[$etapeId] = $s['citycode'] ?? null;
        // Point effectif = ville tant qu'aucune adresse n'est sélectionnée.
        $this->lieuLat[$etapeId] = (float) $s['lat'];
        $this->lieuLng[$etapeId] = (float) $s['lng'];
        $this->lieuAdresse[$etapeId] = '';
        // Ne pas écraser un nom du lieu personnalisé saisi par la secrétaire.
        if ($ancienLibelle === '' || $ancienLibelle === $ancienneAdresse || $ancienLibelle === $ancienneVille) {
            $this->lieuLibelle[$etapeId] = (string) $s['label'];
        }
        $this->lieuGeocodageSource[$etapeId] = $s['source'] ?? null;
        $this->lieuGeocodageProviderId[$etapeId] = $s['provider_id'] ?? null;
        $this->resetErrorBag("lieuVille.$etapeId");

        $this->editionVille[$etapeId] = false;
        $this->villeRecherche[$etapeId] = '';
        $this->suggestionsVille[$etapeId] = [];
        $this->suggestionsAdresse[$etapeId] = [];
    }

    /** Applique l'adresse sélectionnée à l'étape. */
    public function appliquerAdresseEtape(int $etapeId, int $index): void
    {
        $s = ($this->suggestionsAdresse[$etapeId] ?? [])[$index] ?? null;
        if (! $s) {
            return;
        }

        // Ne pas écraser un nom du lieu personnalisé saisi par la secrétaire.
        $ancienLibelle = trim((string) ($this->lieuLibelle[$etapeId] ?? ''));
        $ancienneAdresse = trim((string) ($this->lieuAdresse[$etapeId] ?? ''));
        $ancienneVille = trim((string) ($this->lieuVille[$etapeId] ?? ''));

        $this->lieuAdresse[$etapeId] = $s['adresse_normalisee'] ?? $s['label'];
        $this->lieuLat[$etapeId] = (float) $s['lat'];
        $this->lieuLng[$etapeId] = (float) $s['lng'];
        if ($ancienLibelle === '' || $ancienLibelle === $ancienneAdresse || $ancienLibelle === $ancienneVille) {
            $this->lieuLibelle[$etapeId] = $s['adresse_normalisee'] ?? $s['label'];
        }
        $this->lieuGeocodageSource[$etapeId] = $s['source'] ?? null;
        $this->lieuGeocodageProviderId[$etapeId] = $s['provider_id'] ?? null;

        $this->editionAdresse[$etapeId] = false;
        $this->adresseRecherche[$etapeId] = '';
        $this->suggestionsAdresse[$etapeId] = [];
    }

    /** Fenêtre [début, fin] du déplacement, déduite des dates d'étapes. */
    protected function fenetre(): array
    {
        $dates = $this->demande->etapes->pluck('date')->filter();
        if ($dates->isEmpty()) {
            return [now()->startOfDay(), now()->endOfDay()];
        }

        return [
            Carbon::parse($dates->min())->startOfDay(),
            Carbon::parse($dates->max())->endOfDay(),
        ];
    }

    /** Données pour le bandeau permanent RSE (REQ-S-01, REQ-S-02). */
    public function getResumeRseProperty(): array
    {
        $devis = $this->devisActuel();
        if (! $devis || ! $devis->calcul_payload) {
            return [
                'amplitude' => null, 'tte' => null, 'conduite' => null,
                'heures100' => null, 'heures50' => null,
                'statutAmplitude' => 'neutre', 'statutTte' => 'neutre', 'statutConduite' => 'neutre',
            ];
        }

        $grille = $devis->calcul_payload['rse']['grille_journaliere'] ?? [];
        $amplitudeMax = 0;
        $tteMax = 0;
        $conduiteMax = 0;
        $heures100 = 0;
        $heures50 = 0;

        foreach ($grille as $jour) {
            if ($jour['amplitude_min'] !== null && $jour['amplitude_min'] > $amplitudeMax) {
                $amplitudeMax = $jour['amplitude_min'];
            }
            if ($jour['tte_min'] > $tteMax) {
                $tteMax = $jour['tte_min'];
            }
            if ($jour['heures_100_min'] > $conduiteMax) {
                $conduiteMax = $jour['heures_100_min'];
            }
            $heures100 += $jour['heures_100_min'];
            $heures50 += $jour['heures_50_min'];
        }

        $nombreChauffeurs = max(1, (int) $devis->nb_chauffeurs);
        $conduiteParChauffeurMax = (int) ceil($conduiteMax / $nombreChauffeurs);
        $seuilAmplitude = $nombreChauffeurs > 1 ? 1260 : (int) Parametre::get('rse_amplitude_max_min', 780);
        $seuilConduite = 600;
        $seuilTte = min(660, (int) Parametre::get('rse_tte_max_min', 660));
        $reposJournalierMin = (int) Parametre::get('rse_repos_journalier_min', 660);

        return [
            'amplitude' => $amplitudeMax ?: null,
            'tte' => $tteMax ?: null,
            'conduite' => $conduiteParChauffeurMax ?: null,
            'heures100' => $heures100 ?: null,
            'heures50' => $heures50 ?: null,
            'statutAmplitude' => $this->statutIndicateur($amplitudeMax, $seuilAmplitude, 0.85),
            'statutTte' => $this->statutIndicateur($tteMax, $seuilTte, 0.85),
            'statutConduite' => $this->statutIndicateur($conduiteParChauffeurMax, $seuilConduite, 0.85),
        ];
    }

    protected function statutIndicateur(?int $valeur, int $seuil, float $ratioAlerte): string
    {
        if ($valeur === null) {
            return 'neutre';
        }
        if ($valeur >= $seuil) {
            return 'rouge';
        }
        if ($valeur >= $seuil * $ratioAlerte) {
            return 'orange';
        }

        return 'vert';
    }

    /**
     * Étapes dont la date est hors de la plage plausible (année < 2000 ou > 2100).
     * Une telle date bloque le calcul : il faut la corriger avant de continuer.
     *
     * @return array<int, array{etape_id: int, libelle: string, date: string}>
     */
    public function getEtapesDatesInvalidesProperty(): array
    {
        $invalides = [];
        foreach ($this->demande->etapes as $etape) {
            if (! $etape->date) {
                continue;
            }
            if ($etape->date->year < 2000 || $etape->date->year > 2100) {
                $invalides[] = [
                    'etape_id' => (int) $etape->id,
                    'libelle' => $etape->libelle(),
                    'date' => $etape->date->format('d/m/Y'),
                ];
            }
        }

        return $invalides;
    }

    public function getReglementationAlertesProperty(): array
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            return ['Le calcul de la réglementation n’est pas disponible : affectez un véhicule et lancez le calcul avant de confirmer la demande.'];
        }

        $grille = $devis->calcul_payload['rse']['grille_journaliere'] ?? [];
        if ($grille === []) {
            return ['Le calcul de la réglementation n’est pas disponible : lancez le calcul avant validation.'];
        }

        $nombreChauffeurs = max(1, (int) $devis->nb_chauffeurs);
        $seuilAmplitude = $nombreChauffeurs > 1 ? 1260 : (int) Parametre::get('rse_amplitude_max_min', 780);
        $seuilConduite = (int) Parametre::get('rse_conduite_journaliere_max_min', 540);
        $seuilTte = min(660, (int) Parametre::get('rse_tte_max_min', 660));
        $reposJournalierMin = (int) Parametre::get('rse_repos_journalier_min', 660);

        $alertes = [];
        $reposReduitsParSemaine = [];
        $reposHebdomadairesReduitsParQuinzaine = [];

        foreach ($grille as $jour) {
            $date = Carbon::parse($jour['date'] ?? now())->format('d/m/Y');
            $amplitude = (int) ($jour['amplitude_min'] ?? 0);
            $conduite = (int) ($jour['heures_100_min'] ?? $jour['conduite_min'] ?? 0);
            $tte = (int) ($jour['tte_min'] ?? 0);
            $conduiteParChauffeur = (int) ceil($conduite / $nombreChauffeurs);
            $attente = (int) ($jour['heures_50_min'] ?? $jour['attente_min'] ?? 0);
            $tauxDisposition = (int) Parametre::get('rse_taux_temps_disposition', 50);
            $tteParChauffeur = $conduiteParChauffeur + (int) round($attente * $tauxDisposition / 100);
            $repos = $jour['repos_precedent_min'] ?? null;
            $semaine = Carbon::parse($jour['date'] ?? now())->format('o-W');
            $dateAlerte = Carbon::parse($jour['date'] ?? now());
            $quinzaine = $dateAlerte->format('o').'-'.intdiv(((int) $dateAlerte->format('W')) - 1, 2);

            if (($jour['statut'] ?? null) === 'Horaires incomplets') {
                $alertes[] = 'Journée du '.$date.' : horaires incomplets, la réglementation n’est pas vérifiable (renseigner horaires de service).';
            }

            if (($jour['amplitude_min'] ?? null) !== null && $amplitude > $seuilAmplitude) {
                $alertes[] = 'Journée du '.$date.' : Amplitude de '.($this->formatMinutes($amplitude)).' > '.($this->formatMinutes($seuilAmplitude)).' (réduire amplitude).';
            }

            if ($conduiteParChauffeur > 600) {
                $alertes[] = 'Journée du '.$date.' : conduite de '.$this->formatMinutes($conduiteParChauffeur).' par chauffeur > 10h maximum.';
            } elseif ($conduiteParChauffeur > $seuilConduite && ! empty($jour['depassement_extensions_journalieres'])) {
                $alertes[] = 'Semaine '.$semaine.' : plus de deux journées à 10h de conduite.';
            }

            if (! empty($jour['depassement_semaine']) && ceil((int) $jour['conduite_semaine_min'] / $nombreChauffeurs) > 3360) {
                $alertes[] = 'Semaine '.$semaine.' : conduite de '.$this->formatMinutes((int) ceil((int) $jour['conduite_semaine_min'] / $nombreChauffeurs)).' par chauffeur > 56h maximum.';
            }
            if (! empty($jour['depassement_deux_semaines']) && ceil((int) $jour['conduite_deux_semaines_min'] / $nombreChauffeurs) > 5400) {
                $alertes[] = 'Période de deux semaines autour du '.$date.' : conduite de '.$this->formatMinutes((int) ceil((int) $jour['conduite_deux_semaines_min'] / $nombreChauffeurs)).' par chauffeur > 90h maximum.';
            }
            if (! empty($jour['pause_reglementaire_manquante'])) {
                $alertes[] = 'Journée du '.$date.' : pause de 45 min manquante après 4h30 de conduite continue.';
            }

            if ($tteParChauffeur > $seuilTte) {
                $alertes[] = 'Journée du '.$date.' : TTE de '.($this->formatMinutes($tteParChauffeur)).' par chauffeur > '.($this->formatMinutes($seuilTte)).' (réduire temps de service).';
            }

            if ($repos !== null && $repos < 540) {
                $alertes[] = 'Journée du '.$date.' : repos quotidien de '.$this->formatMinutes(max(0, (int) $repos)).' < 9h minimum.';
            } elseif ($repos !== null && $repos < $reposJournalierMin) {
                $reposReduitsParSemaine[$semaine] = ($reposReduitsParSemaine[$semaine] ?? 0) + 1;
                if ($reposReduitsParSemaine[$semaine] > 3) {
                    $alertes[] = 'Semaine '.$semaine.' : plus de trois repos quotidiens réduits à 9h.';
                }
            }

            $reposHebdo = $jour['repos_hebdomadaire_precedent_min'] ?? null;
            if ($reposHebdo !== null && $reposHebdo < 1440 && (int) ($jour['jours_travail_consecutifs'] ?? 0) > 6) {
                $alertes[] = 'Journée du '.$date.' : repos hebdomadaire de '.$this->formatMinutes(max(0, (int) $reposHebdo)).' < 24h minimum après six jours de travail.';
            }
            if ($reposHebdo !== null && $reposHebdo >= 1440 && $reposHebdo < 2700) {
                $reposHebdomadairesReduitsParQuinzaine[$quinzaine] = ($reposHebdomadairesReduitsParQuinzaine[$quinzaine] ?? 0) + 1;
                if ($reposHebdomadairesReduitsParQuinzaine[$quinzaine] > 1) {
                    $alertes[] = 'Période de deux semaines autour du '.$date.' : plus d’un repos hebdomadaire réduit à 24h.';
                }
            }
            if ((int) ($jour['jours_travail_consecutifs'] ?? 0) > 6 && ($reposHebdo === null || $reposHebdo < 1440)) {
                $alertes[] = 'Journée du '.$date.' : plus de six jours de travail sans repos hebdomadaire d’au moins 24h.';
            }

            if (! empty($jour['relais_necessaire']) && $nombreChauffeurs < 2) {
                $alertes[] = 'Journée du '.$date.' : relais nécessaire / 2 chauffeurs requis selon la réglementation (ajouter second chauffeur).';
            }
        }

        $conduitePlanifiee = (int) collect($grille)->sum(fn (array $jour) => (int) ($jour['heures_100_min'] ?? $jour['conduite_min'] ?? 0));
        $conduiteCalculee = (int) data_get(
            $devis->calcul_payload,
            'itineraire.duree_conduite_chargee_minutes',
            $devis->duree_conduite_minutes
        );
        if ($conduitePlanifiee < $conduiteCalculee) {
            $alertes[] = 'Conduite non planifiée : '.$this->formatMinutes($conduiteCalculee - $conduitePlanifiee).' restent à répartir; ajoutez des jours ou réorganisez la tournée.';
        }

        return array_values(array_unique($alertes));
    }

    public function reglementationEstValide(): bool
    {
        return empty($this->reglementationAlertes);
    }

    protected function formatMinutes(int $minutes): string
    {
        return intdiv($minutes, 60).'h'.str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
    }

    /** Détail poste par poste du temps de service (REQ-S-04). */
    public function getDetailTempsServiceProperty(): array
    {
        $etapes = $this->demande->etapes;
        if ($etapes->count() < 2) {
            return [];
        }

        $detail = [];
        $n = $etapes->count();
        for ($i = 0; $i < $n; $i++) {
            $etape = $etapes[$i];
            $debut = $i === 0 ? null : ($etapes[$i - 1]->heure_depart ?: $etapes[$i - 1]->heure_arrivee);
            $fin = $etape->heure_arrivee;
            $duree = null;
            $taux = '—';

            if ($debut && $fin) {
                $d = Carbon::createFromFormat('H:i', $fin)->diffInMinutes(Carbon::createFromFormat('H:i', $debut));
                if ($d < 0) {
                    $d += 1440;
                }
                $duree = $d;
                $taux = '100 %';
            } elseif ($etape->heure_depart) {
                $taux = '50 %';
            } elseif ($etape->heure_arrivee) {
                $taux = '100 %';
            }

            $detail[] = [
                'ordre' => $etape->ordre,
                'libelle' => $etape->libelle(),
                'date' => $etape->date?->format('d/m/Y'),
                'debut' => $debut ? substr($debut, 0, 5) : '—',
                'fin' => $fin ? substr($fin, 0, 5) : '—',
                'duree_min' => $duree,
                'taux' => $taux,
            ];
        }

        return $detail;
    }

    /**
     * Véhicules proposés à l'affectation : tous les véhicules actifs pouvant accueillir
     * le nombre de passagers demandé (la secrétaire garde la main pour optimiser).
     */
    public function getVehiculesProperty()
    {
        return Vehicule::query()
            ->where('actif', true)
            ->where('nb_places', '>=', $this->demande->nb_passagers)
            ->orderBy('nb_places')
            ->get();
    }

    /** Conflits de planning pour un véhicule donné sur la fenêtre du déplacement. */
    public function conflitsPour(int $vehiculeId): int
    {
        [$debut, $fin] = $this->fenetre();

        return Planning::query()
            ->where('vehicule_id', $vehiculeId)
            ->chevauche($debut, $fin)
            ->count();
    }

    public function affecter(): void
    {
        $this->validate([
            'vehicule_id' => ['required', 'exists:vehicules,id'],
        ], [
            'vehicule_id.required' => 'Choisissez un véhicule à affecter.',
        ]);

        $devis = $this->devisActuel() ?? new Devis([
            'reference' => 'DEV-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'statut' => 'brouillon',
        ]);

        $devis->demande_id = $this->demande->id;
        $devis->vehicule_id = $this->vehicule_id;
        $devis->save();

        if ($this->demande->statut === 'nouvelle') {
            $this->demande->update(['statut' => 'en_traitement']);
        }

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Véhicule affecté à la demande '.$this->demande->reference.'.';
    }

    public function retirerAffectation(): void
    {
        $this->demande->devis()->delete();
        $this->demande->update(['statut' => 'nouvelle']);
        $this->vehicule_id = null;
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Affectation retirée.';
    }

    /** Passe une estimation en demande ferme sans supprimer les informations déjà saisies. */
    public function convertirEnFerme(): void
    {
        if ($this->demande->mode !== 'estimation') {
            return;
        }

        $this->demande->update(['mode' => 'ferme']);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Estimation convertie en demande ferme. Complétez les lieux manquants si nécessaire.';
    }

    /**
     * Lance le calcul complet (itinéraire + RSE + coûts). C'est ICI — et seulement ici —
     * que les API payantes seraient appelées (cf. cahier des charges).
     */
    public function calculer(MoteurCalcul $moteur): void
    {
        $this->erreur = null;
        $devis = $this->devisActuel();

        if (! $devis || ! $devis->vehicule_id) {
            $this->erreur = 'Affectez d\'abord un véhicule.';
            $this->dispatch('scroll-to-section', target: 'affectation-vehicule');

            return;
        }

        // Sauvegarder les overrides existants avant recalcul
        $overrides = $this->getCoutOverrides($devis);

        // L'appel d'itinéraire peut prendre jusqu'à ~45 s (avec sa reprise).
        // Sans cela, PHP tuerait la requête et l'écran resterait bloqué sans message.
        @set_time_limit(120);

        try {
            $resultat = $moteur->calculer($this->demande, $devis->vehicule, $this->marge_taux, $devis);
        } catch (\Throwable $e) {
            $this->erreur = 'Calcul impossible : '.$e->getMessage();
            $this->dispatch('scroll-to-section', target: 'chiffrage');

            return;
        }

        // Appliquer le résultat du calcul
        $devis->fill($resultat['devis'])->save();

        // Réappliquer les overrides s'ils existent
        if ($overrides !== []) {
            $devis->update($overrides);
            $moteur->recalculerRsePourDevis($devis);
            $moteur->recalculerCoutsPourDevis($devis);
        } else {
            $devis->recalculerTotaux();
        }

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $this->flash = 'Calcul effectué (overrides conservés).';
        $this->dispatch('scroll-to-section', target: 'rse-resultat');
    }

    /**
     * Récupère les overrides de coûts actuels du devis.
     */
    protected function getCoutOverrides(Devis $devis): array
    {
        $overrides = [];
        foreach ([
            'distance_km', 'distance_km_charge', 'distance_km_vide',
            'duree_conduite_minutes', 'temps_attente_minutes',
            'cout_carburant', 'cout_peage', 'cout_vignettes', 'cout_chauffeur', 'cout_charges_fixes', 'cout_charges_variables',
        ] as $key) {
            $originalKey = 'original_'.$key;
            if ($devis->$originalKey !== null) {
                $overrides[$key] = $devis->$key;
                $overrides[$originalKey] = $devis->$originalKey;
            }
        }

        return $overrides;
    }

    public function appliquerOverride(): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            $this->erreur = 'Aucun devis.';

            return;
        }

        $this->validate([
            'edit_distance_km' => ['nullable', 'numeric', 'min:0'],
            'edit_distance_km_charge' => ['nullable', 'numeric', 'min:0'],
            'edit_distance_km_vide' => ['nullable', 'numeric', 'min:0'],
            'edit_duree_conduite_minutes' => ['nullable', 'integer', 'min:0'],
            'edit_temps_attente_minutes' => ['nullable', 'integer', 'min:0'],
            'override_raison' => ['nullable', 'string', 'max:500'],
        ]);

        $now = now();
        $author = auth()->user()?->name ?? 'secrétariat';

        $update = [];
        $originalFields = [
            'distance_km', 'distance_km_charge', 'distance_km_vide',
            'duree_conduite_minutes', 'temps_attente_minutes',
        ];
        $editFields = [
            'edit_distance_km' => 'distance_km',
            'edit_distance_km_charge' => 'distance_km_charge',
            'edit_distance_km_vide' => 'distance_km_vide',
            'edit_duree_conduite_minutes' => 'duree_conduite_minutes',
            'edit_temps_attente_minutes' => 'temps_attente_minutes',
        ];

        foreach ($editFields as $editKey => $devisKey) {
            $newValue = $this->$editKey;
            if ($newValue !== null && $newValue != $devis->$devisKey) {
                $originalKey = 'original_'.$devisKey;
                if ($devis->$originalKey === null) {
                    $update[$originalKey] = $devis->$devisKey;
                }
                $update[$devisKey] = $newValue;
            }
        }

        if ($update !== []) {
            $update['override_author'] = $author.($this->override_raison ? ' — '.$this->override_raison : '');
            $update['override_at'] = $now;
            $devis->update($update);
            $moteur = app(MoteurCalcul::class);
            $moteur->recalculerRsePourDevis($devis);
            $moteur->recalculerCoutsPourDevis($devis);
            Journal::enregistrer('Override devis '.$devis->reference, $this->demande->reference);
            $this->flash = 'Surcharges appliquées; devis et réglementation recalculés.';
            $this->erreur = null;
            $this->dispatch('scroll-to-section', target: 'chiffrage');
        }

        $this->reset('edit_distance_km', 'edit_distance_km_charge', 'edit_distance_km_vide', 'edit_duree_conduite_minutes', 'edit_temps_attente_minutes', 'override_raison');
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
    }

    public function reinitialiserOverride(): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            return;
        }

        $update = [];
        foreach (['distance_km', 'distance_km_charge', 'distance_km_vide', 'duree_conduite_minutes', 'temps_attente_minutes'] as $key) {
            $originalKey = 'original_'.$key;
            if ($devis->$originalKey !== null) {
                $update[$key] = $devis->$originalKey;
                $update[$originalKey] = null;
            }
        }
        $update['override_author'] = null;
        $update['override_at'] = null;

        if ($update !== []) {
            $devis->update($update);
            Journal::enregistrer('Reset override devis '.$devis->reference, $this->demande->reference);
            $this->flash = 'Valeurs réinitialisées aux valeurs calculées.';
        }

        $this->reset('edit_distance_km', 'edit_distance_km_charge', 'edit_distance_km_vide', 'edit_duree_conduite_minutes', 'edit_temps_attente_minutes', 'override_raison');
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
    }

    // --- Surcharges manuelles coûts (REQ-S-10) ---

    public function appliquerOverrideCouts(): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            $this->erreur = 'Aucun devis.';

            return;
        }

        $this->validate([
            'edit_cout_carburant' => ['nullable', 'numeric', 'min:0'],
            'edit_cout_peage' => ['nullable', 'numeric', 'min:0'],
            'edit_cout_vignettes' => ['nullable', 'numeric', 'min:0'],
            'edit_cout_chauffeur' => ['nullable', 'numeric', 'min:0'],
            'edit_cout_charges_fixes' => ['nullable', 'numeric', 'min:0'],
            'edit_cout_charges_variables' => ['nullable', 'numeric', 'min:0'],
        ]);

        $now = now();
        $author = auth()->user()?->name ?? 'secrétariat';

        $update = [];
        $coutFields = [
            'edit_cout_carburant' => 'cout_carburant',
            'edit_cout_peage' => 'cout_peage',
            'edit_cout_vignettes' => 'cout_vignettes',
            'edit_cout_chauffeur' => 'cout_chauffeur',
            'edit_cout_charges_fixes' => 'cout_charges_fixes',
            'edit_cout_charges_variables' => 'cout_charges_variables',
        ];

        foreach ($coutFields as $editKey => $devisKey) {
            $newValue = $this->$editKey;
            if ($newValue !== null && $newValue != $devis->$devisKey) {
                $originalKey = 'original_'.$devisKey;
                if ($devis->$originalKey === null) {
                    $update[$originalKey] = $devis->$devisKey;
                }
                $update[$devisKey] = $newValue;
            }
        }

        if ($update !== []) {
            $update['override_author'] = $author.($this->override_raison ? ' — '.$this->override_raison : '');
            $update['override_at'] = $now;
            $devis->update($update);
            $this->recalculerCoutRevient($devis);
            Journal::enregistrer('Override coûts devis '.$devis->reference, $this->demande->reference);
            $this->flash = 'Coûts modifiés avec traçabilité.';
        }

        $this->reset('edit_cout_carburant', 'edit_cout_peage', 'edit_cout_vignettes', 'edit_cout_chauffeur', 'edit_cout_charges_fixes', 'edit_cout_charges_variables', 'override_raison');
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
    }

    public function reinitialiserOverrideCouts(): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            return;
        }

        $update = [];
        foreach (['cout_carburant', 'cout_peage', 'cout_vignettes', 'cout_chauffeur', 'cout_charges_fixes', 'cout_charges_variables'] as $key) {
            $originalKey = 'original_'.$key;
            if ($devis->$originalKey !== null) {
                $update[$key] = $devis->$originalKey;
                $update[$originalKey] = null;
            }
        }
        $update['override_author'] = null;
        $update['override_at'] = null;

        if ($update !== []) {
            $devis->update($update);
            $this->recalculerCoutRevient($devis);
            Journal::enregistrer('Reset override coûts devis '.$devis->reference, $this->demande->reference);
            $this->flash = 'Coûts réinitialisés aux valeurs calculées.';
        }

        $this->reset('edit_cout_carburant', 'edit_cout_peage', 'edit_cout_vignettes', 'edit_cout_chauffeur', 'edit_cout_charges_fixes', 'edit_cout_charges_variables', 'override_raison');
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
    }

    protected function recalculerCoutRevient(Devis $devis): void
    {
        $coutRevient = (float) $devis->cout_carburant
            + (float) $devis->cout_peage
            + (float) $devis->cout_vignettes
            + (float) $devis->cout_chauffeur
            + (float) $devis->cout_charges_fixes
            + (float) $devis->cout_charges_variables;

        $devis->update(['cout_revient_ht' => round($coutRevient, 2)]);
        $devis->recalculerTotaux();
    }

    // --- Postes de service (édition en ligne dans le tableau RSE) ---

    public function ouvrirAjoutPoste(string $date): void
    {
        $this->editingPosteId = null;
        $this->editingPosteDate = $date;
        $this->poste_type = 'conduite';
        $this->poste_heure_debut = null;
        $this->poste_heure_fin = null;
        $this->poste_taux = 100;
    }

    public function fermerAjoutPoste(): void
    {
        $this->editingPosteDate = null;
        $this->editingPosteId = null;
    }

    public function modifierPoste(int $posteId): void
    {
        $devis = $this->devisActuel();
        $poste = $devis ? Poste::query()->where('devis_id', $devis->id)->find($posteId) : null;
        if (! $poste) {
            return;
        }

        $this->editingPosteId = $poste->id;
        $this->editingPosteDate = Carbon::parse($poste->date)->toDateString();
        $this->poste_type = $poste->type;
        $this->poste_heure_debut = $poste->heure_debut?->format('H:i');
        $this->poste_heure_fin = $poste->heure_fin?->format('H:i');
        $this->poste_taux = (int) $poste->taux;
        $this->erreur = null;
    }

    public function enregistrerModificationPoste(): void
    {
        $devis = $this->devisActuel();
        $poste = $devis && $this->editingPosteId
            ? Poste::query()->where('devis_id', $devis->id)->find($this->editingPosteId)
            : null;
        if (! $devis || ! $poste || ! $this->editingPosteDate) {
            return;
        }

        $this->validate([
            'poste_type' => ['required', 'in:prise_service,conduite,attente,pause,fin_service'],
            'poste_heure_debut' => ['required', 'date_format:H:i'],
            'poste_heure_fin' => ['required', 'date_format:H:i'],
            'poste_taux' => ['required', 'integer', 'in:0,50,100'],
        ]);

        $erreurType = $this->erreurTypePostePourDate($this->poste_type, $this->editingPosteDate, $poste->id);
        if ($erreurType) {
            $this->erreur = $erreurType;

            return;
        }

        $dureeMin = $this->dureeEntreHeures($this->poste_heure_debut, $this->poste_heure_fin);
        if ($this->poste_type === 'conduite') {
            $conduiteExistante = (int) $devis->postes()
                ->where('type', 'conduite')
                ->whereKeyNot($poste->id)
                ->sum('duree_min');
            if ($conduiteExistante + $dureeMin > (int) $devis->duree_conduite_minutes) {
                $this->erreur = 'La conduite saisie dépasse la durée totale calculée pour le trajet.';

                return;
            }
        }
        if ($this->poste_type === 'attente') {
            $attenteExistante = (int) $devis->postes()
                ->where('type', 'attente')
                ->whereKeyNot($poste->id)
                ->sum('duree_min');
            if ($attenteExistante + $dureeMin > (int) $devis->temps_attente_minutes) {
                $this->erreur = 'L’attente saisie dépasse la durée totale calculée pour le trajet.';

                return;
            }
        }

        $poste->update([
            'type' => $this->poste_type,
            'heure_debut' => $this->poste_heure_debut,
            'heure_fin' => $this->poste_heure_fin,
            'duree_min' => in_array($this->poste_type, ['prise_service', 'fin_service'], true) ? 0 : $dureeMin,
            'taux' => $this->poste_type === 'pause' ? 0 : $this->poste_taux,
            'origine' => 'saisie_manuelle',
            'auteur' => Auth::user()?->name ?? 'secrétariat',
            'date_modif' => now(),
        ]);

        $this->actualiserRseApresModificationPoste($devis);
        $this->fermerAjoutPoste();
        $this->flash = 'Poste de service modifié.';
        $this->erreur = null;
    }

    public function ajouterPoste(): void
    {
        $devis = $this->devisActuel();
        if (! $devis || ! $this->editingPosteDate) {
            return;
        }

        $this->validate([
            'poste_type' => ['required', 'in:prise_service,conduite,attente,pause,fin_service'],
            'poste_heure_debut' => ['required', 'date_format:H:i'],
            'poste_heure_fin' => ['required', 'date_format:H:i'],
            'poste_taux' => ['required', 'integer', 'in:0,50,100'],
        ]);

        $erreurType = $this->erreurTypePostePourDate($this->poste_type, $this->editingPosteDate);
        if ($erreurType) {
            $this->erreur = $erreurType;

            return;
        }
        $dureeMin = $this->dureeEntreHeures($this->poste_heure_debut, $this->poste_heure_fin);

        $devisActuel = $this->devisActuel();
        if (! $devisActuel) {
            $this->erreur = 'Aucun devis.';

            return;
        }

        if ($this->poste_type === 'conduite') {
            $totalConduite = (int) Poste::where('devis_id', $devisActuel->id)
            ->where('type', 'conduite')
                ->sum('duree_min');
            $maxConduite = $devisActuel->duree_conduite_minutes;
            if ($totalConduite + $dureeMin > $maxConduite) {
                $this->erreur = 'La durée de conduite totale ('.round($totalConduite / 60, 1).'h + '.round($dureeMin / 60, 1).'h) dépasse la durée calculée par le routage ('.round($maxConduite / 60, 1).'h).';

                return;
            }
        }

        if ($this->poste_type === 'attente') {
            $totalAttente = (int) Poste::where('devis_id', $devisActuel->id)
                ->where('type', 'attente')
                ->sum('duree_min');
            $maxAttente = $devisActuel->temps_attente_minutes;
            if ($totalAttente + $dureeMin > $maxAttente) {
                $this->erreur = 'La durée d\'attente totale ('.round($totalAttente / 60, 1).'h + '.round($dureeMin / 60, 1).'h) dépasse la durée calculée par le routage ('.round($maxAttente / 60, 1).'h).';

                return;
            }
        }

        $ordre = Poste::query()
            ->where('devis_id', $devisActuel->id)
            ->whereRaw('DATE(date) = ?', [$this->editingPosteDate])
            ->max('ordre') + 1;

        Poste::create([
            'devis_id' => $devisActuel->id,
            'date' => $this->editingPosteDate,
            'ordre' => $ordre,
            'type' => $this->poste_type,
            'heure_debut' => $this->poste_heure_debut,
            'heure_fin' => $this->poste_heure_fin,
            'duree_min' => in_array($this->poste_type, ['prise_service', 'fin_service'], true) ? 0 : $dureeMin,
            'taux' => $this->poste_type === 'pause' ? 0 : $this->poste_taux,
            'origine' => 'saisie_manuelle',
            'auteur' => Auth::user()?->name ?? 'secrétariat',
            'date_modif' => now(),
        ]);

        $this->actualiserRseApresModificationPoste($devisActuel);
        $this->fermerAjoutPoste();
        $this->flash = 'Poste ajouté.';
        $this->erreur = null;
    }

    public function supprimerPoste(int $posteId): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            return;
        }

        $poste = Poste::where('devis_id', $devis->id)->findOrFail($posteId);
        $poste->delete();
        $this->actualiserRseApresModificationPoste($devis);
        $this->flash = 'Poste supprimé.';
    }

    protected function erreurTypePostePourDate(string $type, string $date, ?int $posteId = null): ?string
    {
        $dates = $this->demande->etapes
            ->pluck('date')
            ->filter()
            ->map(fn ($dateEtape) => Carbon::parse($dateEtape)->toDateString())
            ->sort()
            ->values();
        if ($dates->isEmpty()) {
            return 'Aucune date de trajet ne permet de positionner ce poste.';
        }

        $date = Carbon::parse($date)->toDateString();
        if ($type === 'prise_service' && $date !== $dates->first()) {
            return 'La prise de service doit figurer uniquement le premier jour du trajet.';
        }
        if ($type === 'fin_service' && $date !== $dates->last()) {
            return 'La fin de service doit figurer uniquement le dernier jour du trajet.';
        }
        if (in_array($type, ['prise_service', 'fin_service'], true)) {
            $devisId = $this->devisActuel()?->id;
            $existe = Poste::query()
                ->where('devis_id', $devisId)
                ->whereDate('date', $date)
                ->where('type', $type)
                ->when($posteId, fn ($query) => $query->whereKeyNot($posteId))
                ->exists();
            if ($existe) {
                return $type === 'prise_service'
                    ? 'Une prise de service existe déjà pour le premier jour.'
                    : 'Une fin de service existe déjà pour le dernier jour.';
            }
        }

        return null;
    }

    protected function dureeEntreHeures(string $debut, string $fin): int
    {
        $debutMin = Carbon::createFromFormat('H:i', $debut)->hour * 60 + Carbon::createFromFormat('H:i', $debut)->minute;
        $finCarbon = Carbon::createFromFormat('H:i', $fin);
        $finMin = $finCarbon->hour * 60 + $finCarbon->minute;

        return $finMin >= $debutMin ? $finMin - $debutMin : 1440 - $debutMin + $finMin;
    }

    protected function actualiserRseApresModificationPoste(Devis $devis): void
    {
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        app(MoteurCalcul::class)->recalculerRsePourDevis($devis);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
    }

    // --- Prestations supplémentaires (lignes libres) ---

    public function ajouterLigne(): void
    {
        $this->erreur = null;
        $devis = $this->devisActuel();
        if (! $devis) {
            return;
        }

        $this->validate([
            'ligneLibelle' => ['required', 'string', 'max:120'],
            'ligneMontant' => ['required', 'numeric'],
        ], [
            'ligneLibelle.required' => 'Indiquez le libellé de la prestation.',
            'ligneMontant.required' => 'Indiquez un montant.',
            'ligneMontant.numeric' => 'Le montant doit être un nombre.',
        ]);

        $lignes = $devis->lignes_libres ?? [];
        $lignes[] = ['libelle' => trim($this->ligneLibelle), 'montant' => round((float) $this->ligneMontant, 2)];
        $devis->update(['lignes_libres' => array_values($lignes)]);
        $devis->recalculerTotaux();

        $this->reset('ligneLibelle', 'ligneMontant');
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Prestation ajoutée.';
    }

    public function retirerLigne(int $index): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            return;
        }

        $lignes = $devis->lignes_libres ?? [];
        if (! array_key_exists($index, $lignes)) {
            return;
        }

        unset($lignes[$index]);
        $devis->update(['lignes_libres' => array_values($lignes)]);
        $devis->recalculerTotaux();

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Prestation retirée.';
    }

    /** Recalcule uniquement marge/TVA/totaux à partir du coût de revient (sans re-appeler l'itinéraire). */
    public function updatedMargeTaux(): void
    {
        $devis = $this->devisActuel();
        if (! $devis || $devis->cout_revient_ht <= 0) {
            return;
        }

        $devis->update(['marge_taux' => round(max(0, (float) $this->marge_taux), 2)]);
        $devis->recalculerTotaux();
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
    }

    /** Cycle de statuts du devis (brouillon → valide → envoye → accepte/refuse). */
    public function changerStatutDevis(string $statutDevis, string $statutDemande): void
    {
        $devis = $this->devisActuel();
        if (! $devis || $devis->cout_revient_ht <= 0) {
            $this->erreur = 'Calculez le devis avant de changer son statut.';

            return;
        }

        $devis->update(['statut' => $statutDevis]);
        if ($statutDevis === 'valide') {
            $this->alimenterCarnet();
        }
        $this->demande->update(['statut' => $statutDemande]);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);

        Journal::enregistrer('Devis '.$statutDevis, $this->demande->reference);
        $this->flash = 'Devis : '.$statutDevis.'.';
    }

    public function corrigerReglementation(): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            $this->erreur = 'Aucun devis à corriger.';

            return;
        }

        if (($devis->calcul_payload['rse']['grille_journaliere'] ?? []) === []) {
            $this->erreur = 'Lancez d’abord le calcul du devis pour obtenir les seuils réglementaires.';

            return;
        }

        $moteur = app(MoteurCalcul::class);
        $devis->postes()->where('origine', 'correction_reglementaire')->delete();
        $moteur->recalculerRsePourDevis($devis);
        $devis->refresh();
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $this->completerHeuresArriveeSelonVitesseBus();
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $derniereDateDestination = $this->demande->etapes->max('date');
        if ($derniereDateDestination) {
            $devis->postes()->whereDate('date', '>', $derniereDateDestination)->delete();
        }
        $moteur->recalculerRsePourDevis($devis);
        $devis->refresh();
        $grille = $devis->calcul_payload['rse']['grille_journaliere'] ?? [];
        $this->corrigerHorairesManquantsPourReglementation($devis, $grille);
        $this->synchroniserArriveeFinaleAvecFinService($devis);

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $moteur->recalculerRsePourDevis($devis);
        $moteur->recalculerCoutsPourDevis($devis);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);

        $this->flash = 'Arrivées estimées à 80 km/h et horaires organisés selon les pauses/repos. Vérifiez les alertes restantes.';
        $this->erreur = null;
    }

    protected function synchroniserArriveeFinaleAvecFinService(Devis $devis): void
    {
        $etapeFinale = $this->demande->etapes->sortBy('ordre')->last();
        $posteFin = $devis->postes()
            ->where('type', 'fin_service')
            ->orderByDesc('date')
            ->first();
        if (! $etapeFinale || ! $posteFin?->date || ! $posteFin->heure_fin) {
            return;
        }

        $heureArriveeEtape = $etapeFinale->heure_arrivee ?: '00:00';
        $arriveeEtape = Carbon::parse($etapeFinale->date->toDateString().' '.substr((string) $heureArriveeEtape, 0, 5));
        $arriveePlanifiee = Carbon::parse($posteFin->date->toDateString().' '.$posteFin->heure_fin->format('H:i'));
        if ($arriveePlanifiee->gt($arriveeEtape)) {
            $etapeFinale->update([
                'date' => $arriveePlanifiee->toDateString(),
                'heure_arrivee' => $arriveePlanifiee->format('H:i'),
            ]);
        }
    }

    protected function completerHeuresArriveeSelonVitesseBus(): void
    {
        $etapes = $this->demande->etapes->sortBy('ordre')->values();
        if ($etapes->count() < 2) {
            return;
        }

        $premiere = $etapes->first();
        if (! $premiere->heure_depart && ! $premiere->heure_arrivee) {
            $premiere->heure_depart = '08:00';
            $premiere->save();
        }

        $reposQuotidienMin = (int) Parametre::get('rse_repos_journalier_min', 660);
        $devis = $this->devisActuel();
        $nombreChauffeurs = max(1, (int) ($devis?->nb_chauffeurs ?? 1));
        $conduiteMaxJour = $nombreChauffeurs > 1 ? 1080 : 540;
        $conduiteAvantPause = (int) Parametre::get('rse_conduite_avant_pause_min', 270);
        $dureePause = (int) Parametre::get('rse_duree_pause_min', 45);
        $conduiteContinue = 0;
        $conduiteJour = 0;
        $joursConsecutifs = 1;

        for ($index = 1; $index < $etapes->count(); $index++) {
            $precedente = $etapes[$index - 1];
            $etape = $etapes[$index];
            $dureeTroncon = data_get(
                $devis?->calcul_payload,
                'itineraire.duree_par_troncon_etape.'.$precedente->id.':'.$etape->id
            );
            $distanceKm = $dureeTroncon === null ? $this->distanceRoutiereEstimee($precedente, $etape) : null;
            if ($dureeTroncon === null && $distanceKm === null) {
                continue;
            }

            $minutesConduite = $dureeTroncon !== null
                ? (int) $dureeTroncon
                : (int) ceil(($distanceKm / 80) * 60);
            $datePrecedente = Carbon::parse($precedente->date)->startOfDay();
            $heurePrecedente = $precedente->heure_depart ?: $precedente->heure_arrivee ?: '08:00';
            $departSaisi = Carbon::parse($datePrecedente->toDateString().' '.substr((string) $heurePrecedente, 0, 5));

            if (! $precedente->heure_depart && $etape->heure_arrivee && $etape->date
                && Carbon::parse($etape->date)->toDateString() === $datePrecedente->toDateString()) {
                $arriveeSaisie = Carbon::parse($etape->date->toDateString().' '.substr((string) $etape->heure_arrivee, 0, 5));
                $dureeTrajet = $this->dureeTrajetAvecReglementation($minutesConduite, $conduiteAvantPause, $dureePause);
                $departEstime = $arriveeSaisie->copy()->subMinutes($dureeTrajet);
                if ($departEstime->toDateString() === $datePrecedente->toDateString()) {
                    $departSaisi = $departEstime;
                    $precedente->heure_depart = $departSaisi->format('H:i');
                    $precedente->save();
                }
            }

            if ($precedente->heure_arrivee && $precedente->date) {
                $arriveePrecedente = Carbon::parse($precedente->date->toDateString().' '.substr((string) $precedente->heure_arrivee, 0, 5));
                if ($departSaisi->gte($arriveePrecedente)) {
                    $reposEntreEtapes = $arriveePrecedente->diffInMinutes($departSaisi);
                    if ($reposEntreEtapes >= $reposQuotidienMin) {
                        $conduiteContinue = 0;
                        $conduiteJour = 0;
                        $joursConsecutifs = 1;
                    } elseif ($reposEntreEtapes >= $dureePause) {
                        $conduiteContinue = 0;
                    }
                }
            }

            $arriveeCalculee = $this->avancerConduiteAvecRepos(
                $departSaisi,
                $minutesConduite,
                $conduiteContinue,
                $conduiteJour,
                $joursConsecutifs,
                $conduiteAvantPause,
                $dureePause,
                $reposQuotidienMin,
                $conduiteMaxJour,
                $nombreChauffeurs
            );

            if (! $etape->heure_arrivee) {
                $etape->date = $arriveeCalculee->toDateString();
                $etape->heure_arrivee = $arriveeCalculee->format('H:i');
                $etape->save();
            } elseif ($etape->date) {
                $arriveeSaisie = Carbon::parse($etape->date->toDateString().' '.substr((string) $etape->heure_arrivee, 0, 5));
                if ($arriveeSaisie->gt($arriveeCalculee)) {
                    $arriveeCalculee = $arriveeSaisie;
                } else {
                    $etape->date = $arriveeCalculee->toDateString();
                    $etape->heure_arrivee = $arriveeCalculee->format('H:i');
                    $etape->save();
                }
            }

            if ($index < $etapes->count() - 1 && ! $etape->heure_depart && $etape->heure_arrivee) {
                $etape->heure_depart = substr((string) $etape->heure_arrivee, 0, 5);
                $etape->save();
            }

            if ($etape->heure_depart && $etape->date) {
                $departEtapeSuivante = Carbon::parse($etape->date->toDateString().' '.substr((string) $etape->heure_depart, 0, 5));
                if ($departEtapeSuivante->gte($arriveeCalculee)) {
                    $tempsArret = $arriveeCalculee->diffInMinutes($departEtapeSuivante);
                    if ($tempsArret >= $dureePause) {
                        $conduiteContinue = 0;
                    }
                    if ($tempsArret >= $reposQuotidienMin) {
                        $conduiteJour = 0;
                        $joursConsecutifs = 1;
                    }
                }
            }
        }
    }

    protected function distanceRoutiereEstimee(Etape $depart, Etape $arrivee): ?float
    {
        $lat1 = $depart->latitude ?? $depart->commune?->latitude;
        $lng1 = $depart->longitude ?? $depart->commune?->longitude;
        $lat2 = $arrivee->latitude ?? $arrivee->commune?->latitude;
        $lng2 = $arrivee->longitude ?? $arrivee->commune?->longitude;
        if (! is_numeric($lat1) || ! is_numeric($lng1) || ! is_numeric($lat2) || ! is_numeric($lng2)) {
            return null;
        }

        $rayonTerreKm = 6371.0;
        $differenceLatitude = deg2rad((float) $lat2 - (float) $lat1);
        $differenceLongitude = deg2rad((float) $lng2 - (float) $lng1);
        $a = sin($differenceLatitude / 2) ** 2
            + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($differenceLongitude / 2) ** 2;
        $distanceVolOiseau = $rayonTerreKm * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $distanceVolOiseau * 1.25;
    }

    protected function dureeTrajetAvecReglementation(int $minutesConduite, int $seuilPause, int $dureePause): int
    {
        if ($minutesConduite <= $seuilPause || $seuilPause <= 0) {
            return $minutesConduite;
        }

        return $minutesConduite + (int) floor(($minutesConduite - 1) / $seuilPause) * $dureePause;
    }

    protected function avancerConduiteAvecRepos(
        Carbon $depart,
        int $minutesConduite,
        int &$conduiteContinue,
        int &$conduiteJour,
        int &$joursConsecutifs,
        int $seuilPause,
        int $dureePause,
        int $reposQuotidienMin,
        int $conduiteMaxJour,
        int $nombreChauffeurs
    ): Carbon {
        $arrivee = $depart->copy();
        $restant = $minutesConduite;
        while ($restant > 0) {
            if ($conduiteJour >= $conduiteMaxJour) {
                $repos = $joursConsecutifs >= 6 ? 2700 : $reposQuotidienMin;
                $arrivee->addMinutes($repos);
                $debutJournee = $arrivee->copy()->startOfDay()->addHours(8);
                if ($arrivee->lt($debutJournee)) {
                    $arrivee = $debutJournee;
                }
                $conduiteContinue = 0;
                $conduiteJour = 0;
                $joursConsecutifs = $repos >= 2700 ? 1 : $joursConsecutifs + 1;
            }
            if ($nombreChauffeurs === 1 && $conduiteContinue >= $seuilPause) {
                $arrivee->addMinutes($dureePause);
                $conduiteContinue = 0;
            }

            $segment = $nombreChauffeurs > 1
                ? min($restant, $conduiteMaxJour - $conduiteJour)
                : min($restant, $seuilPause - $conduiteContinue, $conduiteMaxJour - $conduiteJour);
            if ($segment <= 0) {
                break;
            }
            $arrivee->addMinutes($segment);
            $conduiteContinue = $nombreChauffeurs > 1 ? 0 : $conduiteContinue + $segment;
            $conduiteJour += $segment;
            $restant -= $segment;
        }

        return $arrivee;
    }

    protected function corrigerHorairesManquantsPourReglementation(Devis $devis, array $grille): void
    {
        $jours = collect($grille)
            ->pluck('date')
            ->filter(fn ($date) => filled($date))
            ->unique()
            ->values()
            ->all();

        if ($jours === []) {
            $jours = $this->demande->etapes
                ->pluck('date')
                ->filter(fn ($date) => filled($date))
                ->unique()
                ->values()
                ->all();
        }

        $jours = collect($jours)
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();
        if ($jours->isEmpty()) {
            return;
        }

        $postesManuels = Poste::query()
            ->where('devis_id', $devis->id)
            ->where('origine', '!=', 'correction_reglementaire')
            ->get();
        $joursConduiteImpossible = $postesManuels
            ->where('type', 'conduite')
            ->groupBy(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString())
            ->filter(fn ($postes) => (int) $postes->sum('duree_min') > 600)
            ->keys();
        if ($joursConduiteImpossible->isNotEmpty()) {
            $postesAReplanifier = $postesManuels
                ->filter(fn (Poste $poste) => $joursConduiteImpossible->contains(Carbon::parse($poste->date)->toDateString())
                    && in_array($poste->type, ['conduite', 'pause'], true))
                ->pluck('id');
            Poste::query()->whereKey($postesAReplanifier)->delete();
            $postesManuels = $postesManuels
                ->reject(fn (Poste $poste) => $postesAReplanifier->contains($poste->id))
                ->values();
        }
        $marqueursConserves = [];
        $postesServiceARejeter = collect();
        foreach ($postesManuels->whereIn('type', ['prise_service', 'fin_service']) as $posteService) {
            $datePoste = Carbon::parse($posteService->date)->toDateString();
            $dateAutorisee = $posteService->type === 'prise_service' ? $jours->first() : $jours->last();
            if ($datePoste !== $dateAutorisee || isset($marqueursConserves[$posteService->type])) {
                $postesServiceARejeter->push($posteService->id);

                continue;
            }
            $marqueursConserves[$posteService->type] = true;
        }
        if ($postesServiceARejeter->isNotEmpty()) {
            Poste::query()->whereKey($postesServiceARejeter)->delete();
            $postesManuels = $postesManuels
                ->reject(fn (Poste $poste) => $postesServiceARejeter->contains($poste->id))
                ->values();
        }
        $datesEtapes = $this->demande->etapes
            ->pluck('date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        // Prévoir un jour de repos hebdomadaire lorsqu’une journée sans étape est disponible.
        $joursRepos = [];
        $joursTravaillesConsecutifs = 0;
        foreach ($jours as $index => $date) {
            if ($joursTravaillesConsecutifs >= 6
                && $index < $jours->count() - 1
                && ! in_array($date, $datesEtapes, true)) {
                $joursRepos[] = $date;
                $joursTravaillesConsecutifs = 0;

                continue;
            }
            $joursTravaillesConsecutifs++;
        }

        $joursActifs = $jours->reject(fn ($date) => in_array($date, $joursRepos, true))->values();
        $conduiteManuelleParJour = $postesManuels->where('type', 'conduite')
            ->groupBy(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString())
            ->map(fn ($postes) => (int) $postes->sum('duree_min'));
        $attenteManuelleParJour = $postesManuels->where('type', 'attente')
            ->groupBy(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString())
            ->map(fn ($postes) => (int) $postes->sum('duree_min'));
        $dureeConduitePlanning = (int) data_get(
            $devis->calcul_payload,
            'itineraire.duree_conduite_chargee_minutes',
            $devis->duree_conduite_minutes
        );
        $nombreChauffeurs = max(1, (int) $devis->nb_chauffeurs);
        $capaciteMaxParJour = $nombreChauffeurs > 1 ? 1080 : 540;
        $conduiteRestante = max(0, $dureeConduitePlanning - (int) $conduiteManuelleParJour->sum());
        $attenteRestante = max(0, (int) $devis->temps_attente_minutes - (int) $attenteManuelleParJour->sum());

        $capaciteConduiteParJour = $joursActifs->mapWithKeys(fn ($date) => [$date => $capaciteMaxParJour])->all();
        $premierJour = $joursActifs->first();
        $etapesPremierJour = $this->demande->etapes
            ->filter(fn ($etape) => $etape->date && $etape->date->toDateString() === $premierJour)
            ->sortBy('ordre')
            ->values();
        if ($premierJour && $etapesPremierJour->count() >= 2) {
            $heureDebutPremierJour = $etapesPremierJour->first()->heure_depart;
            $heureArriveePremierJour = $etapesPremierJour->last()->heure_arrivee;
            if ($heureDebutPremierJour && $heureArriveePremierJour) {
                $debutDisponible = Carbon::createFromFormat('H:i', substr((string) $heureDebutPremierJour, 0, 5));
                $finDisponible = Carbon::createFromFormat('H:i', substr((string) $heureArriveePremierJour, 0, 5));
                $minutesDisponibles = $debutDisponible->diffInMinutes($finDisponible);
                if ($minutesDisponibles < 0) {
                    $minutesDisponibles += 1440;
                }
                $capaciteConduiteParJour[$premierJour] = min(
                    $capaciteMaxParJour,
                    $nombreChauffeurs > 1 ? $minutesDisponibles : $this->conduiteMaxDansFenetre($minutesDisponibles)
                );
            }
        }

        $derniereEtape = $this->demande->etapes->sortBy('ordre')->last();
        $derniereDate = $derniereEtape?->date?->toDateString();
        if ($derniereDate && $derniereEtape?->heure_arrivee) {
            $debutFenetre = Carbon::parse($derniereDate.' 08:00');
            if ($derniereDate === $joursActifs->first()) {
                $heureDepart = $this->demande->etapes->sortBy('ordre')->first()?->heure_depart;
                if ($heureDepart) {
                    $debutFenetre = Carbon::parse($derniereDate.' '.substr((string) $heureDepart, 0, 5));
                }
            }
            $finFenetre = Carbon::parse($derniereDate.' '.substr((string) $derniereEtape->heure_arrivee, 0, 5));
            $minutesDisponibles = max(0, (int) floor(($finFenetre->timestamp - $debutFenetre->timestamp) / 60));
            $capaciteConduiteParJour[$derniereDate] = min(
                (int) ($capaciteConduiteParJour[$derniereDate] ?? $capaciteMaxParJour),
                $nombreChauffeurs > 1 ? $minutesDisponibles : $this->conduiteMaxDansFenetre($minutesDisponibles)
            );
        }

        $capaciteDisponible = array_sum($capaciteConduiteParJour)
            - (int) $conduiteManuelleParJour->sum();
        $joursConsecutifsPlanifies = 0;
        foreach ($jours as $date) {
            if (in_array($date, $joursRepos, true)) {
                $joursConsecutifsPlanifies = 0;
            } else {
                $joursConsecutifsPlanifies++;
            }
        }
        $prochaineDate = Carbon::parse($jours->last());
        $joursEtendus = false;
        while ($capaciteDisponible < $conduiteRestante && $jours->count() < 370) {
            $prochaineDate->addDay();
            $date = $prochaineDate->toDateString();
            if ($joursConsecutifsPlanifies >= 6 && ! in_array($date, $datesEtapes, true)) {
                $joursRepos[] = $date;
                $jours->push($date);
                $joursConsecutifsPlanifies = 0;

                continue;
            }

            $jours->push($date);
            $joursActifs->push($date);
            $capaciteConduiteParJour[$date] = $capaciteMaxParJour;
            $capaciteDisponible += $capaciteMaxParJour;
            $joursEtendus = true;
            $joursConsecutifsPlanifies++;
        }

        $marqueursFinEnTrop = $postesManuels
            ->where('type', 'fin_service')
            ->filter(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString() !== $jours->last())
            ->pluck('id');
        if ($marqueursFinEnTrop->isNotEmpty()) {
            Poste::query()->whereKey($marqueursFinEnTrop)->delete();
            $postesManuels = $postesManuels
                ->reject(fn (Poste $poste) => $marqueursFinEnTrop->contains($poste->id))
                ->values();
        }

        $conduiteAutoParJour = [];
        $conduiteAutoParSemaine = [];
        $conduiteAutoParQuinzaine = [];
        $seuilTte = min(660, (int) Parametre::get('rse_tte_max_min', 660));
        $joursRestants = max(1, $joursActifs->count());
        foreach ($joursActifs as $indexJour => $date) {
            $semaine = Carbon::parse($date)->format('o-W');
            $dateQuinzaine = Carbon::parse($date);
            $quinzaine = $dateQuinzaine->format('o').'-'.intdiv(((int) $dateQuinzaine->format('W')) - 1, 2);
            $conduiteManuelleJour = (int) $conduiteManuelleParJour->get($date, 0);
            $conduiteManuelleSemaine = (int) $postesManuels
                ->where('type', 'conduite')
                ->filter(fn (Poste $poste) => Carbon::parse($poste->date)->format('o-W') === $semaine)
                ->sum('duree_min');
            $conduiteManuelleQuinzaine = (int) $postesManuels
                ->where('type', 'conduite')
                ->filter(function (Poste $poste) use ($quinzaine) {
                    $datePoste = Carbon::parse($poste->date);

                    return $datePoste->format('o').'-'.intdiv(((int) $datePoste->format('W')) - 1, 2) === $quinzaine;
                })
                ->sum('duree_min');
            $cumulSemaine = (int) collect($conduiteAutoParSemaine)->get($semaine, 0);
            $cumulQuinzaine = (int) collect($conduiteAutoParQuinzaine)->get($quinzaine, 0);
            $conduiteParJourEquilibree = $joursEtendus ? $conduiteRestante : (int) ceil($conduiteRestante / $joursRestants);
            $datesSuivantes = $joursActifs->slice($indexJour + 1);
            $capaciteSuivante = (int) $datesSuivantes->sum(fn ($dateSuivant) => $capaciteConduiteParJour[$dateSuivant] ?? 0);
            $conduiteNecessaireAujourdhui = max(0, $conduiteRestante - $capaciteSuivante);
            $conduiteAuto = min(
                max($conduiteParJourEquilibree, $conduiteNecessaireAujourdhui),
                max(0, (int) ($capaciteConduiteParJour[$date] ?? 540) - $conduiteManuelleJour),
                max(0, 3360 * $nombreChauffeurs - $conduiteManuelleSemaine - $cumulSemaine),
                max(0, 5400 * $nombreChauffeurs - $conduiteManuelleQuinzaine - $cumulQuinzaine)
            );
            $conduiteAutoParJour[$date] = $conduiteAuto;
            $conduiteRestante -= $conduiteAuto;
            $joursRestants--;
            $conduiteAutoParSemaine[$semaine] = $cumulSemaine + $conduiteAuto;
            $conduiteAutoParQuinzaine[$quinzaine] = $cumulQuinzaine + $conduiteAuto;
        }

        $attenteAutoParJour = [];
        $joursRestants = max(1, $joursActifs->count());
        foreach ($joursActifs as $date) {
            $attenteManuelleJour = (int) $attenteManuelleParJour->get($date, 0);
            $conduiteJour = (int) $conduiteManuelleParJour->get($date, 0) + (int) ($conduiteAutoParJour[$date] ?? 0);
            $attenteAuto = min(
                $attenteRestante,
                max(0, ($seuilTte - $conduiteJour) * 2 - $attenteManuelleJour),
                (int) ceil($attenteRestante / $joursRestants)
            );
            $attenteAutoParJour[$date] = $attenteAuto;
            $attenteRestante -= $attenteAuto;
            $joursRestants--;
        }

        // Retirer les postes produits par une correction précédente rend l’action répétable.
        Poste::query()
            ->where('devis_id', $devis->id)
            ->where('origine', 'correction_reglementaire')
            ->delete();

        $derniereJourneeActive = null;
        $finDerniereJourneeActive = null;
        foreach ($jours as $index => $date) {
            $etapesJour = $this->demande->etapes
                ->filter(fn ($etape) => $etape->date && $etape->date->toDateString() === $date)
                ->sortBy('ordre');
            $postesJour = $postesManuels->filter(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString() === $date);
            $conduiteManuelleJour = (int) $postesJour->where('type', 'conduite')->sum('duree_min');
            $attenteManuelleJour = (int) $postesJour->where('type', 'attente')->sum('duree_min');
            $conduiteAuto = (int) ($conduiteAutoParJour[$date] ?? 0);
            $attenteAuto = (int) ($attenteAutoParJour[$date] ?? 0);
            if (in_array($date, $joursRepos, true)) {
                continue;
            }

            $heureEtapeDebut = $etapesJour->pluck('heure_depart')->filter()->first();
            $heureEtapeFin = $etapesJour->pluck('heure_arrivee')->filter()->last();
            $postePrise = $index === 0 ? $postesJour->firstWhere('type', 'prise_service') : null;
            $posteFin = $index === $jours->count() - 1 ? $postesJour->firstWhere('type', 'fin_service') : null;
            $heureDebutSaisie = $postePrise?->heure_debut ?? $heureEtapeDebut;
            $heureDebut = $this->normaliserHeurePoste($heureDebutSaisie, '08:00');
            if (! $heureDebutSaisie && $finDerniereJourneeActive) {
                $reposMinimum = count(array_filter($joursRepos, fn ($jourRepos) => $jourRepos > $derniereJourneeActive && $jourRepos < $date)) > 0
                    ? 2700
                    : (int) Parametre::get('rse_repos_journalier_min', 660);
                $debutMinimum = $finDerniereJourneeActive->copy()->addMinutes($reposMinimum);
                $debutParDefaut = Carbon::parse($date.' 08:00');
                if ($debutMinimum->toDateString() === $date && $debutParDefaut->lt($debutMinimum)) {
                    $heureDebut = $debutMinimum->format('H:i');
                }
            }
            $heureConduiteDebut = $this->ajouterMinutesHeure($heureDebut, $conduiteManuelleJour);
            $heureFin = $this->ajouterMinutesHeure(
                $heureConduiteDebut,
                $conduiteAuto + $attenteManuelleJour + $attenteAuto + ($conduiteAuto > 270 ? 45 : 0)
            );
            if ($nombreChauffeurs > 1) {
                $heureFin = $this->ajouterMinutesHeure(
                    $heureConduiteDebut,
                    $conduiteAuto + $attenteManuelleJour + $attenteAuto
                );
            }
            $heureFinSaisie = $posteFin?->heure_fin ?? $heureEtapeFin;
            if ($heureFinSaisie && $index === $jours->count() - 1) {
                $heureFin = $this->normaliserHeurePoste($heureFinSaisie, $heureFin);
            }
            $ordre = (int) Poste::query()
                ->where('devis_id', $devis->id)
                ->whereDate('date', $date)
                ->max('ordre');

            if ($index === 0 && ! $postePrise) {
                $this->creerPosteCorrection($devis, $date, ++$ordre, 'prise_service', $heureDebut, $heureDebut, 0);
            }
            if ($index === $jours->count() - 1 && ! $posteFin) {
                $this->creerPosteCorrection($devis, $date, ++$ordre, 'fin_service', $heureFin, $heureFin, 0);
            }

            if ($conduiteAuto > 0) {
                if ($nombreChauffeurs > 1) {
                    $minutesRestantes = $conduiteAuto;
                    $debutConduite = $heureConduiteDebut;
                    while ($minutesRestantes > 0) {
                        $dureeConduite = min(270, $minutesRestantes);
                        $finConduite = $this->ajouterMinutesHeure($debutConduite, $dureeConduite);
                        $this->creerPosteCorrection($devis, $date, ++$ordre, 'conduite', $debutConduite, $finConduite, $dureeConduite);
                        $minutesRestantes -= $dureeConduite;
                        $debutConduite = $finConduite;
                    }
                } else {
                    $premiereConduite = min(270, $conduiteAuto);
                    $finPremiereConduite = $this->ajouterMinutesHeure($heureConduiteDebut, $premiereConduite);
                    $this->creerPosteCorrection($devis, $date, ++$ordre, 'conduite', $heureConduiteDebut, $finPremiereConduite, $premiereConduite);
                    if ($conduiteAuto > 270) {
                        $debutSecondeConduite = $this->ajouterMinutesHeure($finPremiereConduite, 45);
                        $this->creerPosteCorrection($devis, $date, ++$ordre, 'pause', $finPremiereConduite, $debutSecondeConduite, 45);
                    $this->creerPosteCorrection(
                        $devis,
                        $date,
                        ++$ordre,
                        'conduite',
                        $debutSecondeConduite,
                        $this->ajouterMinutesHeure($debutSecondeConduite, $conduiteAuto - 270),
                        $conduiteAuto - 270
                    );
                    }
                }
            }

            if ($attenteAuto > 0) {
                $heureAttenteDebut = $this->ajouterMinutesHeure($heureConduiteDebut, $conduiteAuto + ($conduiteAuto > 270 ? 45 : 0));
                $this->creerPosteCorrection(
                    $devis,
                    $date,
                    ++$ordre,
                    'attente',
                    $heureAttenteDebut,
                    $this->ajouterMinutesHeure($heureAttenteDebut, $attenteAuto),
                    $attenteAuto
                );
            }

            $derniereJourneeActive = $date;
            $finDerniereJourneeActive = Carbon::parse($date.' '.$heureFin);
        }

        $this->synchroniserPostesAvecHorairesEtapes($devis);
    }

    protected function synchroniserPostesAvecHorairesEtapes(Devis $devis): void
    {
        $frontieres = $this->demande->etapes
            ->flatMap(function (Etape $etape) {
                if (! $etape->date) {
                    return [];
                }

                return collect([$etape->heure_arrivee, $etape->heure_depart])
                    ->filter()
                    ->map(fn ($heure) => [
                        'date' => Carbon::parse($etape->date)->toDateString(),
                        'heure' => substr((string) $heure, 0, 5),
                    ]);
            })
            ->sortBy(fn (array $frontiere) => $frontiere['date'].' '.$frontiere['heure']);

        foreach ($frontieres as $frontiere) {
            $heureFrontiere = Carbon::createFromFormat('H:i', $frontiere['heure']);
            $poste = Poste::query()
                ->where('devis_id', $devis->id)
                ->whereDate('date', $frontiere['date'])
                ->where('type', 'conduite')
                ->where('origine', 'correction_reglementaire')
                ->get()
                ->first(fn (Poste $poste) => Carbon::parse($poste->heure_debut)->lt($heureFrontiere)
                    && Carbon::parse($poste->heure_fin)->gt($heureFrontiere));

            if (! $poste) {
                continue;
            }

            $debutPoste = Carbon::parse($poste->heure_debut);
            $finPoste = Carbon::parse($poste->heure_fin);
            $dureeAvant = (int) $debutPoste->diffInMinutes($heureFrontiere);
            $dureeApres = (int) $heureFrontiere->diffInMinutes($finPoste);
            if ($dureeAvant <= 0 || $dureeApres <= 0) {
                continue;
            }

            Poste::query()
                ->where('devis_id', $devis->id)
                ->whereDate('date', $frontiere['date'])
                ->where('ordre', '>', $poste->ordre)
                ->orderByDesc('ordre')
                ->get()
                ->each(fn (Poste $posteSuivant) => $posteSuivant->update(['ordre' => $posteSuivant->ordre + 1]));

            $poste->update([
                'heure_fin' => $heureFrontiere->format('H:i'),
                'duree_min' => $dureeAvant,
            ]);
            Poste::create([
                'devis_id' => $poste->devis_id,
                'date' => $frontiere['date'],
                'ordre' => $poste->ordre + 1,
                'type' => 'conduite',
                'heure_debut' => $heureFrontiere->format('H:i'),
                'heure_fin' => $finPoste->format('H:i'),
                'duree_min' => $dureeApres,
                'taux' => $poste->taux,
                'origine' => 'correction_reglementaire',
                'auteur' => Auth::user()?->name ?? 'secrétariat',
                'date_modif' => now(),
            ]);
        }
    }

    protected function creerPosteCorrection(Devis $devis, string $date, int $ordre, string $type, string $heureDebut, string $heureFin, int $dureeMin): void
    {
        Poste::create([
            'devis_id' => $devis->id,
            'date' => $date,
            'ordre' => $ordre,
            'type' => $type,
            'heure_debut' => $heureDebut,
            'heure_fin' => $heureFin,
            'duree_min' => $dureeMin,
            'taux' => $type === 'attente' ? 50 : 100,
            'origine' => 'correction_reglementaire',
            'auteur' => Auth::user()?->name ?? 'secrétariat',
            'date_modif' => now(),
        ]);
    }

    protected function normaliserHeurePoste(mixed $heure, string $defaut): string
    {
        if ($heure instanceof \DateTimeInterface) {
            return $heure->format('H:i');
        }

        $heure = substr(trim((string) $heure), 0, 5);

        return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $heure) === 1 ? $heure : $defaut;
    }

    protected function ajouterMinutesHeure(string $heure, int $minutes): string
    {
        return Carbon::createFromFormat('H:i', $heure)->addMinutes($minutes)->format('H:i');
    }

    protected function conduiteMaxDansFenetre(int $minutesDisponibles): int
    {
        $pauseSeuil = (int) Parametre::get('rse_conduite_avant_pause_min', 270);
        $pauseMinutes = (int) Parametre::get('rse_duree_pause_min', 45);
        for ($conduite = min(540, $minutesDisponibles); $conduite > 0; $conduite--) {
            $nombrePauses = $conduite <= $pauseSeuil ? 0 : (int) floor(($conduite - 1) / $pauseSeuil);
            if ($conduite + ($nombrePauses * $pauseMinutes) <= $minutesDisponibles) {
                return $conduite;
            }
        }

        return 0;
    }

    /** Clic sur « Devis PDF » alors que le devis est encore en brouillon. */
    public function pdfAvantValidation(): void
    {
        $this->flash = null;
        $this->erreur = 'Veuillez valider le devis avant de l’éditer en PDF (bouton « Valider le devis »).';
    }

    public function valider(): void
    {
        $this->changerStatutDevis('valide', 'devis_edite');
    }

    public function envoyer(): void
    {
        $this->changerStatutDevis('envoye', 'envoye');
    }

    public function accepter(): void
    {
        $this->changerStatutDevis('accepte', 'accepte');
    }

    public function refuser(): void
    {
        $this->changerStatutDevis('refuse', 'refuse');
    }

    public function enregistrerEnrichissementLieu(int $etapeId): void
    {
        $etape = $this->demande->etapes->firstWhere('id', $etapeId);
        if (! $etape) {
            return;
        }

        $latitude = $this->lieuLat[$etapeId] ?? $this->lieuVilleLat[$etapeId] ?? $etape->latitude;
        $longitude = $this->lieuLng[$etapeId] ?? $this->lieuVilleLng[$etapeId] ?? $etape->longitude;
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            $this->addError("lieuVille.$etapeId", 'Sélectionnez une ville dans les suggestions avant d’enregistrer.');

            return;
        }

        $this->validate([
            "lieuAcces.$etapeId" => ['nullable', 'string', 'max:2000'],
            "lieuContact.$etapeId" => ['nullable', 'string', 'max:255'],
            "lieuCommentaire.$etapeId" => ['nullable', 'string', 'max:2000'],
            "lieuLibelle.$etapeId" => ['required', 'string', 'max:255'],
            "lieuAdresse.$etapeId" => ['nullable', 'string', 'max:255'],
        ]);

        $ville = ($this->lieuVille[$etapeId] ?? null);
        $villeLat = ($this->lieuVilleLat[$etapeId] ?? null);
        $villeLng = ($this->lieuVilleLng[$etapeId] ?? null);
        $citycode = ($this->lieuCitycode[$etapeId] ?? null);
        $ancienneVille = trim((string) ($etape->ville ?: $etape->commune?->nom));
        $ancienneAdresse = trim((string) ($etape->adresse_normalisee ?: $etape->adresse));
        $nouvelleVille = trim((string) ($ville ?: $ancienneVille));
        $nouvelleAdresse = trim((string) ($this->lieuAdresse[$etapeId] ?? ''));
        $destinationModifiee = abs((float) $etape->latitude - (float) $latitude) > 0.000001
            || abs((float) $etape->longitude - (float) $longitude) > 0.000001
            || $ancienneVille !== $nouvelleVille
            || $ancienneAdresse !== $nouvelleAdresse;

        $etape->update([
            'commune_id' => ! empty($citycode) ? Commune::where('code_insee', $citycode)->value('id') : $etape->commune_id,
            'ville' => $ville ?: ($etape->ville ?: ($etape->commune?->nom ?? null)),
            'adresse' => $this->lieuAdresse[$etapeId] ?: null,
            'lieu_libelle' => $this->lieuLibelle[$etapeId],
            'adresse_normalisee' => $this->lieuAdresse[$etapeId] ?: null,
            'geocodage_source' => ($this->lieuGeocodageSource[$etapeId] ?? null) ?: $etape->geocodage_source,
            'geocodage_provider_id' => ($this->lieuGeocodageProviderId[$etapeId] ?? null) ?: $etape->geocodage_provider_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'lieu_acces' => $this->lieuAcces[$etapeId] ?? null,
            'lieu_contact' => $this->lieuContact[$etapeId] ?? null,
            'lieu_commentaire' => $this->lieuCommentaire[$etapeId] ?? null,
        ]);
        $this->alimenterCarnet($etape);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);

        if (! $destinationModifiee) {
            $devis = $this->devisActuel();
            if ($devis && $devis->cout_revient_ht > 0) {
                $this->synchroniserPlanningApresModificationEtape($devis);
                $this->flash = 'Informations du lieu enregistrées. Horaires, postes et coûts synchronisés.';
                $this->dispatch('lieu-enregistre', icon: 'success', title: 'Lieu enregistré', text: 'Les horaires, les postes et les coûts ont été synchronisés avec les étapes.');
            } else {
                $this->flash = 'Informations du lieu enregistrées.';
                $this->dispatch('lieu-enregistre', icon: 'success', title: 'Lieu enregistré', text: 'Les informations du lieu ont bien été enregistrées.');
            }

            return;
        }

        $devis = $this->devisActuel();
        if (! $devis || ! $devis->vehicule_id) {
            $this->flash = 'Destination enregistrée.';
            $this->erreur = 'Aucun véhicule n’est affecté : le devis et ses horaires seront recalculés après affectation.';
            $this->dispatch('lieu-enregistre', icon: 'warning', title: 'Destination enregistrée', text: $this->erreur);

            return;
        }

        $champsOverrides = [
            'distance_km', 'distance_km_charge', 'distance_km_vide', 'duree_conduite_minutes', 'temps_attente_minutes',
            'cout_carburant', 'cout_peage', 'cout_vignettes', 'cout_chauffeur', 'cout_charges_fixes', 'cout_charges_variables',
        ];
        $champsOrigineOverride = array_map(fn (string $champ) => 'original_'.$champ, $champsOverrides);
        $etatOverrideAvant = array_intersect_key(
            $devis->getAttributes(),
            array_flip([...$champsOverrides, ...$champsOrigineOverride, 'override_author', 'override_at'])
        );
        $anciensPostes = $devis->postes()->get()->map(fn (Poste $poste) => $poste->getAttributes())->all();

        $devis->postes()->delete();
        $devis->update(array_merge(
            array_fill_keys($champsOrigineOverride, null),
            ['override_author' => null, 'override_at' => null]
        ));
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);

        $this->calculer(app(MoteurCalcul::class));
        if ($this->erreur) {
            $devis = $this->devisActuel();
            if ($devis) {
                DB::table('postes')->insert($anciensPostes);
                $devis->update($etatOverrideAvant);
                $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
            }
            $this->flash = 'Destination enregistrée, mais le recalcul du devis a échoué.';
            $this->dispatch('lieu-enregistre', icon: 'warning', title: 'Destination enregistrée', text: $this->erreur.' Les anciens postes et surcharges ont été conservés.');

            return;
        }

        $devis = $this->devisActuel();
        $grille = $devis?->calcul_payload['rse']['grille_journaliere'] ?? [];
        if ($devis && $grille !== []) {
            $this->completerHeuresArriveeSelonVitesseBus();
            $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
            app(MoteurCalcul::class)->recalculerRsePourDevis($devis);
            $devis->refresh();
            $grille = $devis->calcul_payload['rse']['grille_journaliere'] ?? [];
            $this->corrigerHorairesManquantsPourReglementation($devis, $grille);
            app(MoteurCalcul::class)->recalculerRsePourDevis($devis);
            app(MoteurCalcul::class)->recalculerCoutsPourDevis($devis);
            $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        }

        $this->flash = 'Destination enregistrée. Itinéraire, prix et horaires du devis recalculés.';
        $this->erreur = null;
        $this->dispatch('lieu-enregistre', icon: 'success', title: 'Destination enregistrée', text: 'L’itinéraire, les prix et les horaires du devis ont été recalculés.');
    }

    protected function alimenterCarnet(?Etape $seule = null): void
    {
        $etapes = $seule ? collect([$seule]) : $this->demande->etapes;
        foreach ($etapes as $etape) {
            if ($etape->latitude === null || $etape->longitude === null || ! $etape->lieu_libelle) {
                continue;
            }
            $lieu = Lieu::query()
                ->when($etape->geocodage_source && $etape->geocodage_provider_id, fn ($q) => $q
                    ->where('source', $etape->geocodage_source)->where('provider_id', $etape->geocodage_provider_id))
                ->where('libelle', $etape->lieu_libelle)
                ->first();
            $lieu ??= new Lieu;
            $lieu->fill([
                'libelle' => $etape->lieu_libelle,
                'adresse_normalisee' => $etape->adresse_normalisee ?: $etape->adresse,
                'ville' => $etape->ville,
                'code_postal' => null,
                'latitude' => $etape->latitude,
                'longitude' => $etape->longitude,
                'source' => $etape->geocodage_source ?: 'manuel',
                'provider_id' => $etape->geocodage_provider_id,
                'acces' => $etape->lieu_acces,
                'contact' => $etape->lieu_contact,
                'commentaire' => $etape->lieu_commentaire,
                'utilisations' => ((int) $lieu->utilisations) + 1,
            ])->save();
        }
    }

    public function render()
    {
        [$debut, $fin] = $this->fenetre();
        $devis = $this->devisActuel();

        return view('livewire.admin.demande-show', [
            'devis' => $devis,
            'fenetre' => [$debut, $fin],
            'resume_rse' => $this->resume_rse,
            'detail_temps_service' => $this->detail_temps_service,
        ]);
    }
}
