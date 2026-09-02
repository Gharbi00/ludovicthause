<?php

namespace App\Livewire\Admin;

use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class DemandesList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $recherche = '';

    #[Url]
    public string $statut = '';

    public function updating($name): void
    {
        // Revenir en page 1 quand on filtre/recherche
        if (in_array($name, ['recherche', 'statut'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $demandes = Demande::query()
            ->with('categorie')
            ->withCount('etapes')
            ->when($this->statut !== '', fn ($q) => $q->where('statut', $this->statut))
            ->when($this->recherche !== '', function ($q) {
                $terme = '%' . $this->recherche . '%';
                $q->where(fn ($sub) => $sub
                    ->where('reference', 'like', $terme)
                    ->orWhere('client_nom', 'like', $terme)
                    ->orWhere('client_email', 'like', $terme));
            })
            ->latest()
            ->paginate(15);

        $compteurs = Demande::selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return view('livewire.admin.demandes-list', [
            'demandes'  => $demandes,
            'compteurs' => $compteurs,
        ]);
    }
}
