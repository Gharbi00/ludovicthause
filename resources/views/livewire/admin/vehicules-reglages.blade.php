<div>
    <h1 class="text-2xl font-semibold text-slate-900 mb-4">Réglages</h1>

    <x-reglages-tabs />

    @if ($flash)
        <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">{{ $flash }}</div>
    @endif

    <div class="space-y-3">
        @foreach ($vehicules as $v)
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200" wire:key="veh-{{ $v->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex items-center gap-3">
                        <span class="font-mono font-semibold text-brand">{{ $v->immatriculation }}</span>
                        <span class="text-sm text-slate-600">{{ $v->nb_places }} places · {{ $v->nb_essieux }} essieux · {{ $v->conso_l_100km }} L/100</span>
                        @if ($v->actif)
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">actif</span>
                        @else
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-500">inactif</span>
                        @endif
                    </div>
                    <button wire:click="editer({{ $v->id }})" class="rounded-md bg-green-50 px-3 py-1.5 text-xs font-medium text-brand hover:bg-green-100">Éditer</button>
                </div>

                @if ($editId === $v->id)
                    <div class="border-t border-slate-100 p-4">
                        <div class="grid gap-4 sm:grid-cols-3">
                            @php
                                $champs = [
                                    'immatriculation' => ['Immatriculation','text'],
                                    'numero_parc' => ['N° parc','text'],
                                    'nb_places' => ['Places','number'],
                                    'nb_essieux' => ['Essieux (péage)','number'],
                                    'type_energie' => ['Énergie','text'],
                                    'conso_l_100km' => ['Conso (L/100 km)','number'],
                                    'loyer_credit_bail_mensuel' => ['Loyer crédit-bail (€/mois)','number'],
                                    'assurance_annuelle' => ['Assurance (€/an)','number'],
                                    'quote_part_loyers_annuelle' => ['Quote-part loyers (€/an)','number'],
                                    'autres_charges_fixes_annuelles' => ['Autres charges fixes (€/an)','number'],
                                    'cout_entretien_km' => ['Entretien (€/km)','number'],
                                    'cout_pneus_km' => ['Pneus (€/km)','number'],
                                    'cout_adblue_km' => ['AdBlue (€/km)','number'],
                                    'autres_variables_km' => ['Autres variables (€/km)','number'],
                                    'jours_exploitation_an' => ["Jours d'exploitation / an",'number'],
                                ];
                            @endphp
                            @foreach ($champs as $cle => [$label, $type])
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">{{ $label }}</label>
                                    <input type="{{ $type }}" @if($type==='number') step="0.0001" @endif wire:model="form.{{ $cle }}"
                                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                    @error("form.$cle") <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Catégorie</label>
                                <select wire:model="form.categorie_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->id }}">{{ $c->libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-slate-700 mt-6">
                                <input type="checkbox" wire:model="form.actif" class="rounded border-slate-300 text-brand focus:ring-brand">
                                Véhicule actif
                            </label>
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-medium text-slate-600">Notes</label>
                                <textarea wire:model="form.notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand"></textarea>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button wire:click="enregistrer" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Enregistrer</button>
                            <button wire:click="annuler" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
