<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BlokkadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['veld_id' => ['required', 'integer', 'exists:velden,id'], 'reden' => ['required', 'string', 'max:255'], 'start' => ['required', 'date_format:Y-m-d\\TH:i'], 'einde' => ['required', 'date_format:Y-m-d\\TH:i', 'after:start']];
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
