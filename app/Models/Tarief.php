<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarief extends Model
{
    protected $fillable = ['veld_id', 'dagtype', 'starttijd', 'eindtijd', 'prijs_per_uur'];

    protected $table = 'tarieven';

    public function veld(): BelongsTo
    {
        return $this->belongsTo(Veld::class, 'veld_id');
    }
}
