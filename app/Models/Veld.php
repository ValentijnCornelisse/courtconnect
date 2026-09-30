<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veld extends Model
{
    protected $fillable = ['naam', 'sporttype', 'omschrijving', 'openingstijd', 'sluitingstijd', 'actief'];

    protected $table = 'velden';

    public function veldtype(): string
    {
        return mb_strtolower(trim($this->naam)) === 'multicourt' ? 'multicourt' : $this->sporttype;
    }

    protected function casts(): array
    {
        return [
            'actief' => 'boolean',
        ];
    }

    public function tarieven(): HasMany
    {
        return $this->hasMany(Tarief::class, 'veld_id');
    }

    public function reserveringen(): HasMany
    {
        return $this->hasMany(Reservering::class, 'veld_id');
    }

    public function blokkades(): HasMany
    {
        return $this->hasMany(Blokkade::class, 'veld_id');
    }
}
