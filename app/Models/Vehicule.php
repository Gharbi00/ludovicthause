<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicule extends Model
{
    use HasFactory;

    protected $table = 'vehicules';

    protected $fillable = [
        'immatriculation',
        'numero_parc',
        'categorie_id',
        'nb_places',
        'nb_essieux',
        'type_energie',
        'conso_l_100km',
        'loyer_credit_bail_mensuel',
        'assurance_annuelle',
        'quote_part_loyers_annuelle',
        'autres_charges_fixes_annuelles',
        'cout_entretien_km',
        'cout_pneus_km',
        'cout_adblue_km',
        'autres_variables_km',
        'jours_exploitation_an',
        'actif',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'nb_places'                      => 'integer',
            'nb_essieux'                     => 'integer',
            'conso_l_100km'                  => 'decimal:2',
            'loyer_credit_bail_mensuel'      => 'decimal:2',
            'assurance_annuelle'             => 'decimal:2',
            'quote_part_loyers_annuelle'     => 'decimal:2',
            'autres_charges_fixes_annuelles' => 'decimal:2',
            'cout_entretien_km'              => 'decimal:4',
            'cout_pneus_km'                  => 'decimal:4',
            'cout_adblue_km'                 => 'decimal:4',
            'autres_variables_km'            => 'decimal:4',
            'jours_exploitation_an'          => 'integer',
            'actif'                          => 'boolean',
        ];
    }

    /**
     * Classe de péage déduite du nombre d'essieux : 2 essieux => classe 3, 3 et + => classe 4.
     */
    protected function classePeage(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->nb_essieux <= 2 ? 3 : 4,
        );
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function devis(): HasMany
    {
        return $this->hasMany(Devis::class);
    }

    public function planning(): HasMany
    {
        return $this->hasMany(Planning::class);
    }
}
