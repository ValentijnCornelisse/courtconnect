<?php

namespace App\Http\Requests;

use App\Models\Materiaaltype;
use App\Models\Reservering;
use Illuminate\Validation\Validator;

class ReserveringRequest extends CourtConnectRequest
{
    public function rules(): array
    {
        return ['actie' => ['nullable', 'in:prijs,controle'], 'veld_id' => ['required', 'integer', 'exists:velden,id'], 'datum' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'starttijd' => ['required', 'date_format:H:i'], 'duur' => ['required', 'integer', 'between:1,'.Reservering::MAXIMALE_DUUR], 'aantal_spelers' => ['required', 'integer', 'between:1,12'], 'materiaal' => ['nullable', 'array'], 'materiaal.*' => ['required', 'integer', 'min:0', 'max:999']];
    }

    public function after(): array
    {
        return [function (Validator $validatie): void {
            if ($validatie->errors()->isNotEmpty()) {
                return;
            }
            foreach (array_keys($this->input('materiaal', []) ?? []) as $id) {
                if (! ctype_digit((string) $id) || ! Materiaaltype::whereKey($id)->exists()) {
                    $validatie->errors()->add('materiaal', 'Kies een bestaand materiaaltype.');
                }
            }
        }];
    }
}
