<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1e293b; margin: 0; }
        .entete { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .entete td { vertical-align: top; }
        .societe { font-size: 14px; font-weight: bold; color: #0c3d20; }
        .devis-titre { font-size: 18px; font-weight: bold; color: #0c3d20; text-align: right; }
        .ref { text-align: right; color: #475569; }
        .bloc { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; }
        .bloc h3 { margin: 0 0 4px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.detail th { background: #0c3d20; color: #fff; text-align: left; padding: 4px 6px; font-size: 10px; }
        table.detail td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; }
        .totaux { width: 50%; margin-left: 50%; border-collapse: collapse; margin-top: 8px; }
        .totaux td { padding: 4px 6px; }
        .totaux .ttc td { border-top: 2px solid #0c3d20; font-weight: bold; font-size: 12px; color: #0c3d20; }
        .muted { color: #64748b; }
        .footer { margin-top: 18px; font-size: 9px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .etape-date { color: #64748b; font-size: 10px; }
        .alerte { background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; padding: 6px 8px; margin-bottom: 10px; font-size: 10px; color: #991b1b; }
        .vert { color: #166534; }
        .orange { color: #92400e; }
        .rouge { color: #991b1b; }
    </style>
</head>
<body>
    @php
        $soc = fn ($c) => \App\Models\Parametre::get($c);
        $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', ' ');
        $grilleRse = $devis->calcul_payload['rse']['grille_journaliere'] ?? [];
        $it = $devis->calcul_payload['itineraire'] ?? [];
        $par = $devis->calcul_payload['parametres'] ?? [];
        $veh = $devis->vehicule;
    @endphp
    <table class="entete">
        <tr>
            <td style="width:60%">
                <div class="societe">{{ $soc('societe_nom') }}</div>
                <div class="muted">
                    {{ $soc('societe_adresse') }}, {{ $soc('societe_cp_ville') }}<br>
                    SIRET : {{ $soc('societe_siret') }} · TVA : {{ $soc('societe_tva') }}
                </div>
            </td>
            <td style="width:40%">
                <div class="devis-titre">DOCUMENT INTERNE</div>
                <div class="ref">
                    Devis N° {{ $devis->reference }}<br>
                    Client : {{ $devis->demande->client_nom }}<br>
                    Date : {{ $devis->updated_at->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="bloc">
        <h3>Données de calcul</h3>
        <table class="detail">
            <tr><td>Véhicule</td><td>{{ $veh->immatriculation ?? '—' }} ({{ $veh->nb_places ?? '?' }} pl.)</td></tr>
            <tr><td>Distance totale</td><td>{{ $fmt($devis->distance_km) }} km</td></tr>
            <tr><td>Dont chargés</td><td>{{ $fmt($devis->distance_km_charge) }} km</td></tr>
            <tr><td>Dont à vide</td><td>{{ $fmt($devis->distance_km_vide) }} km</td></tr>
            <tr><td>Durée de conduite</td><td>{{ intdiv($devis->duree_conduite_minutes, 60) }}h{{ str_pad($devis->duree_conduite_minutes % 60, 2, '0', STR_PAD_LEFT) }}</td></tr>
            <tr><td>Temps d'attente</td><td>{{ intdiv($devis->temps_attente_minutes, 60) }}h{{ str_pad($devis->temps_attente_minutes % 60, 2, '0', STR_PAD_LEFT) }}</td></tr>
            <tr><td>Nb chauffeurs</td><td>{{ $devis->nb_chauffeurs }}</td></tr>
            <tr><td>Nb nuitées</td><td>{{ $devis->nb_nuitees }}</td></tr>
            @if ($devis->override_author)
                <tr><td class="rouge">Override</td><td class="rouge">{{ $devis->override_author }} le {{ \Carbon\Carbon::parse($devis->override_at)->format('d/m/Y H:i') }}</td></tr>
            @endif
        </table>
    </div>

    <div class="bloc">
        <h3>Coût de revient détaillé</h3>
        <table class="detail">
            <tr><td>Carburant</td><td style="text-align:right">{{ $fmt($devis->cout_carburant) }} €</td></tr>
            <tr><td>Péage</td><td style="text-align:right">{{ $fmt($devis->cout_peage) }} €</td></tr>
            @if ($devis->cout_vignettes > 0)
                <tr><td>Vignettes</td><td style="text-align:right">{{ $fmt($devis->cout_vignettes) }} €</td></tr>
            @endif
            <tr><td>Chauffeur</td><td style="text-align:right">{{ $fmt($devis->cout_chauffeur) }} €</td></tr>
            <tr><td>Charges fixes</td><td style="text-align:right">{{ $fmt($devis->cout_charges_fixes) }} €</td></tr>
            <tr><td>Charges variables</td><td style="text-align:right">{{ $fmt($devis->cout_charges_variables) }} €</td></tr>
            <tr style="font-weight:bold; border-top:2px solid #0c3d20;"><td>Coût de revient HT</td><td style="text-align:right">{{ $fmt($devis->cout_revient_ht) }} €</td></tr>
        </table>
    </div>

    <div class="bloc">
        <h3>RSE — Régulation (CE) 561/2006</h3>
        @if ($grilleRse)
            @php $total100 = 0; $total50 = 0; $totalTte = 0; @endphp
            <table class="detail">
                <thead>
                    <tr><th>Jour</th><th>100 %</th><th>50 %</th><th>TTE</th><th>Amplitude</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    @foreach ($grilleRse as $jour)
                        @php
                            $jourDate = \Carbon\Carbon::parse($jour['date']);
                            $total100 += $jour['heures_100_min'];
                            $total50 += $jour['heures_50_min'];
                            $totalTte += $jour['tte_min'];
                        @endphp
                        <tr>
                            <td>{{ $jourDate->format('d/m/Y') }}</td>
                            <td>{{ intdiv($jour['heures_100_min'], 60) }}h{{ str_pad($jour['heures_100_min'] % 60, 2, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ intdiv($jour['heures_50_min'], 60) }}h{{ str_pad($jour['heures_50_min'] % 60, 2, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ intdiv($jour['tte_min'], 60) }}h{{ str_pad($jour['tte_min'] % 60, 2, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $jour['amplitude_min'] ? intdiv($jour['amplitude_min'], 60).'h'.str_pad($jour['amplitude_min'] % 60, 2, '0', STR_PAD_LEFT) : '—' }}</td>
                            <td class="{{ $jour['relais_necessaire'] ? 'rouge' : 'vert' }}">{{ $jour['relais_necessaire'] ? 'Relais nécessaire' : $jour['statut'] }}</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight:bold; border-top:2px solid #0c3d20;">
                        <td>Cumul</td>
                        <td>{{ intdiv($total100, 60) }}h{{ str_pad($total100 % 60, 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ intdiv($total50, 60) }}h{{ str_pad($total50 % 60, 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ intdiv($totalTte, 60) }}h{{ str_pad($totalTte % 60, 2, '0', STR_PAD_LEFT) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        @else
            <p class="muted">Aucune donnée RSE disponible.</p>
        @endif
    </div>

    <div class="bloc">
        <h3>Temps de service poste par poste</h3>
        <table class="detail">
            <thead>
                <tr><th>#</th><th>Poste</th><th>Date</th><th>Début</th><th>Fin</th><th>Durée</th><th>Taux</th></tr>
            </thead>
            <tbody>
                @php $formatMin = fn ($minutes) => $minutes === null ? '—' : intdiv($minutes, 60) . 'h' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT); @endphp
                @foreach ($devis->demande->etapes as $i => $etape)
                    @php
                        $debut = $i === 0 ? null : ($etape->heure_depart ?: $etape->heure_arrivee);
                        $fin = $etape->heure_arrivee;
                        $duree = null;
                        $taux = '—';
                        if ($debut && $fin) {
                            $d = \Carbon\Carbon::createFromFormat('H:i', $fin)->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i', $debut));
                            if ($d < 0) { $d += 1440; }
                            $duree = $d;
                            $taux = '100 %';
                        } elseif ($etape->heure_depart) {
                            $taux = '50 %';
                        } elseif ($etape->heure_arrivee) {
                            $taux = '100 %';
                        }
                    @endphp
                    <tr>
                        <td>{{ $etape->ordre }}</td>
                        <td>{{ $etape->libelle() }}</td>
                        <td>{{ $etape->date?->format('d/m/Y') }}</td>
                        <td>{{ $debut ? substr($debut, 0, 5) : '—' }}</td>
                        <td>{{ $fin ? substr($fin, 0, 5) : '—' }}</td>
                        <td>{{ $formatMin($duree) }}</td>
                        <td>{{ $taux }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bloc">
        <h3>Marge & prix de vente</h3>
        <table class="detail">
            <tr><td>Taux de marge</td><td style="text-align:right">{{ $fmt($devis->marge_taux, 0) }} %</td></tr>
            <tr><td>Montant HT</td><td style="text-align:right">{{ $fmt($devis->montant_ht) }} €</td></tr>
            <tr><td>TVA ({{ $fmt($devis->taux_tva, 0) }} %)</td><td style="text-align:right">{{ $fmt($devis->montant_tva) }} €</td></tr>
            <tr class="ttc"><td>Total TTC</td><td style="text-align:right">{{ $fmt($devis->montant_ttc) }} €</td></tr>
        </table>
    </div>

    @if (!empty($devis->lignes_libres))
        <div class="bloc">
            <h3>Prestations supplémentaires</h3>
            <table class="detail">
                <thead><tr><th>Désignation</th><th style="width:30%; text-align:right">Montant HT</th></tr></thead>
                <tbody>
                    @foreach ($devis->lignes_libres as $ligne)
                        <tr><td>{{ $ligne['libelle'] }}</td><td style="text-align:right">{{ $fmt((float) $ligne['montant']) }} €</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        Document interne — ne pas remettre au client. Généré le {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
