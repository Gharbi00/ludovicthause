<?php

namespace App\Livewire\Admin;

use App\Models\Journal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class MotDePasse extends Component
{
    public string $actuel = '';
    public string $nouveau = '';
    public string $nouveau_confirmation = '';

    /** Vrai si l'utilisateur est ici parce que son mot de passe est provisoire. */
    public bool $oblige = false;

    public function mount(): void
    {
        $this->oblige = (bool) Auth::user()?->must_change_password;
    }

    protected function messages(): array
    {
        return [
            'actuel.required'   => 'Saisissez votre mot de passe actuel.',
            'actuel.current_password' => 'Mot de passe actuel incorrect.',
            'nouveau.required'  => 'Choisissez un nouveau mot de passe.',
            'nouveau.min'       => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'nouveau.confirmed' => 'La confirmation ne correspond pas.',
            'nouveau.different' => 'Le nouveau mot de passe doit être différent de l’actuel.',
        ];
    }

    public function enregistrer()
    {
        $this->validate([
            'actuel'  => ['required', 'current_password'],
            'nouveau' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:actuel'],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => $this->nouveau,
            'must_change_password' => false,
        ]);

        Journal::enregistrer('Changement de mot de passe');

        session()->flash('status', 'Votre mot de passe a été mis à jour.');

        return redirect()->route('admin.demandes');
    }

    public function render()
    {
        return view('livewire.admin.mot-de-passe');
    }
}
