<div class="mx-auto max-w-lg">
    <h1 class="mb-4 text-2xl font-semibold text-slate-900">Changer mon mot de passe</h1>

    @if ($oblige)
        <div class="mb-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
            🔒 Votre mot de passe est <strong>provisoire</strong>. Merci d’en choisir un nouveau pour continuer.
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
        <form wire:submit="enregistrer" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Mot de passe actuel</label>
                <x-password-input model="actuel" />
                @error('actuel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Nouveau mot de passe</label>
                <x-password-input model="nouveau" placeholder="8 caractères minimum" />
                @error('nouveau') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Confirmer le nouveau mot de passe</label>
                <x-password-input model="nouveau_confirmation" />
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-brand px-4 py-2.5 text-white font-semibold hover:bg-brand-dark">
                Enregistrer le nouveau mot de passe
            </button>
        </form>
    </div>
</div>
