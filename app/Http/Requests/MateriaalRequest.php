<?php

namespace App\Http\Requests;

class MateriaalRequest extends CourtConnectRequest
{
    public function rules(): array
    {
        return ['naam' => ['required', 'string', 'max:255'], 'sporttype' => ['required', 'in:padel,basketbal,zaalvoetbal'], 'totaal_aantal' => ['required', 'integer', 'between:0,9999'], 'prijs_per_set' => ['required', 'numeric', 'between:0,999.99', 'decimal:0,2']];
    }
}
