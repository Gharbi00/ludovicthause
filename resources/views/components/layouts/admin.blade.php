<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Espace secrétaire' }} — LTT</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ltt.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: {
            brand: { DEFAULT: '#15663a', dark: '#0c3d20', light: '#7cb342' }
        } } } }
    </script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @livewireStyles
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <div class="min-h-full">
        <nav class="bg-brand-dark text-white" x-data="{ navOuvert: false }">
            <div class="mx-auto max-w-6xl px-4">
                <div class="flex h-16 items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-3 sm:gap-6">
                        <a href="{{ route('admin.demandes') }}" title="Accueil — Ludovic Thause Tourisme"
                           class="inline-flex shrink-0 items-center">
                            <span class="inline-flex rounded-md bg-white px-2 py-1">
                                <img src="{{ asset('images/logo-ltt.png') }}" alt="Ludovic Thause Tourisme" class="block h-6 w-auto sm:h-7">
                            </span>
                        </a>
                        {{-- Liens de navigation : affichés en ligne sur écran moyen et plus --}}
                        <div class="hidden items-center gap-6 md:flex">
                            <a href="{{ route('admin.demandes') }}"
                               class="text-sm {{ request()->routeIs('admin.demande*') ? 'text-white font-medium' : 'text-white/70 hover:text-white' }}">
                                Demandes
                            </a>
                            @if (auth()->user()?->isAdmin())
                                <a href="{{ route('admin.reglages') }}"
                                   class="text-sm {{ request()->routeIs('admin.reglages*') ? 'text-white font-medium' : 'text-white/70 hover:text-white' }}">
                                    Réglages
                                </a>
                            @endif
                            <a href="{{ route('admin.aide') }}" target="_blank" rel="noopener"
                               class="text-sm text-white/70 hover:text-white">
                                Mode d'emploi ↗
                            </a>
                            <a href="{{ route('admin.motdepasse') }}"
                               class="text-sm {{ request()->routeIs('admin.motdepasse') ? 'text-white font-medium' : 'text-white/70 hover:text-white' }}">
                                Mon mot de passe
                            </a>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 text-sm sm:gap-4">
                        <span class="hidden text-white/70 sm:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-md bg-white/15 px-3 py-1.5 hover:bg-white/25">Déconnexion</button>
                        </form>
                        {{-- Bouton hamburger (mobile uniquement) --}}
                        <button type="button"
                                class="inline-flex items-center justify-center rounded-md p-2 text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50 md:hidden"
                                @click="navOuvert = ! navOuvert"
                                :aria-expanded="navOuvert ? 'true' : 'false'"
                                aria-controls="menu-navigation-mobile"
                                aria-label="Ouvrir ou fermer le menu">
                            <svg x-show="! navOuvert" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg x-show="navOuvert" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                {{-- Menu mobile : liens empilés sous la barre (affiché via le bouton hamburger) --}}
                <div id="menu-navigation-mobile"
                     x-show="navOuvert"
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="space-y-1 px-1 pb-4 md:hidden">
                    <a href="{{ route('admin.demandes') }}" @click="navOuvert = false"
                       class="block rounded-md px-3 py-2 text-sm {{ request()->routeIs('admin.demande*') ? 'bg-white/10 font-medium text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        Demandes
                    </a>
                    @if (auth()->user()?->isAdmin())
                        <a href="{{ route('admin.reglages') }}" @click="navOuvert = false"
                           class="block rounded-md px-3 py-2 text-sm {{ request()->routeIs('admin.reglages*') ? 'bg-white/10 font-medium text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                            Réglages
                        </a>
                    @endif
                    <a href="{{ route('admin.aide') }}" target="_blank" rel="noopener" @click="navOuvert = false"
                       class="block rounded-md px-3 py-2 text-sm text-white/70 hover:bg-white/10 hover:text-white">
                        Mode d'emploi ↗
                    </a>
                    <a href="{{ route('admin.motdepasse') }}" @click="navOuvert = false"
                       class="block rounded-md px-3 py-2 text-sm {{ request()->routeIs('admin.motdepasse') ? 'bg-white/10 font-medium text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        Mon mot de passe
                    </a>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">
                    {{ session('status') }}
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>

    <x-demo-notice area="backend" />

    <script>
        // Bascule afficher/masquer pour les champs mot de passe (bouton œil).
        function basculerMdp(btn) {

            const champ = btn.parentElement.querySelector('input');
            if (! champ) return;
            const oeil = btn.querySelector('[data-oeil]');
            const oeilBarre = btn.querySelector('[data-oeil-off]');
            const masque = champ.type === 'password';
            champ.type = masque ? 'text' : 'password';
            oeil.classList.toggle('hidden', masque);
            oeilBarre.classList.toggle('hidden', !masque);
            btn.setAttribute('aria-label', masque ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            
            }

    </script>

    @livewireScripts
</body>
</html>
