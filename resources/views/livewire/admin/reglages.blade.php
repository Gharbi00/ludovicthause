<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">Réglages</h1>
        <button wire:click="enregistrer" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Enregistrer</button>
    </div>

    <x-reglages-tabs />

    @if ($flash)
        <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">{{ $flash }}</div>
    @endif

    <div class="space-y-6">
        @foreach ($groupes as $cle => $titre)
            @if (! empty($params[$cle]))
                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $titre }}</h2>
                        @if ($cle === 'carburant')
                            <button wire:click="actualiserGasoil" wire:target="actualiserGasoil" wire:loading.attr="disabled"
                                    class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-brand hover:bg-green-50 disabled:opacity-50">
                                <span wire:loading.remove wire:target="actualiserGasoil">⛽ Actualiser le prix du gasoil</span>
                                <span wire:loading wire:target="actualiserGasoil">Récupération…</span>
                            </button>
                        @endif
                    </div>

                    @if ($cle === 'carburant' && $gasoilInfo)
                        <p class="mt-2 text-xs text-brand">{{ $gasoilInfo }}</p>
                    @endif

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($params[$cle] as $p)
                            @php $longTexte = in_array($p->cle, ['pdf_conditions']); @endphp
                            <div class="{{ $longTexte ? 'sm:col-span-2' : '' }}">
                                <label class="block text-xs font-medium text-slate-600">{{ $p->libelle ?: $p->cle }}</label>
                                @if ($longTexte)
                                    <textarea wire:model="valeurs.{{ $p->cle }}" rows="4"
                                              class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand"></textarea>
                                @elseif ($p->type === 'string')
                                    <input type="{{ str_contains($p->cle, 'key') ? 'password' : 'text' }}"
                                           wire:model="valeurs.{{ $p->cle }}" placeholder="{{ str_contains($p->cle,'key') ? '— clé non renseignée —' : '' }}"
                                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                @else
                                    <input type="number" step="{{ $p->type === 'integer' ? '1' : '0.0001' }}"
                                           wire:model="valeurs.{{ $p->cle }}"
                                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                @endif
                                <p class="mt-0.5 text-[11px] text-slate-400 font-mono">{{ $p->cle }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
</div>
