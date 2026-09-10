<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsage extends Model
{
    use HasFactory;

    protected $table = 'api_usages';

    protected $fillable = [
        'provider',
        'endpoint',
        'cache_key',
        'cached',
        'response_time_ms',
        'http_status',
        'user_agent',
        'ip_address',
        'devis_id',
    ];

    protected function casts(): array
    {
        return [
            'cached' => 'boolean',
            'response_time_ms' => 'integer',
            'http_status' => 'integer',
            'devis_id' => 'integer',
        ];
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }
}
