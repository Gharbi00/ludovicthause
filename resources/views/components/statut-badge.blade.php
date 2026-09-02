@props(['statut'])

@php
    $map = [
        'nouvelle'      => ['Nouvelle', 'bg-amber-100 text-amber-800'],
        'en_traitement' => ['En traitement', 'bg-green-100 text-brand'],
        'devis_edite'   => ['Devis édité', 'bg-violet-100 text-violet-800'],
        'envoye'        => ['Devis envoyé', 'bg-blue-100 text-blue-800'],
        'accepte'       => ['Accepté', 'bg-emerald-100 text-emerald-800'],
        'refuse'        => ['Refusé', 'bg-red-100 text-red-700'],
        'close'         => ['Clôturée', 'bg-slate-200 text-slate-600'],
    ];
    [$label, $classes] = $map[$statut] ?? [ucfirst($statut), 'bg-slate-100 text-slate-600'];
@endphp

<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>
