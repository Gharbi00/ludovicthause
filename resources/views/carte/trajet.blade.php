<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trajet — devis {{ $devis->reference }}</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: {
            brand: { DEFAULT: '#15663a', dark: '#0c3d20', light: '#7cb342' }
        } } } }
    </script>
    <style>
        html, body { height: 100%; margin: 0; }
        #map { height: calc(100% - 56px); }
        .pin { background:#0c3d20; color:#fff; border-radius:9999px; width:26px; height:26px;
               display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;
               box-shadow:0 1px 4px rgba(0,0,0,.4); border:2px solid #fff; }
        .pin.depot { background:#15663a; }
        .legende { background:#fff; padding:8px 10px; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,.3);
                   font:12px/1.5 system-ui, sans-serif; color:#334155; }
        .legende div { display:flex; align-items:center; gap:6px; }
        .legende i { width:18px; height:4px; border-radius:2px; display:inline-block; }
        .avertissement { background:#fef3c7; color:#92400e; padding:8px 10px; border-radius:8px;
                         box-shadow:0 1px 6px rgba(0,0,0,.2); font:12px/1.4 system-ui, sans-serif; max-width:260px; }
    </style>
</head>
<body class="h-full bg-slate-100">
    <header class="h-14 bg-brand-dark text-white flex items-center justify-between px-4">
        <div class="flex items-center gap-3">
            <span class="text-xl">🗺️</span>
            <div>
                <div class="text-sm font-semibold">Trajet — devis {{ $devis->reference }}</div>
                <div class="text-xs text-white/70">{{ number_format($devis->distance_km, 0, ',', ' ') }} km · dépôt Varennes-Vauzelles → étapes → retour</div>
            </div>
        </div>
        <button onclick="window.close()" class="rounded-md bg-brand px-3 py-1.5 text-sm hover:bg-brand">Fermer</button>
    </header>

    <div id="map"></div>

    <script>
        const points   = @json($points);
        const geometry = @json($geometry);
        const tollways = @json($tollways);

        const COULEUR_ROUTE = '#0c3d20';
        const COULEUR_PEAGE = '#dc2626';

        const map = L.map('map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        const latlngs = points.map(p => [p.lat, p.lng]);

        /** Décode une polyline encodée (algorithme Google, précision 5) → [[lat, lng], …]. */
        function decoderPolyline(str, precision = 5) {
            let index = 0, lat = 0, lng = 0, coords = [], shift, result, byte;
            const facteur = Math.pow(10, precision);
            while (index < str.length) {
                shift = 0; result = 0;
                do { byte = str.charCodeAt(index++) - 63; result |= (byte & 0x1f) << shift; shift += 5; } while (byte >= 0x20);
                lat += (result & 1) ? ~(result >> 1) : (result >> 1);
                shift = 0; result = 0;
                do { byte = str.charCodeAt(index++) - 63; result |= (byte & 0x1f) << shift; shift += 5; } while (byte >= 0x20);
                lng += (result & 1) ? ~(result >> 1) : (result >> 1);
                coords.push([lat / facteur, lng / facteur]);
            }
            return coords;
        }

        points.forEach((p, i) => {
            const icon = L.divIcon({
                className: '',
                html: '<div class="pin ' + (p.type === 'depot' ? 'depot' : '') + '">' + (i + 1) + '</div>',
                iconSize: [26, 26],
                iconAnchor: [13, 13],
            });
            L.marker([p.lat, p.lng], { icon })
                .addTo(map)
                .bindPopup('<strong>' + (i + 1) + '. ' + p.nom + '</strong>' + (p.type === 'depot' ? '<br>Dépôt' : ''));
        });

        // --- Tracé ---
        let cadrage = latlngs;
        const trace = geometry ? decoderPolyline(geometry) : null;

        if (trace && trace.length > 1) {
            if (tollways && tollways.length) {
                // Un segment par tronçon : rouge = autoroute à péage, vert = route normale.
                tollways.forEach(([debut, fin, valeur]) => {
                    const segment = trace.slice(debut, fin + 1);
                    if (segment.length < 2) return;
                    L.polyline(segment, {
                        color: valeur === 1 ? COULEUR_PEAGE : COULEUR_ROUTE,
                        weight: valeur === 1 ? 6 : 4,
                        opacity: 0.9,
                    }).addTo(map);
                });
            } else {
                L.polyline(trace, { color: COULEUR_ROUTE, weight: 4, opacity: 0.85 }).addTo(map);
            }
            cadrage = trace;

            const legende = L.control({ position: 'bottomright' });
            legende.onAdd = () => {
                const div = L.DomUtil.create('div', 'legende');
                div.innerHTML =
                    '<div><i style="background:' + COULEUR_ROUTE + '"></i> Route</div>' +
                    '<div><i style="background:' + COULEUR_PEAGE + '"></i> Autoroute à péage</div>';
                return div;
            };
            legende.addTo(map);
        } else {
            // Devis calculé sans tracé (ancien calcul ou mode estimation) : lignes directes.
            L.polyline(latlngs, { color: COULEUR_ROUTE, weight: 4, opacity: 0.75, dashArray: '6 6' }).addTo(map);

            const avert = L.control({ position: 'bottomright' });
            avert.onAdd = () => {
                const div = L.DomUtil.create('div', 'avertissement');
                div.innerHTML = '⚠️ Tracé approximatif (lignes directes).<br>Relancez « Recalculer » sur le devis pour obtenir la route réelle et les péages en rouge.';
                return div;
            };
            avert.addTo(map);
        }

        if (cadrage.length > 1) {
            map.fitBounds(L.latLngBounds(cadrage), { padding: [50, 50] });
        } else if (cadrage.length === 1) {
            map.setView(cadrage[0], 12);
        } else {
            map.setView([46.8, 2.5], 6);
        }
    </script>
</body>
</html>
