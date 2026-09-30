<?php

namespace App\Http\Requests;

use App\Models\Reservering;
use Illuminate\Foundation\Http\FormRequest;

class ZoekenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['sporttype' => ['nullable', 'in:padel,basketbal,zaalvoetbal'], 'datum' => ['nullable', 'date_format:Y-m-d'], 'van' => ['nullable', 'date_format:H:i'], 'tot' => ['nullable', 'date_format:H:i', ...($this->filled('van') ? ['after:van'] : [])], 'starttijd' => ['nullable', 'date_format:H:i'], 'duur' => ['nullable', 'integer', 'between:1,'.Reservering::MAXIMALE_DUUR], 'veld_id' => ['nullable', 'integer', 'exists:velden,id']];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vul dit veld in.', 'email' => 'Vul een geldig e-mailadres in.',
            'unique' => 'Dit e-mailadres is al in gebruik.', 'date_format' => 'Vul een geldige datum of tijd in.',
            'after' => 'De eindtijd moet na de starttijd liggen.', 'after_or_equal' => 'Kies vandaag of een latere datum.',
            'integer' => 'Vul een heel getal in.', 'numeric' => 'Vul een geldig bedrag in.', 'in' => 'Kies een geldige optie.',
            'exists' => 'Deze keuze bestaat niet meer.', 'boolean' => 'Kies actief of inactief.',
            'min' => 'De ingevoerde waarde is te kort of te laag.', 'max' => 'De ingevoerde waarde is te lang of te hoog.',
            'between' => 'De waarde valt buiten het toegestane bereik.', 'confirmed' => 'De wachtwoorden komen niet overeen.',
            'array' => 'Kies geldige waarden.', 'decimal' => 'Gebruik maximaal twee decimalen.',
            'aantal_spelers.*' => 'Vul een aantal spelers in tussen 1 en 12.',
            'wachtwoord.min' => 'Gebruik een wachtwoord van minimaal 8 tekens.',
        ];
    }
}
