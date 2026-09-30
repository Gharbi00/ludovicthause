<?php

namespace Tests\Unit;

use App\Livewire\Admin\DemandeShow;
use App\Livewire\DemandeForm;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Lieu;
use App\Models\Parametre;
use App\Models\Poste;
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
        $config = $calc->calculer(300, now()->startOfDay(), now()->endOfDay(), []);

        $this->assertSame(1, $config->nbChauffeurs);
        $this->assertSame(0, $config->nbNuitees);
        $this->assertSame(1, $config->nbJours);
    }

    public function test_conduite_excessif_declenche_deux_chauffeurs(): void
    {
        $calc = new RseCalculator;
        $config = $calc->calculer(600, now()->startOfDay(), now()->endOfDay(), []);

        $this->assertSame(2, $config->nbChauffeurs);
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
        $config = $calc->calculer(300, now()->startOfDay(), now()->endOfDay(), $postes);

        $this->assertSame(2, $config->nbChauffeurs);
        $this->assertTrue($config->details['relais_necessaire']);
    }

    public function test_grille_journaliere_contient_les_totaux(): void
    {
        $calc = new RseCalculator;
        $config = $calc->calculer(600, now()->startOfDay(), now()->addDay()->endOfDay(), []);

        $grille = $config->details['grille_journaliere'];
        $this->assertCount(2, $grille);

        $total100 = array_sum(array_column($grille, 'heures_100_min'));
        $this->assertSame(600, $total100);
    }

    public function test_reglementation_invalide_declenche_alerte_et_bloc_le_devis(): void
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

        $this->assertStringContainsString('réglementation sociale', strtolower($component->erreur));
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

        $this->assertNotNull($demande->fresh()->etapes()->first()->heure_depart);
        $this->assertNotNull($demande->fresh()->etapes()->first()->heure_arrivee);
        $this->assertStringNotContainsString('Horaires incomplets', json_encode($devis->fresh()->calcul_payload['rse']['grille_journaliere'] ?? []));
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
        $calc->calculer(300, now()->startOfDay(), now()->addYears(2)->startOfDay(), []);
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
        $component->dateEdition[$etape->id] = '10/10/2026';
        $component->appliquerDateEtape($etape->id);

        $this->assertSame('2026-10-10', $etape->fresh()->date->format('Y-m-d'));
        $this->assertSame([], $component->etapesDatesInvalides);
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
