<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devis extends Model
{
    use HasFactory;

    protected $table = 'devis';

    protected $fillable = [
        'reference',
        'demande_id',
        'vehicule_id',
        'distance_km',
        'distance_km_charge',
        'distance_km_vide',
        'duree_conduite_minutes',
        'temps_attente_minutes',
        'nb_chauffeurs',
        'nb_nuitees',
        'cout_carburant',
        'cout_peage',
        'cout_vignettes',
        'cout_chauffeur',
        'cout_charges_fixes',
        'cout_charges_variables',
        'cout_revient_ht',
        'marge_taux',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_ttc',
        'marge_montant',
        'statut',
        'pdf_path',
        'calcul_payload',
        'lignes_libres',
    ];

    protected function casts(): array
    {
        return [
            'distance_km'            => 'decimal:2',
            'distance_km_charge'     => 'decimal:2',
            'distance_km_vide'       => 'decimal:2',
            'duree_conduite_minutes' => 'integer',
            'temps_attente_minutes'  => 'integer',
            'nb_chauffeurs'          => 'integer',
            'nb_nuitees'             => 'integer',
            'cout_carburant'         => 'decimal:2',
            'cout_peage'             => 'decimal:2',
            'cout_vignettes'         => 'decimal:2',
            'cout_chauffeur'         => 'decimal:2',
            'cout_charges_fixes'     => 'decimal:2',
            'cout_charges_variables' => 'decimal:2',
            'cout_revient_ht'        => 'decimal:2',
            'marge_taux'             => 'decimal:2',
            'montant_ht'             => 'decimal:2',
            'taux_tva'               => 'decimal:2',
            'montant_tva'            => 'decimal:2',
            'montant_ttc'            => 'decimal:2',
            'marge_montant'          => 'decimal:2',
            'calcul_payload'         => 'array',
            'lignes_libres'          => 'array',
        ];
    }

    /** Total HT des prestations supplémentaires libres. */
    public function totalLignesLibres(): float
    {
        return round(collect($this->lignes_libres ?? [])->sum(fn ($l) => (float) ($l['montant'] ?? 0)), 2);
    }

    /**
     * Recalcule marge / TVA / totaux à partir du coût de revient, du taux de marge
     * et des prestations supplémentaires (facturées telles quelles, sans marge).
     */
    public function recalculerTotaux(): void
    {
        $htTransport = (float) $this->cout_revient_ht * (1 + (float) $this->marge_taux / 100);
        $supplements = $this->totalLignesLibres();
        $ht  = $htTransport + $supplements;
        $tva = $ht * (float) $this->taux_tva / 100;

        $this->update([
            'montant_ht'    => round($ht, 2),
            'montant_tva'   => round($tva, 2),
            'montant_ttc'   => round($ht + $tva, 2),
            'marge_montant' => round($htTransport - (float) $this->cout_revient_ht, 2),
        ]);
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function planning(): HasMany
    {
        return $this->hasMany(Planning::class);
    }
}
