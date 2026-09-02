<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.demandes') }}" class="text-sm text-slate-400 hover:text-slate-600">← Toutes les demandes</a>
            <h1 class="mt-1 flex items-center gap-3 text-2xl font-semibold text-slate-900">
                <span class="font-mono text-brand">{{ $demande->reference }}</span>
                <x-statut-badge :statut="$demande->statut" />
            </h1>
            <p class="text-sm text-slate-500">Reçue le {{ $demande->created_at->format('d/m/Y à H:i') }}</p>
        </div>
    </div>

    @if ($flash)
        <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">{{ $flash }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Colonne principale : voyage + étapes --}}
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Voyage demandé</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-3 text-sm">
                    <div>
                        <div class="text-slate-400">Catégorie</div>
                        <div class="font-medium text-slate-800">{{ $demande->categorie?->libelle ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Passagers</div>
                        <div class="font-medium text-slate-800">{{ $demande->nb_passagers }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Étapes</div>
                        <div class="font-medium text-slate-800">{{ $demande->etapes->count() }}</div>
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

                        <div class="flex justify-between pt-1"><span>Carburant</span><span>{{ $fmt($devis->cout_carburant) }} €</span></div>
                        @if ($veh && !empty($par['prixGasoil']))
                            <p class="-mt-1 text-[11px] text-slate-400">{{ $fmt($veh->conso_l_100km, 1) }} L/100 km × {{ $fmt($devis->distance_km, 0) }} km × {{ $fmt($par['prixGasoil'], 3) }} €/L</p>
                        @endif

                        <div class="flex justify-between"><span>Péage</span><span>{{ $fmt($devis->cout_peage) }} €</span></div>
                        @if ($kmPeage > 0 && $tarifKm > 0)
                            <p class="-mt-1 text-[11px] text-slate-400">{{ $fmt($kmPeage, 0) }} km à péage × {{ $fmt($tarifKm, 2) }} €/km @if($classe)(classe {{ $classe }})@endif</p>
                        @endif

                        @if ($devis->cout_vignettes > 0)
                            <div class="flex justify-between"><span>Vignettes</span><span>{{ $fmt($devis->cout_vignettes) }} €</span></div>
                        @endif

                        <div class="flex justify-between"><span>Chauffeur</span><span>{{ $fmt($devis->cout_chauffeur) }} €</span></div>
                        @if (!empty($par['tauxChauffeur']))
                            <p class="-mt-1 text-[11px] text-slate-400">
                                {{ $fmt($par['tauxChauffeur']) }} €/h × {{ $fmt($heuresCh, 1) }} h × {{ $devis->nb_chauffeurs }} chauffeur(s)
                                @if ($devis->nb_nuitees > 0)
                                    + {{ $fmt($par['fraisNuitee'] ?? 0, 0) }} € × {{ $devis->nb_nuitees }} nuitée(s) × {{ $devis->nb_chauffeurs }}
                                @endif
                                @if ($devis->nb_chauffeurs > 1)<br><span class="text-amber-600">2 chauffeurs imposés : conduite &gt; 9 h/jour (RSE 561/2006)</span>@endif
                            </p>
                        @endif

                        <div class="flex justify-between"><span>Charges fixes</span><span>{{ $fmt($devis->cout_charges_fixes) }} €</span></div>
                        @if ($veh)
                            <p class="-mt-1 text-[11px] text-slate-400">quote-part {{ $veh->libelle ?? 'véhicule' }} sur {{ $veh->jours_exploitation_an }} j/an @if(!empty($par['coefSaison']) && $par['coefSaison'] != 1) · coef. saison {{ $fmt($par['coefSaison'], 2) }}@endif</p>
                        @endif

                        <div class="flex justify-between"><span>Charges variables</span><span>{{ $fmt($devis->cout_charges_variables) }} €</span></div>
                        @if ($varKm > 0)
                            <p class="-mt-1 text-[11px] text-slate-400">{{ $fmt($varKm, 3) }} €/km (entretien, pneus, AdBlue…) × {{ $fmt($devis->distance_km, 0) }} km</p>
                        @endif
                        <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-slate-800">
                            <span>Coût de revient HT</span><span>{{ number_format($devis->cout_revient_ht,2,',',' ') }} €</span>
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
                            {{-- Devis pas encore validé : on explique au lieu de laisser un lien sans effet. --}}
                            <button type="button" wire:click="pdfAvantValidation"
                                    title="Validez le devis pour pouvoir l’éditer"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-400 hover:bg-slate-50">
                                📄 Devis PDF 🔒
                            </button>
                        @else
                            <a href="{{ route('admin.devis.pdf', $devis) }}" target="_blank"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                📄 Devis PDF
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
