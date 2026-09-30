<?php

namespace App\Http\Requests;

class InloggenRequest extends CourtConnectRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'wachtwoord' => ['required', 'string', 'max:255']];
    }
}
