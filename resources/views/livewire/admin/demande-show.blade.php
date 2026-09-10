<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.demandes') }}" class="text-sm text-slate-400 hover:text-slate-600">← Toutes les demandes</a>
            <h1 class="mt-1 flex items-center gap-3 text-2xl font-semibold text-slate-900">
                <span class="font-mono text-brand">{{ $demande->reference }}</span>
                <x-statut-badge :statut="$demande->statut" />
            </h1>
            <p class="text-sm text-slate-500">Reçue le {{ $demande->created_at->format('d/m/Y à H:i') }}</p>
            <p class="mt-1 text-sm font-medium {{ $demande->mode === 'estimation' ? 'text-amber-700' : 'text-brand' }}">
                {{ $demande->mode === 'estimation' ? 'Estimation indicative, non contractuelle' : 'Demande ferme' }}
                @if ($demande->nature_prestation) · {{ $demande->nature_prestation }} @endif
            </p>
            @if ($demande->mode === 'estimation')
                <button type="button" wire:click="convertirEnFerme" class="mt-2 rounded-lg bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">
                    Convertir en demande ferme
                </button>
            @endif
        </div>
    </div>

    @if ($flash)
        <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">{{ $flash }}</div>
    @endif

    @if ($devis?->cout_revient_ht > 0)
        @php $rse = $resume_rse; @endphp
        <div class="mb-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-4">
            <div class="flex flex-wrap items-center gap-4 text-xs font-medium">
                <span class="text-slate-400 uppercase tracking-wide">RSE</span>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full
                    @if($rse['statutAmplitude']==='vert') bg-green-100 text-green-700
                    @elseif($rse['statutAmplitude']==='orange') bg-orange-100 text-orange-700
                    @elseif($rse['statutAmplitude']==='rouge') bg-red-100 text-red-700
                    @else bg-slate-100 text-slate-500 @endif">
                    Amplitude {{ $rse['amplitude'] ? intdiv($rse['amplitude'],60).'h'.str_pad($rse['amplitude']%60,2,'0',STR_PAD_LEFT) : '—' }}
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full
                    @if($rse['statutTte']==='vert') bg-green-100 text-green-700
                    @elseif($rse['statutTte']==='orange') bg-orange-100 text-orange-700
                    @elseif($rse['statutTte']==='rouge') bg-red-100 text-red-700
                    @else bg-slate-100 text-slate-500 @endif">
                    TTE {{ $rse['tte'] ? intdiv($rse['tte'],60).'h'.str_pad($rse['tte']%60,2,'0',STR_PAD_LEFT) : '—' }}
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full
                    @if($rse['statutConduite']==='vert') bg-green-100 text-green-700
                    @elseif($rse['statutConduite']==='orange') bg-orange-100 text-orange-700
                    @elseif($rse['statutConduite']==='rouge') bg-red-100 text-red-700
                    @else bg-slate-100 text-slate-500 @endif">
                    Conduite {{ $rse['conduite'] ? intdiv($rse['conduite'],60).'h'.str_pad($rse['conduite']%60,2,'0',STR_PAD_LEFT) : '—' }}
                </span>
                <span class="text-slate-500">100 % {{ $rse['heures100'] ? intdiv($rse['heures100'],60).'h'.str_pad($rse['heures100']%60,2,'0',STR_PAD_LEFT) : '—' }}</span>
                <span class="text-slate-500">50 % {{ $rse['heures50'] ? intdiv($rse['heures50'],60).'h'.str_pad($rse['heures50']%60,2,'0',STR_PAD_LEFT) : '—' }}</span>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Colonne principale : voyage + étapes --}}
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Voyage demandé</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-3 text-sm">
                    <div>
                        <div class="text-slate-400">Type de trajet</div>
                        <div class="font-medium text-slate-800">{{ ['simple'=>'Aller simple','journee'=>'Aller-retour dans la journée','multi_jours'=>'Mise à disposition / voyage multi-jours'][$demande->type_trajet] ?? $demande->type_trajet }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Nature de la prestation</div>
                        <div class="font-medium text-slate-800">{{ $demande->nature_prestation ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Catégorie</div>
                        <div class="font-medium text-slate-800">{{ $demande->categorie?->libelle ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Passagers</div>
                        <div class="font-medium text-slate-800">{{ $demande->nb_passagers }}</div>
                    </div>
                </div>
                @if ($demande->commentaire)
                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                        <span class="text-slate-400">Précisions du client :</span> {{ $demande->commentaire }}
                    </div>
                @endif
            </section>

            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-4">Itinéraire</h2>
                <ol class="relative border-l border-slate-200 ml-3 space-y-5">
                    @foreach ($demande->etapes as $etape)
                        <li class="ml-5">
                            <span class="absolute -left-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand text-[10px] text-white">{{ $etape->ordre }}</span>
                            <div class="flex flex-wrap items-baseline gap-x-3">
                                <span class="font-medium text-slate-800">{{ $etape->libelle() }}</span>
                                <span class="text-sm text-slate-500">{{ $etape->date?->format('d/m/Y') }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                @if ($etape->heure_arrivee)
                                    <span>Arrivée {{ \Illuminate\Support\Str::of($etape->heure_arrivee)->substr(0,5) }}
                                        @if ($etape->arrivee_imperative)<span class="text-red-600 font-medium">(impératif)</span>@endif
                                    </span>
                                @endif
                                @if ($etape->heure_depart)
                                    <span>· Départ {{ \Illuminate\Support\Str::of($etape->heure_depart)->substr(0,5) }}
                                        @if ($etape->depart_imperatif)<span class="text-red-600 font-medium">(impératif)</span>@endif
                                    </span>
                                @endif
                            </div>
                            @if ($etape->lieu_libelle || $etape->latitude)
                                <div class="mt-2 rounded-lg bg-slate-50 p-3">
                                    <div class="text-xs font-medium text-slate-600">
                                        {{ $etape->lieu_libelle ?: $etape->libelle() }}
                                        @if ($etape->geocodage_source)
                                            <span class="ml-1 rounded bg-white px-1.5 py-0.5 text-[10px] text-slate-400">{{ $etape->geocodage_source }}</span>
                                        @endif
                                    </div>
                                     <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                         <input type="text" wire:model="lieuLibelle.{{ $etape->id }}" placeholder="Nom du lieu"
                                             class="rounded border-slate-300 text-xs shadow-sm">
                                         <input type="text" wire:model="lieuAdresse.{{ $etape->id }}" placeholder="Adresse normalisée"
                                             class="rounded border-slate-300 text-xs shadow-sm">
                                        <input type="text" wire:model="lieuAcces.{{ $etape->id }}" placeholder="Accès / dépose"
                                               class="rounded border-slate-300 text-xs shadow-sm">
                                        <input type="text" wire:model="lieuContact.{{ $etape->id }}" placeholder="Contact sur place"
                                               class="rounded border-slate-300 text-xs shadow-sm">
                                        <input type="text" wire:model="lieuCommentaire.{{ $etape->id }}" placeholder="Commentaire"
                                               class="rounded border-slate-300 text-xs shadow-sm">
                                    </div>
                                    <button type="button" wire:click="enregistrerEnrichissementLieu({{ $etape->id }})"
                                            class="mt-2 rounded border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-white">
                                        Enregistrer les informations du lieu
                                    </button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- Colonne latérale : client + affectation --}}
        <div class="space-y-6">
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Client</h2>
                <div class="mt-3 space-y-1 text-sm">
                    <div class="font-medium text-slate-800">{{ $demande->client_nom }}</div>
                    <div><a href="mailto:{{ $demande->client_email }}" class="text-brand hover:underline">{{ $demande->client_email }}</a></div>
                    @if ($demande->client_telephone)
                        <div class="text-slate-600">{{ $demande->client_telephone }}</div>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-1">Affectation du véhicule</h2>
                <p class="text-xs text-slate-400 mb-3">
                    Déplacement du {{ $fenetre[0]->format('d/m/Y') }} au {{ $fenetre[1]->format('d/m/Y') }}
                </p>

                @if ($this->vehicules->isEmpty())
                    <p class="rounded-lg bg-amber-50 p-3 text-xs text-amber-700">
                        Aucun véhicule actif dans cette catégorie.
                    </p>
                @else
                    <div class="space-y-3">
                        <select wire:model="vehicule_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                            <option value="">— Choisir un véhicule —</option>
                            @foreach ($this->vehicules as $v)
                                <option value="{{ $v->id }}">
                                    {{ $v->immatriculation }} · {{ $v->nb_places }} places @if($this->conflitsPour($v->id)) ⚠ conflit @endif
                                </option>
                            @endforeach
                        </select>
                        @error('vehicule_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        @if ($vehicule_id && $this->conflitsPour($vehicule_id))
                            <div class="rounded-lg bg-red-50 p-3 text-xs text-red-700 ring-1 ring-red-200">
                                ⚠ Ce véhicule a une occupation qui chevauche ces dates (voir planning). Vous pouvez tout de même l'affecter.
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <button wire:click="affecter" wire:loading.attr="disabled"
                                    class="flex-1 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50">
                                {{ $devis?->vehicule_id ? 'Modifier l\'affectation' : 'Affecter' }}
                            </button>
                            @if ($devis?->vehicule_id)
                                <button wire:click="retirerAffectation"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-500 hover:bg-slate-50">
                                    Retirer
                                </button>
                            @endif
                        </div>
                    </div>

                    @if ($devis?->vehicule)
                        <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm">
                            <div class="text-xs text-brand uppercase tracking-wide">Véhicule affecté</div>
                            <div class="font-medium text-brand-dark">{{ $devis->vehicule->immatriculation }}</div>
                            <div class="text-xs text-brand">Parc {{ $devis->vehicule->numero_parc }} · {{ $devis->vehicule->nb_places }} places · devis {{ $devis->reference }} ({{ $devis->statut }})</div>
                        </div>
                    @endif
                @endif
            </section>
        </div>
    </div>

    {{-- ---------- Chiffrage (Phase 4) ---------- --}}
    @if ($devis?->vehicule_id)
        <section class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Chiffrage</h2>
                <button wire:click="calculer" wire:target="calculer" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50">
                    <span wire:loading.remove wire:target="calculer">{{ $devis->cout_revient_ht > 0 ? 'Recalculer' : 'Calculer le coût' }}</span>
                    <span wire:loading wire:target="calculer">Calcul…</span>
                </button>
            </div>

            @if ($erreur)
                <div class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">{{ $erreur }}</div>
            @endif

            @if ($devis->cout_revient_ht > 0)
                @php $source = $devis->calcul_payload['source_itineraire'] ?? 'estimation'; @endphp
                @if ($source === 'estimation')
                    <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-700 ring-1 ring-amber-200">
                        ⚠ <strong>Distances approchées et péages estimés</strong> (aucune API de routage configurée). À valider avant tout devis ferme.
                    </div>
                @elseif ($source === 'ors')
                    <div class="mt-3 rounded-lg bg-green-50 p-3 text-xs text-brand ring-1 ring-green-200">
                        ✔ Distances, durées et <strong>km à péage réels</strong> (OpenRouteService). Péage calculé au <strong>tarif de la classe autocar</strong> (paramétrable en Réglages) — précis en France ; à l'étranger, tarif approché et <strong>vignettes non incluses</strong>.
                    </div>
                @endif

                <div class="mt-5 grid gap-8 lg:grid-cols-2">
                    {{-- Détail des postes --}}
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between text-xs text-slate-400 uppercase tracking-wide">
                            <span>Poste</span><span>Montant HT</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1 text-slate-500">
                            <span>Distance</span>
                            <span>{{ number_format($devis->distance_km, 0, ',', ' ') }} km
                                <span class="text-slate-400">({{ number_format($devis->distance_km_charge,0,',',' ') }} chargés / {{ number_format($devis->distance_km_vide,0,',',' ') }} à vide)</span>
                            </span>
                        </div>
                        @php $dDeb = $demande->etapes->min('date'); $dFin = $demande->etapes->max('date'); @endphp
                        <div class="flex justify-between border-b border-slate-100 pb-1 text-slate-500">
                            <span>Dates</span>
                            <span>@if($dDeb && $dFin && $dDeb->ne($dFin))du {{ $dDeb->format('d/m/Y') }} au {{ $dFin->format('d/m/Y') }}@elseif($dDeb)le {{ $dDeb->format('d/m/Y') }}@else—@endif</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1 text-slate-500">
                            <span>Conduite / config.</span>
                            <span>{{ intdiv($devis->duree_conduite_minutes,60) }}h{{ str_pad($devis->duree_conduite_minutes%60,2,'0',STR_PAD_LEFT) }}
                                · {{ $devis->nb_chauffeurs }} chauffeur(s)@if($devis->nb_nuitees) · {{ $devis->nb_nuitees }} nuitée(s)@endif
                            </span>
                        </div>
                        @php $grilleRse = $devis->calcul_payload['rse']['grille_journaliere'] ?? []; @endphp
                        @php $postesParJour = $devis->postes->groupBy(fn ($p) => $p->date->format('Y-m-d')); @endphp
                        @if ($grilleRse)
                            <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-slate-50 text-left text-slate-500">
                                        <tr>
                                            <th class="px-2 py-2">Jour</th>
                                            <th class="px-2 py-2">100 %</th>
                                            <th class="px-2 py-2">50 %</th>
                                            <th class="px-2 py-2">TTE</th>
                                            <th class="px-2 py-2">Amplitude</th>
                                            <th class="px-2 py-2">RSE</th>
                                            <th class="px-2 py-2">Postes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $total100 = 0; $total50 = 0; $totalTte = 0; @endphp
                                        @foreach ($grilleRse as $jour)
                                            @php
                                                $formatMin = fn ($minutes) => $minutes === null ? '—' : intdiv($minutes, 60) . 'h' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
                                                $total100 += $jour['heures_100_min'];
                                                $total50 += $jour['heures_50_min'];
                                                $totalTte += $jour['tte_min'];
                                                $postesJour = $postesParJour->get(Carbon\Carbon::parse($jour['date'])->format('Y-m-d'), collect());
                                            @endphp
                                            <tr class="border-t border-slate-100">
                                                <td class="px-2 py-2">{{ Carbon\Carbon::parse($jour['date'])->format('d/m/Y') }}</td>
                                                <td class="px-2 py-2">{{ $formatMin($jour['heures_100_min']) }}</td>
                                                <td class="px-2 py-2">{{ $formatMin($jour['heures_50_min']) }}</td>
                                                <td class="px-2 py-2">{{ $formatMin($jour['tte_min']) }}</td>
                                                <td class="px-2 py-2">{{ $formatMin($jour['amplitude_min']) }}</td>
                                                <td class="px-2 py-2 @if($jour['relais_necessaire']) text-red-600 font-semibold @else text-slate-500 @endif">
                                                    @if ($jour['relais_necessaire']) Relais nécessaire
                                                    @else {{ $jour['statut'] }} @endif
                                                </td>
                                                <td class="px-2 py-2">
                                                    @if($postesJour->isNotEmpty())
                                                        <table class="text-xs w-full">
                                                            @foreach($postesJour as $p)
                                                                <tr>
                                                                    <td class="py-0.5">{{ $p->type }}</td>
                                                                    <td class="py-0.5">{{ $p->heure_debut ? substr($p->heure_debut, 0, 5) : '—' }}</td>
                                                                    <td class="py-0.5">{{ $p->heure_fin ? substr($p->heure_fin, 0, 5) : '—' }}</td>
                                                                    <td class="py-0.5">{{ $p->duree_min ? $formatMin($p->duree_min) : '—' }}</td>
                                                                    <td class="py-0.5 text-right">
                                                                        <button wire:click="supprimerPoste({{ $p->id }})" class="text-red-500 hover:text-red-700">×</button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </table>
                                                    @else
                                                        <span class="text-slate-400">—</span>
                                                    @endif
                                                    <button wire:click="ouvrirAjoutPoste('{{ $jour['date'] }}')" class="mt-1 text-xs text-brand hover:underline">+ Ajouter</button>
                                                    @if($editingPosteDate === $jour['date'])
                                                        <div class="mt-1 p-2 bg-slate-50 rounded border">
                                                            <select wire:model="poste_type" class="text-xs border rounded mb-1">
                                                                <option value="prise_service">Prise</option>
                                                                <option value="conduite">Conduite</option>
                                                                <option value="attente">Attente</option>
                                                                <option value="fin_service">Fin</option>
                                                            </select>
                                                            <input type="time" wire:model="poste_heure_debut" class="text-xs border rounded mb-1 w-full">
                                                            <input type="time" wire:model="poste_heure_fin" class="text-xs border rounded mb-1 w-full">
                                                            <select wire:model="poste_taux" class="text-xs border rounded mb-1">
                                                                <option value="100">100%</option>
                                                                <option value="50">50%</option>
                                                            </select>
                                                            <button wire:click="ajouterPoste" class="text-xs bg-brand text-white px-2 py-1 rounded">OK</button>
                                                            <button wire:click="fermerAjoutPoste" class="text-xs text-slate-500 px-2 py-1">✕</button>
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr class="border-t-2 border-slate-300 font-semibold text-slate-800">
                                            <td class="px-2 py-2">Cumul</td>
                                            <td class="px-2 py-2">{{ $formatMin($total100) }}</td>
                                            <td class="px-2 py-2">{{ $formatMin($total50) }}</td>
                                            <td class="px-2 py-2">{{ $formatMin($totalTte) }}</td>
                                            <td class="px-2 py-2" colspan="3"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">100 % = conduite, 50 % = attente. Amplitude contrôlée à 13 h ; horaires incomplets à compléter.</p>
                        @endif

                        {{-- Surcharges manuelles km / durées (REQ-S-06, REQ-S-10) --}}
                        @php $d = $devis; @endphp
                        <div class="mt-4 rounded-lg border border-slate-200 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Surcharges manuelles (km / durées)</p>
                            <div class="grid gap-2 sm:grid-cols-3 text-xs">
                                <div>
                                    <label class="block text-slate-600">Distance (km)</label>
                                    <input type="number" step="0.1" wire:model="edit_distance_km" value="{{ $d->distance_km }}" class="mt-1 w-full rounded border-slate-300 text-sm">
                                    @if($d->original_distance_km !== null)<span class="text-slate-400">origine : {{ number_format($d->original_distance_km,2,',',' ') }}</span>@endif
                                </div>
                                <div>
                                    <label class="block text-slate-600">Dont chargés (km)</label>
                                    <input type="number" step="0.1" wire:model="edit_distance_km_charge" value="{{ $d->distance_km_charge }}" class="mt-1 w-full rounded border-slate-300 text-sm">
                                    @if($d->original_distance_km_charge !== null)<span class="text-slate-400">origine : {{ number_format($d->original_distance_km_charge,2,',',' ') }}</span>@endif
                                </div>
                                <div>
                                    <label class="block text-slate-600">Dont à vide (km)</label>
                                    <input type="number" step="0.1" wire:model="edit_distance_km_vide" value="{{ $d->distance_km_vide }}" class="mt-1 w-full rounded border-slate-300 text-sm">
                                    @if($d->original_distance_km_vide !== null)<span class="text-slate-400">origine : {{ number_format($d->original_distance_km_vide,2,',',' ') }}</span>@endif
                                </div>
                                <div>
                                    <label class="block text-slate-600">Conduite (min)</label>
                                    <input type="number" wire:model="edit_duree_conduite_minutes" value="{{ $d->duree_conduite_minutes }}" class="mt-1 w-full rounded border-slate-300 text-sm">
                                    @if($d->original_duree_conduite_minutes !== null)<span class="text-slate-400">origine : {{ $d->original_duree_conduite_minutes }}</span>@endif
                                </div>
                                <div>
                                    <label class="block text-slate-600">Attente (min)</label>
                                    <input type="number" wire:model="edit_temps_attente_minutes" value="{{ $d->temps_attente_minutes }}" class="mt-1 w-full rounded border-slate-300 text-sm">
                                    @if($d->original_temps_attente_minutes !== null)<span class="text-slate-400">origine : {{ $d->original_temps_attente_minutes }}</span>@endif
                                </div>
                                <div>
                                    <label class="block text-slate-600">Raison (traçabilité)</label>
                                    <input type="text" wire:model="override_raison" placeholder="Ex. ajustement client" class="mt-1 w-full rounded border-slate-300 text-sm">
                                </div>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <button wire:click="appliquerOverride" class="rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-dark">Appliquer</button>
                                <button wire:click="reinitialiserOverride" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">Réinitialiser</button>
                            </div>
                            @if($d->override_author)
                                <p class="mt-1 text-[10px] text-red-600">Override par {{ $d->override_author }} le {{ \Carbon\Carbon::parse($d->override_at)->format('d/m/Y H:i') }}</p>
                            @endif
                        </div>

                        {{-- Détail poste par poste du temps de service (REQ-S-04) --}}
                        @php $detailTS = $detail_temps_service; @endphp
                        @if ($detailTS)
                            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-slate-50 text-left text-slate-500">
                                        <tr><th class="px-2 py-2">#</th><th>Poste</th><th>Date</th><th>Début</th><th>Fin</th><th>Durée</th><th>Taux</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detailTS as $poste)
                                            <tr class="border-t border-slate-100">
                                                <td class="px-2 py-2">{{ $poste['ordre'] }}</td>
                                                <td class="px-2 py-2">{{ $poste['libelle'] }}</td>
                                                <td class="px-2 py-2">{{ $poste['date'] }}</td>
                                                <td class="px-2 py-2">{{ $poste['debut'] }}</td>
                                                <td class="px-2 py-2">{{ $poste['fin'] }}</td>
                                                <td class="px-2 py-2">{{ $poste['duree_min'] ? intdiv($poste['duree_min'],60).'h'.str_pad($poste['duree_min']%60,2,'0',STR_PAD_LEFT) : '—' }}</td>
                                                <td class="px-2 py-2">{{ $poste['taux'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">Temps de service poste par poste. 100 % = conduite effective, 50 % = attente / disponibilité. Document interne uniquement.</p>
                        @endif
                        @php
                            $it   = $devis->calcul_payload['itineraire'] ?? [];
                            $par  = $devis->calcul_payload['parametres'] ?? [];
                            $veh  = $devis->vehicule;
                            $kmPeage  = (float) ($it['km_a_peage'] ?? 0);
                            $tarifKm  = (float) ($it['tarif_peage_km'] ?? 0);
                            $classe   = $it['classe_peage'] ?? null;
                            $partAuto = $devis->distance_km > 0 ? $kmPeage / $devis->distance_km * 100 : 0;
                            $heuresCh = ($devis->duree_conduite_minutes + $devis->temps_attente_minutes) / 60;
                            $varKm    = $veh ? ($veh->cout_entretien_km + $veh->cout_pneus_km + $veh->cout_adblue_km + $veh->autres_variables_km) : 0;
                            $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', ' ');
                        @endphp

                        @if (($it['km_a_peage'] ?? null) !== null)
                            <div class="flex justify-between border-b border-slate-100 pb-1 text-slate-500">
                                <span>Dont autoroute à péage</span>
                                <span>{{ $fmt($kmPeage, 0) }} km <span class="text-slate-400">({{ $fmt($partAuto, 0) }} % du trajet)</span></span>
                            </div>
                        @endif

                        <div class="flex justify-between pt-1 items-center gap-2">
                            <span>Carburant</span>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" wire:model="edit_cout_carburant" class="w-24 rounded border-slate-300 text-xs py-1" placeholder="{{ $fmt($devis->cout_carburant) }}">
                                <span class="text-xs text-slate-500">{{ $fmt($devis->cout_carburant) }} €</span>
                            </div>
                        </div>
                        @if ($veh && !empty($par['prixGasoil']))
                            <p class="-mt-1 text-[11px] text-slate-400">{{ $fmt($veh->conso_l_100km, 1) }} L/100 km × {{ $fmt($devis->distance_km, 0) }} km × {{ $fmt($par['prixGasoil'], 3) }} €/L</p>
                        @endif

                        <div class="flex justify-between items-center gap-2">
                            <span>Péage</span>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" wire:model="edit_cout_peage" class="w-24 rounded border-slate-300 text-xs py-1" placeholder="{{ $fmt($devis->cout_peage) }}">
                                <span class="text-xs text-slate-500">{{ $fmt($devis->cout_peage) }} €</span>
                            </div>
                        </div>
                        @if ($kmPeage > 0 && $tarifKm > 0)
                            <p class="-mt-1 text-[11px] text-slate-400">{{ $fmt($kmPeage, 0) }} km à péage × {{ $fmt($tarifKm, 2) }} €/km @if($classe)(classe {{ $classe }})@endif</p>
                        @endif

                        @if ($devis->cout_vignettes > 0 || $edit_cout_vignettes !== null)
                            <div class="flex justify-between items-center gap-2">
                                <span>Vignettes</span>
                                <div class="flex items-center gap-2">
                                    <input type="number" step="0.01" wire:model="edit_cout_vignettes" class="w-24 rounded border-slate-300 text-xs py-1" placeholder="{{ $fmt($devis->cout_vignettes) }}">
                                    <span class="text-xs text-slate-500">{{ $fmt($devis->cout_vignettes) }} €</span>
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-between items-center gap-2">
                            <span>Chauffeur</span>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" wire:model="edit_cout_chauffeur" class="w-24 rounded border-slate-300 text-xs py-1" placeholder="{{ $fmt($devis->cout_chauffeur) }}">
                                <span class="text-xs text-slate-500">{{ $fmt($devis->cout_chauffeur) }} €</span>
                            </div>
                        </div>
                        @if (!empty($par['tauxChauffeur']))
                            <p class="-mt-1 text-[11px] text-slate-400">
                                {{ $fmt($par['tauxChauffeur']) }} €/h × {{ $fmt($heuresCh, 1) }} h × {{ $devis->nb_chauffeurs }} chauffeur(s)
                                @if ($devis->nb_nuitees > 0)
                                    + {{ $fmt($par['fraisNuitee'] ?? 0, 0) }} € × {{ $devis->nb_nuitees }} nuitée(s) × {{ $devis->nb_chauffeurs }}
                                @endif
                                @if ($devis->nb_chauffeurs > 1)<br><span class="text-amber-600">2 chauffeurs imposés : conduite &gt; 9 h/jour (RSE 561/2006)</span>@endif
                            </p>
                        @endif

                        <div class="flex justify-between items-center gap-2">
                            <span>Charges fixes</span>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" wire:model="edit_cout_charges_fixes" class="w-24 rounded border-slate-300 text-xs py-1" placeholder="{{ $fmt($devis->cout_charges_fixes) }}">
                                <span class="text-xs text-slate-500">{{ $fmt($devis->cout_charges_fixes) }} €</span>
                            </div>
                        </div>
                        @if ($veh)
                            <p class="-mt-1 text-[11px] text-slate-400">quote-part {{ $veh->libelle ?? 'véhicule' }} sur {{ $veh->jours_exploitation_an }} j/an @if(!empty($par['coefSaison']) && $par['coefSaison'] != 1) · coef. saison {{ $fmt($par['coefSaison'], 2) }}@endif</p>
                        @endif

                        <div class="flex justify-between items-center gap-2">
                            <span>Charges variables</span>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" wire:model="edit_cout_charges_variables" class="w-24 rounded border-slate-300 text-xs py-1" placeholder="{{ $fmt($devis->cout_charges_variables) }}">
                                <span class="text-xs text-slate-500">{{ $fmt($devis->cout_charges_variables) }} €</span>
                            </div>
                        </div>
                        @if ($varKm > 0)
                            <p class="-mt-1 text-[11px] text-slate-400">{{ $fmt($varKm, 3) }} €/km (entretien, pneus, AdBlue…) × {{ $fmt($devis->distance_km, 0) }} km</p>
                        @endif
                        <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-slate-800">
                            <span>Coût de revient HT</span><span>{{ number_format($devis->cout_revient_ht,2,',',' ') }} €</span>
                        </div>
                        @if($d->override_author)
                            <p class="mt-1 text-[10px] text-red-600">Override coûts par {{ $d->override_author }} le {{ \Carbon\Carbon::parse($d->override_at)->format('d/m/Y H:i') }}</p>
                        @endif
                        <div class="mt-2 flex gap-2">
                            <button wire:click="appliquerOverrideCouts" class="rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-dark">Appliquer les coûts</button>
                            <button wire:click="reinitialiserOverrideCouts" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">Réinitialiser coûts</button>
                        </div>
                    </div>

                    {{-- Marge + totaux (garde-fou) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Marge : <span class="font-semibold text-brand">{{ number_format($marge_taux,0,',',' ') }} %</span></label>
                        <input type="range" min="0" max="50" step="1" wire:model.live="marge_taux" class="mt-2 w-full accent-brand">

                        @php
                            $supplements = $devis->totalLignesLibres();
                            $htTransport = (float) $devis->montant_ht - $supplements;
                        @endphp
                        <div class="mt-4 space-y-1.5 text-sm">
                            @if ($supplements != 0)
                                <div class="flex justify-between"><span class="text-slate-500">Transport HT</span><span>{{ number_format($htTransport,2,',',' ') }} €</span></div>
                                <div class="flex justify-between"><span class="text-slate-500">Prestations suppl. HT</span><span>{{ number_format($supplements,2,',',' ') }} €</span></div>
                            @endif
                            <div class="flex justify-between"><span class="text-slate-500">Montant HT</span><span class="font-medium">{{ number_format($devis->montant_ht,2,',',' ') }} €</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">TVA ({{ number_format($devis->taux_tva,0,',',' ') }} %)</span><span>{{ number_format($devis->montant_tva,2,',',' ') }} €</span></div>
                            <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-bold text-slate-900"><span>Total TTC</span><span>{{ number_format($devis->montant_ttc,2,',',' ') }} €</span></div>
                        </div>

                        {{-- Prestations supplémentaires (lignes libres) --}}
                        <div class="mt-5 rounded-lg border border-slate-200 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prestations supplémentaires</p>

                            @if (! empty($devis->lignes_libres))
                                <div class="mt-2 space-y-1 text-sm">
                                    @foreach ($devis->lignes_libres as $i => $ligne)
                                        <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1">
                                            <span class="text-slate-700">{{ $ligne['libelle'] }}</span>
                                            <span class="flex items-center gap-2 shrink-0">
                                                <span class="font-medium">{{ number_format((float) $ligne['montant'],2,',',' ') }} €</span>
                                                <button wire:click="retirerLigne({{ $i }})" title="Retirer"
                                                        class="text-xs text-red-500 hover:text-red-700">✕</button>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-1 text-xs text-slate-400">Aucune. Ajoutez ici toute prestation à facturer en plus du transport (guide, parking, repas…).</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-start gap-2">
                                <div class="min-w-[9rem] flex-1">
                                    <input type="text" wire:model="ligneLibelle" placeholder="Libellé (ex. Parking)"
                                           class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                    @error('ligneLibelle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="w-28">
                                    <input type="number" step="0.01" wire:model="ligneMontant" placeholder="€ HT"
                                           class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                    @error('ligneMontant') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <button wire:click="ajouterLigne"
                                        class="rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Ajouter</button>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-400">Facturées telles quelles (sans marge), soumises à la TVA. Elles apparaissent sur le devis PDF.</p>
                        </div>

                        @php $perte = $devis->marge_montant <= 0; @endphp
                        <div class="mt-4 rounded-lg p-3 text-sm {{ $perte ? 'bg-red-50 text-red-800 ring-1 ring-red-200' : 'bg-green-50 text-green-800 ring-1 ring-green-200' }}">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ $perte ? '⚠ PERTE' : 'Marge dégagée' }}</span>
                                <span class="text-lg font-bold">{{ number_format($devis->marge_montant,2,',',' ') }} €</span>
                            </div>
                            <p class="mt-1 text-xs opacity-80">Seuil de rentabilité (coût de revient) : {{ number_format($devis->cout_revient_ht,2,',',' ') }} € HT</p>
                        </div>
                    </div>
                </div>

                {{-- Cycle du devis + PDF --}}
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-slate-500">Statut du devis :</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ ['brouillon'=>'Brouillon','valide'=>'Validé','envoye'=>'Envoyé','accepte'=>'Accepté','refuse'=>'Refusé'][$devis->statut] ?? ucfirst($devis->statut) }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.devis.carte', $devis) }}" target="_blank"
                           class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            🗺️ Ouvrir sur la carte
                        </a>
                        @if ($devis->statut === 'brouillon')
                            <button type="button" wire:click="pdfAvantValidation"
                                    title="Validez le devis pour pouvoir l'éditer"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-400 hover:bg-slate-50">
                                📄 Devis PDF 🔒
                            </button>
                        @else
                            <a href="{{ route('admin.devis.pdf', $devis) }}" target="_blank"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                📄 Devis PDF
                            </a>
                        @endif
                        @if ($devis->cout_revient_ht > 0)
                            <a href="{{ route('admin.devis.interne', $devis) }}" target="_blank"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                📄 Document interne
                            </a>
                            <a href="{{ route('admin.devis.csv', $devis) }}"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                📥 CSV
                            </a>
                        @endif
                        @if ($devis->statut === 'brouillon')
                            <button wire:click="valider" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Valider le devis</button>
                        @elseif ($devis->statut === 'valide')
                            <button wire:click="envoyer" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Marquer comme envoyé</button>
                        @elseif ($devis->statut === 'envoye')
                            <button wire:click="accepter" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Accepté</button>
                            <button wire:click="refuser" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Refusé</button>
                        @endif
                    </div>
                </div>
            @else
                <p class="mt-3 text-sm text-slate-400">Lancez le calcul pour obtenir le détail des coûts, la marge et le seuil de rentabilité.</p>
            @endif
        </section>
    @endif
</div>
