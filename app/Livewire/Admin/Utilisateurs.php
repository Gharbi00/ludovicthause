<?php

namespace App\Livewire\Admin;

use App\Mail\IdentifiantsUtilisateur;
use App\Models\Journal;
use App\Models\User;
use App\Services\TransportMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Utilisateurs extends Component
{
    // --- Formulaire d'ajout ---
    #[Validate('required|string|max:255')]
    public string $nom = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|max:255')]
    public string $motdepasse = '';

    public bool $estAdmin = false;
    public bool $envoyerParEmail = true;

    // --- Réinitialisation de mot de passe ---
    public ?int $resetId = null;
    public string $resetMotDePasse = '';

    public string $flash = '';
    public string $erreur = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    protected function messages(): array
    {
        return [
            'nom.required'        => 'Indiquez un nom.',
            'email.required'      => 'Indiquez une adresse e-mail.',
            'email.email'         => 'Adresse e-mail invalide.',
            'email.unique'        => 'Cette adresse e-mail est déjà utilisée.',
            'motdepasse.required' => 'Choisissez un mot de passe.',
            'motdepasse.min'      => 'Le mot de passe doit contenir au moins 8 caractères.',
        ];
    }

    public function getUtilisateursProperty()
    {
        return User::orderBy('name')->get();
    }

    public function getJournalProperty()
    {
        try {
            return Journal::orderByDesc('created_at')->orderByDesc('id')->limit(100)->get();
        } catch (\Throwable $e) {
            return collect(); // table pas encore migrée : on n'empêche pas l'affichage
        }
    }

    /** Nombre d'administrateurs actifs (pour éviter de se verrouiller dehors). */
    protected function nombreAdmins(): int
    {
        return User::where('role', 'admin')->count();
    }

    public function ajouter(): void
    {
        $this->erreur = '';
        $this->validate();

        $user = User::create([
            'name'     => $this->nom,
            'email'    => strtolower($this->email),
            'password' => $this->motdepasse,
            'role'     => $this->estAdmin ? 'admin' : 'secretaire',
            // Mot de passe envoyé en clair par e-mail = provisoire, à changer à la 1re connexion.
            'must_change_password' => $this->envoyerParEmail,
        ]);

        Journal::enregistrer('Création de compte', $user->email . ' (' . $user->role . ')');

        $message = 'Utilisateur créé.';
        if ($this->envoyerParEmail) {
            $message .= $this->envoyerIdentifiants($user, $this->motdepasse)
                ? ' Identifiants envoyés par e-mail.'
                : ' ⚠️ Mais l’e-mail n’a pas pu être envoyé (voir Réglages → E-mails).';
        }

        $this->reset('nom', 'email', 'motdepasse', 'estAdmin', 'envoyerParEmail');
        $this->envoyerParEmail = true;
        $this->flash = $message;
    }

    /** Génère un mot de passe provisoire, l'affecte à l'utilisateur et le lui envoie par e-mail. */
    public function envoyerAcces(int $id): void
    {
        $this->erreur = '';
        $user = User::find($id);
        if (! $user) {
            return;
        }

        $provisoire = Str::password(12, symbols: false);
        $user->update(['password' => $provisoire, 'must_change_password' => true]);

        if ($this->envoyerIdentifiants($user, $provisoire)) {
            Journal::enregistrer('Envoi des identifiants', $user->email);
            $this->flash = 'Identifiants envoyés à ' . $user->email . ' (mot de passe provisoire).';
        } else {
            $this->erreur = 'Le mot de passe a été réinitialisé mais l’e-mail n’a pas pu être envoyé (voir Réglages → E-mails).';
        }
    }

    /** Envoie l'e-mail d'identifiants ; renvoie false en cas d'échec (sans interrompre). */
    protected function envoyerIdentifiants(User $user, string $motDePasse): bool
    {
        try {
            Mail::mailer(TransportMail::resoudre())
                ->to($user->email)
                ->send(new IdentifiantsUtilisateur($user, $motDePasse));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    public function basculerRole(int $id): void
    {
        $this->erreur = '';
        $user = User::find($id);
        if (! $user) {
            return;
        }

        if ($user->id === Auth::id()) {
            $this->erreur = 'Vous ne pouvez pas modifier votre propre rôle.';
            return;
        }

        if ($user->isAdmin() && $this->nombreAdmins() <= 1) {
            $this->erreur = 'Il doit rester au moins un administrateur.';
            return;
        }

        $user->update(['role' => $user->isAdmin() ? 'secretaire' : 'admin']);
        Journal::enregistrer('Changement de rôle', $user->email . ' → ' . $user->role);
        $this->flash = 'Rôle mis à jour : ' . $user->name . ' est désormais ' . ($user->isAdmin() ? 'administrateur' : 'secrétaire') . '.';
    }

    public function supprimer(int $id): void
    {
        $this->erreur = '';
        $user = User::find($id);
        if (! $user) {
            return;
        }

        if ($user->id === Auth::id()) {
            $this->erreur = 'Vous ne pouvez pas supprimer votre propre compte.';
            return;
        }

        if ($user->isAdmin() && $this->nombreAdmins() <= 1) {
            $this->erreur = 'Impossible de supprimer le dernier administrateur.';
            return;
        }

        $email = $user->email;
        $user->delete();
        Journal::enregistrer('Suppression de compte', $email);
        $this->flash = 'Utilisateur supprimé.';
    }

    public function ouvrirReset(int $id): void
    {
        $this->erreur = '';
        $this->resetId = $id;
        $this->resetMotDePasse = '';
    }

    public function annulerReset(): void
    {
        $this->resetId = null;
        $this->resetMotDePasse = '';
    }

    public function enregistrerReset(): void
    {
        $this->erreur = '';
        $user = User::find($this->resetId);
        if (! $user) {
            $this->annulerReset();
            return;
        }

        $this->validate(
            ['resetMotDePasse' => 'required|string|min:8|max:255'],
            ['resetMotDePasse.required' => 'Saisissez un mot de passe.', 'resetMotDePasse.min' => 'Au moins 8 caractères.'],
        );

        // Mot de passe défini manuellement par l'admin (communiqué de vive voix) : non provisoire.
        $user->update(['password' => $this->resetMotDePasse, 'must_change_password' => false]);
        Journal::enregistrer('Réinitialisation de mot de passe', $user->email);
        $this->flash = 'Mot de passe réinitialisé pour ' . $user->name . '.';
        $this->annulerReset();
    }

    public function render()
    {
        return view('livewire.admin.utilisateurs');
    }
}
