<?php

namespace Tests\Feature;

use App\Livewire\Admin\DemandeShow;
use App\Models\Demande;
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
}
