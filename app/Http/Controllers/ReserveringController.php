<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReserveringRequest;
use App\Models\Reservering;
use App\Services\ReserveringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReserveringController extends Controller
{
    public function controle(ReserveringRequest $verzoek, ReserveringService $service): RedirectResponse
    {
        $gegevens = $verzoek->validated();
        try {
            $overzicht = $service->overzicht($gegevens);
        } catch (ValidationException $fout) {
            return redirect()->route('velden.tijden', ['veld' => $gegevens['veld_id'], 'datum' => $gegevens['datum']])->withErrors($fout->errors())->withInput();
        }
        $verzoek->session()->put('reservering', ['gegevens' => $gegevens, 'prijs' => $overzicht['prijs']['totaal']]);
        if (($gegevens['actie'] ?? null) === 'prijs') {
            return redirect()->route('velden.tijden', ['veld' => $gegevens['veld_id'], 'datum' => $gegevens['datum'], 'starttijd' => $gegevens['starttijd']])->withInput();
        }

        return redirect()->route('reserveringen.controle');
    }

    public function overzicht(Request $verzoek, ReserveringService $service): View|RedirectResponse
    {
        $concept = $verzoek->session()->get('reservering');
        if (! $concept) {
            return redirect()->route('velden')->with('melding', 'Kies eerst een veld en tijdsblok.');
        }
        try {
            return view('reserveringen.controle', $service->overzicht($concept['gegevens']));
        } catch (ValidationException $fout) {
            return redirect()->route('velden.tijden', ['veld' => $concept['gegevens']['veld_id'], 'datum' => $concept['gegevens']['datum']])->withErrors($fout->errors());
        }
    }

    public function bevestigen(Request $verzoek, ReserveringService $service): RedirectResponse
    {
        $concept = $verzoek->session()->get('reservering');
        if (! $concept) {
            return redirect()->route('velden')->with('melding', 'Kies eerst een veld en tijdsblok.');
        }
        try {
            $service->bevestigen($verzoek->user(), $concept['gegevens'], $concept['prijs']);
        } catch (ValidationException $fout) {
            $verzoek->session()->forget('reservering');

            return redirect()->route('velden.tijden', ['veld' => $concept['gegevens']['veld_id'], 'datum' => $concept['gegevens']['datum']])->withErrors($fout->errors());
        }
        $verzoek->session()->forget('reservering');

        return redirect()->route('reserveringen.mijn')->with('succes', 'Je reservering is bevestigd.');
    }

    public function mijn(Request $verzoek): View
    {
        return view('reserveringen.mijn', ['reserveringen' => $verzoek->user()->reserveringen()->with(['veld', 'materiaalReserveringen.materiaaltype'])->orderByDesc('datum')->orderBy('starttijd')->get()]);
    }

    public function annuleren(Request $verzoek, Reservering $reservering, ReserveringService $service): RedirectResponse
    {
        $service->annuleren($verzoek->user(), $reservering);

        return redirect()->route('reserveringen.mijn')->with('succes', 'Je reservering is geannuleerd.');
    }
}
