<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class VeldRequest extends CourtConnectRequest
{
    public function rules(): array
    {
        return [
            'naam' => ['required', 'string', 'max:255'], 'sporttype' => ['required', 'in:padel,basketbal,zaalvoetbal'],
            'omschrijving' => ['nullable', 'string', 'max:2000'], 'openingstijd' => ['required', 'date_format:H:i'],
            'sluitingstijd' => ['required', 'date_format:H:i', 'after:openingstijd'], 'actief' => ['required', 'boolean'],
            'tarieven' => ['required', 'array', 'min:2', 'max:50'], 'tarieven.*' => ['required', 'array:dagtype,starttijd,eindtijd,prijs_per_uur'],
            'tarieven.*.dagtype' => ['required', 'in:doordeweeks,weekend'], 'tarieven.*.starttijd' => ['required', 'date_format:H:i'],
            'tarieven.*.eindtijd' => ['required', 'date_format:H:i'], 'tarieven.*.prijs_per_uur' => ['required', 'numeric', 'between:0,9999.99', 'decimal:0,2'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validatie): void {
            if ($validatie->errors()->isNotEmpty()) {
                return;
            }
            foreach (['doordeweeks', 'weekend'] as $dagtype) {
                $regels = collect($this->input('tarieven'))->where('dagtype', $dagtype)->sortBy('starttijd');
                $einde = $this->input('openingstijd');
                foreach ($regels as $sleutel => $regel) {
                    if ($regel['starttijd'] !== $einde || $regel['eindtijd'] <= $regel['starttijd'] || $regel['eindtijd'] > $this->input('sluitingstijd')) {
                        $validatie->errors()->add("tarieven.$sleutel.starttijd", 'Tarieven moeten zonder overlap of gaten de volledige openingstijd vullen.');
                    }
                    $einde = $regel['eindtijd'];
                }
                if ($regels->isEmpty() || $einde !== $this->input('sluitingstijd')) {
                    $validatie->errors()->add('tarieven', "Vul voor $dagtype tarieven voor de volledige openingstijd in.");
                }
            }
        }];
    }
}
