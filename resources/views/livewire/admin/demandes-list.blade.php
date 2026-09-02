<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Demandes de devis</h1>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Réf, nom, e-mail…"
                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
            <select wire:model.live="statut" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                <option value="">Tous les statuts</option>
                <option value="nouvelle">Nouvelles</option>
                <option value="en_traitement">En traitement</option>
                <option value="devis_edite">Devis édité</option>
                <option value="envoye">Devis envoyé</option>
                <option value="accepte">Accepté</option>
                <option value="refuse">Refusé</option>
                <option value="close">Clôturées (ancien)</option>
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Référence</th>
                    <th class="px-4 py-3">Reçue le</th>
                    <th class="px-4 py-3">Client</th>
                    <th class="px-4 py-3">Catégorie</th>
                    <th class="px-4 py-3 text-center">Pax</th>
                    <th class="px-4 py-3 text-center">Étapes</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($demandes as $demande)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs font-semibold text-brand">{{ $demande->reference }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $demande->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800">{{ $demande->client_nom }}</div>
                            <div class="text-xs text-slate-400">{{ $demande->client_email }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $demande->categorie?->libelle ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ $demande->nb_passagers }}</td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ $demande->etapes_count }}</td>
                        <td class="px-4 py-3">
                            <x-statut-badge :statut="$demande->statut" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.demande', $demande) }}"
                               class="inline-flex rounded-md bg-green-50 px-3 py-1.5 text-xs font-medium text-brand hover:bg-green-100">
                                Ouvrir
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">
                            Aucune demande{{ $recherche || $statut ? ' pour ce filtre' : '' }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $demandes->links() }}
    </div>
</div>
