{{-- Bloc Ville + Adresse d'une étape.
     Variables : $prefixe, $i, $etape, $locked, $adresseRequise,
                 $sugVille, $sugAdresse, $mVille, $mAdresse, $mChangerVille, $mChangerAdresse --}}

{{-- Ville --}}
@php
    $villeSuggestions = data_get($sugVille, (string) $i, []);
    $adresseSuggestions = data_get($sugAdresse, (string) $i, []);
@endphp
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-slate-600">Ville</label>
    @if ($locked)
        <div class="mt-1 rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600">
            {{ $etape['ville'] ?: '—' }} <span class="text-slate-400">(imposée)</span>
        </div>
    @elseif (!empty($etape['ville']))
        <div class="mt-1 flex items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-3 py-2">
            <span class="text-sm font-medium text-slate-800">{{ $etape['ville'] }}</span>
            <button type="button" wire:click="{{ $mChangerVille }}({{ $i }})" class="text-xs text-brand hover:underline shrink-0 ml-2">changer</button>
        </div>
    @else
        <div class="relative mt-1">
            <input type="text" autocomplete="off"
                   wire:model.live.debounce.350ms="{{ $prefixe }}.{{ $i }}.ville_recherche"
                   placeholder="Ville (France ou Europe) — ex. Nevers, Milan…"
                   class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
            @if ($villeSuggestions !== [])
                <ul class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                    @foreach ($villeSuggestions as $k => $s)
                        <li>
                            <button type="button" wire:click="{{ $mVille }}({{ $i }}, {{ $k }})"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-green-50">{{ $s['label'] }}</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
    @error("$prefixe.$i.ville") <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @error("$prefixe.$i.latitude") <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

{{-- Adresse (rue et n°) — visible une fois la ville choisie --}}
@if ($locked || !empty($etape['ville']))
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600">
            Adresse (rue et n°)
            @if ($adresseRequise)<span class="text-red-500">*</span>
            @else <span class="font-normal text-slate-400">(facultatif)</span>@endif
        </label>
        @if ($locked)
            <div class="mt-1 rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600">
                {{ $etape['adresse'] ?: 'Ville seule' }} <span class="text-slate-400">(imposée)</span>
            </div>
        @elseif (!empty($etape['adresse']))
            <div class="mt-1 flex items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-3 py-2">
                <span class="text-sm font-medium text-slate-800">{{ $etape['adresse'] }}</span>
                <button type="button" wire:click="{{ $mChangerAdresse }}({{ $i }})" class="text-xs text-brand hover:underline shrink-0 ml-2">changer</button>
            </div>
        @else
            <div class="relative mt-1">
                <input type="text" autocomplete="off"
                       wire:model.live.debounce.350ms="{{ $prefixe }}.{{ $i }}.adresse_recherche"
                       placeholder="N° et nom de rue dans {{ $etape['ville'] }}…"
                       class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                @if ($adresseSuggestions !== [])
                    <ul class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                        @foreach ($adresseSuggestions as $k => $s)
                            <li>
                                <button type="button" wire:click="{{ $mAdresse }}({{ $i }}, {{ $k }})"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-green-50">{{ $s['label'] }}</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        @error("$prefixe.$i.adresse") <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @error("$prefixe.$i.adresse_validee") <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
@endif
