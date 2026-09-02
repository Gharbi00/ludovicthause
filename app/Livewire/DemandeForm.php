<?php

namespace App\Livewire;

use App\Mail\ConfirmationDemande;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Demande;
use App\Models\Parametre;
use App\Services\Geocodage;
use App\Services\TransportMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DemandeForm extends Component
{
    public ?int $nb_passagers = 30;
    public ?int $categorie_id = null;

    public string $client_nom = '';
    public string $client_email = '';
    public string $client_telephone = '';
    public string $commentaire = '';

    /** @var array<int, array<string, mixed>> */
    public array $etapes = [];

    public string $retour_type = 'aucun';
    /** @var array<int, array<string, mixed>> */
    public array $etapes_retour = [];

    // Suggestions (villes / adresses) pour l'aller et le retour.
    public array $suggestionsVille = [];
    public array $suggestionsAdresse = [];
    public array $suggestionsVilleRetour = [];
    public array $suggestionsAdresseRetour = [];

    public string $website = '';
    public bool $submitted = false;
    public ?string $reference = null;

    public function mount(): void
    {
        $this->etapes = [$this->etapeVide(), $this->etapeVide()];
    }

    protected function etapeVide(bool $locked = false): array
    {
        return [
            'ville'             => '',
            'ville_lat'         => null,
            'ville_lng'         => null,
            'citycode'          => null,
            'ville_recherche'   => '',
            'adresse'           => '',
            'latitude'          => null,   // point effectif (adresse si dispo, sinon ville)
            'longitude'         => null,
            'adresse_recherche' => '',
            'date'              => '',
            'heure_arrivee'     => '',
            'heure_depart'      => '',
            'arrivee_imperative' => false,
            'depart_imperatif'  => false,
            'locked'            => $locked,
        ];
    }

    /** Contexte ville d'une étape, pour restreindre la recherche d'adresse. */
    protected function contexteVille(array $e): array
    {
        return [
            'citycode' => $e['citycode'] ?? null,
            'ville'    => $e['ville'] ?? null,
            'lat'      => $e['ville_lat'] ?? null,
            'lng'      => $e['ville_lng'] ?? null,
        ];
    }

    /** Recherche ville/adresse dès que le texte change. */
    public function updated(string $name, $value): void
    {
        // La date de départ sert de date par défaut aux étapes suivantes non renseignées.
        if ($name === 'etapes.0.date') {
            $this->heriterDatesDepart();
            return;
        }

        $geo = app(Geocodage::class);

        if (preg_match('/^etapes\.(\d+)\.ville_recherche$/', $name, $m)) {
            $this->suggestionsVille[(int) $m[1]] = $geo->rechercherVilles((string) $value);
        } elseif (preg_match('/^etapes\.(\d+)\.adresse_recherche$/', $name, $m)) {
            $i = (int) $m[1];
            $this->suggestionsAdresse[$i] = $geo->rechercherAdresses((string) $value, $this->contexteVille($this->etapes[$i] ?? []));
        } elseif (preg_match('/^etapes_retour\.(\d+)\.ville_recherche$/', $name, $m)) {
            $this->suggestionsVilleRetour[(int) $m[1]] = $geo->rechercherVilles((string) $value);
        } elseif (preg_match('/^etapes_retour\.(\d+)\.adresse_recherche$/', $name, $m)) {
            $i = (int) $m[1];
            $this->suggestionsAdresseRetour[$i] = $geo->rechercherAdresses((string) $value, $this->contexteVille($this->etapes_retour[$i] ?? []));
        }
    }

    // --- Sélection ville / adresse (helpers génériques) ---

    protected function appliquerVille(array &$liste, array &$sug, int $i, int $k): void
    {
        $s = $sug[$i][$k] ?? null;
        if (! $s || ! isset($liste[$i])) {
            return;
        }
        $liste[$i]['ville']     = $s['label'];
        $liste[$i]['ville_lat'] = $s['lat'];
        $liste[$i]['ville_lng'] = $s['lng'];
        $liste[$i]['citycode']  = $s['citycode'] ?? null;
        $liste[$i]['latitude']  = $s['lat'];   // point effectif = ville tant qu'aucune adresse
        $liste[$i]['longitude'] = $s['lng'];
        $liste[$i]['adresse']   = '';           // la ville a changé
        $liste[$i]['ville_recherche']   = '';
        $liste[$i]['adresse_recherche'] = '';
        $sug[$i] = [];
    }

    protected function appliquerAdresse(array &$liste, array &$sug, int $i, int $k): void
    {
        $s = $sug[$i][$k] ?? null;
        if (! $s || ! isset($liste[$i])) {
            return;
        }
        $liste[$i]['adresse']   = $s['label'];
        $liste[$i]['latitude']  = $s['lat'];
        $liste[$i]['longitude'] = $s['lng'];
        $liste[$i]['adresse_recherche'] = '';
        $sug[$i] = [];
    }

    public function choisirVille(int $i, int $k): void { $this->appliquerVille($this->etapes, $this->suggestionsVille, $i, $k); }
    public function choisirVilleRetour(int $i, int $k): void { $this->appliquerVille($this->etapes_retour, $this->suggestionsVilleRetour, $i, $k); }
    public function choisirAdresse(int $i, int $k): void { $this->appliquerAdresse($this->etapes, $this->suggestionsAdresse, $i, $k); }
    public function choisirAdresseRetour(int $i, int $k): void { $this->appliquerAdresse($this->etapes_retour, $this->suggestionsAdresseRetour, $i, $k); }

    public function changerVille(int $i): void
    {
        if (isset($this->etapes[$i])) {
            foreach (['ville', 'adresse'] as $c) { $this->etapes[$i][$c] = ''; }
            $this->etapes[$i]['latitude'] = $this->etapes[$i]['longitude'] = $this->etapes[$i]['citycode'] = null;
        }
    }

    public function changerVilleRetour(int $i): void
    {
        if (isset($this->etapes_retour[$i]) && ! ($this->etapes_retour[$i]['locked'] ?? false)) {
            foreach (['ville', 'adresse'] as $c) { $this->etapes_retour[$i][$c] = ''; }
            $this->etapes_retour[$i]['latitude'] = $this->etapes_retour[$i]['longitude'] = $this->etapes_retour[$i]['citycode'] = null;
        }
    }

    public function changerAdresse(int $i): void
    {
        if (isset($this->etapes[$i])) {
            $this->etapes[$i]['adresse'] = '';
            $this->etapes[$i]['latitude'] = $this->etapes[$i]['ville_lat'];
            $this->etapes[$i]['longitude'] = $this->etapes[$i]['ville_lng'];
        }
    }

    public function changerAdresseRetour(int $i): void
    {
        if (isset($this->etapes_retour[$i])) {
            $this->etapes_retour[$i]['adresse'] = '';
            $this->etapes_retour[$i]['latitude'] = $this->etapes_retour[$i]['ville_lat'];
            $this->etapes_retour[$i]['longitude'] = $this->etapes_retour[$i]['ville_lng'];
        }
    }

    // --- Ajout / retrait / déplacement (aller) : départ & arrivée fixes, étapes au milieu ---

    public function ajouterEtape(): void
    {
        $pos = max(1, count($this->etapes) - 1); // insère juste avant l'arrivée
        $nouvelle = $this->etapeVide();
        $nouvelle['date'] = $this->etapes[0]['date'] ?? ''; // même date que le départ par défaut
        array_splice($this->etapes, $pos, 0, [$nouvelle]);
    }

    /** Les étapes suivant le départ, sans date, héritent de la date de départ. */
    protected function heriterDatesDepart(): void
    {
        $dateDepart = $this->etapes[0]['date'] ?? '';
        if ($dateDepart === '') {
            return;
        }
        foreach (array_keys($this->etapes) as $i) {
            if ($i > 0 && empty($this->etapes[$i]['date'])) {
                $this->etapes[$i]['date'] = $dateDepart;
            }
        }
    }

    public function retirerEtape(int $index): void
    {
        $dernier = count($this->etapes) - 1;
        if ($index <= 0 || $index >= $dernier) {
            return; // on ne retire pas le départ ni l'arrivée
        }
        array_splice($this->etapes, $index, 1);
    }

    public function monterEtape(int $index): void
    {
        if ($index <= 1 || $index >= count($this->etapes) - 1) {
            return; // reste entre départ et arrivée
        }
        [$this->etapes[$index - 1], $this->etapes[$index]] = [$this->etapes[$index], $this->etapes[$index - 1]];
    }

    public function descendreEtape(int $index): void
    {
        if ($index < 1 || $index >= count($this->etapes) - 2) {
            return;
        }
        [$this->etapes[$index + 1], $this->etapes[$index]] = [$this->etapes[$index], $this->etapes[$index + 1]];
    }

    // --- Retour ---

    public function updatedRetourType(): void
    {
        $aller = array_values($this->etapes);

        if ($this->retour_type === 'meme') {
            $this->etapes_retour = array_map(fn ($e) => $this->etapeRetourDepuis($e), array_reverse($aller));
        } elseif ($this->retour_type === 'different') {
            $this->etapes_retour = [
                $this->etapeRetourDepuis($aller[count($aller) - 1]),
                $this->etapeRetourDepuis($aller[0]),
            ];
        } else {
            $this->etapes_retour = [];
        }

        $dateArriveeAller = $aller[count($aller) - 1]['date'] ?? '';
        foreach ($this->etapes_retour as &$etape) {
            if (empty($etape['date'])) {
                $etape['date'] = $dateArriveeAller;
            }
        }
        unset($etape);
    }

    protected function etapeRetourDepuis(array $s): array
    {
        $e = $this->etapeVide(locked: true);
        foreach (['ville', 'ville_lat', 'ville_lng', 'citycode', 'adresse', 'latitude', 'longitude'] as $c) {
            $e[$c] = $s[$c] ?? null;
        }

        return $e;
    }

    public function ajouterEtapeRetour(): void
    {
        $pos = max(1, count($this->etapes_retour) - 1);
        array_splice($this->etapes_retour, $pos, 0, [$this->etapeVide()]);
    }

    public function retirerEtapeRetour(int $index): void
    {
        if (! isset($this->etapes_retour[$index]) || ($this->etapes_retour[$index]['locked'] ?? false)) {
            return;
        }
        array_splice($this->etapes_retour, $index, 1);
    }

    public function updatedNbPassagers(): void
    {
        if ($this->categorie_id) {
            $cat = Categorie::find($this->categorie_id);
            if (! $cat || $cat->capacite < $this->nb_passagers) {
                $this->categorie_id = null;
            }
        }
    }

    public function getCategoriesProperty()
    {
        return Categorie::query()
            ->where('actif', true)
            ->where('capacite', '>=', max(1, (int) $this->nb_passagers))
            ->orderBy('capacite')
            ->get();
    }

    protected function rules(): array
    {
        $rules = [
            'nb_passagers'     => ['required', 'integer', 'min:1', 'max:120'],
            'categorie_id'     => ['nullable', 'exists:categories,id'],
            'client_nom'       => ['required', 'string', 'max:255'],
            'client_email'     => ['required', 'email', 'max:255'],
            'client_telephone' => ['nullable', 'string', 'max:30'],
            'commentaire'      => ['nullable', 'string', 'max:2000'],
            'etapes'           => ['required', 'array', 'min:2'],
            'retour_type'      => ['in:aucun,meme,different'],
        ];

        $this->reglesEtapes($rules, 'etapes', $this->etapes);
        if ($this->retour_type !== 'aucun') {
            $this->reglesEtapes($rules, 'etapes_retour', $this->etapes_retour);
        }

        return $rules;
    }

    /** Ajoute les règles par étape (adresse obligatoire seulement au départ/arrivée). */
    protected function reglesEtapes(array &$rules, string $prefixe, array $liste): void
    {
        $n = count($liste);
        foreach (array_keys($liste) as $i) {
            $rules["$prefixe.$i.ville"]    = ['required', 'string'];
            $rules["$prefixe.$i.latitude"] = ['required', 'numeric'];
            $rules["$prefixe.$i.date"]     = ['required', 'date'];
            if ($i === 0 || $i === $n - 1) {
                $rules["$prefixe.$i.adresse"] = ['required', 'string'];
            }
        }
    }

    protected function messages(): array
    {
        return [
            'etapes.*.ville.required'      => 'Indiquez la ville de chaque étape.',
            'etapes.*.latitude.required'   => 'Sélectionnez une ville dans la liste.',
            'etapes.*.adresse.required'    => 'L’adresse précise est obligatoire au départ et à l’arrivée.',
            'etapes.*.date.required'       => 'Indiquez la date de chaque étape.',
            'etapes_retour.*.ville.required'    => 'Indiquez la ville de chaque étape du retour.',
            'etapes_retour.*.latitude.required' => 'Sélectionnez une ville (retour).',
            'etapes_retour.*.adresse.required'  => 'Adresse précise obligatoire au départ et à l’arrivée du retour.',
            'etapes_retour.*.date.required'     => 'Indiquez la date de chaque étape du retour.',
            'client_email.email'           => 'Adresse e-mail invalide.',
        ];
    }

    public function submit(): void
    {
        if ($this->website !== '') {
            return;
        }

        $cle = 'demande:' . request()->ip();
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            $this->addError('rate_limit', 'Trop de demandes envoyées. Merci de réessayer plus tard.');
            return;
        }

        // Les étapes sans date reprennent la date de départ avant contrôle.
        $this->heriterDatesDepart();

        try {
            $data = $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->dispatch('formulaire-invalide');
            return;
        }

        RateLimiter::hit($cle, 3600);

        $demande = Demande::create([
            'reference'        => $this->genererReference(),
            'categorie_id'     => $data['categorie_id'],
            'nb_passagers'     => $data['nb_passagers'],
            'client_nom'       => $data['client_nom'],
            'client_email'     => $data['client_email'],
            'client_telephone' => $data['client_telephone'] ?: null,
            'statut'           => 'nouvelle',
            'commentaire'      => $data['commentaire'] ?: null,
            'ip_soumission'    => request()->ip(),
        ]);

        $journee = array_values($this->etapes);
        if ($this->retour_type !== 'aucun') {
            $journee = array_merge($journee, array_values($this->etapes_retour));
        }

        $nbEtapes = count($journee);
        foreach ($journee as $i => $e) {
            $estPremier = $i === 0;
            $estDernier = $i === $nbEtapes - 1;
            $demande->etapes()->create([
                'ordre'              => $i + 1,
                'commune_id'         => ! empty($e['citycode']) ? Commune::where('code_insee', $e['citycode'])->value('id') : null,
                'ville'              => $e['ville'],
                'adresse'            => $e['adresse'] ?: null,
                'latitude'           => $e['latitude'],
                'longitude'          => $e['longitude'],
                'date'               => $e['date'],
                'heure_arrivee'      => $estPremier ? null : ($e['heure_arrivee'] ?: null),
                'heure_depart'       => $estDernier ? null : ($e['heure_depart'] ?: null),
                'arrivee_imperative' => ! $estPremier && (bool) ($e['arrivee_imperative'] ?? false),
                'depart_imperatif'   => ! $estDernier && (bool) ($e['depart_imperatif'] ?? false),
            ]);
        }

        if (Parametre::get('email_confirmation_active', true) && filter_var($demande->client_email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::mailer(TransportMail::resoudre())->to($demande->client_email)->send(new ConfirmationDemande($demande));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->reference = $demande->reference;
        $this->submitted = true;
    }

    protected function genererReference(): string
    {
        return 'DEM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));
    }

    public function render()
    {
        return view('livewire.demande-form');
    }
}
