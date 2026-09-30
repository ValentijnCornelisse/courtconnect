<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materiaaltype extends Model
{
    protected $fillable = ['naam', 'sporttype', 'totaal_aantal', 'prijs_per_set'];

    protected $table = 'materiaaltypes';

    protected function casts(): array
    {
        return [
            'prijs_per_set' => 'decimal:2',
        ];
    }

    public function materiaalReserveringen(): HasMany
    {
        return $this->hasMany(MateriaalReservering::class, 'materiaaltype_id');
    }
}
