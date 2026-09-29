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
            $this->lieuLibelle[$etape->id] = (string) ($etape->lieu_libelle ?: $etape->libelle());
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

        $seuilAmplitude = (int) Parametre::get('rse_amplitude_max_min', 780);
        $seuilConduite = (int) Parametre::get('rse_conduite_journaliere_max_min', 540);
        $seuilTte = (int) Parametre::get('rse_tte_max_min', 900);

        return [
            'amplitude' => $amplitudeMax ?: null,
            'tte' => $tteMax ?: null,
            'conduite' => $conduiteMax ?: null,
            'heures100' => $heures100 ?: null,
            'heures50' => $heures50 ?: null,
            'statutAmplitude' => $this->statutIndicateur($amplitudeMax, $seuilAmplitude, 0.85),
            'statutTte' => $this->statutIndicateur($tteMax, $seuilTte, 0.85),
            'statutConduite' => $this->statutIndicateur($conduiteMax, $seuilConduite, 0.85),
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

        $seuilAmplitude = (int) Parametre::get('rse_amplitude_max_min', 780);
        $seuilConduite = (int) Parametre::get('rse_conduite_journaliere_max_min', 540);
        $seuilTte = (int) Parametre::get('rse_tte_max_min', 900);

        $alertes = [];

        foreach ($grille as $jour) {
            $date = Carbon::parse($jour['date'] ?? now())->format('d/m/Y');
            $amplitude = (int) ($jour['amplitude_min'] ?? 0);
            $conduite = (int) ($jour['heures_100_min'] ?? $jour['conduite_min'] ?? 0);
            $tte = (int) ($jour['tte_min'] ?? 0);

            if (($jour['statut'] ?? null) === 'Horaires incomplets') {
                $alertes[] = 'Journée du '.$date.' : horaires incomplets, la réglementation n’est pas vérifiable (renseigner horaires de service).';
            }

            if (($jour['amplitude_min'] ?? null) !== null && $amplitude > $seuilAmplitude) {
                $alertes[] = 'Journée du '.$date.' : Amplitude de '.($this->formatMinutes($amplitude)).' > '.($this->formatMinutes($seuilAmplitude)).' (réduire amplitude).';
            }

            if ($conduite > $seuilConduite) {
                $alertes[] = 'Journée du '.$date.' : Conduite de '.($this->formatMinutes($conduite)).' > '.($this->formatMinutes($seuilConduite)).' (réduire conduite).';
            }

            if ($tte > $seuilTte) {
                $alertes[] = 'Journée du '.$date.' : TTE de '.($this->formatMinutes($tte)).' > '.($this->formatMinutes($seuilTte)).' (réduire temps de service).';
            }

            if (! empty($jour['relais_necessaire'])) {
                $alertes[] = 'Journée du '.$date.' : relais nécessaire / 2 chauffeurs requis selon la réglementation (ajouter second chauffeur).';
            }
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
            $this->recalculerCoutRevient($devis);
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
        foreach (['cout_carburant', 'cout_peage', 'cout_vignettes', 'cout_chauffeur', 'cout_charges_fixes', 'cout_charges_variables'] as $key) {
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
            Journal::enregistrer('Override devis '.$devis->reference, $this->demande->reference);
            $this->flash = 'Valeurs modifiées avec traçabilité.';
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
        $this->editingPosteDate = $date;
        $this->poste_type = 'conduite';
        $this->poste_heure_debut = null;
        $this->poste_heure_fin = null;
        $this->poste_taux = 100;
    }

    public function fermerAjoutPoste(): void
    {
        $this->editingPosteDate = null;
    }

    public function ajouterPoste(): void
    {
        $devis = $this->devisActuel();
        if (! $devis || ! $this->editingPosteDate) {
            return;
        }

        $this->validate([
            'poste_type' => ['required', 'in:prise_service,conduite,attente,fin_service'],
            'poste_heure_debut' => ['sometimes', 'date_format:H:i'],
            'poste_heure_fin' => ['sometimes', 'date_format:H:i'],
            'poste_taux' => ['required', 'integer', 'in:50,100'],
        ]);

        $dureeMin = null;
        if ($this->poste_heure_debut && $this->poste_heure_fin) {
            $d1 = Carbon::createFromFormat('H:i', $this->poste_heure_debut);
            $d2 = Carbon::createFromFormat('H:i', $this->poste_heure_fin);
            $dureeMin = $d1->diffInMinutes($d2);
            if ($dureeMin < 0) {
                $dureeMin += 1440;
            }
        }

        $devisActuel = $this->devisActuel();
        if (! $devisActuel) {
            $this->erreur = 'Aucun devis.';

            return;
        }

        if (in_array($this->poste_type, ['conduite', 'prise_service', 'fin_service'], true) && $dureeMin !== null) {
            $totalConduite = (int) Poste::where('devis_id', $devisActuel->id)
                ->whereIn('type', ['conduite', 'prise_service', 'fin_service'])
                ->sum('duree_min');
            $maxConduite = $devisActuel->duree_conduite_minutes;
            if ($totalConduite + $dureeMin > $maxConduite) {
                $this->erreur = 'La durée de conduite totale ('.round($totalConduite / 60, 1).'h + '.round($dureeMin / 60, 1).'h) dépasse la durée calculée par le routage ('.round($maxConduite / 60, 1).'h).';

                return;
            }
        }

        if ($this->poste_type === 'attente' && $dureeMin !== null) {
            $totalAttente = (int) Poste::where('devis_id', $devisActuel->id)
                ->where('type', 'attente')
                ->sum('duree_min');
            $maxAttente = $devisActuel->temps_attente_minutes;
            if ($totalAttente + $dureeMin > $maxAttente) {
                $this->erreur = 'La durée d\'attente totale ('.round($totalAttente / 60, 1).'h + '.round($dureeMin / 60, 1).'h) dépasse la durée calculée par le routage ('.round($maxAttente / 60, 1).'h).';

                return;
            }
        }

        $ordre = Poste::where('devis_id', $devisActuel->id)->where('date', $this->editingPosteDate)->max('ordre') + 1;

        Poste::create([
            'devis_id' => $devisActuel->id,
            'date' => $this->editingPosteDate,
            'ordre' => $ordre,
            'type' => $this->poste_type,
            'heure_debut' => $this->poste_heure_debut,
            'heure_fin' => $this->poste_heure_fin,
            'duree_min' => $dureeMin,
            'taux' => $this->poste_taux,
            'origine' => 'saisie_manuelle',
            'auteur' => Auth::user()?->name ?? 'secrétariat',
            'date_modif' => now(),
        ]);

        $this->editingPosteDate = null;
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $devis = $this->devisActuel();
        if ($devis) {
            app(MoteurCalcul::class)->recalculerRsePourDevis($devis);
            $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        }
        $this->flash = 'Poste ajouté.';
    }

    public function supprimerPoste(int $posteId): void
    {
        $devis = $this->devisActuel();
        if (! $devis) {
            return;
        }

        $poste = Poste::where('devis_id', $devis->id)->findOrFail($posteId);
        $poste->delete();
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        $devis = $this->devisActuel();
        if ($devis) {
            app(MoteurCalcul::class)->recalculerRsePourDevis($devis);
            $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        }
        $this->flash = 'Poste supprimé.';
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

        if (! $this->reglementationEstValide()) {
            $this->erreur = 'La demande ne peut pas être confirmée tant que la réglementation sociale (RSE) n’est pas conforme. Vérifiez les alertes ci-dessous.';

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

        $grille = $devis->calcul_payload['rse']['grille_journaliere'] ?? [];
        if ($grille === []) {
            $this->erreur = 'Lancez d’abord le calcul du devis pour obtenir les seuils réglementaires.';

            return;
        }

        $seuilAmplitude = (int) Parametre::get('rse_amplitude_max_min', 780);
        $seuilConduite = (int) Parametre::get('rse_conduite_journaliere_max_min', 540);
        $seuilTte = (int) Parametre::get('rse_tte_max_min', 900);

        $this->corrigerHorairesManquantsPourReglementation($devis, $grille);

        $maxConduite = min((int) $devis->duree_conduite_minutes, $seuilConduite * max(1, count($grille)));
        $maxAttente = max(0, min((int) $devis->temps_attente_minutes, max(0, $seuilTte - $maxConduite)));

        $this->edit_duree_conduite_minutes = $maxConduite;
        $this->edit_temps_attente_minutes = $maxAttente;
        $this->override_raison = 'Correction automatique réglementaire';
        $this->appliquerOverride();

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);
        app(MoteurCalcul::class)->recalculerRsePourDevis($devis);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule', 'devis.postes']);

        $this->flash = 'Réglages RSE ramenés au minimum réglementaire.';
        $this->erreur = null;
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

        foreach ($jours as $date) {
            $dateObj = Carbon::parse($date);
            $etapesJour = $this->demande->etapes
                ->filter(fn ($etape) => $etape->date && $etape->date->format('Y-m-d') === $dateObj->toDateString())
                ->sortBy('ordre');

            if ($etapesJour->isEmpty()) {
                continue;
            }

            $premiere = $etapesJour->first();
            $derniere = $etapesJour->last();

            if ($premiere->heure_depart === null) {
                $premiere->heure_depart = '08:00';
            }
            if ($derniere->heure_arrivee === null) {
                $derniere->heure_arrivee = '17:00';
            }
            if ($premiere->date === null) {
                $premiere->date = $dateObj->format('Y-m-d');
            }
            if ($derniere->date === null) {
                $derniere->date = $dateObj->format('Y-m-d');
            }
            $premiere->save();
            $derniere->save();

            $posteTypeMin = ['prise_service', 'fin_service', 'conduite'];
            foreach ($posteTypeMin as $type) {
                $exists = Poste::query()
                    ->where('devis_id', $devis->id)
                    ->where('date', $dateObj->toDateString())
                    ->where('type', $type)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $heureDebut = '08:00';
                $heureFin = '17:00';
                $dureeMin = 540;

                if ($type === 'fin_service') {
                    $heureDebut = '17:00';
                    $heureFin = '17:00';
                    $dureeMin = 0;
                }

                Poste::create([
                    'devis_id' => $devis->id,
                    'date' => $dateObj->toDateString(),
                    'ordre' => 1,
                    'type' => $type,
                    'heure_debut' => $heureDebut,
                    'heure_fin' => $heureFin,
                    'duree_min' => $dureeMin,
                    'taux' => 100,
                    'origine' => 'correction_reglementaire',
                    'auteur' => Auth::user()?->name ?? 'secrétariat',
                    'date_modif' => now(),
                ]);
            }
        }
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

        $etape->update([
            'commune_id' => ! empty($citycode) ? Commune::where('code_insee', $citycode)->value('id') : $etape->commune_id,
            'ville' => $ville ?: ($etape->ville ?: ($etape->commune?->nom ?? null)),
            'adresse' => $this->lieuAdresse[$etapeId] ?: null,
            'lieu_libelle' => $this->lieuLibelle[$etapeId],
            'adresse_normalisee' => $this->lieuAdresse[$etapeId] ?: null,
            'geocodage_source' => ($this->lieuGeocodageSource[$etapeId] ?? null) ?: $etape->geocodage_source,
            'geocodage_provider_id' => ($this->lieuGeocodageProviderId[$etapeId] ?? null) ?: $etape->geocodage_provider_id,
            'latitude' => ($this->lieuLat[$etapeId] ?? null) ?: ($villeLat ?? $etape->latitude),
            'longitude' => ($this->lieuLng[$etapeId] ?? null) ?: ($villeLng ?? $etape->longitude),
            'lieu_acces' => $this->lieuAcces[$etapeId] ?? null,
            'lieu_contact' => $this->lieuContact[$etapeId] ?? null,
            'lieu_commentaire' => $this->lieuCommentaire[$etapeId] ?? null,
        ]);
        $this->alimenterCarnet($etape);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Informations du lieu enregistrées.';
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

        return view('livewire.admin.demande-show', [
            'devis' => $this->devisActuel(),
            'fenetre' => [$debut, $fin],
            'resume_rse' => $this->resume_rse,
            'detail_temps_service' => $this->detail_temps_service,
        ]);
    }
}
