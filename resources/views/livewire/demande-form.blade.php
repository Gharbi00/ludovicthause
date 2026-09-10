<div>
    @if ($submitted)
        {{-- ---------- Accusé de réception ---------- --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-8 text-center">
            <div class="mx-auto h-14 w-14 rounded-full bg-green-100 flex items-center justify-center text-3xl">✅</div>
            <h1 class="mt-4 text-2xl font-semibold text-slate-900">Demande envoyée&nbsp;!</h1>
            <p class="mt-2 text-slate-600">
                Merci, votre demande de devis a bien été enregistrée sous la référence
                <span class="font-mono font-semibold text-brand">{{ $reference }}</span>.
            </p>
            <p class="mt-1 text-slate-600">
                Notre équipe étudie votre trajet et vous recontacte rapidement avec une proposition chiffrée.
            </p>
            <button wire:click="$refresh" onclick="window.location.reload()"
                    class="mt-6 inline-flex items-center rounded-lg bg-brand px-5 py-2.5 text-white font-medium hover:bg-brand-dark">
                Faire une nouvelle demande
            </button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            @error('rate_limit')
                <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 ring-1 ring-red-200">{{ $message }}</div>
            @enderror

            {{-- ---------- 1. Le voyage ---------- --}}
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="text-brand">1.</span> Votre voyage
                </h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <span class="block text-sm font-medium text-slate-700">Mode de demande</span>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="radio" wire:model.live="mode" value="estimation" class="mt-1 text-brand focus:ring-brand">
                                <span><strong>Estimation</strong><br><small class="text-slate-500">Sans lieu obligatoire, estimation indicative non contractuelle.</small></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="radio" wire:model.live="mode" value="ferme" class="mt-1 text-brand focus:ring-brand">
                                <span><strong>Demande ferme</strong><br><small class="text-slate-500">Lieux et adresses obligatoires, sélectionnés dans la liste.</small></span>
                            </label>
                        </div>
                        @error('mode') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Nature de la prestation</label>
                        <select wire:model.live="nature_prestation"
                                class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                            <option value="">— Choisir une prestation —</option>
                            <option value="mariage">Mariage</option>
                            <option value="team_building">Team building</option>
                            <option value="voyage_organise">Voyage organisé</option>
                            <option value="sortie_scolaire">Sortie scolaire</option>
                            <option value="excursion">Excursion</option>
                            <option value="transfert">Transfert</option>
                            <option value="evenement">Événement</option>
                            <option value="autre">Autre</option>
                        </select>
                        @error('nature_prestation') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @if ($nature_prestation === 'autre')
                            <input type="text" wire:model.blur="nature_prestation_autre"
                                   placeholder="Précisez la nature de la prestation"
                                   class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                            @error('nature_prestation_autre') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @endif
                    </div>
                    <div class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">
                        @if ($mode === 'estimation')
                            Estimation indicative, non contractuelle. Les lieux sont facultatifs et pourront être ajoutés plus tard sans ressaisie.
                        @else
                            Demande ferme : sélectionnez chaque ville et chaque adresse dans les suggestions.
                        @endif
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Type de trajet</label>
                        <select wire:model.live="type_trajet" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                            <option value="simple">Aller simple</option>
                            <option value="journee">Aller-retour dans la journée</option>
                            <option value="multi_jours">Mise à disposition / voyage multi-jours</option>
                        </select>
                        @error('type_trajet') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nombre de passagers</label>
                        <input type="number" min="1" max="120" wire:model.live="nb_passagers"
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                        @error('nb_passagers') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Catégorie de véhicule <span class="text-slate-400 font-normal">(facultatif)</span></label>
                        <select wire:model="categorie_id"
                                class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                            <option value="">— Choisir —</option>
                            @foreach ($this->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->libelle }} ({{ $cat->capacite }} pl.)</option>
                            @endforeach
                        </select>
                        @error('categorie_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @if ($this->categories->isEmpty())
                            <p class="mt-1 text-xs text-amber-600">Aucune catégorie ne couvre ce nombre de passagers.</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- ---------- 2. Itinéraire ---------- --}}
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <span class="text-brand">2.</span> Itinéraire &amp; étapes
                    </h2>
                    <span class="text-xs text-slate-400">{{ count($etapes) }} étape(s)</span>
                </div>

                <div class="mt-4 space-y-4">
                    @foreach ($etapes as $i => $etape)
                        @php
                            $estPremier = $i === 0;
                            $estDernier = $i === count($etapes) - 1;
                            $estMilieu = ! $estPremier && ! $estDernier;
                        @endphp

                        @if ($estDernier)
                            {{-- Bouton d'ajout entre le départ (et étapes) et l'arrivée --}}
                            <div class="flex justify-center">
                                <button type="button" wire:click="ajouterEtape"
                                        class="inline-flex items-center gap-1 rounded-lg border border-dashed border-brand bg-white px-4 py-2 text-sm font-medium text-brand transition hover:bg-brand-dark hover:text-white hover:border-brand-dark">
                                    <span class="text-lg leading-none">+</span> Ajouter une étape intermédiaire
                                </button>
                            </div>
                        @endif

                        <div class="rounded-xl border border-slate-200 p-4" wire:key="etape-{{ $i }}">
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <span class="h-6 w-6 rounded-full bg-brand text-white text-xs flex items-center justify-center">{{ $i + 1 }}</span>
                                    @if ($estPremier) Départ
                                    @elseif ($estDernier) Arrivée
                                    @else Étape @endif
                                </span>
                                @if ($estMilieu)
                                    <div class="flex items-center gap-1">
                                        <button type="button" wire:click="monterEtape({{ $i }})" @disabled($i <= 1)
                                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent" title="Monter">↑</button>
                                        <button type="button" wire:click="descendreEtape({{ $i }})" @disabled($i >= count($etapes) - 2)
                                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent" title="Descendre">↓</button>
                                        <button type="button" wire:click="retirerEtape({{ $i }})" class="ml-2 text-xs text-red-500 hover:text-red-700">Retirer</button>
                                    </div>
                                @endif
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                @include('livewire.partials.etape-adresse', [
                                    'prefixe' => 'etapes', 'i' => $i, 'etape' => $etape,
                                    'locked' => false, 'adresseRequise' => $mode === 'ferme' && ($estPremier || $estDernier),
                                    'sugVille' => $suggestionsVille, 'sugAdresse' => $suggestionsAdresse,
                                    'mVille' => 'choisirVille', 'mAdresse' => 'choisirAdresse',
                                    'mChangerVille' => 'changerVille', 'mChangerAdresse' => 'changerAdresse',
                                ])
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">Date</label>
                                    <input type="date" wire:model{{ $estPremier ? '.live' : '' }}="etapes.{{ $i }}.date"
                                           class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                                    @error("etapes.$i.date") <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @if ($estPremier)
                                        <p class="mt-1 text-[11px] text-slate-400">Les étapes suivantes reprennent cette date par défaut.</p>
                                    @endif
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    @if (! $estPremier)
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Heure d'arrivée</label>
                                            <input type="time" wire:model="etapes.{{ $i }}.heure_arrivee"
                                                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                                        </div>
                                    @endif
                                    @if (! $estDernier)
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Heure de départ</label>
                                            <input type="time" wire:model="etapes.{{ $i }}.heure_depart"
                                                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                                        </div>
                                    @endif
                                </div>
                                <div class="sm:col-span-2 flex flex-wrap gap-x-6 gap-y-2 pt-1">
                                    @if (! $estPremier)
                                        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                                            <input type="checkbox" wire:model="etapes.{{ $i }}.arrivee_imperative"
                                                   class="rounded border-slate-300 text-brand focus:ring-brand">
                                            Horaire d'arrivée impératif
                                        </label>
                                    @endif
                                    @if (! $estDernier)
                                        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                                            <input type="checkbox" wire:model="etapes.{{ $i }}.depart_imperatif"
                                                   class="rounded border-slate-300 text-brand focus:ring-brand">
                                            Horaire de départ impératif
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ---------- 3. Retour ---------- --}}
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="text-brand">3.</span> Retour
                </h2>

                <div class="mt-4 space-y-2 text-sm">
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50">
                        <input type="radio" wire:model.live="retour_type" value="aucun" class="text-brand focus:ring-brand">
                        Pas de retour à prévoir
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50">
                        <input type="radio" wire:model.live="retour_type" value="meme" class="text-brand focus:ring-brand">
                        Retour par le même itinéraire (inversé)
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50">
                        <input type="radio" wire:model.live="retour_type" value="different" class="text-brand focus:ring-brand">
                        Retour par un itinéraire différent
                    </label>
                </div>

                @if ($retour_type !== 'aucun')
                    <p class="mt-4 mb-2 text-xs text-slate-400">
                        @if ($retour_type === 'meme') Même trajet inversé (villes imposées) — indiquez les dates/horaires du retour.
                        @else Complétez le retour ; ajoutez des étapes intermédiaires si besoin. @endif
                    </p>
                    <div class="space-y-4">
                        @foreach ($etapes_retour as $i => $etape)
                            @php $rPremier = $i === 0; $rDernier = $i === count($etapes_retour) - 1; @endphp
                            <div class="rounded-xl border border-slate-200 p-4" wire:key="retour-{{ $i }}">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <span class="h-6 w-6 rounded-full bg-brand text-white text-xs flex items-center justify-center">{{ $i + 1 }}</span>
                                        @if ($rPremier) Départ retour
                                        @elseif ($rDernier) Arrivée retour
                                        @else Étape @endif
                                    </span>
                                    @if (! ($etape['locked'] ?? false))
                                        <button type="button" wire:click="retirerEtapeRetour({{ $i }})" class="text-xs text-red-500 hover:text-red-700">Retirer</button>
                                    @endif
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    @include('livewire.partials.etape-adresse', [
                                        'prefixe' => 'etapes_retour', 'i' => $i, 'etape' => $etape,
                                        'locked' => (bool) ($etape['locked'] ?? false),
                                        'adresseRequise' => $mode === 'ferme' && ($rPremier || $rDernier),
                                        'sugVille' => $suggestionsVilleRetour, 'sugAdresse' => $suggestionsAdresseRetour,
                                        'mVille' => 'choisirVilleRetour', 'mAdresse' => 'choisirAdresseRetour',
                                        'mChangerVille' => 'changerVilleRetour', 'mChangerAdresse' => 'changerAdresseRetour',
                                    ])
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Date</label>
                                        <input type="date" wire:model="etapes_retour.{{ $i }}.date"
                                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                                        @error("etapes_retour.$i.date") <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        @if (! $rPremier)
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Heure d'arrivée</label>
                                                <input type="time" wire:model="etapes_retour.{{ $i }}.heure_arrivee"
                                                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                                            </div>
                                        @endif
                                        @if (! $rDernier)
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Heure de départ</label>
                                                <input type="time" wire:model="etapes_retour.{{ $i }}.heure_depart"
                                                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($retour_type === 'different')
                        <button type="button" wire:click="ajouterEtapeRetour"
                                class="mt-4 inline-flex items-center gap-1 rounded-lg border border-dashed border-brand bg-white px-4 py-2 text-sm font-medium text-brand transition hover:bg-brand-dark hover:text-white hover:border-brand-dark">
                            <span class="text-lg leading-none">+</span> Ajouter une étape au retour
                        </button>
                    @endif
                @endif
            </section>

            {{-- ---------- 4. Coordonnées ---------- --}}
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="text-brand">4.</span> Vos coordonnées
                </h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Nom / Organisation</label>
                        <input type="text" wire:model.blur="client_nom"
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                        @error('client_nom') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">E-mail</label>
                        <input type="email" wire:model.blur="client_email"
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                        @error('client_email') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Téléphone</label>
                        <input type="tel" wire:model.blur="client_telephone"
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Précisions (facultatif)</label>
                        <textarea wire:model.blur="commentaire" rows="3"
                                  class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand"></textarea>
                    </div>
                </div>

                {{-- Champ piège anti-robot : masqué aux humains --}}
                <div class="hidden" aria-hidden="true">
                    <label>Ne pas remplir <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <label class="mt-4 flex items-start gap-2 text-xs text-slate-600">
                    <input type="checkbox" wire:model="consentement_rgpd" class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand">
                    <span>J’accepte que mes données soient utilisées pour traiter ma demande de devis. Consultez notre <a href="/confidentialite" target="_blank" class="text-brand underline">politique de confidentialité</a>.</span>
                </label>
                @error('consentement_rgpd') <p data-erreur class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </section>

            @if ($errors->any() && ! $errors->has('rate_limit'))
                <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 ring-1 ring-red-200">
                    ⚠️ Votre demande est incomplète : les champs à corriger sont signalés en rouge ci-dessus.
                </div>
            @endif

            @if ($showRecap)
                <section class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">Récapitulatif de votre demande</h2>
                    <p class="mt-1 text-sm text-slate-600">Vérifiez les informations avant l’envoi. Vous pouvez revenir à chaque étape.</p>
                    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-400">Type</dt><dd class="font-medium">{{ ['simple'=>'Aller simple','journee'=>'Aller-retour dans la journée','multi_jours'=>'Mise à disposition / voyage multi-jours'][$type_trajet] }}</dd></div>
                        <div><dt class="text-slate-400">Prestation</dt><dd class="font-medium">{{ $nature_prestation === 'autre' ? $nature_prestation_autre : ucfirst(str_replace('_', ' ', $nature_prestation)) }}</dd></div>
                    </dl>
                    <div class="mt-4 space-y-2 text-sm">
                        @foreach ($etapes as $etape)
                            <div class="rounded-lg bg-white p-3">{{ $etape['ville'] ?: 'Lieu à préciser' }} @if($etape['adresse']) · {{ $etape['adresse'] }} @endif · {{ $etape['date'] ?: 'Date à préciser' }}</div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" wire:click="modifierRecap" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">Modifier</button>
                        <button type="button" wire:click="submit" class="rounded-lg bg-brand px-5 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Confirmer et envoyer</button>
                    </div>
                </section>
            @endif

            <div class="flex items-center justify-between gap-4">
                <p class="text-xs text-slate-400">Aucun tarif n'est affiché : notre équipe chiffre votre demande manuellement.</p>
                <button type="button" wire:click="ouvrirRecap"
                        class="inline-flex items-center rounded-lg bg-brand px-6 py-3 text-white font-semibold shadow-sm hover:bg-brand-dark disabled:opacity-50"
                        wire:loading.attr="disabled" @disabled($showRecap)>
                    <span wire:loading.remove wire:target="ouvrirRecap">Voir le récapitulatif</span>
                    <span wire:loading wire:target="ouvrirRecap">Vérification…</span>
                </button>
            </div>
        </form>

        @script
        <script>
            // À l'échec de validation, amener l'utilisateur au premier champ manquant.
            const aligner = (focus) => {
                const cible = document.querySelector('[data-erreur]');
                if (! cible) return;
                const y = window.scrollY + cible.getBoundingClientRect().top - 120;
                window.scrollTo(0, Math.max(0, y));
                if (focus) {
                    const champ = cible.closest('div')?.querySelector('input, select, textarea');
                    champ?.focus({ preventScroll: true });
                }
            };
            // Ré-alignement répété : la mise en page se stabilise après coup (Tailwind CDN).
            Livewire.on('formulaire-invalide', () => {
                aligner(true);
                let n = 0;
                const id = setInterval(() => { aligner(false); if (++n >= 6) clearInterval(id); }, 130);
            });
        </script>
        @endscript
    @endif
</div>
