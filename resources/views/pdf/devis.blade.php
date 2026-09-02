<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1e293b; margin: 0; }
        .entete { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .entete td { vertical-align: top; }
        .societe { font-size: 16px; font-weight: bold; color: #0c3d20; }
        .devis-titre { font-size: 22px; font-weight: bold; color: #0c3d20; text-align: right; }
        .ref { text-align: right; color: #475569; }
        .bloc { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; }
        .bloc h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.detail th { background: #0c3d20; color: #fff; text-align: left; padding: 6px 8px; font-size: 11px; }
        table.detail td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; }
        .totaux { width: 45%; margin-left: 55%; border-collapse: collapse; margin-top: 10px; }
        .totaux td { padding: 5px 8px; }
        .totaux .ttc td { border-top: 2px solid #0c3d20; font-weight: bold; font-size: 14px; color: #0c3d20; }
        .muted { color: #64748b; }
        .footer { margin-top: 26px; font-size: 10px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .etape-date { color: #64748b; font-size: 11px; }
    </style>
</head>
<body>
    @php
        $soc = fn ($c) => \App\Models\Parametre::get($c);
        $validite = (int) \App\Models\Parametre::get('pdf_validite_jours', 30);
    @endphp
    <table class="entete">
        <tr>
            <td style="width:60%">
                <img src="{{ public_path('images/logo-ltt.png') }}" style="height:46px; margin-bottom:6px;" alt="{{ $soc('societe_nom') }}">
                <div class="muted">
                    {{ $soc('societe_activite') }}<br>
                    {{ $soc('societe_adresse') }}, {{ $soc('societe_cp_ville') }}<br>
                    @if($soc('societe_telephone') || $soc('societe_email')){{ $soc('societe_telephone') }}@if($soc('societe_telephone') && $soc('societe_email')) · @endif{{ $soc('societe_email') }}<br>@endif
                    SIRET : {{ $soc('societe_siret') }} · TVA : {{ $soc('societe_tva') }}
                </div>
            </td>
            <td style="width:40%">
                <div class="devis-titre">DEVIS</div>
                <div class="ref">
                    N° {{ $devis->reference }}<br>
                    Date : {{ $devis->updated_at->format('d/m/Y') }}<br>
                    Validité : {{ $validite }} jours
                </div>
            </td>
        </tr>
    </table>

    <table style="width:100%; border-collapse:collapse; margin-bottom:14px;">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:8px;">
                <div class="bloc">
                    <h3>Client</h3>
                    <strong>{{ $devis->demande->client_nom }}</strong><br>
                    {{ $devis->demande->client_email }}<br>
                    {{ $devis->demande->client_telephone ?: '' }}
                </div>
            </td>
            <td style="width:50%; vertical-align:top; padding-left:8px;">
                <div class="bloc">
                    <h3>Prestation</h3>
                    Transport {{ $devis->vehicule ? '· '.$devis->vehicule->nb_places.' places' : '' }}<br>
                    {{ $devis->demande->nb_passagers }} passagers<br>
                    Distance estimée : {{ number_format($devis->distance_km, 0, ',', ' ') }} km
                </div>
            </td>
        </tr>
    </table>

    <h3 style="font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#64748b; margin-bottom:2px;">Itinéraire</h3>
    <table class="detail">
        <thead>
            <tr><th style="width:8%">#</th><th>Ville</th><th style="width:28%">Date</th></tr>
        </thead>
        <tbody>
            @foreach ($itineraire as $i => $etape)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $etape->libelle() }}</td>
                    <td class="etape-date">{{ $etape->date?->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php $supplements = $devis->totalLignesLibres(); @endphp
    @if (! empty($devis->lignes_libres))
        <h3>Prestations supplémentaires</h3>
        <table class="detail">
            <thead>
                <tr><th>Désignation</th><th style="width:28%; text-align:right">Montant HT</th></tr>
            </thead>
            <tbody>
                @foreach ($devis->lignes_libres as $ligne)
                    <tr>
                        <td>{{ $ligne['libelle'] }}</td>
                        <td style="text-align:right">{{ number_format((float) $ligne['montant'], 2, ',', ' ') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totaux">
        @if ($supplements != 0)
            <tr><td class="muted">Transport HT</td><td style="text-align:right">{{ number_format($devis->montant_ht - $supplements, 2, ',', ' ') }} €</td></tr>
            <tr><td class="muted">Prestations supplémentaires HT</td><td style="text-align:right">{{ number_format($supplements, 2, ',', ' ') }} €</td></tr>
        @endif
        <tr><td class="muted">Montant HT</td><td style="text-align:right">{{ number_format($devis->montant_ht, 2, ',', ' ') }} €</td></tr>
        <tr><td class="muted">TVA ({{ number_format($devis->taux_tva, 0, ',', ' ') }} %)</td><td style="text-align:right">{{ number_format($devis->montant_tva, 2, ',', ' ') }} €</td></tr>
        <tr class="ttc"><td>Total TTC</td><td style="text-align:right">{{ number_format($devis->montant_ttc, 2, ',', ' ') }} €</td></tr>
    </table>

    <div class="footer">
        {{ $soc('pdf_conditions') }}<br>
        {{ $soc('societe_nom') }} — {{ $soc('societe_adresse') }}, {{ $soc('societe_cp_ville') }} — SIRET {{ $soc('societe_siret') }} — TVA {{ $soc('societe_tva') }}<br>
        Document généré le {{ now()->format('d/m/Y') }}.
    </div>
</body>
</html>
