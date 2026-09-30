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
        $zoek = $gegevens['zoek'] ?? [];
        unset($gegevens['zoek']);
        try {
            $overzicht = $service->overzicht($gegevens);
        } catch (ValidationException $fout) {
            return $this->naarReserveren($gegevens, false, $zoek)->withErrors($fout->errors())->withInput();
        }
        $verzoek->session()->put('reservering', ['gegevens' => $gegevens, 'prijs' => $overzicht['prijs']['totaal']]);
        if (($gegevens['actie'] ?? null) === 'prijs') {
            return $this->naarReserveren($gegevens, true, $zoek)->withInput();
        }

        return redirect()->route('reserveringen.controle');
    }

    public function overzicht(Request $verzoek, ReserveringService $service): View|RedirectResponse
    {
        $concept = $verzoek->session()->get('reservering');
        if (! $concept) {
            return redirect()->route('reserveren')->with('melding', 'Kies eerst een veld en tijdsblok.');
        }
        try {
            return view('reserveringen.controle', $service->overzicht($concept['gegevens']));
        } catch (ValidationException $fout) {
            return $this->naarReserveren($concept['gegevens'])->withErrors($fout->errors());
        }
    }

    public function bevestigen(Request $verzoek, ReserveringService $service): RedirectResponse
    {
        $concept = $verzoek->session()->get('reservering');
        if (! $concept) {
            return redirect()->route('reserveren')->with('melding', 'Kies eerst een veld en tijdsblok.');
        }
        try {
            $service->bevestigen($verzoek->user(), $concept['gegevens'], $concept['prijs']);
        } catch (ValidationException $fout) {
            $verzoek->session()->forget('reservering');

            return $this->naarReserveren($concept['gegevens'])->withErrors($fout->errors());
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

    /**
     * Terug naar de reserveerpagina bij het gekozen veld; met tijdsblok als het boekpaneel open moet blijven.
     */
    private function naarReserveren(array $gegevens, bool $metTijdsblok = false, array $zoek = []): RedirectResponse
    {
        $parameters = $zoek + ['datum' => $gegevens['datum'], 'gekozen_veld' => $gegevens['veld_id']];
        if ($metTijdsblok) {
            $parameters += ['starttijd' => $gegevens['starttijd'], 'duur' => $gegevens['duur']];
        }

        return redirect()->to(route('reserveren', $parameters).'#boekpaneel');
    }
}
