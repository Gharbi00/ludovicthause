<?php

namespace App\Livewire\Admin;

use App\Models\Categorie;
use App\Models\Vehicule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class VehiculesReglages extends Component
{
    public ?int $editId = null;

    public array $form = [];

    public ?string $flash = null;

    public ?int $categorieEditId = null;

    public array $categorieForm = [];

    protected array $champs = [
        'immatriculation', 'numero_parc', 'categorie_id', 'nb_places', 'nb_essieux',
        'type_energie', 'conso_l_100km', 'loyer_credit_bail_mensuel', 'assurance_annuelle',
        'quote_part_loyers_annuelle', 'autres_charges_fixes_annuelles', 'cout_entretien_km',
        'cout_pneus_km', 'cout_adblue_km', 'autres_variables_km', 'jours_exploitation_an',
        'actif', 'notes',
    ];

    public function editer(int $id): void
    {
        $v = Vehicule::findOrFail($id);
        $this->editId = $id;
        $this->form = $v->only($this->champs);
        $this->form['actif'] = (bool) $v->actif;
    }

    public function annuler(): void
    {
        $this->editId = null;
        $this->form = [];
        $this->categorieEditId = null;
        $this->categorieForm = [];
    }

    public function editerCategorie(int $id): void
    {
        $categorie = Categorie::findOrFail($id);
        $this->categorieEditId = $id;
        $this->categorieForm = $categorie->only(['libelle', 'capacite', 'gabarit', 'photo_path', 'ordre', 'actif']);
    }

    public function nouvelleCategorie(): void
    {
        $this->categorieEditId = 0;
        $this->categorieForm = ['libelle' => '', 'capacite' => '', 'gabarit' => '', 'photo_path' => '', 'ordre' => 0, 'actif' => true];
    }

    public function enregistrerCategorie(): void
    {
        $data = $this->validate([
            'categorieForm.libelle' => ['required', 'string', 'max:120'],
            'categorieForm.capacite' => ['required', 'integer', 'min:1', 'max:120'],
            'categorieForm.gabarit' => ['nullable', 'string', 'max:50'],
            'categorieForm.photo_path' => ['nullable', 'string', 'max:255'],
            'categorieForm.ordre' => ['required', 'integer', 'min:0'],
            'categorieForm.actif' => ['boolean'],
        ])['categorieForm'];
        if ($this->categorieEditId) {
            Categorie::findOrFail($this->categorieEditId)->update($data);
        } else {
            Categorie::create($data);
        }
        $this->categorieEditId = null;
        $this->categorieForm = [];
        $this->flash = 'Catégorie enregistrée.';
    }

    public function enregistrer(): void
    {
        $data = $this->validate([
            'form.immatriculation' => ['required', 'string', 'max:20'],
            'form.numero_parc' => ['nullable', 'string', 'max:20'],
            'form.categorie_id' => ['required', 'exists:categories,id'],
            'form.nb_places' => ['required', 'integer', 'min:1', 'max:120'],
            'form.nb_essieux' => ['required', 'integer', 'min:2', 'max:5'],
            'form.type_energie' => ['required', 'string', 'max:30'],
            'form.conso_l_100km' => ['required', 'numeric', 'min:0'],
            'form.loyer_credit_bail_mensuel' => ['required', 'numeric', 'min:0'],
            'form.assurance_annuelle' => ['required', 'numeric', 'min:0'],
            'form.quote_part_loyers_annuelle' => ['required', 'numeric', 'min:0'],
            'form.autres_charges_fixes_annuelles' => ['required', 'numeric', 'min:0'],
            'form.cout_entretien_km' => ['required', 'numeric', 'min:0'],
            'form.cout_pneus_km' => ['required', 'numeric', 'min:0'],
            'form.cout_adblue_km' => ['required', 'numeric', 'min:0'],
            'form.autres_variables_km' => ['required', 'numeric', 'min:0'],
            'form.jours_exploitation_an' => ['required', 'integer', 'min:1', 'max:366'],
            'form.actif' => ['boolean'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ])['form'];

        Vehicule::findOrFail($this->editId)->update($data);
        $this->flash = 'Véhicule '.$data['immatriculation'].' enregistré.';
        $this->editId = null;
        $this->form = [];
    }

    public function render()
    {
        return view('livewire.admin.vehicules-reglages', [
            'vehicules' => Vehicule::with('categorie')->orderByDesc('actif')->orderByDesc('nb_places')->get(),
            'categories' => Categorie::orderBy('ordre')->orderBy('capacite')->get(),
        ]);
    }
}
