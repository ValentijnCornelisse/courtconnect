<?php

namespace App\Http\Requests;

use App\Models\Reservering;

class ZoekenRequest extends CourtConnectRequest
{
    public function rules(): array
    {
        return ['sporttype' => ['nullable', 'in:padel,basketbal,zaalvoetbal'], 'datum' => ['nullable', 'date_format:Y-m-d'], 'van' => ['nullable', 'date_format:H:i'], 'tot' => ['nullable', 'date_format:H:i', ...($this->filled('van') ? ['after:van'] : [])], 'starttijd' => ['nullable', 'date_format:H:i'], 'duur' => ['nullable', 'integer', 'between:1,'.Reservering::MAXIMALE_DUUR], 'veld_id' => ['nullable', 'integer', 'exists:velden,id']];
    }
}
