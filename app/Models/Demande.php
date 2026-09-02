<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demande extends Model
{
    use HasFactory;

    protected $table = 'demandes';

    protected $fillable = [
        'reference',
        'categorie_id',
        'nb_passagers',
        'client_nom',
        'client_email',
        'client_telephone',
        'statut',
        'commentaire',
        'ip_soumission',
    ];

    protected function casts(): array
    {
        return [
            'nb_passagers' => 'integer',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(Etape::class)->orderBy('ordre');
    }

    public function devis(): HasMany
    {
        return $this->hasMany(Devis::class);
    }
}
