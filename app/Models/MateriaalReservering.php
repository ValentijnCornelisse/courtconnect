<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MateriaalReservering extends Model
{
    protected $fillable = ['reservering_id', 'materiaaltype_id', 'aantal'];

    protected $table = 'materiaal_reserveringen';

    public function reservering(): BelongsTo
    {
        return $this->belongsTo(Reservering::class, 'reservering_id');
    }

    public function materiaaltype(): BelongsTo
    {
        return $this->belongsTo(Materiaaltype::class, 'materiaaltype_id');
    }
}
