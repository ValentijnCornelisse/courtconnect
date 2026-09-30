<?php

namespace App\Services;

use App\Models\Gebruiker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatieService
{
    public function inloggen(array $gegevens): void
    {
        if (! Auth::attempt(['email' => $gegevens['email'], 'password' => $gegevens['wachtwoord']])) {
            throw ValidationException::withMessages(['email' => 'Het e-mailadres of wachtwoord klopt niet.']);
        }
    }

    public function registreren(array $gegevens): void
    {
        $gebruiker = Gebruiker::create(['naam' => $gegevens['naam'], 'email' => $gegevens['email'], 'wachtwoord' => Hash::make($gegevens['wachtwoord']), 'rol' => 'klant']);
        Auth::login($gebruiker);
    }
}
