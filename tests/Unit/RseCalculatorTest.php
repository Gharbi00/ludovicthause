<?php

namespace Tests\Unit;

use App\Livewire\Admin\DemandeShow;
use App\Livewire\DemandeForm;
use App\Models\Categorie;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Etape;
use App\Models\Lieu;
use App\Models\Parametre;
use App\Models\Poste;
use App\Models\Vehicule;
use App\Services\Geocodage;
use App\Services\Itineraire\ItineraireProvider;
use App\Services\MoteurCalcul;
use App\Services\Rse\RseCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RseCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Parametre::create([
            'cle' => 'rse_conduite_journaliere_max_min',
            'valeur' => '540',
            'type' => 'integer',
            'groupe' => 'rse',
            'libelle' => 'Conduite journalière max (min) — 9h',
        ]);
    }

    public function test_un_seul_jour_court_ne_declenche_pas_de_second_chauffeur(): void
    {
        $calc = new RseCalculator;
        $config = $calc->calculer(300, Carbon::now()->startOfDay(), Carbon::now()->endOfDay(), []);

        $this->assertSame(1, $config->nbChauffeurs);
        $this->assertSame(0, $config->nbNuitees);
        $this->assertSame(1, $config->nbJours);
    }

    public function test_dix_heures_de_conduite_autorisees_ne_declenchent_pas_de_relais(): void
    {
        $calc = new RseCalculator;
        $config = $calc->calculer(600, Carbon::now()->startOfDay(), Carbon::now()->endOfDay(), []);

        $this->assertSame(1, $config->nbChauffeurs);

        $depassement = $calc->calculer(601, Carbon::now()->startOfDay(), Carbon::now()->endOfDay(), []);
        $this->assertSame(2, $depassement->nbChauffeurs);
    }

    public function test_pause_reglementaire_fractionnee_15_plus_30_est_acceptee(): void
    {
        $date = now()->addDay()->toDateString();
        $poste = fn (string $type, string $debut, string $fin, int $duree) => Poste::make([
            'date' => $date,
            'type' => $type,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'duree_min' => $duree,
        ]);
        $calc = new RseCalculator;

        $sansPause = $calc->calculer(300, Carbon::parse($date), Carbon::parse($date), [
            $poste('conduite', '08:00', '13:00', 300),
        ]);
        $this->assertTrue($sansPause->details['grille_journaliere'][0]['pause_reglementaire_manquante']);

        $avecPauseFractionnee = $calc->calculer(540, Carbon::parse($date), Carbon::parse($date), [
            $poste('conduite', '08:00', '12:30', 270),
            $poste('pause', '12:30', '12:45', 15),
            $poste('conduite', '12:45', '17:15', 270),
            $poste('pause', '17:15', '17:45', 30),
        ]);
        $this->assertFalse($avecPauseFractionnee->details['grille_journaliere'][0]['pause_reglementaire_manquante']);
    }

    public function test_tte_maximum_journalier_est_borne_a_onze_heures(): void
    {
        $date = Carbon::parse('2026-10-12');
        $posteAttente = fn (int $minutes) => Poste::make([
            'date' => $date,
            'type' => 'attente',
            'heure_debut' => '08:00',
            'heure_fin' => '08:00',
            'duree_min' => $minutes,
        ]);
        $calc = new RseCalculator;

        $limite = $calc->calculer(0, $date, $date, [$posteAttente(1320)]);
        $depassement = $calc->calculer(0, $date, $date, [$posteAttente(1322)]);

        $this->assertSame(660, $limite->details['grille_journaliere'][0]['tte_min']);
        $this->assertFalse($limite->details['grille_journaliere'][0]['relais_necessaire']);
        $this->assertSame(661, $depassement->details['grille_journaliere'][0]['tte_min']);
        $this->assertTrue($depassement->details['grille_journaliere'][0]['relais_necessaire']);
    }

    public function test_limites_56_heures_par_semaine_et_90_heures_sur_deux_semaines(): void
    {
        $dateDebut = Carbon::parse('2026-10-05')->startOfDay();
        $dateFin = $dateDebut->copy()->addDays(13);
        $postes = collect(range(0, 13))->map(function (int $index) use ($dateDebut) {
            $date = $dateDebut->copy()->addDays($index);

            return Poste::make([
                'date' => $date,
                'type' => 'conduite',
                'heure_debut' => '08:00',
                'heure_fin' => '14:30',
                'duree_min' => 390,
            ]);
        });

        $config = (new RseCalculator)->calculer(5460, $dateDebut, $dateFin, $postes);
        $grille = $config->details['grille_journaliere'];

        $this->assertSame(14, count($grille));
        $this->assertFalse($grille[0]['depassement_semaine']);
        $this->assertTrue($grille[7]['depassement_deux_semaines']);
    }

    public function test_modification_poste_recalcule_les_heures_et_la_duree(): void
    {
        $date = now()->addDay()->toDateString();
        $demande = Demande::create([
            'reference' => 'DEM-POSTE-EDIT-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->createMany([
            ['ordre' => 1, 'date' => $date, 'ville' => 'Paris'],
            ['ordre' => 2, 'date' => $date, 'ville' => 'Lyon'],
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-POSTE-EDIT-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'duree_conduite_minutes' => 300,
            'temps_attente_minutes' => 0,
            'calcul_payload' => ['rse' => ['grille_journaliere' => []]],
        ]);
        $poste = Poste::create([
            'devis_id' => $devis->id,
            'date' => $date,
            'ordre' => 1,
            'type' => 'conduite',
            'heure_debut' => '08:00',
            'heure_fin' => '13:00',
            'duree_min' => 300,
            'taux' => 100,
            'origine' => 'saisie_manuelle',
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes']);
        $component->modifierPoste($poste->id);
        $component->poste_heure_debut = '09:15';
        $component->poste_heure_fin = '13:45';
        $component->enregistrerModificationPoste();

        $posteModifie = $poste->fresh();
        $this->assertSame('09:15', $posteModifie->heure_debut->format('H:i'));
        $this->assertSame('13:45', $posteModifie->heure_fin->format('H:i'));
        $this->assertSame(270, $posteModifie->duree_min);
    }

    public function test_appliquer_surcharge_recalcule_les_couts_et_le_rse(): void
    {
        $date = now()->addDay()->toDateString();
        $categorie = Categorie::create([
            'libelle' => 'Autocar test',
            'capacite' => 50,
            'gabarit' => 'standard',
            'actif' => true,
        ]);
        $vehicule = Vehicule::create([
            'immatriculation' => 'TEST-OVERRIDE',
            'categorie_id' => $categorie->id,
            'nb_places' => 50,
            'conso_l_100km' => 20,
            'cout_entretien_km' => 0.1,
        ]);
        $demande = Demande::create([
            'reference' => 'DEM-OVERRIDE-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->createMany([
            ['ordre' => 1, 'date' => $date, 'ville' => 'Paris'],
            ['ordre' => 2, 'date' => $date, 'ville' => 'Lyon'],
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'vehicule_id' => $vehicule->id,
            'reference' => 'DEV-OVERRIDE-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'distance_km' => 100,
            'distance_km_charge' => 80,
            'distance_km_vide' => 20,
            'duree_conduite_minutes' => 300,
            'temps_attente_minutes' => 0,
            'cout_carburant' => 34,
            'cout_charges_variables' => 10,
            'cout_revient_ht' => 44,
            'calcul_payload' => ['rse' => ['grille_journaliere' => []]],
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes']);
        $component->edit_distance_km = 50;
        $component->appliquerOverride();

        $devisActualise = $devis->fresh();
        $this->assertSame(100.0, (float) $devisActualise->original_distance_km);
        $this->assertSame(50.0, (float) $devisActualise->distance_km);
        $this->assertSame(17.0, (float) $devisActualise->cout_carburant);
        $this->assertNotEmpty($devisActualise->calcul_payload['rse']['grille_journaliere']);
        $this->assertStringContainsString('recalculés', $component->flash);
    }

    public function test_prise_et_fin_de_service_sont_refusees_hors_des_extremites_du_trajet(): void
    {
        $dateDebut = now()->addDay()->toDateString();
        $dateIntermediaire = now()->addDays(2)->toDateString();
        $dateFin = now()->addDays(3)->toDateString();
        $demande = Demande::create([
            'reference' => 'DEM-POSTE-EXT-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->createMany([
            ['ordre' => 1, 'date' => $dateDebut, 'ville' => 'Paris'],
            ['ordre' => 2, 'date' => $dateIntermediaire, 'ville' => 'Nevers'],
            ['ordre' => 3, 'date' => $dateFin, 'ville' => 'Lyon'],
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-POSTE-EXT-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'duree_conduite_minutes' => 240,
            'temps_attente_minutes' => 0,
            'calcul_payload' => ['rse' => ['grille_journaliere' => []]],
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes']);
        $component->ouvrirAjoutPoste($dateIntermediaire);
        $component->poste_type = 'prise_service';
        $component->poste_heure_debut = '08:00';
        $component->poste_heure_fin = '08:00';
        $component->ajouterPoste();

        $this->assertStringContainsString('uniquement le premier jour', $component->erreur);
        $this->assertDatabaseMissing('postes', [
            'devis_id' => $devis->id,
            'date' => $dateIntermediaire,
            'type' => 'prise_service',
        ]);

        $component->fermerAjoutPoste();
        $component->ouvrirAjoutPoste($dateIntermediaire);
        $component->poste_type = 'fin_service';
        $component->poste_heure_debut = '17:00';
        $component->poste_heure_fin = '17:00';
        $component->ajouterPoste();

        $this->assertStringContainsString('uniquement le dernier jour', $component->erreur);
        $this->assertDatabaseMissing('postes', [
            'devis_id' => $devis->id,
            'date' => $dateIntermediaire,
            'type' => 'fin_service',
        ]);
    }

    public function test_amplitude_max_declenche_relais(): void
    {
        $calc = new RseCalculator;
        $demande = Demande::create([
            'reference' => 'DEM-TEST-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'nouvelle',
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-TEST-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
        ]);
        Poste::create([
            'devis_id' => $devis->id,
            'date' => now()->startOfDay(),
            'ordre' => 1,
            'type' => 'prise_service',
            'heure_debut' => '06:00',
            'heure_fin' => null,
            'duree_min' => null,
            'taux' => 100,
            'origine' => 'saisie_manuelle',
            'auteur' => 'test',
            'date_modif' => now(),
        ]);
        Poste::create([
            'devis_id' => $devis->id,
            'date' => now()->startOfDay(),
            'ordre' => 2,
            'type' => 'fin_service',
            'heure_debut' => null,
            'heure_fin' => '23:00',
            'duree_min' => null,
            'taux' => 100,
            'origine' => 'saisie_manuelle',
            'auteur' => 'test',
            'date_modif' => now(),
        ]);
        $postes = Poste::where('devis_id', $devis->id)->get();
        $config = $calc->calculer(300, Carbon::now()->startOfDay(), Carbon::now()->endOfDay(), $postes);

        $this->assertSame(2, $config->nbChauffeurs);
        $this->assertTrue($config->details['relais_necessaire']);
    }

    public function test_grille_journaliere_contient_les_totaux(): void
    {
        $calc = new RseCalculator;
        $config = $calc->calculer(600, Carbon::now()->startOfDay(), Carbon::now()->addDay()->endOfDay(), []);

        $grille = $config->details['grille_journaliere'];
        $this->assertCount(2, $grille);

        $total100 = array_sum(array_column($grille, 'heures_100_min'));
        $this->assertSame(600, $total100);
    }

    public function test_reglementation_invalide_affiche_une_alerte_sans_bloquer_le_devis(): void
    {
        $demande = Demande::create([
            'reference' => 'DEM-RSE-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'nouvelle',
        ]);

        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-RSE-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'cout_revient_ht' => 1500,
            'calcul_payload' => [
                'rse' => [
                    'grille_journaliere' => [[
                        'date' => now()->toDateString(),
                        'amplitude_min' => 781,
                        'tte_min' => 901,
                        'heures_100_min' => 541,
                        'heures_50_min' => 10,
                        'relais_necessaire' => true,
                        'statut' => 'Contrôle effectué',
                    ]],
                ],
            ],
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load('devis');

        $this->assertFalse($component->reglementationEstValide());
        $this->assertNotEmpty($component->reglementationAlertes);
        $this->assertStringContainsString('Amplitude', $component->reglementationAlertes[0]);

        $component->changerStatutDevis('valide', 'devis_edite');

        $this->assertNull($component->erreur);
        $this->assertSame('valide', $devis->fresh()->statut);
        $this->assertSame('devis_edite', $demande->fresh()->statut);
    }

    public function test_corriger_reglementation_remplit_les_horaires_incomplets_avec_un_minimum_valide(): void
    {
        $date = now()->addDay()->toDateString();
        $demande = Demande::create([
            'reference' => 'DEM-CORR-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);

        $demande->etapes()->create([
            'ordre' => 1,
            'date' => $date,
            'heure_arrivee' => null,
            'heure_depart' => null,
            'ville' => 'Paris',
            'adresse' => 'Paris',
        ]);

        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-CORR-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'cout_revient_ht' => 1200,
            'duree_conduite_minutes' => 420,
            'temps_attente_minutes' => 0,
            'calcul_payload' => [
                'rse' => [
                    'grille_journaliere' => [[
                        'date' => $date,
                        'amplitude_min' => null,
                        'tte_min' => 0,
                        'heures_100_min' => 0,
                        'heures_50_min' => 0,
                        'relais_necessaire' => false,
                        'statut' => 'Horaires incomplets',
                    ]],
                ],
            ],
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes']);
        $component->corrigerReglementation();

        $this->assertDatabaseHas('postes', [
            'devis_id' => $devis->id,
            'type' => 'prise_service',
            'heure_debut' => '08:00',
        ]);
        $this->assertDatabaseHas('postes', [
            'devis_id' => $devis->id,
            'type' => 'fin_service',
            'heure_fin' => '15:45',
        ]);
        $this->assertStringNotContainsString('Horaires incomplets', json_encode($devis->fresh()->calcul_payload['rse']['grille_journaliere'] ?? []));
    }

    public function test_corriger_reglementation_remplit_aussi_les_jours_sans_etape(): void
    {
        $dateDebut = now()->addDays(1)->toDateString();
        $dateMilieu = now()->addDays(2)->toDateString();
        $dateFin = now()->addDays(3)->toDateString();
        $demande = Demande::create([
            'reference' => 'DEM-CORR-MULTI-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);

        $demande->etapes()->createMany([
            ['ordre' => 1, 'date' => $dateDebut, 'ville' => 'Paris', 'adresse' => 'Paris', 'heure_depart' => '07:30'],
            ['ordre' => 2, 'date' => $dateFin, 'ville' => 'Lyon', 'adresse' => 'Lyon', 'heure_arrivee' => '16:45'],
        ]);

        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-CORR-MULTI-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'cout_revient_ht' => 1200,
            'duree_conduite_minutes' => 180,
            'temps_attente_minutes' => 90,
            'calcul_payload' => [
                'rse' => [
                    'grille_journaliere' => collect([$dateDebut, $dateMilieu, $dateFin])->map(fn ($date) => [
                        'date' => $date,
                        'amplitude_min' => null,
                        'tte_min' => 60,
                        'heures_100_min' => 60,
                        'heures_50_min' => 0,
                        'relais_necessaire' => false,
                        'statut' => 'Horaires incomplets',
                    ])->all(),
                ],
            ],
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes']);
        $component->corrigerReglementation();
        $component->corrigerReglementation();

        $posteIntermediaire = Poste::query()
            ->where('devis_id', $devis->id)
            ->whereDate('date', $dateMilieu)
            ->where('type', 'conduite')
            ->first();
        $this->assertNotNull($posteIntermediaire);
        $this->assertDatabaseMissing('postes', [
            'devis_id' => $devis->id,
            'date' => $dateMilieu,
            'type' => 'prise_service',
        ]);
        $this->assertDatabaseMissing('postes', [
            'devis_id' => $devis->id,
            'date' => $dateMilieu,
            'type' => 'fin_service',
        ]);
        $postesCorrection = Poste::query()->where('devis_id', $devis->id)->where('origine', 'correction_reglementaire');
        $this->assertSame(180, (int) (clone $postesCorrection)->where('type', 'conduite')->sum('duree_min'));
        $this->assertSame(90, (int) (clone $postesCorrection)->where('type', 'attente')->sum('duree_min'));
        $this->assertSame(3, (clone $postesCorrection)->where('type', 'conduite')->count());
        $priseService = (clone $postesCorrection)->whereDate('date', $dateDebut)->where('type', 'prise_service')->first();
        $finService = (clone $postesCorrection)->whereDate('date', $dateFin)->where('type', 'fin_service')->first();
        $this->assertSame('07:30', $priseService->heure_debut->format('H:i'));
        $this->assertSame('16:45', $finService->heure_fin->format('H:i'));
        $this->assertSame(180, $devis->fresh()->duree_conduite_minutes);
        $grille = $devis->fresh()->calcul_payload['rse']['grille_journaliere'];
        $this->assertCount(3, $grille);
        $this->assertSame([], array_values(array_filter($grille, fn ($jour) => $jour['statut'] === 'Horaires incomplets')));
        foreach (array_slice($grille, 1) as $jour) {
            $this->assertGreaterThanOrEqual(660, $jour['repos_precedent_min']);
        }
        $this->assertSame([], $component->reglementationAlertes);
    }

    public function test_correction_planifie_un_repos_hebdomadaire_apres_six_jours(): void
    {
        $dateDebut = Carbon::parse('2026-10-05')->startOfDay();
        $dates = collect(range(0, 7))->map(fn (int $jour) => $dateDebut->copy()->addDays($jour)->toDateString());
        $demande = Demande::create([
            'reference' => 'DEM-REPOS-HEBDO-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        foreach ($dates as $index => $date) {
            if ($index !== 6) {
                $demande->etapes()->create([
                    'ordre' => $index + 1,
                    'date' => $date,
                    'ville' => 'Ville '.$index,
                ]);
            }
        }

        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-REPOS-HEBDO-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'cout_revient_ht' => 1200,
            'duree_conduite_minutes' => 420,
            'temps_attente_minutes' => 0,
            'calcul_payload' => [
                'rse' => [
                    'grille_journaliere' => $dates->map(fn (string $date) => [
                        'date' => $date,
                        'amplitude_min' => null,
                        'tte_min' => 60,
                        'heures_100_min' => 60,
                        'heures_50_min' => 0,
                        'relais_necessaire' => false,
                        'statut' => 'Horaires incomplets',
                    ])->all(),
                ],
            ],
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes']);
        $component->corrigerReglementation();

        $grille = $devis->fresh()->calcul_payload['rse']['grille_journaliere'];
        $this->assertSame('Repos', $grille[6]['statut']);
        $this->assertGreaterThanOrEqual(2700, $grille[7]['repos_hebdomadaire_precedent_min']);
        $this->assertSame(1, Poste::where('devis_id', $devis->id)->where('type', 'prise_service')->count());
        $this->assertSame(1, Poste::where('devis_id', $devis->id)->where('type', 'fin_service')->count());
        $this->assertSame([], $component->reglementationAlertes);
    }

    public function test_fixation_complete_les_arrivees_a_80_km_h_sans_ecraser_les_horaires_saisis(): void
    {
        $date = '2026-10-20';
        $demande = Demande::create([
            'reference' => 'DEM-ARRIVEE-80-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->createMany([
            ['ordre' => 1, 'date' => $date, 'ville' => 'Varennes-Vauzelles', 'latitude' => 47.004, 'longitude' => 3.140],
            ['ordre' => 2, 'date' => $date, 'ville' => 'Paris', 'latitude' => 48.8566, 'longitude' => 2.3522, 'heure_arrivee' => '12:00'],
            ['ordre' => 3, 'date' => $date, 'ville' => 'Paris', 'latitude' => 48.8566, 'longitude' => 2.3522, 'heure_depart' => '19:00'],
            ['ordre' => 4, 'date' => $date, 'ville' => 'Varennes-Vauzelles', 'latitude' => 47.004, 'longitude' => 3.140],
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-ARRIVEE-80-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'cout_revient_ht' => 1200,
            'duree_conduite_minutes' => 400,
            'temps_attente_minutes' => 0,
            'calcul_payload' => [
                'rse' => [
                    'grille_journaliere' => [[
                        'date' => $date,
                        'amplitude_min' => null,
                        'tte_min' => 400,
                        'heures_100_min' => 400,
                        'heures_50_min' => 0,
                        'relais_necessaire' => false,
                        'statut' => 'Horaires incomplets',
                    ]],
                ],
            ],
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes.commune']);
        $component->corrigerReglementation();

        $etapes = $demande->fresh()->etapes()->orderBy('ordre')->get();
        $this->assertNotNull($etapes[0]->heure_depart);
        $this->assertLessThan('12:00', $etapes[0]->heure_depart);
        $this->assertSame('12:00', substr((string) $etapes[1]->heure_arrivee, 0, 5));
        $this->assertSame('19:00', substr((string) $etapes[2]->heure_depart, 0, 5));
        $this->assertNotNull($etapes[3]->heure_arrivee);
        $this->assertGreaterThan('19:00', substr((string) $etapes[3]->heure_arrivee, 0, 5));
        $this->assertSame(substr((string) $etapes[1]->heure_arrivee, 0, 5), substr((string) $etapes[1]->heure_depart, 0, 5));
        $this->assertDatabaseHas('postes', ['devis_id' => $devis->id, 'type' => 'pause', 'duree_min' => 45]);
    }

    public function test_fixer_replanifie_une_conduite_manuelle_superieure_a_dix_heures(): void
    {
        $date = '2026-09-08';
        $demande = Demande::create([
            'reference' => 'DEM-FIX-918-'.now()->format('YmdHis'),
            'mode' => 'estimation',
            'type_trajet' => 'simple',
            'nb_passagers' => 30,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'devis_edite',
        ]);
        $etapes = $demande->etapes()->createMany([
            ['ordre' => 1, 'date' => $date, 'heure_depart' => '08:00', 'ville' => 'Paris', 'latitude' => 48.8566, 'longitude' => 2.3522],
            ['ordre' => 2, 'date' => $date, 'heure_arrivee' => '14:53', 'ville' => 'Lyon', 'latitude' => 45.7580, 'longitude' => 4.8351],
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-FIX-918-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'cout_revient_ht' => 1200,
            'duree_conduite_minutes' => 923,
            'temps_attente_minutes' => 0,
            'calcul_payload' => [
                'itineraire' => [
                    'duree_conduite_chargee_minutes' => 923,
                    'duree_par_troncon_etape' => [$etapes[0]->id.':'.$etapes[1]->id => 923],
                ],
                'rse' => ['grille_journaliere' => [[
                    'date' => $date,
                    'amplitude_min' => 413,
                    'tte_min' => 918,
                    'heures_100_min' => 918,
                    'heures_50_min' => 0,
                    'relais_necessaire' => true,
                    'statut' => 'Contrôle effectué',
                ]]],
            ],
        ]);
        $ancienPoste = Poste::create([
            'devis_id' => $devis->id,
            'date' => $date,
            'ordre' => 1,
            'type' => 'conduite',
            'heure_debut' => '00:00',
            'heure_fin' => '15:18',
            'duree_min' => 918,
            'taux' => 100,
            'origine' => 'saisie_manuelle',
        ]);

        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes.commune']);
        $component->corrigerReglementation();

        $postes = Poste::query()->where('devis_id', $devis->id)->get();
        $conduiteParJour = $postes->where('type', 'conduite')->groupBy(fn (Poste $poste) => Carbon::parse($poste->date)->toDateString());
        $this->assertDatabaseMissing('postes', ['id' => $ancienPoste->id]);
        $this->assertSame(923, (int) $postes->where('type', 'conduite')->sum('duree_min'));
        $this->assertCount(1, $conduiteParJour);
        $this->assertSame(923, (int) $conduiteParJour->get($date)->sum('duree_min'));
        $this->assertSame(2, (int) $devis->fresh()->nb_chauffeurs);
        $this->assertLessThanOrEqual(270, (int) $conduiteParJour->get($date)->max('duree_min'));
        $this->assertSame('23:23', $demande->fresh()->etapes()->reorder()->orderByDesc('ordre')->first()->heure_arrivee);

        $grille = $devis->fresh()->calcul_payload['rse']['grille_journaliere'];
        $this->assertCount(1, $grille);
        $this->assertSame([], $component->reglementationAlertes);
    }

    public function test_secretariat_peut_saisir_arrivee_et_depart_sur_une_etape_sans_horaires(): void
    {
        $date = now()->addDay()->toDateString();
        $demande = Demande::create([
            'reference' => 'DEM-EDIT-HEURES-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->createMany([
            ['ordre' => 1, 'date' => $date, 'ville' => 'Paris'],
            ['ordre' => 2, 'date' => $date, 'ville' => 'Lyon'],
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-EDIT-HEURES-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'duree_conduite_minutes' => 240,
            'temps_attente_minutes' => 0,
            'calcul_payload' => ['rse' => ['grille_journaliere' => []]],
        ]);

        $etape = $demande->etapes()->where('ordre', 2)->firstOrFail();
        $component = new DemandeShow;
        $component->demande = $demande->load(['devis', 'etapes']);
        $component->modifierHorairesEtape($etape->id);
        $component->horairesEdition[$etape->id] = ['heure_arrivee' => '10:00', 'heure_depart' => '10:30'];
        $component->enregistrerHorairesEtape($etape->id);

        $etapeActualisee = Etape::findOrFail($etape->id);
        $this->assertSame('10:00', substr((string) $etapeActualisee->heure_arrivee, 0, 5));
        $this->assertSame('10:30', substr((string) $etapeActualisee->heure_depart, 0, 5));
        $this->assertSame([], $component->getErrorBag()->all());
        $this->assertNotEmpty($devis->fresh()->calcul_payload['rse']['grille_journaliere']);
    }

    public function test_date_annee_aberrante_declenche_erreur_protectrice(): void
    {
        $calc = new RseCalculator;

        // Année 0020 au lieu de 2026 : le calcul doit refuser au lieu d'exploser.
        $this->expectException(\RuntimeException::class);
        $calc->calculer(300, Carbon::create(20, 10, 10, 8), Carbon::create(2026, 10, 10, 18), []);
    }

    public function test_ecart_dates_trop_grand_declenche_erreur_protectrice(): void
    {
        $calc = new RseCalculator;

        $this->expectException(\RuntimeException::class);
        $calc->calculer(300, Carbon::now()->startOfDay(), Carbon::now()->addYears(2)->startOfDay(), []);
    }

    public function test_moteur_calcul_refuse_une_fenetre_de_dates_aberrante(): void
    {
        $rse = new RseCalculator;
        $itineraire = $this->createMock(ItineraireProvider::class);
        $moteur = new MoteurCalcul($itineraire, $rse);

        $ref = new \ReflectionMethod($moteur, 'validerFenetreDates');
        $ref->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $ref->invoke($moteur, Carbon::create(20, 10, 10, 8), Carbon::create(2026, 10, 10, 18));
    }

    public function test_demande_form_refuse_une_date_avec_annee_aberrante(): void
    {
        $form = new DemandeForm;
        $form->mode = 'estimation';
        $form->nature_prestation = 'transfert';
        $form->client_nom = 'Test';
        $form->client_email = 'test@test.com';
        $form->consentement_rgpd = true;
        $form->etapes = [
            ['date' => '10/10/0020'],
            ['date' => '10/10/2026'],
        ];

        $form->ouvrirRecap();

        // ouvrirRecap attrape l'exception en interne : on vérifie l'état du composant.
        $this->assertFalse($form->showRecap, 'Le récapitulatif ne doit pas s’ouvrir avec une date d’année 0020.');
        $this->assertStringContainsString('année 20', collect($form->getErrorBag()->all())->implode(' '));
    }

    public function test_dates_etapes_invalides_sont_signalees(): void
    {
        $demande = Demande::create([
            'reference' => 'DEM-DATE-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->create(['ordre' => 1, 'date' => '0020-10-10', 'ville' => 'Paris', 'adresse' => 'Paris']);
        $demande->etapes()->create(['ordre' => 2, 'date' => '2026-10-10', 'ville' => 'Lyon', 'adresse' => 'Lyon']);

        $component = new DemandeShow;
        $component->demande = $demande->load('etapes');

        $invalides = $component->etapesDatesInvalides;

        $this->assertCount(1, $invalides);
        $this->assertSame('10/10/0020', $invalides[0]['date']);
    }

    public function test_date_etape_peut_etre_corrigee_dans_le_secretariat(): void
    {
        $demande = Demande::create([
            'reference' => 'DEM-CORR-DATE-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $etape = $demande->etapes()->create(['ordre' => 1, 'date' => '0020-10-10', 'ville' => 'Paris', 'adresse' => 'Paris']);

        $component = new DemandeShow;
        $component->demande = $demande->load(['etapes', 'devis']);

        $component->changerDateEtape($etape->id);
        $component->dateEdition[$etape->id] = '2026-10-10';
        $component->appliquerDateEtape($etape->id);

        $this->assertSame('2026-10-10', $etape->fresh()->date->format('Y-m-d'));
        $this->assertSame([], $component->etapesDatesInvalides);

        Livewire::test(DemandeShow::class, ['demande' => $demande->fresh()])
            ->call('changerDateEtape', $etape->id)
            ->assertSeeHtml('type="date"');
    }

    public function test_l_alerte_dates_non_conformes_est_affichee_dans_la_page(): void
    {
        $demande = Demande::create([
            'reference' => 'DEM-ALERTE-'.now()->format('YmdHis'),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->create([
            'ordre' => 1, 'date' => '0020-10-10', 'ville' => 'Nevers',
            'adresse' => '13 Rue de la Raie, 58300 Decize', 'latitude' => 46.83, 'longitude' => 3.45,
        ]);
        $demande->etapes()->create([
            'ordre' => 2, 'date' => '2026-10-10', 'ville' => 'Paris',
            'adresse' => 'Rue des Petits Champs, 75001 Paris', 'latitude' => 48.86, 'longitude' => 2.33,
        ]);

        Livewire::test(DemandeShow::class, ['demande' => $demande])
            ->assertSee('non conformes')
            ->assertSee('10/10/0020');
    }

    public function test_retour_meme_itineraire_ne_remplit_pas_les_heures_automatiquement(): void
    {
        $form = new DemandeForm;
        $form->etapes = [
            ['date' => '2026-09-15', 'heure_depart' => '08:00', 'ville' => 'Paris 2e arrondissement (75002)',
                'latitude' => 48.86, 'longitude' => 2.35, 'adresse' => 'Paris', 'adresse_validee' => true, 'citycode' => '75102'],
            ['date' => '2026-09-15', 'heure_arrivee' => '12:00', 'ville' => 'Lyon 2e arrondissement (69002)',
                'latitude' => 45.76, 'longitude' => 4.83, 'adresse' => 'Lyon', 'adresse_validee' => true, 'citycode' => '69382'],
        ];

        $form->retour_type = 'meme';
        $form->updatedRetourType();

        $this->assertNotEmpty($form->etapes_retour);
        $dernier = array_key_last($form->etapes_retour);
        $this->assertSame('', (string) ($form->etapes_retour[0]['heure_depart'] ?? ''));
        $this->assertSame('', (string) ($form->etapes_retour[$dernier]['heure_arrivee'] ?? ''));
    }

    public function test_heure_arrivee_doit_etre_strictement_posterieure_au_depart(): void
    {
        $form = $this->formulaireValide();
        $form->etapes = [
            ['date' => '2026-09-15', 'heure_depart' => '14:00', 'ville' => 'Paris', 'latitude' => 48.86,
                'longitude' => 2.35, 'adresse' => 'Paris', 'adresse_validee' => true, 'citycode' => '75056'],
            ['date' => '2026-09-15', 'heure_arrivee' => '12:00', 'ville' => 'Lyon', 'latitude' => 45.76,
                'longitude' => 4.83, 'adresse' => 'Lyon', 'adresse_validee' => true, 'citycode' => '69123'],
        ];

        $form->ouvrirRecap();

        $this->assertFalse($form->showRecap);
        $this->assertStringContainsString('strictement postérieure', collect($form->getErrorBag()->all())->implode(' '));
    }

    public function test_depart_retour_doit_etre_strictement_posterieur_au_depart_aller(): void
    {
        $form = $this->formulaireValide();
        $form->retour_type = 'meme';
        $form->etapes = [
            ['date' => '2026-09-15', 'heure_depart' => '14:00', 'ville' => 'Paris', 'latitude' => 48.86,
                'longitude' => 2.35, 'adresse' => 'Paris', 'adresse_validee' => true, 'citycode' => '75056'],
            ['date' => '2026-09-15', 'heure_arrivee' => '18:00', 'ville' => 'Lyon', 'latitude' => 45.76,
                'longitude' => 4.83, 'adresse' => 'Lyon', 'adresse_validee' => true, 'citycode' => '69123'],
        ];
        $form->updatedRetourType();

        // Le retour ne doit pas être pré-rempli : on saisit un départ ANTÉRIEUR au départ aller.
        $dernier = array_key_last($form->etapes_retour);
        $form->etapes_retour[0]['heure_depart'] = '13:00';
        $form->etapes_retour[$dernier]['heure_arrivee'] = '19:00';

        $form->ouvrirRecap();

        $this->assertFalse($form->showRecap);
        $this->assertStringContainsString('de départ du retour', collect($form->getErrorBag()->all())->implode(' '));
    }

    public function test_horaires_chronologiques_valides_ouvrent_le_recapitulatif(): void
    {
        $form = $this->formulaireValide();
        $form->retour_type = 'meme';
        $form->etapes = [
            ['date' => '2026-09-15', 'heure_depart' => '08:00', 'ville' => 'Paris', 'latitude' => 48.86,
                'longitude' => 2.35, 'adresse' => 'Paris', 'adresse_validee' => true, 'citycode' => '75056'],
            ['date' => '2026-09-15', 'heure_arrivee' => '12:00', 'ville' => 'Lyon', 'latitude' => 45.76,
                'longitude' => 4.83, 'adresse' => 'Lyon', 'adresse_validee' => true, 'citycode' => '69123'],
        ];
        $form->updatedRetourType();
        $dernier = array_key_last($form->etapes_retour);
        $form->etapes_retour[0]['heure_depart'] = '15:00';
        $form->etapes_retour[$dernier]['heure_arrivee'] = '19:00';

        $form->ouvrirRecap();

        $this->assertTrue($form->showRecap, 'Des horaires cohérents doivent ouvrir le récapitulatif.');
    }

    /** Le carnet ne doit proposer que des lieux de la ville sélectionnée. */
    public function test_carnet_filtre_les_adresses_incoherentes_avec_la_ville(): void
    {
        Lieu::create(['libelle' => 'Carnet LTT', 'adresse_normalisee' => 'Rue Tony Tollet, 69002 Lyon, France',
            'ville' => 'Lyon 2e arrondissement', 'code_postal' => '69002', 'latitude' => 45.7536,
            'longitude' => 4.8357, 'source' => 'manuel', 'utilisations' => 10]);
        Lieu::create(['libelle' => 'Carnet LTT', 'adresse_normalisee' => 'Rue de la Republique, 69005 Lyon, France',
            'ville' => 'Lyon 5e arrondissement', 'code_postal' => '69005', 'latitude' => 45.7578,
            'longitude' => 4.8085, 'source' => 'manuel', 'utilisations' => 5]);

        $geo = app(Geocodage::class);
        $resultats = $geo->rechercherAdresses('Rue', [
            'citycode' => '69385',
            'ville' => 'Lyon 5e arrondissement (69005)',
            'lat' => 45.7578,
            'lng' => 4.8085,
        ]);

        $labels = collect($resultats)->pluck('label')->implode(' | ');
        $this->assertStringContainsString('69005', $labels);
        $this->assertStringNotContainsString('69002', $labels, 'Une adresse du 2e arrondissement ne doit pas être proposée pour le 5e.');
    }

    /** Les adresses BAN sont restreintes par code postal / ville cohérente. */
    public function test_ban_ecarte_les_adresses_hors_commune(): void
    {
        Http::fake([
            'api-adresse.data.gouv.fr/*' => Http::response(['features' => [
                ['geometry' => ['coordinates' => [4.8085, 45.7578]],
                    'properties' => ['label' => 'Rue Tony Tollet, 69005 Lyon, France', 'city' => 'Lyon', 'postcode' => '69005', 'citycode' => '69385']],
                ['geometry' => ['coordinates' => [4.8357, 45.7536]],
                    'properties' => ['label' => 'Rue Tony Tollet, 69002 Lyon, France', 'city' => 'Lyon', 'postcode' => '69002', 'citycode' => '69382']],
                ['geometry' => ['coordinates' => [2.35, 48.86]],
                    'properties' => ['label' => 'Rue Tony Tollet, 75002 Paris, France', 'city' => 'Paris', 'postcode' => '75002', 'citycode' => '75102']],
            ]]),
            'photon.komoot.io/*' => Http::response(['features' => []]),
        ]);

        $resultats = app(Geocodage::class)->rechercherAdresses('Rue Tony Tollet', [
            'citycode' => '69385',
            'ville' => 'Lyon 5e arrondissement (69005)',
            'lat' => 45.7578,
            'lng' => 4.8085,
        ]);

        $labels = collect($resultats)->pluck('label')->all();
        $this->assertNotEmpty($labels);
        foreach ($labels as $label) {
            $this->assertStringContainsString('69005', $label, "L'adresse « {$label} » n'appartient pas au 5e arrondissement.");
        }
    }

    /** Le formulaire de demande propose des adresses cohérentes avec la ville choisie. */
    public function test_formulaire_filtre_les_suggestions_d_adresse_par_ville(): void
    {
        Lieu::create(['libelle' => 'Carnet LTT', 'adresse_normalisee' => 'Rue Tony Tollet, 69002 Lyon, France',
            'ville' => 'Lyon 2e arrondissement', 'code_postal' => '69002', 'latitude' => 45.7536,
            'longitude' => 4.8357, 'source' => 'manuel', 'utilisations' => 10]);
        Lieu::create(['libelle' => 'Carnet LTT', 'adresse_normalisee' => 'Rue Tony Tollet, 69005 Lyon, France',
            'ville' => 'Lyon 5e arrondissement', 'code_postal' => '69005', 'latitude' => 45.7578,
            'longitude' => 4.8085, 'source' => 'manuel', 'utilisations' => 5]);

        Http::fake(['*' => Http::response(['features' => []])]);

        $form = $this->formulaireValide();
        $form->etapes = [
            ['ville' => 'Lyon 5e arrondissement (69005)', 'citycode' => '69385',
                'ville_lat' => 45.7578, 'ville_lng' => 4.8085, 'adresse' => '', 'adresse_validee' => false],
        ];

        $form->updated('etapes.0.adresse_recherche', 'Rue Tony');

        $labels = collect($form->suggestionsAdresse[0] ?? [])->pluck('label')->implode(' | ');
        $this->assertStringContainsString('69005', $labels);
        $this->assertStringNotContainsString('69002', $labels);
    }

    /** Les suggestions d'adresse du secrétariat sont aussi filtrées par la ville de l'étape. */
    public function test_secretariat_filtre_les_suggestions_d_adresse_par_ville(): void
    {
        Lieu::create(['libelle' => 'Carnet LTT', 'adresse_normalisee' => 'Rue Tony Tollet, 69002 Lyon, France',
            'ville' => 'Lyon 2e arrondissement', 'code_postal' => '69002', 'latitude' => 45.7536,
            'longitude' => 4.8357, 'source' => 'manuel', 'utilisations' => 10]);
        Lieu::create(['libelle' => 'Carnet LTT', 'adresse_normalisee' => 'Rue Tony Tollet, 69005 Lyon, France',
            'ville' => 'Lyon 5e arrondissement', 'code_postal' => '69005', 'latitude' => 45.7578,
            'longitude' => 4.8085, 'source' => 'manuel', 'utilisations' => 5]);

        Http::fake(['*' => Http::response(['features' => []])]);

        $demande = $this->demandeAvecEtapeVille('Lyon 5e arrondissement (69005)');
        $etapeId = $demande->etapes()->first()->id;

        $test = Livewire::test(DemandeShow::class, ['demande' => $demande]);
        $test->set("lieuVilleLat.{$etapeId}", 45.7578)
            ->set("lieuVilleLng.{$etapeId}", 4.8085)
            ->set("lieuCitycode.{$etapeId}", '69385')
            ->set("adresseRecherche.{$etapeId}", 'Rue Tony');

        $labels = collect($test->get("suggestionsAdresse.{$etapeId}") ?? [])->pluck('label')->implode(' | ');
        $this->assertStringContainsString('69005', $labels);
        $this->assertStringNotContainsString('69002', $labels);
    }

    /** Un lieu du carnet situé dans un autre arrondissement est écarté (même sans code postal). */
    public function test_carnet_ecarte_un_autre_arrondissement_meme_sans_code_postal(): void
    {
        Lieu::create(['libelle' => 'Carnet LTT', 'adresse_normalisee' => 'Lieu Lyon 2e',
            'ville' => 'Lyon 2e arrondissement', 'code_postal' => null, 'latitude' => 45.7536,
            'longitude' => 4.8357, 'source' => 'manuel', 'utilisations' => 20]);

        Http::fake(['*' => Http::response(['features' => []])]);

        $resultats = app(Geocodage::class)->rechercherAdresses('Lieu Lyon', [
            'citycode' => '69385',
            'ville' => 'Lyon 5e arrondissement (69005)',
            'lat' => 45.7578,
            'lng' => 4.8085,
        ]);

        $this->assertSame([], $resultats, 'Un lieu du 2e arrondissement ne doit pas être proposé pour le 5e.');
    }

    /** Un code postal « commune » (…000) laisse passer toutes les adresses de la ville. */
    public function test_code_postal_de_commune_accepte_toute_la_ville(): void
    {
        Http::fake([
            'api-adresse.data.gouv.fr/*' => Http::response(['features' => [
                ['geometry' => ['coordinates' => [3.16, 46.99]],
                    'properties' => ['label' => 'Rue du Commerce, 58000 Nevers, France', 'city' => 'Nevers', 'postcode' => '58000', 'citycode' => '58194']],
            ]]),
            'photon.komoot.io/*' => Http::response(['features' => []]),
        ]);

        $resultats = app(Geocodage::class)->rechercherAdresses('Rue du Commerce', [
            'citycode' => '58194',
            'ville' => 'Nevers (58000)',
            'lat' => 46.9896,
            'lng' => 3.1596,
        ]);

        $this->assertCount(1, $resultats);
        $this->assertStringContainsString('58000', $resultats[0]['label']);
    }

    /** Demande minimale avec une étape portant la ville indiquée. */
    protected function demandeAvecEtapeVille(string $ville): Demande
    {
        $demande = Demande::create([
            'reference' => 'DEM-VILLE-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'mode' => 'ferme',
            'type_trajet' => 'simple',
            'nb_passagers' => 1,
            'client_nom' => 'Test',
            'client_email' => 'test@test.com',
            'statut' => 'en_traitement',
        ]);
        $demande->etapes()->create([
            'ordre' => 1, 'date' => '2026-10-10', 'ville' => $ville,
            'adresse' => '', 'latitude' => 45.7578, 'longitude' => 4.8085,
        ]);

        return $demande->fresh('etapes');
    }

    /** Formulaire minimal valide (mode estimation) pour tester les contrôles. */
    protected function formulaireValide(): DemandeForm
    {
        $form = new DemandeForm;
        $form->mode = 'estimation';
        $form->nature_prestation = 'transfert';
        $form->client_nom = 'Test';
        $form->client_email = 'test@test.com';
        $form->consentement_rgpd = true;

        return $form;
    }
}
