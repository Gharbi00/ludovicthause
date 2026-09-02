<?php

namespace App\Livewire\Admin;

use App\Models\Demande;
use App\Models\Devis;
use App\Models\Parametre;
use App\Models\Planning;
use App\Models\Vehicule;
use App\Services\MoteurCalcul;
use Illuminate\Support\Carbon;
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

    // Saisie d'une prestation supplémentaire (ligne libre).
    public string $ligneLibelle = '';
    public ?string $ligneMontant = null;

    public function mount(Demande $demande): void
    {
        $this->demande = $demande->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $devis = $this->devisActuel();
        $this->vehicule_id = $devis?->vehicule_id;
        $this->marge_taux = $devis && $devis->cout_revient_ht > 0
            ? (float) $devis->marge_taux
            : (float) Parametre::get('marge_cible', 15);
    }

    /** Le devis en cours (affectation) pour cette demande, s'il existe. */
    protected function devisActuel(): ?Devis
    {
        return $this->demande->devis->sortByDesc('id')->first();
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
            'reference'  => 'DEV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
            'statut'     => 'brouillon',
        ]);

        $devis->demande_id  = $this->demande->id;
        $devis->vehicule_id = $this->vehicule_id;
        $devis->save();

        if ($this->demande->statut === 'nouvelle') {
            $this->demande->update(['statut' => 'en_traitement']);
        }

        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Véhicule affecté à la demande ' . $this->demande->reference . '.';
    }

    public function retirerAffectation(): void
    {
        $this->demande->devis()->delete();
        $this->demande->update(['statut' => 'nouvelle']);
        $this->vehicule_id = null;
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Affectation retirée.';
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
            $this->erreur = 'Affectez d’abord un véhicule.';
            return;
        }

        // L'appel d'itinéraire peut prendre jusqu'à ~45 s (avec sa reprise).
        // Sans cela, PHP tuerait la requête et l'écran resterait bloqué sans message.
        @set_time_limit(120);

        try {
            $resultat = $moteur->calculer($this->demande, $devis->vehicule, $this->marge_taux);
        } catch (\Throwable $e) {
            $this->erreur = 'Calcul impossible : ' . $e->getMessage();
            return;
        }

        $devis->fill($resultat['devis'])->save();
        $devis->recalculerTotaux(); // réintègre les prestations supplémentaires
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);
        $this->flash = 'Calcul effectué.';
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
            'ligneMontant.numeric'  => 'Le montant doit être un nombre.',
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
        $this->demande->update(['statut' => $statutDemande]);
        $this->demande->refresh()->load(['categorie', 'etapes.commune', 'devis.vehicule']);

        \App\Models\Journal::enregistrer('Devis ' . $statutDevis, $this->demande->reference);
        $this->flash = 'Devis : ' . $statutDevis . '.';
    }

    /** Clic sur « Devis PDF » alors que le devis est encore en brouillon. */
    public function pdfAvantValidation(): void
    {
        $this->flash = null;
        $this->erreur = 'Veuillez valider le devis avant de l’éditer en PDF (bouton « Valider le devis »).';
    }

    public function valider(): void  { $this->changerStatutDevis('valide',  'devis_edite'); }
    public function envoyer(): void  { $this->changerStatutDevis('envoye',  'envoye'); }
    public function accepter(): void { $this->changerStatutDevis('accepte', 'accepte'); }
    public function refuser(): void  { $this->changerStatutDevis('refuse',  'refuse'); }

    public function render()
    {
        [$debut, $fin] = $this->fenetre();

        return view('livewire.admin.demande-show', [
            'devis'    => $this->devisActuel(),
            'fenetre'  => [$debut, $fin],
        ]);
    }
}
