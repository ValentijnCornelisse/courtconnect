<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservering extends Model
{
    public const MAXIMALE_DUUR = 3;

    protected function datum(): Attribute
    {
        return Attribute::make(set: fn ($waarde) => Carbon::parse($waarde)->toDateString());
    }

    protected $fillable = ['klant_id', 'veld_id', 'datum', 'starttijd', 'eindtijd', 'aantal_spelers', 'status', 'totaalprijs'];

    protected $table = 'reserveringen';

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'totaalprijs' => 'decimal:2',
        ];
    }

    public function klant(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'klant_id');
    }

    public function veld(): BelongsTo
    {
        return $this->belongsTo(Veld::class, 'veld_id');
    }

    public function materiaalReserveringen(): HasMany
    {
        return $this->hasMany(MateriaalReservering::class, 'reservering_id');
    }
}
