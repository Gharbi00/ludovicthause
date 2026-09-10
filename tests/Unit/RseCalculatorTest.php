<?php

namespace Tests\Unit;

use App\Models\Demande;
use App\Models\Devis;
use App\Models\Parametre;
use App\Models\Poste;
use App\Services\Rse\RseCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
