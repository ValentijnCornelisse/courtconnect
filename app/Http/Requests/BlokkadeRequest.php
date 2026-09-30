<?php

namespace App\Http\Requests;

class BlokkadeRequest extends CourtConnectRequest
{
    public function rules(): array
    {
        return ['veld_id' => ['required', 'integer', 'exists:velden,id'], 'reden' => ['required', 'string', 'max:255'], 'start' => ['required', 'date_format:Y-m-d\\TH:i'], 'einde' => ['required', 'date_format:Y-m-d\\TH:i', 'after:start']];
    }
}
