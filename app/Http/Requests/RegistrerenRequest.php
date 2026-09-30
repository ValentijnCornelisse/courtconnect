<?php

namespace App\Http\Requests;

class RegistrerenRequest extends CourtConnectRequest
{
    public function rules(): array
    {
        return ['naam' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:gebruikers,email'], 'wachtwoord' => ['required', 'string', 'min:8', 'max:255', 'confirmed']];
    }
}
