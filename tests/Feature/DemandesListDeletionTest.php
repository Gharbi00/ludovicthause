<?php

namespace Tests\Feature;

use App\Livewire\Admin\DemandesList;
use App\Models\ApiUsage;
use App\Models\Categorie;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Poste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemandesListDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected Categorie $categorie;

    protected User $secretaire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categorie = Categorie::create([
            'libelle' => 'Autocar de test',
            'capacite' => 50,
            'gabarit' => 'standard',
            'actif' => true,
        ]);

        $this->secretaire = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
    }

    protected function creerDemande(int $numero): Demande
    {
        return Demande::create([
            'reference' => 'DEM-SUP-'.$numero,
            'mode' => 'estimation',
            'type_trajet' => 'simple',
            'categorie_id' => $this->categorie->id,
            'nb_passagers' => 10,
            'nature_prestation' => 'Transfert',
            'client_nom' => 'Client '.$numero,
            'client_email' => 'client'.$numero.'@example.test',
            'statut' => 'nouvelle',
        ]);
    }

    public function test_suppression_individuelle_efface_les_devis_et_conserve_les_traces_api_detachees(): void
    {
        $demande = $this->creerDemande(1);
        $devis = Devis::create([
            'reference' => 'DEV-SUP-1',
            'demande_id' => $demande->id,
        ]);
        $poste = Poste::create([
            'devis_id' => $devis->id,
            'date' => '2026-10-01',
            'ordre' => 1,
            'type' => 'conduite',
            'taux' => 100,
            'origine' => 'calculee',
        ]);
        $usage = ApiUsage::create([
            'provider' => 'ors',
            'devis_id' => $devis->id,
        ]);

        Livewire::actingAs($this->secretaire)
            ->test(DemandesList::class)
            ->call('supprimerDemande', $demande->id)
            ->assertSet('flash', 'Demande supprimée.');

        $this->assertDatabaseMissing('demandes', ['id' => $demande->id]);
        $this->assertDatabaseMissing('devis', ['id' => $devis->id]);
        $this->assertDatabaseMissing('postes', ['id' => $poste->id]);
        $this->assertDatabaseHas('api_usages', ['id' => $usage->id, 'devis_id' => null]);
    }

    public function test_suppression_groupee_ne_supprime_que_les_demandes_selectionnees(): void
    {
        $selectionnee = $this->creerDemande(1);
        $conservee = $this->creerDemande(2);

        Livewire::actingAs($this->secretaire)
            ->test(DemandesList::class)
            ->set('demandesSelectionnees', [$selectionnee->id])
            ->call('supprimerSelection')
            ->assertSet('flash', 'Demande supprimée.');

        $this->assertDatabaseMissing('demandes', ['id' => $selectionnee->id]);
        $this->assertDatabaseHas('demandes', ['id' => $conservee->id]);
    }

    public function test_selectionner_toute_la_page_ne_supprime_que_les_15_lignes_affichees(): void
    {
        $demandes = collect(range(1, 16))->map(fn (int $numero) => $this->creerDemande($numero));

        Livewire::actingAs($this->secretaire)
            ->test(DemandesList::class)
            ->call('toggleSelectionPage')
            ->call('supprimerSelection')
            ->assertSet('flash', '15 demandes supprimées.');

        $this->assertSame(1, Demande::count());
        $this->assertDatabaseHas('demandes', ['id' => $demandes->last()->id]);
    }
}