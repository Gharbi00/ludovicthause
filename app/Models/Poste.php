<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Poste extends Model
{
    use HasFactory;

    protected $table = 'postes';

    protected $fillable = [
        'devis_id',
        'date',
        'ordre',
        'type',
        'heure_debut',
        'heure_fin',
        'duree_min',
        'taux',
        'origine',
        'auteur',
        'date_modif',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'heure_debut' => 'datetime:H:i',
            'heure_fin' => 'datetime:H:i',
            'duree_min' => 'integer',
            'taux' => 'integer',
            'date_modif' => 'datetime',
        ];
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }
}
