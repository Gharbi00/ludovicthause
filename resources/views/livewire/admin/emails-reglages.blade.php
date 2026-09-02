<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">Réglages</h1>
        <button wire:click="enregistrer" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Enregistrer</button>
    </div>

    <x-reglages-tabs />

    @if ($flash)
        <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">{{ $flash }}</div>
    @endif

    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Accusé de réception client</h2>
        <p class="mt-1 text-xs text-slate-400">Envoyé automatiquement au client après l'envoi de sa demande. Le devis, lui, est transmis depuis votre logiciel.</p>

        <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="actif" class="rounded border-slate-300 text-brand focus:ring-brand">
            Envoyer l'e-mail de confirmation au client
        </label>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600">Adresse expéditeur</label>
                <input type="email" wire:model="from_address" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                @error('from_address') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Nom expéditeur</label>
                <input type="text" wire:model="from_nom" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                @error('from_nom') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600">Objet</label>
                <input type="text" wire:model="sujet" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                @error('sujet') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600">Texte du message</label>
                <textarea wire:model="corps" rows="9" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand"></textarea>
                @error('corps') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-400">Variables disponibles : <code class="font-mono">{nom}</code> (nom du client), <code class="font-mono">{reference}</code> (référence de la demande).</p>
            </div>
        </div>

        <div class="mt-6 border-t border-slate-200 pt-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mode d'envoi</h3>
            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" wire:model.live="transport" value="sendmail" class="text-brand focus:ring-brand">
                    Sendmail (serveur local — simple, mais souvent en spam)
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" wire:model.live="transport" value="smtp" class="text-brand focus:ring-brand">
                    SMTP (boîte mail LWS — recommandé, arrive en boîte de réception)
                </label>
            </div>

            @if ($transport === 'smtp')
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Serveur SMTP</label>
                        <input type="text" wire:model="smtp_host" placeholder="mail.doliexpert.fr" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Port</label>
                            <input type="number" wire:model="smtp_port" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Chiffrement</label>
                            <select wire:model="smtp_encryption" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                                <option value="ssl">SSL (465)</option>
                                <option value="tls">TLS (587)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Identifiant (adresse complète)</label>
                        <input type="text" wire:model="smtp_username" placeholder="contact@doliexpert.fr" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Mot de passe de la boîte</label>
                        <input type="password" wire:model="smtp_password" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-400">Créez une boîte mail dans cPanel (ex. <code class="font-mono">contact@doliexpert.fr</code>), puis renseignez ici ses identifiants. L'adresse expéditeur ci-dessus doit correspondre à cette boîte.</p>
            @endif
        </div>

        <div class="mt-6 border-t border-slate-200 pt-4">
            <label class="block text-xs font-medium text-slate-600">Tester l'envoi (à une adresse de votre choix)</label>
            @if ($testInfo)
                <div class="mt-2 rounded-lg bg-green-50 p-2 text-xs text-brand ring-1 ring-green-200">{{ $testInfo }}</div>
            @endif
            <div class="mt-2 flex flex-wrap items-start gap-2">
                <input type="email" wire:model="testEmail" placeholder="votre@email.fr" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand focus:ring-brand">
                <button wire:click="envoyerTest" wire:target="envoyerTest" wire:loading.attr="disabled"
                        class="rounded-lg border border-green-300 px-4 py-2 text-sm font-medium text-brand hover:bg-green-50 disabled:opacity-50">
                    <span wire:loading.remove wire:target="envoyerTest">Envoyer un test</span>
                    <span wire:loading wire:target="envoyerTest">Envoi…</span>
                </button>
            </div>
            @error('testEmail') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
        </div>
    </section>
</div>
