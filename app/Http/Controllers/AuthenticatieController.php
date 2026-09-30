<?php

namespace App\Http\Controllers;

use App\Http\Requests\InloggenRequest;
use App\Http\Requests\RegistrerenRequest;
use App\Services\AuthenticatieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatieController extends Controller
{
    public function inloggen(InloggenRequest $verzoek, AuthenticatieService $service): RedirectResponse
    {
        $service->inloggen($verzoek->validated());
        $verzoek->session()->regenerate();

        return redirect()->intended(route($verzoek->user()->rol === 'beheerder' ? 'beheer.weekrooster' : 'velden'));
    }

    public function registreren(RegistrerenRequest $verzoek, AuthenticatieService $service): RedirectResponse
    {
        $service->registreren($verzoek->validated());
        $verzoek->session()->regenerate();

        return redirect()->route('velden')->with('succes', 'Je account is aangemaakt.');
    }

    public function uitloggen(Request $verzoek): RedirectResponse
    {
        Auth::logout();
        $verzoek->session()->invalidate();
        $verzoek->session()->regenerateToken();

        return redirect()->route('home')->with('succes', 'Je bent uitgelogd.');
    }
}
