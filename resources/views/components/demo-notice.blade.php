@props(['area' => 'public'])

<div id="demo-notice" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🔧</span>
            <h2 class="text-lg font-semibold text-slate-900">Version de démonstration</h2>
        </div>
        <p class="mt-3 text-sm text-slate-600">
            Cette application est une <strong>démonstration</strong>. Elle est pleinement fonctionnelle,
            mais certains éléments restent à <strong>affiner</strong>, notamment :
        </p>
        <ul class="mt-2 space-y-1 text-sm text-slate-600 list-disc pl-5">
            <li>le calcul des <strong>péages</strong> (estimation au tarif de la classe du véhicule, à caler finement) ;</li>
            <li>la couverture <strong>Europe</strong> (vignettes non incluses, précision variable hors de France).</li>
        </ul>
        <p class="mt-3 text-xs text-slate-400">
            Les montants affichés ne constituent pas encore un devis ferme.
        </p>
        <button type="button" onclick="fermerDemoNotice()"
                class="mt-5 w-full rounded-lg bg-brand px-4 py-2.5 text-white font-semibold hover:bg-brand-dark">
            J'ai compris
        </button>
    </div>
</div>

<script>
    (function () {
        var key = 'ltt_demo_notice_{{ $area }}';
        var el = document.getElementById('demo-notice');
        if (el && !sessionStorage.getItem(key)) {
            el.style.display = 'flex';
        }
        window.fermerDemoNotice = function () {
            sessionStorage.setItem(key, '1');
            if (el) el.style.display = 'none';
        };
    })();
</script>
