<?php

namespace App\Livewire\Admin;

use App\Models\Demande;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class DemandesList extends Component
{
    use WithPagination;

    private const PAR_PAGE = 15;

    #[Url(as: 'q')]
    public string $recherche = '';

    #[Url]
    public string $statut = '';

    /** @var array<int, int|string> */
    public array $demandesSelectionnees = [];

    public ?string $flash = null;

    public function updating($name): void
    {
        // Revenir en page 1 quand on filtre/recherche
        if (in_array($name, ['recherche', 'statut'], true)) {
            $this->resetPage();
            $this->demandesSelectionnees = [];
        }
    }

    public function toggleSelectionPage(): void
    {
        $idsPage = $this->requeteDemandes()
            ->latest()
            ->paginate(self::PAR_PAGE)
            ->getCollection()
            ->modelKeys();
        $selection = collect($this->demandesSelectionnees)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique();

        if ($idsPage !== [] && collect($idsPage)->every(fn ($id) => $selection->contains($id))) {
            $this->demandesSelectionnees = $selection
                ->reject(fn ($id) => in_array($id, $idsPage, true))
                ->values()
                ->all();

            return;
        }

        $this->demandesSelectionnees = $selection
            ->merge($idsPage)
            ->unique()
            ->values()
            ->all();
    }

    public function viderSelection(): void
    {
        $this->demandesSelectionnees = [];
    }

    public function supprimerDemande(int $demandeId): void
    {
        $idsPage = $this->requeteDemandes()
            ->latest()
            ->paginate(self::PAR_PAGE)
            ->getCollection()
            ->modelKeys();

        if (! in_array($demandeId, $idsPage, true)) {
            return;
        }

        $this->supprimerParIds([$demandeId]);
        $this->demandesSelectionnees = collect($this->demandesSelectionnees)
            ->reject(fn ($id) => (int) $id === $demandeId)
            ->values()
            ->all();
        $this->flash = 'Demande supprimée.';

        $dernierePage = max(1, (int) ceil($this->requeteDemandes()->count() / self::PAR_PAGE));
        $this->setPage(min($this->getPage(), $dernierePage));
    }

    public function supprimerSelection(): void
    {
        $ids = collect($this->demandesSelectionnees)
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        // Re-appliquer le filtre serveur empêche de supprimer des IDs hors liste.
        $idsAutorises = $this->requeteDemandes()->whereKey($ids)->pluck('id')->all();
        $nombre = $this->supprimerParIds($idsAutorises);
        $this->demandesSelectionnees = [];
        $this->flash = match ($nombre) {
            0 => 'Aucune demande sélectionnée ne correspond à la liste affichée.',
            1 => 'Demande supprimée.',
            default => $nombre.' demandes supprimées.',
        };

        $dernierePage = max(1, (int) ceil($this->requeteDemandes()->count() / self::PAR_PAGE));
        $this->setPage(min($this->getPage(), $dernierePage));
    }

    /** @param array<int, int> $ids */
    protected function supprimerParIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids): int {
            $demandes = Demande::with('devis')
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            foreach ($demandes as $demande) {
                foreach ($demande->devis as $devis) {
                    $devis->postes()->delete();
                    $devis->delete();
                }
                $demande->delete();
            }

            return $demandes->count();
        });
    }

    protected function requeteDemandes()
    {
        return Demande::query()
            ->when($this->statut !== '', fn ($q) => $q->where('statut', $this->statut))
            ->when($this->recherche !== '', function ($q) {
                $terme = '%'.$this->recherche.'%';
                $q->where(fn ($sub) => $sub
                    ->where('reference', 'like', $terme)
                    ->orWhere('client_nom', 'like', $terme)
                    ->orWhere('client_email', 'like', $terme));
            });
    }

    public function render()
    {
        $demandes = $this->requeteDemandes()
            ->with('categorie')
            ->withCount('etapes')
            ->latest()
            ->paginate(self::PAR_PAGE);

        $selection = collect($this->demandesSelectionnees)->map(fn ($id) => (int) $id);
        $toutesSelectionnees = $demandes->isNotEmpty()
            && $demandes->getCollection()->every(fn ($demande) => $selection->contains($demande->id));

        $compteurs = Demande::selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return view('livewire.admin.demandes-list', [
            'demandes' => $demandes,
            'compteurs' => $compteurs,
            'toutesSelectionnees' => $toutesSelectionnees,
        ]);
    }
}
