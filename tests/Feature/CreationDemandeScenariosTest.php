<?php

namespace Tests\Feature;

use App\Livewire\DemandeForm;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Demande;
use App\Models\Parametre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 20+ scénarios variés de création d'une demande via le formulaire public (/demande).
 *
 * Chaque scénario décrit le COMPORTEMENT ATTENDU du point de vue métier :
 * un test en échec révèle une faille de la création de demandes.
 */
class CreationDemandeScenariosTest extends TestCase
{
    use RefreshDatabase;

    /** Villes de test : label => [latitude, longitude, code INSEE]. */
    private const VILLES = [
        'paris' => ['Paris (75001)', 48.8566, 2.3522, '75056'],
        'lyon' => ['Lyon (69001)', 45.7640, 4.8357, '69123'],
        'nevers' => ['Nevers (58000)', 46.9896, 3.1596, '58194'],
        'marseille' => ['Marseille (13001)', 43.2965, 5.3698, '13055'],
    ];

    protected Categorie $categorie55;

    protected Categorie $categorie19;

    protected function setUp(): void
    {
        parent::setUp();

        // Une demande = 5 envois max par heure et par IP : on repart d'un quota vierge.
        RateLimiter::clear('demande:127.0.0.1');

        // Aucun e-mail réel pendant les tests.
        Parametre::create(['cle' => 'email_confirmation_active', 'valeur' => '0', 'type' => 'boolean', 'groupe' => 'mail']);
        Parametre::create(['cle' => 'email_secretariat', 'valeur' => '', 'type' => 'string', 'groupe' => 'mail']);

        $this->categorie55 = Categorie::create(['libelle' => 'Autocar 55 places', 'capacite' => 55, 'gabarit' => 'standard', 'actif' => true]);
        $this->categorie19 = Categorie::create(['libelle' => 'Minibus 19 places', 'capacite' => 19, 'gabarit' => 'minibus', 'actif' => true]);

        foreach (self::VILLES as [$label, $lat, $lng, $code]) {
            Commune::create(['nom' => $label, 'code_insee' => $code, 'code_postal' => $code === '75056' ? '75001' : $code,
                'latitude' => $lat, 'longitude' => $lng]);
        }
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /** Étape complète, prête à être validée (mode fermé). */
    protected function etape(string $cleVille, array $surcharge = []): array
    {
        [$label, $lat, $lng, $code] = self::VILLES[$cleVille];
        $adresse = '1 Rue de Test, '.$label.', France';

        return array_merge([
            'ville' => $label,
            'ville_lat' => $lat,
            'ville_lng' => $lng,
            'citycode' => $code,
            'ville_recherche' => '',
            'adresse' => $adresse,
            'adresse_validee' => true,
            'lieu_libelle' => $adresse,
            'adresse_normalisee' => $adresse,
            'geocodage_source' => 'ban',
            'geocodage_provider_id' => $code.'_00001',
            'latitude' => $lat,
            'longitude' => $lng,
            'adresse_recherche' => '',
            'date' => '2026-10-10',
            'heure_arrivee' => '',
            'heure_depart' => '',
            'arrivee_imperative' => false,
            'depart_imperatif' => false,
            'locked' => false,
        ], $surcharge);
    }

    /** Jeu de données complet du formulaire (aller Paris -> Lyon). */
    protected function donnees(array $surcharge = []): array
    {
        return array_merge([
            'mode' => 'ferme',
            'nb_passagers' => 30,
            'categorie_id' => $this->categorie55->id,
            'nature_prestation' => 'transfert',
            'nature_prestation_autre' => '',
            'client_nom' => 'Jean Dupont',
            'client_email' => 'jean.dupont@example.com',
            'client_telephone' => '0601020304',
            'commentaire' => 'Demande de test',
            'consentement_rgpd' => true,
            // L'ordre compte : les étapes doivent être posées AVANT le type de retour.
            'etapes' => [
                $this->etape('paris', ['heure_depart' => '08:00']),
                $this->etape('lyon', ['heure_arrivee' => '12:00']),
            ],
            'retour_type' => 'aucun',
        ], $surcharge);
    }

    /** Formulaire pré-rempli, prêt à être soumis. */
    protected function formulaire(array $surcharge = [])
    {
        return Livewire::test(DemandeForm::class)->set($this->donnees($surcharge));
    }

    protected function nbDemandes(): int
    {
        return Demande::query()->count();
    }

    // ==================================================================
    // GROUPE A — parcours nominaux
    // ==================================================================

    /** S01 — Estimation valide (aller simple, 2 étapes). */
    public function test_s01_estimation_valide_cree_la_demande(): void
    {
        $this->formulaire([
            'mode' => 'estimation',
            'categorie_id' => null,
            'etapes' => [
                $this->etape('paris', ['heure_depart' => '08:00']),
                $this->etape('lyon', ['heure_arrivee' => '12:30']),
            ],
        ])->call('submit')->assertHasNoErrors();

        $this->assertSame(1, $this->nbDemandes());
        $demande = Demande::query()->firstOrFail();
        $this->assertSame('estimation', $demande->mode);
        $this->assertSame('nouvelle', $demande->statut);
        $this->assertMatchesRegularExpression('/^DEM-\d{8}-[A-Z0-9]{4}$/', $demande->reference);
        $this->assertTrue((bool) $demande->consentement_rgpd);
        $this->assertNotNull($demande->consentement_rgpd_at);
        $this->assertCount(2, $demande->etapes);
        $this->assertSame('08:00', substr((string) $demande->etapes[0]->heure_depart, 0, 5));
        $this->assertSame('12:30', substr((string) $demande->etapes[1]->heure_arrivee, 0, 5));
    }

    /** S02 — Réservation ferme avec véhicule : catégorie et commune enregistrées. */
    public function test_s02_reservation_ferme_enregistre_la_categorie_et_la_commune(): void
    {
        $this->formulaire()->call('submit')->assertHasNoErrors();

        $demande = Demande::query()->firstOrFail();
        $this->assertSame('ferme', $demande->mode);
        $this->assertSame($this->categorie55->id, $demande->categorie_id);
        $this->assertNotNull($demande->etapes[0]->commune_id, 'La commune doit être reliée via le code INSEE.');
        $this->assertSame('Transfert', $demande->nature_prestation);
        $this->assertSame('0601020304', $demande->client_telephone);
    }

    /** S03 — Retour par le même itinéraire : 4 étapes, villes inversées, heures retour vides. */
    public function test_s03_retour_meme_itineraire_genere_le_trajet_inverse(): void
    {
        $this->formulaire(['retour_type' => 'meme'])->call('submit')->assertHasNoErrors();

        $demande = Demande::query()->firstOrFail();
        $etapes = $demande->etapes()->orderBy('ordre')->get();
        $this->assertCount(4, $etapes->toArray(), 'Aller + retour = 4 étapes.');
        $this->assertSame('Paris (75001)', $etapes[0]->ville);
        $this->assertSame('Lyon (69001)', $etapes[1]->ville);
        $this->assertSame('Lyon (69001)', $etapes[2]->ville, 'Le retour repart de la ville d’arrivée.');
        $this->assertSame('Paris (75001)', $etapes[3]->ville, 'Le retour se termine à la ville de départ.');
        $this->assertNull($etapes[2]->heure_depart, 'L’heure de départ du retour ne doit pas être pré-remplie.');
        $this->assertNull($etapes[3]->heure_arrivee, 'L’heure d’arrivée du retour ne doit pas être pré-remplie.');
    }

    /** S04 — Retour par un itinéraire différent : villes modifiables (non verrouillées). */
    public function test_s04_retour_different_laisse_les_villes_modifiables(): void
    {
        Livewire::test(DemandeForm::class)
            ->set($this->donnees(['retour_type' => 'different']))
            ->assertSet('etapes_retour.0.locked', false)
            ->assertSet('etapes_retour.1.locked', false)
            ->call('changerVilleRetour', 0)
            ->assertSet('etapes_retour.0.ville', '')
            ->assertSet('etapes_retour.0.latitude', null)
            ->set('etapes_retour.0.ville', 'Marseille (13001)')
            ->set('etapes_retour.0.ville_lat', 43.2965)
            ->set('etapes_retour.0.ville_lng', 5.3698)
            ->set('etapes_retour.0.latitude', 43.2965)
            ->set('etapes_retour.0.longitude', 5.3698)
            ->set('etapes_retour.0.adresse', '1 Rue de Test, Marseille (13001), France')
            ->set('etapes_retour.0.adresse_validee', true)
            ->call('submit')
            ->assertHasNoErrors();

        $demande = Demande::query()->firstOrFail();
        $this->assertCount(4, $demande->etapes);
        $this->assertSame('Marseille (13001)', $demande->etapes()->orderBy('ordre')->get()[2]->ville);
    }

    /** S05 — Étape intermédiaire : ordre 1..3 et heures du milieu non imposées. */
    public function test_s05_etape_intermediaire_conserve_l_ordre_du_trajet(): void
    {
        $this->formulaire([
            'etapes' => [
                $this->etape('paris', ['heure_depart' => '07:00']),
                $this->etape('nevers', ['adresse' => '', 'adresse_validee' => false]),
                $this->etape('lyon', ['heure_arrivee' => '13:00']),
            ],
        ])->call('submit')->assertHasNoErrors();

        $etapes = Demande::query()->firstOrFail()->etapes()->orderBy('ordre')->get();
        $this->assertSame([1, 2, 3], $etapes->pluck('ordre')->all());
        $this->assertSame('Nevers (58000)', $etapes[1]->ville);
        $this->assertSame('07:00', substr((string) $etapes[0]->heure_depart, 0, 5));
        $this->assertSame('13:00', substr((string) $etapes[2]->heure_arrivee, 0, 5));
    }

    // ==================================================================
    // GROUPE B — données manquantes ou invalides
    // ==================================================================

    /** S06 — Consentement RGPD absent : envoi refusé. */
    public function test_s06_consentement_rgpd_obligatoire(): void
    {
        $this->formulaire(['consentement_rgpd' => false])
            ->call('submit')
            ->assertHasErrors('consentement_rgpd');

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S07 — Adresse e-mail invalide : envoi refusé. */
    public function test_s07_email_invalide_refuse(): void
    {
        $this->formulaire(['client_email' => 'jean.dupont[at]example'])
            ->call('submit')
            ->assertHasErrors('client_email');

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S08 — Prestation « autre » sans précision : envoi refusé. */
    public function test_s08_prestation_autre_sans_precision_refusee(): void
    {
        $this->formulaire(['nature_prestation' => 'autre', 'nature_prestation_autre' => ''])
            ->call('submit')
            ->assertHasErrors('nature_prestation_autre');

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S09 — Prestation hors liste : envoi refusé. */
    public function test_s09_prestation_hors_liste_refusee(): void
    {
        $this->formulaire(['nature_prestation' => 'safari'])
            ->call('submit')
            ->assertHasErrors('nature_prestation');

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S10 — Nombre de passagers hors bornes (0 puis 121) : envoi refusé. */
    public function test_s10_nb_passagers_hors_bornes_refuse(): void
    {
        $this->formulaire(['nb_passagers' => 0])->call('submit')->assertHasErrors('nb_passagers');
        $this->formulaire(['nb_passagers' => 121])->call('submit')->assertHasErrors('nb_passagers');

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S11 — Une seule étape (< 2) : envoi refusé. */
    public function test_s11_une_seule_etape_refusee(): void
    {
        $this->formulaire(['etapes' => [$this->etape('paris', ['heure_depart' => '08:00'])]])
            ->call('submit')
            ->assertHasErrors('etapes');

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S12 — Estimation sans aucune ville : demande inexploitable, l'envoi doit être refusé. */
    public function test_s12_estimation_sans_ville_doit_etre_refusee(): void
    {
        $vide = $this->etape('paris', ['ville' => '', 'ville_lat' => null, 'ville_lng' => null,
            'citycode' => null, 'latitude' => null, 'longitude' => null, 'adresse' => '',
            'adresse_validee' => false, 'lieu_libelle' => '', 'adresse_normalisee' => '']);

        $this->formulaire([
            'mode' => 'estimation',
            'categorie_id' => null,
            'etapes' => [$vide, $vide],
        ])->call('submit');

        $this->assertSame(0, $this->nbDemandes(),
            'FAILLE : une demande sans aucun point de départ est créée (aucun chiffrage possible).');
    }

    /** S13 — Mode fermé : ville non sélectionnée dans la liste (latitude absente) refusée. */
    public function test_s13_ville_non_selectionnee_refusee_en_mode_ferme(): void
    {
        $this->formulaire([
            'etapes' => [
                $this->etape('paris', ['ville' => '', 'latitude' => null, 'adresse_validee' => false, 'adresse' => '']),
                $this->etape('lyon', ['heure_arrivee' => '12:00']),
            ],
        ])->call('submit')->assertHasErrors(['etapes.0.ville', 'etapes.0.latitude']);

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S14 — Adresse non validée au départ/arrivée en mode fermé : refusé. */
    public function test_s14_adresse_non_validee_refusee_en_mode_ferme(): void
    {
        $this->formulaire([
            'etapes' => [
                $this->etape('paris', ['adresse_validee' => false, 'heure_depart' => '08:00']),
                $this->etape('lyon', ['heure_arrivee' => '12:00']),
            ],
        ])->call('submit')->assertHasErrors('etapes.0.adresse');

        $this->assertSame(0, $this->nbDemandes());
    }

    // ==================================================================
    // GROUPE C — dates et horaires aberrants
    // ==================================================================

    /** S15 — Une demande datée dans le passé n'est plus organisable : envoi refusé. */
    public function test_s15_date_dans_le_passe_refusee(): void
    {
        $this->formulaire([
            'etapes' => [
                $this->etape('paris', ['heure_depart' => '08:00', 'date' => '2020-10-10']),
                $this->etape('lyon', ['heure_arrivee' => '12:00', 'date' => '2020-10-10']),
            ],
        ])->call('submit');

        $this->assertSame(0, $this->nbDemandes(),
            'FAILLE : une demande datée dans le passé est acceptée (transport impossible).');
    }

    /** S16 — Année aberrante (0020) : envoi refusé. */
    public function test_s16_annee_aberrante_refusee(): void
    {
        $this->formulaire([
            'etapes' => [
                $this->etape('paris', ['heure_depart' => '08:00', 'date' => '0020-10-10']),
                $this->etape('lyon', ['heure_arrivee' => '12:00', 'date' => '0020-10-10']),
            ],
        ])->call('submit')->assertHasErrors('etapes.0.date');

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S17 — Heures mal formées (25:00, 8h00, « midi », 08:60) : envoi refusé. */
    public function test_s17_heures_mal_formees_refusees(): void
    {
        foreach (['25:00', '8h00', 'midi', '08:60'] as $heure) {
            $this->formulaire([
                'etapes' => [
                    $this->etape('paris', ['heure_depart' => '08:00']),
                    $this->etape('lyon', ['heure_arrivee' => $heure]),
                ],
            ])->call('submit')->assertHasErrors('etapes.1.heure_arrivee');
        }

        $this->assertSame(0, $this->nbDemandes());
    }

    /** S18 — Étape intermédiaire datée avant le départ : incohérence refusée. */
    public function test_s18_etape_intermediaire_avant_le_depart_refusee(): void
    {
        $this->formulaire([
            'etapes' => [
                $this->etape('paris', ['heure_depart' => '08:00', 'date' => '2026-10-10']),
                $this->etape('nevers', ['date' => '2026-10-08']),
                $this->etape('lyon', ['heure_arrivee' => '12:00', 'date' => '2026-10-10']),
            ],
        ])->call('submit');

        $this->assertSame(0, $this->nbDemandes(),
            'FAILLE : une étape datée avant le départ est acceptée (trajet incohérent).');
    }

    /** S19 — Retour daté avant le départ du trajet aller : envoi refusé. */
    public function test_s19_retour_avant_le_depart_refuse(): void
    {
        $form = $this->formulaire(['retour_type' => 'meme']);
        $form->set('etapes_retour.0.date', '2026-10-05');
        $form->set('etapes_retour.1.date', '2026-10-05');
        $form->call('submit')->assertHasErrors(['etapes_retour.0.date']);

        $this->assertSame(0, $this->nbDemandes());
    }
}