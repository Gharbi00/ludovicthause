<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">Réglages</h1>
    </div>

    <x-reglages-tabs />

    @if ($flash)
        <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">{{ $flash }}</div>
    @endif
    @if ($erreur)
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">{{ $erreur }}</div>
    @endif

    <div class="space-y-6">
        {{-- ---------- Comptes ---------- --}}
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Comptes utilisateurs</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                            <th class="py-2 pr-3">Nom</th>
                            <th class="py-2 pr-3">E-mail</th>
                            <th class="py-2 pr-3">Rôle</th>
                            <th class="py-2 pr-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->utilisateurs as $u)
                            <tr>
                                <td class="py-2 pr-3 font-medium text-slate-800">
                                    {{ $u->name }}
                                    @if ($u->id === auth()->id())<span class="ml-1 text-xs text-slate-400">(vous)</span>@endif
                                </td>
                                <td class="py-2 pr-3 text-slate-600">{{ $u->email }}</td>
                                <td class="py-2 pr-3">
                                    @if ($u->isAdmin())
                                        <span class="inline-flex rounded-full bg-brand/10 px-2 py-0.5 text-xs font-semibold text-brand">Administrateur</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Secrétaire</span>
                                    @endif
                                    @if ($u->must_change_password)
                                        <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700" title="Mot de passe provisoire, pas encore changé">provisoire</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button wire:click="basculerRole({{ $u->id }})"
                                                class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                            {{ $u->isAdmin() ? 'Passer secrétaire' : 'Passer admin' }}
                                        </button>
                                        <button wire:click="envoyerAcces({{ $u->id }})"
                                                wire:confirm="Réinitialiser le mot de passe de {{ $u->name }} et lui envoyer ses identifiants par e-mail (mot de passe provisoire) ?"
                                                class="rounded-md border border-brand/40 px-2.5 py-1 text-xs font-medium text-brand hover:bg-green-50">
                                            Envoyer un accès
                                        </button>
                                        <button wire:click="ouvrirReset({{ $u->id }})"
                                                class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                            Mot de passe
                                        </button>
                                        @if ($u->id !== auth()->id())
                                            <button wire:click="supprimer({{ $u->id }})"
                                                    wire:confirm="Supprimer définitivement le compte de {{ $u->name }} ?"
                                                    class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                                Supprimer
                                            </button>
                                        @endif
                                    </div>

                                    @if ($resetId === $u->id)
                                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2">
                                            <input type="text" wire:model="resetMotDePasse" placeholder="Nouveau mot de passe (8+ car.)"
                                                   class="w-56 rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                            <button wire:click="enregistrerReset"
                                                    class="rounded-md bg-brand px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-dark">Enregistrer</button>
                                            <button wire:click="annulerReset"
                                                    class="rounded-md border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">Annuler</button>
                                            @error('resetMotDePasse') <span class="w-full text-right text-xs text-red-600">{{ $message }}</span> @enderror
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ---------- Ajout ---------- --}}
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ajouter un utilisateur</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Nom</label>
                    <input type="text" wire:model="nom"
                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                    @error('nom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">E-mail</label>
                    <input type="email" wire:model="email"
                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Mot de passe</label>
                    <input type="text" wire:model="motdepasse" placeholder="8 caractères minimum"
                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                    @error('motdepasse') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col justify-end gap-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="estAdmin" class="rounded border-slate-300 text-brand focus:ring-brand">
                        Administrateur (accès aux Réglages)
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="envoyerParEmail" class="rounded border-slate-300 text-brand focus:ring-brand">
                        Envoyer les identifiants par e-mail <span class="text-slate-400">(mot de passe provisoire)</span>
                    </label>
                </div>
            </div>
            <div class="mt-4">
                <button wire:click="ajouter"
                        class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">
                    Créer l'utilisateur
                </button>
            </div>
            <p class="mt-3 text-xs text-slate-400">
                Un <strong>administrateur</strong> accède aux Réglages et à la gestion des comptes. Une
                <strong>secrétaire</strong> accède aux demandes et aux devis, mais pas aux Réglages.
            </p>
        </section>

        {{-- ---------- Journal ---------- --}}
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Journal d'activité</h2>
            <p class="mt-1 text-xs text-slate-400">100 dernières entrées (connexions, devis, gestion des comptes).</p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">Utilisateur</th>
                            <th class="py-2 pr-3">Action</th>
                            <th class="py-2 pr-3">Détails</th>
                            <th class="py-2 pr-3">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->journal as $e)
                            <tr>
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-500">{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-3 text-slate-700">{{ $e->utilisateur }}</td>
                                <td class="py-2 pr-3 font-medium text-slate-800">{{ $e->action }}</td>
                                <td class="py-2 pr-3 text-slate-600">{{ $e->details }}</td>
                                <td class="py-2 pr-3 font-mono text-xs text-slate-400">{{ $e->ip }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-slate-400">Aucune activité enregistrée pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
