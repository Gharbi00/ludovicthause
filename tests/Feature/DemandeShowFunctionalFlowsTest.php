<?php

namespace Tests\Feature;

use App\Livewire\Admin\DemandeShow;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Etape;
use App\Models\Poste;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemandeShowFunctionalFlowsTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaire;

    private Categorie $categorie;

    private Vehicule $vehicule;

    private Commune $paris;

    private Commune $lyon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretaire = User::factory()->create([
            'name' => 'Secrétaire de test',
            'role' => 'secretaire',
            'must_change_password' => false,
        ]);
        $this->categorie = Categorie::create([
            'libelle' => 'Autocar QA',
            'capacite' => 50,
            'gabarit' => 'standard',
            'actif' => true,
        ]);
        $this->vehicule = Vehicule::create([
            'immatriculation' => 'QA-'.now()->format('His'),
            'categorie_id' => $this->categorie->id,
            'nb_places' => 50,
            'conso_l_100km' => 25,
        ]);
        $this->paris = Commune::create([
            'code_insee' => '75056',
            'nom' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
        $this->lyon = Commune::create([
            'code_insee' => '69123',
            'nom' => 'Lyon',
            'latitude' => 45.7640,
            'longitude' => 4.8357,
        ]);
    }

    public function test_01_secretary_opens_request_and_assigns_vehicle(): void
    {
        $demande = $this->creerDemande(1);

        $this->actingAs($this->secretaire)
            ->get(route('admin.demande', $demande))
            ->assertOk()
            ->assertSee('Voyage demandé')
            ->assertSee('Itinéraire');

        Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->set('vehicule_id', $this->vehicule->id)
            ->call('affecter')
            ->assertSet('flash', 'Véhicule affecté à la demande '.$demande->reference.'.');

        $this->assertDatabaseHas('devis', [
            'demande_id' => $demande->id,
            'vehicule_id' => $this->vehicule->id,
        ]);
    }

    public function test_02_changing_step_date_invalidates_the_old_rse_grid(): void
    {
        ['demande' => $demande, 'devis' => $devis, 'arrivee' => $arrivee] = $this->creerDemandeCalculee(2, 240);

        Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->call('changerDateEtape', $arrivee->id)
            ->set('dateEdition.'.$arrivee->id, '2026-11-11')
            ->call('appliquerDateEtape', $arrivee->id)
            ->assertSet('erreur', null)
            ->assertSee('relancez le calcul du devis');

        $this->assertSame('2026-11-11', $arrivee->fresh()->date->toDateString());
        $this->assertSame([], $devis->fresh()->calcul_payload['rse']);
    }

    public function test_03_saving_location_details_reconciles_arrival_and_posts(): void
    {
        ['demande' => $demande, 'devis' => $devis, 'depart' => $depart, 'arrivee' => $arrivee] = $this->creerDemandeCalculee(3, 240, '10:00');

        Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->set('lieuContact.'.$depart->id, 'Accueil QA')
            ->call('enregistrerEnrichissementLieu', $depart->id)
            ->assertDispatched('lieu-enregistre');

        $this->assertSame('12:00', $arrivee->fresh()->heure_arrivee);
        $this->assertSame('12:00', $devis->postes()->where('type', 'fin_service')->firstOrFail()->heure_fin->format('H:i'));
        $this->assertLessThanOrEqual(
            $arrivee->fresh()->date->toDateString(),
            $devis->postes()->get()->max('date')->toDateString()
        );
    }

    public function test_04_saving_an_impossible_manual_arrival_moves_it_to_the_route_minimum(): void
    {
        ['demande' => $demande, 'arrivee' => $arrivee] = $this->creerDemandeCalculee(4, 240, '09:00');

        Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->call('modifierHorairesEtape', $arrivee->id)
            ->set('horairesEdition.'.$arrivee->id.'.heure_arrivee', '09:00')
            ->call('enregistrerHorairesEtape', $arrivee->id)
            ->assertSet('erreur', null);

        $this->assertSame('12:00', $arrivee->fresh()->heure_arrivee);
    }

    public function test_05_regulatory_fix_creates_a_minimum_legal_multiday_schedule(): void
    {
        ['demande' => $demande, 'devis' => $devis, 'arrivee' => $arrivee] = $this->creerDemandeCalculee(5, 1100, '08:30');

        Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->call('corrigerReglementation')
            ->assertSet('erreur', null);

        $this->assertGreaterThan('2026-11-10', $arrivee->fresh()->date->toDateString());
        $this->assertGreaterThan(0, $devis->postes()->where('type', 'conduite')->sum('duree_min'));
        $this->assertSame(
            $arrivee->fresh()->date->toDateString(),
            $devis->postes()->get()->max('date')->toDateString()
        );
        $this->assertSame(
            $arrivee->fresh()->heure_arrivee,
            $devis->postes()->where('type', 'fin_service')->orderByDesc('date')->firstOrFail()->heure_fin->format('H:i')
        );
    }

    public function test_06_service_post_can_be_added_edited_and_deleted(): void
    {
        ['demande' => $demande, 'devis' => $devis] = $this->creerDemandeCalculee(6, 360);

        $component = Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->call('ouvrirAjoutPoste', '2026-11-10')
            ->set('poste_type', 'conduite')
            ->set('poste_heure_debut', '08:00')
            ->set('poste_heure_fin', '08:30')
            ->set('poste_taux', 100)
            ->call('ajouterPoste')
            ->assertSet('flash', 'Poste ajouté.');

        $poste = $devis->postes()->where('origine', 'saisie_manuelle')->firstOrFail();
        $component->call('modifierPoste', $poste->id)
            ->set('poste_heure_fin', '08:45')
            ->call('enregistrerModificationPoste')
            ->assertSet('flash', 'Poste de service modifié.');
        $this->assertSame(45, (int) $poste->fresh()->duree_min);

        $component->call('supprimerPoste', $poste->id)
            ->assertSet('flash', 'Poste supprimé.');
        $this->assertDatabaseMissing('postes', ['id' => $poste->id]);
    }

    public function test_07_manual_distance_override_can_be_reset_to_calculated_value(): void
    {
        ['demande' => $demande, 'devis' => $devis] = $this->creerDemandeCalculee(7, 240);

        $component = Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->set('edit_distance_km', 200)
            ->set('override_raison', 'Contrôle QA')
            ->call('appliquerOverride')
            ->assertSet('flash', 'Surcharges appliquées; devis et réglementation recalculés.');

        $this->assertSame(200.0, (float) $devis->fresh()->distance_km);
        $this->assertSame(150.0, (float) $devis->fresh()->original_distance_km);

        $component->call('reinitialiserOverride')
            ->assertSet('flash', 'Valeurs réinitialisées aux valeurs calculées.');
        $this->assertSame(150.0, (float) $devis->fresh()->distance_km);
        $this->assertNull($devis->fresh()->original_distance_km);
    }

    public function test_08_validated_quote_can_be_marked_as_sent(): void
    {
        ['demande' => $demande, 'devis' => $devis] = $this->creerDemandeCalculee(8, 240);
        $devis->update(['statut' => 'valide']);

        Livewire::actingAs($this->secretaire)
            ->test(DemandeShow::class, ['demande' => $demande])
            ->call('envoyer')
            ->assertSet('flash', 'Devis : envoye.');

        $this->assertSame('envoye', $devis->fresh()->statut);
        $this->assertSame('envoye', $demande->fresh()->statut);
    }

    private function creerDemande(int $numero): Demande
    {
        $demande = Demande::create([
            'reference' => 'QA-DEM-'.str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
            'mode' => 'estimation',
            'type_trajet' => 'simple',
            'categorie_id' => $this->categorie->id,
            'nb_passagers' => 20,
            'nature_prestation' => 'Transfert',
            'client_nom' => 'Demande QA '.$numero,
            'client_email' => 'qa-'.$numero.'@example.test',
            'statut' => 'nouvelle',
        ]);
        $demande->etapes()->createMany([
            [
                'ordre' => 1,
                'commune_id' => $this->paris->id,
                'ville' => 'Paris',
                'adresse' => 'Paris',
                'latitude' => 48.8566,
                'longitude' => 2.3522,
                'date' => '2026-11-10',
                'heure_depart' => '08:00',
            ],
            [
                'ordre' => 2,
                'commune_id' => $this->lyon->id,
                'ville' => 'Lyon',
                'adresse' => 'Lyon',
                'latitude' => 45.7640,
                'longitude' => 4.8357,
                'date' => '2026-11-10',
                'heure_arrivee' => '10:00',
            ],
        ]);

        return $demande;
    }

    private function creerDemandeCalculee(int $numero, int $dureeChargee, string $heureArrivee = '12:00'): array
    {
        $demande = $this->creerDemande($numero);
        $etapes = $demande->etapes()->orderBy('ordre')->get();
        $depart = $etapes[0];
        $arrivee = $etapes[1];
        $arrivee->update(['heure_arrivee' => $heureArrivee]);
        $distanceChargee = 100.0;
        $distanceVide = 50.0;
        $devis = Devis::create([
            'reference' => 'QA-DEV-'.str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
            'demande_id' => $demande->id,
            'vehicule_id' => $this->vehicule->id,
            'distance_km' => $distanceChargee + $distanceVide,
            'distance_km_charge' => $distanceChargee,
            'distance_km_vide' => $distanceVide,
            'duree_conduite_minutes' => $dureeChargee + 90,
            'temps_attente_minutes' => 0,
            'nb_chauffeurs' => $dureeChargee > 600 ? 2 : 1,
            'nb_nuitees' => 0,
            'cout_carburant' => 100,
            'cout_peage' => 20,
            'cout_vignettes' => 0,
            'cout_chauffeur' => 150,
            'cout_charges_fixes' => 30,
            'cout_charges_variables' => 25,
            'cout_revient_ht' => 325,
            'marge_taux' => 20,
            'taux_tva' => 10,
            'statut' => 'brouillon',
            'calcul_payload' => [
                'source_itineraire' => 'ors',
                'itineraire' => [
                    'segments' => [
                        ['distance_km' => 25, 'duree_minutes' => 90, 'charge' => false],
                        ['distance_km' => $distanceChargee, 'duree_minutes' => $dureeChargee, 'charge' => true],
                        ['distance_km' => $distanceVide, 'duree_minutes' => 90, 'charge' => false],
                    ],
                    'duree_conduite_chargee_minutes' => $dureeChargee,
                    'duree_par_troncon_etape' => [$depart->id.':'.$arrivee->id => $dureeChargee],
                ],
                'rse' => ['grille_journaliere' => [[
                    'date' => '2026-11-10',
                    'conduite_min' => $dureeChargee,
                    'attente_min' => 0,
                    'tte_min' => $dureeChargee,
                    'heures_100_min' => $dureeChargee,
                    'heures_50_min' => 0,
                    'amplitude_min' => null,
                    'pause_reglementaire_manquante' => false,
                    'relais_necessaire' => $dureeChargee > 600,
                    'statut' => 'Horaires incomplets',
                ]]],
            ],
        ]);

        return compact('demande', 'devis', 'depart', 'arrivee');
    }
}