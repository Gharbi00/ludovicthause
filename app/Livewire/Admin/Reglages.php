<?php

namespace App\Livewire\Admin;

use App\Models\Parametre;
use App\Services\PrixGasoilProvider;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Reglages extends Component
{
    /** @var array<string,string|null> cle => valeur */
    public array $valeurs = [];
    public ?string $flash = null;
    public ?string $gasoilInfo = null;

    public function mount(): void
    {
        foreach (Parametre::all() as $p) {
            $this->valeurs[$p->cle] = $p->valeur;
        }
    }

    public function enregistrer(): void
    {
        foreach ($this->valeurs as $cle => $valeur) {
            Parametre::set($cle, $valeur);
        }
        $this->flash = 'Paramètres enregistrés.';
    }

    public function actualiserGasoil(PrixGasoilProvider $provider): void
    {
        $prix = $provider->actualiser();

        if ($prix !== null) {
            $this->valeurs['prix_gasoil_litre'] = (string) $prix;
            $this->gasoilInfo = "Prix du gasoil actualisé : {$prix} €/L (moyenne nationale du jour).";
        } else {
            $this->gasoilInfo = "Impossible de récupérer le prix (API indisponible).";
        }
    }

    public function render()
    {
        // Les groupes affichés dépendent de l'onglet (route).
        if (request()->routeIs('admin.reglages.societe')) {
            $groupes = ['societe' => 'Coordonnées de la société'];
        } elseif (request()->routeIs('admin.reglages.pdf')) {
            $groupes = ['pdf' => 'Devis PDF'];
        } else {
            $groupes = [
                'calcul'    => 'Calcul & fiscalité',
                'structure' => 'Structure & chauffeur',
                'carburant' => 'Carburant',
                'peage'     => 'Péage (tarifs au km sur autoroute)',
                'rse'       => 'Réglementation sociale (RSE)',
                'api'       => "Clés d'API",
            ];
        }

        $params = Parametre::all()->groupBy('groupe');

        return view('livewire.admin.reglages', [
            'groupes' => $groupes,
            'params'  => $params,
        ]);
    }
}
