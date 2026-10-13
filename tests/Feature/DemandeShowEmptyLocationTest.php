<?php

namespace Tests\Feature;

use App\Livewire\Admin\DemandeShow;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Parametre;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemandeShowEmptyLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_peut_completer_une_etape_sans_destination(): void
    {
        $demande = Demande::create([
            'reference' => 'DEM-LIEU-'.now()->format('YmdHis'),
            'mode' => 'estimation',
            'type_trajet' => 'simple',
            'nb_passagers' => 10,
            'nature_prestation' => 'Transfert',
            'client_nom' => 'Client test',
            'client_email' => 'client@example.test',
            'statut' => 'nouvelle',
        ]);
        $etape = $demande->etapes()->create([
            'ordre' => 1,
            'date' => '2026-10-10',
        ]);

        Livewire::test(DemandeShow::class, ['demande' => $demande])
            ->assertSee('Destination à compléter')
            ->assertSee('Aucune ville')
            ->call('enregistrerEnrichissementLieu', $etape->id)
            ->assertHasErrors("lieuVille.{$etape->id}")
            ->call('changerVilleEtape', $etape->id)
            ->set("suggestionsVille.{$etape->id}", [[
                'label' => 'Paris (75001)',
                'lat' => 48.8566,
                'lng' => 2.3522,
                'citycode' => '75056',
                'source' => 'ban',
                'provider_id' => '75056-test',
            ]])
            ->call('appliquerVilleEtape', $etape->id, 0)
            ->assertSet("lieuLibelle.{$etape->id}", 'Paris (75001)')
            ->call('enregistrerEnrichissementLieu', $etape->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('etapes', [
            'id' => $etape->id,
            'ville' => 'Paris (75001)',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
    }

    public function test_enregistrement_infos_lieu_synchronise_aussi_les_horaires_et_postes_deja_calcules(): void
    {
        $demande = Demande::create([
            'reference' => 'DEM-LIEU-PLANNING-'.now()->format('YmdHis'),
            'mode' => 'estimation',
            'type_trajet' => 'simple',
            'nb_passagers' => 10,
            'client_nom' => 'Client test',
            'client_email' => 'client@example.test',
            'statut' => 'en_traitement',
        ]);
        $etapeDepart = $demande->etapes()->create([
            'ordre' => 1,
            'ville' => 'Paris',
            'adresse' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'date' => '2026-10-20',
            'heure_depart' => '08:00',
        ]);
        $etapeArrivee = $demande->etapes()->create([
            'ordre' => 2,
            'ville' => 'Lyon',
            'adresse' => 'Lyon',
            'latitude' => 45.7580,
            'longitude' => 4.8351,
            'date' => '2026-10-20',
            'heure_arrivee' => '12:00',
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'reference' => 'DEV-LIEU-PLANNING-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'duree_conduite_minutes' => 600,
            'cout_revient_ht' => 500,
            'calcul_payload' => [
                'source_itineraire' => 'ors',
                'itineraire' => [
                    'duree_conduite_chargee_minutes' => 600,
                    'duree_par_troncon_etape' => [$etapeDepart->id.':'.$etapeArrivee->id => 600],
                ],
                'rse' => ['grille_journaliere' => [[
                    'date' => '2026-10-20',
                    'amplitude_min' => null,
                    'tte_min' => 600,
                    'heures_100_min' => 600,
                    'heures_50_min' => 0,
                    'relais_necessaire' => false,
                    'statut' => 'Horaires incomplets',
                ]]],
            ],
        ]);
        $posteObsolete = $devis->postes()->create([
            'date' => '2026-10-22',
            'ordre' => 1,
            'type' => 'conduite',
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
            'duree_min' => 60,
            'taux' => 100,
            'origine' => 'correction_reglementaire',
        ]);

        Livewire::test(DemandeShow::class, ['demande' => $demande])
            ->set("lieuAcces.{$etapeDepart->id}", 'Accès principal')
            ->call('enregistrerEnrichissementLieu', $etapeDepart->id)
            ->assertDispatched('lieu-enregistre');

        $arriveeFinale = $etapeArrivee->fresh();
        $derniereDatePoste = $devis->postes()->get()->max('date')->toDateString();
        $this->assertSame('2026-10-21', $arriveeFinale->date->toDateString());
        $this->assertSame('09:00', $arriveeFinale->heure_arrivee);
        $this->assertDatabaseMissing('postes', ['id' => $posteObsolete->id]);
        $this->assertLessThanOrEqual(
            $arriveeFinale->date->toDateString(),
            $derniereDatePoste,
            'Les postes doivent être reconstruits dans la fenêtre des destinations.'
        );
        $this->assertGreaterThan(0, $devis->postes()->where('type', 'conduite')->sum('duree_min'));
    }

    public function test_changement_destination_recalcule_le_devis_et_reconstruit_la_rse(): void
    {
        $depot = Commune::create([
            'code_insee' => '58303',
            'nom' => 'Varennes-Vauzelles',
            'latitude' => 47.0040,
            'longitude' => 3.1400,
        ]);
        $paris = Commune::create([
            'code_insee' => '75056',
            'nom' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
        $lille = Commune::create([
            'code_insee' => '59350',
            'nom' => 'Lille',
            'latitude' => 50.6292,
            'longitude' => 3.0573,
        ]);
        $nice = Commune::create([
            'code_insee' => '06088',
            'nom' => 'Nice',
            'latitude' => 43.7102,
            'longitude' => 7.2620,
        ]);
        $categorie = Categorie::create([
            'libelle' => 'Autocar de test',
            'capacite' => 50,
            'gabarit' => 'standard',
            'actif' => true,
        ]);
        $vehicule = Vehicule::create([
            'immatriculation' => 'TEST-DESTINATION-34',
            'categorie_id' => $categorie->id,
            'nb_places' => 50,
            'conso_l_100km' => 25,
        ]);
        $demande = Demande::create([
            'reference' => 'DEM-DEST-'.now()->format('YmdHis'),
            'mode' => 'estimation',
            'type_trajet' => 'simple',
            'categorie_id' => $categorie->id,
            'nb_passagers' => 30,
            'nature_prestation' => 'Transfert',
            'client_nom' => 'Client test',
            'client_email' => 'client@example.test',
            'statut' => 'en_traitement',
        ]);
        $etapeDepart = $demande->etapes()->create([
            'ordre' => 1,
            'commune_id' => $paris->id,
            'ville' => 'Paris',
            'adresse' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'date' => '2026-10-20',
            'heure_depart' => '08:00',
        ]);
        $etapeArrivee = $demande->etapes()->create([
            'ordre' => 2,
            'commune_id' => $nice->id,
            'ville' => 'Nice',
            'adresse' => 'Nice',
            'latitude' => 43.7102,
            'longitude' => 7.2620,
            'date' => '2026-10-20',
            'heure_arrivee' => '14:55',
        ]);
        $devis = Devis::create([
            'demande_id' => $demande->id,
            'vehicule_id' => $vehicule->id,
            'reference' => 'DEV-DEST-'.now()->format('YmdHis'),
            'statut' => 'brouillon',
            'distance_km' => 10,
            'duree_conduite_minutes' => 10,
            'cout_revient_ht' => 100,
            'calcul_payload' => ['rse' => ['grille_journaliere' => []]],
        ]);
        Parametre::create([
            'cle' => 'depot_commune_id',
            'valeur' => (string) $depot->id,
            'type' => 'integer',
            'groupe' => 'itineraire',
        ]);

        Livewire::test(DemandeShow::class, ['demande' => $demande])
            ->set("suggestionsVille.{$etapeDepart->id}", [[
                'label' => 'Lille (59000)',
                'lat' => 50.6292,
                'lng' => 3.0573,
                'citycode' => '59350',
                'source' => 'ban',
                'provider_id' => '59350-test',
            ]])
            ->call('appliquerVilleEtape', $etapeDepart->id, 0)
            ->set("lieuAdresse.{$etapeDepart->id}", 'Avenue Le Corbusier, 59777 Lille, France')
            ->set("lieuLibelle.{$etapeDepart->id}", 'Avenue Le Corbusier, 59777 Lille, France')
            ->call('enregistrerEnrichissementLieu', $etapeDepart->id)
            ->assertDispatched('lieu-enregistre')
            ->assertDontSee('Retour au dépôt estimé')
            ->call('corrigerReglementation')
            ->assertSet('erreur', null)
            ->assertSee('Conduite / chauffeur');

        $this->assertDatabaseHas('etapes', [
            'id' => $etapeDepart->id,
            'ville' => 'Lille (59000)',
            'latitude' => 50.6292,
            'longitude' => 3.0573,
        ]);
        $departActualise = $etapeDepart->fresh();
        $postePrise = $devis->postes()->where('type', 'prise_service')->first();
        $this->assertSame(
            substr((string) $departActualise->heure_depart, 0, 5),
            $postePrise?->heure_debut->format('H:i'),
            'Le départ de la première étape doit correspondre à la prise de service.'
        );
        $this->assertGreaterThan(
            '2026-10-20',
            $etapeArrivee->fresh()->date->toDateString(),
            'L’arrivée doit être repoussée lorsque le nouvel itinéraire ne peut pas tenir dans l’horaire saisi.'
        );
        $arriveeActualisee = $etapeArrivee->fresh();
        $derniereDateDestination = $demande->fresh()->etapes()->max('date');
        $derniereDatePoste = $devis->postes()->max('date');
        $this->assertLessThanOrEqual(
            $derniereDateDestination,
            $derniereDatePoste,
            'Aucun poste ne doit dépasser la date de la dernière destination.'
        );
        $heureArrivee = substr((string) $arriveeActualisee->heure_arrivee, 0, 5);
        $arriveeDansUnPosteDeConduite = $devis->postes()
            ->whereDate('date', $arriveeActualisee->date)
            ->where('type', 'conduite')
            ->get()
            ->contains(fn ($poste) => $poste->heure_fin->format('H:i') === $heureArrivee);
        $finServiceALarrivee = $devis->postes()
            ->whereDate('date', $arriveeActualisee->date)
            ->where('type', 'fin_service')
            ->get()
            ->contains(fn ($poste) => $poste->heure_fin->format('H:i') === $heureArrivee);
        $postesArrivee = $devis->postes()
            ->whereDate('date', $arriveeActualisee->date)
            ->where('type', 'conduite')
            ->get()
            ->map(fn ($poste) => $poste->heure_debut->format('H:i').'-'.$poste->heure_fin->format('H:i'))
            ->implode(', ');
        $this->assertTrue(
            $arriveeDansUnPosteDeConduite || $finServiceALarrivee,
            "L’arrivée {$arriveeActualisee->date->toDateString()} à {$heureArrivee} doit correspondre à la fin d’un poste ou du service ({$postesArrivee})."
        );
        $this->assertNotEquals(10.0, (float) $devis->fresh()->distance_km);
        $this->assertGreaterThan(0, $devis->fresh()->duree_conduite_minutes);
        $this->assertNotEmpty($devis->fresh()->calcul_payload['rse']['grille_journaliere']);
        $this->assertGreaterThan(0, $devis->postes()->count());

        $devisCalcule = $devis->fresh();
        $this->assertSame(2, (int) $devisCalcule->nb_chauffeurs);
        $this->assertFalse(collect($devisCalcule->calcul_payload['rse']['grille_journaliere'])
            ->contains(fn (array $jour) => $jour['pause_reglementaire_manquante']));
        $this->assertFalse(collect($devisCalcule->calcul_payload['rse']['grille_journaliere'])
            ->contains(fn (array $jour) => $jour['relais_necessaire']));
        $tauxChauffeur = (float) Parametre::get('taux_horaire_chauffeur', 0);
        $fraisNuitee = (float) Parametre::get('frais_nuitee_chauffeur', 90);
        $coutChauffeurAttendu = $tauxChauffeur
            * (((int) $devisCalcule->duree_conduite_minutes + (int) $devisCalcule->temps_attente_minutes) / 60)
            * max(1, (int) $devisCalcule->nb_chauffeurs)
            + $fraisNuitee * (int) $devisCalcule->nb_nuitees * max(1, (int) $devisCalcule->nb_chauffeurs);
        $this->assertEquals(round($coutChauffeurAttendu, 2), (float) $devisCalcule->cout_chauffeur);
    }
}
