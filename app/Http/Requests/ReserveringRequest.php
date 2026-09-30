<?php

namespace App\Http\Requests;

use App\Models\Materiaaltype;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReserveringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['actie' => ['nullable', 'in:prijs,controle'], 'veld_id' => ['required', 'integer', 'exists:velden,id'], 'datum' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'starttijd' => ['required', 'date_format:H:i'], 'aantal_spelers' => ['required', 'integer', 'between:1,12'], 'materiaal' => ['nullable', 'array'], 'materiaal.*' => ['required', 'integer', 'min:0', 'max:999']];
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
