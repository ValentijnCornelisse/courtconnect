<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blokkade extends Model
{
    protected $fillable = ['veld_id', 'start', 'einde', 'reden', 'aangemaakt_door'];

    protected $table = 'blokkades';

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'einde' => 'datetime',
        ];
    }

    public function veld(): BelongsTo
    {
        return $this->belongsTo(Veld::class, 'veld_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'aangemaakt_door');
    }
}
