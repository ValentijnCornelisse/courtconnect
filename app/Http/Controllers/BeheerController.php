<?php

namespace App\Http\Controllers;

use App\Http\Requests\BlokkadeRequest;
use App\Http\Requests\MateriaalRequest;
use App\Http\Requests\VeldRequest;
use App\Http\Requests\ZoekenRequest;
use App\Models\Blokkade;
use App\Models\Materiaaltype;
use App\Models\Veld;
use App\Services\BeheerService;
use App\Services\BeschikbaarheidChecker;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BeheerController extends Controller
{
    public function weekrooster(ZoekenRequest $verzoek, BeschikbaarheidChecker $checker): View
    {
        $gegevens = $verzoek->validated();
        $velden = Veld::orderBy('id')->get();
        $veld = $velden->firstWhere('id', $gegevens['veld_id'] ?? null) ?? $velden->first();
        $begin = CarbonImmutable::parse($gegevens['datum'] ?? today())->startOfWeek();
        $dagen = collect(range(0, 6))->map(fn ($dag) => $begin->addDays($dag));
        $rooster = [];
        if ($veld) {
            foreach ($dagen as $dag) {
                $rooster[$dag->format('Y-m-d')] = $checker->blokken($veld, $dag->format('Y-m-d'));
            }
        }

        return view('beheer.weekrooster', compact('velden', 'veld', 'dagen', 'begin', 'rooster'));
    }

    public function velden(?Veld $veld = null): View
    {
        return view('beheer.velden', ['velden' => Veld::with('tarieven')->get(), 'bewerken' => $veld?->load('tarieven')]);
    }

    public function veldOpslaan(VeldRequest $verzoek, BeheerService $service, ?Veld $veld = null): RedirectResponse
    {
        $service->veldOpslaan($verzoek->validated(), $veld);

        return redirect()->route('beheer.velden')->with('succes', 'Het veld en de tarieven zijn opgeslagen.');
    }

    public function veldVerwijderen(Veld $veld, BeheerService $service): RedirectResponse
    {
        $service->veldVerwijderen($veld);

        return redirect()->route('beheer.velden')->with('succes', 'Het veld is verwijderd.');
    }

    public function blokkades(): View
    {
        return view('beheer.blokkades', ['velden' => Veld::all(), 'blokkades' => Blokkade::with('veld')->orderBy('start')->get()]);
    }

    public function blokkadeOpslaan(BlokkadeRequest $verzoek, BeheerService $service): RedirectResponse
    {
        $service->blokkadeOpslaan($verzoek->validated(), $verzoek->user());

        return redirect()->route('beheer.blokkades')->with('succes', 'De onderhoudsblokkade is opgeslagen.');
    }

    public function materiaal(ZoekenRequest $verzoek, BeschikbaarheidChecker $checker, ?Materiaaltype $materiaal = null): View
    {
        $gegevens = $verzoek->validated();
        $datum = $gegevens['datum'] ?? today()->format('Y-m-d');
        $starttijd = $gegevens['starttijd'] ?? '10:00';
        $voorraad = Materiaaltype::orderBy('id')->get()->map(fn ($type) => ['type' => $type, 'vrij' => $checker->materiaalVrij($type, $datum, $starttijd)]);

        return view('beheer.materiaal', ['voorraad' => $voorraad, 'datum' => $datum, 'starttijd' => $starttijd, 'bewerken' => $materiaal]);
    }

    public function materiaalOpslaan(MateriaalRequest $verzoek, BeheerService $service, ?Materiaaltype $materiaal = null): RedirectResponse
    {
        $service->materiaalOpslaan($verzoek->validated(), $materiaal);

        return redirect()->route('beheer.materiaal')->with('succes', 'Het materiaal is opgeslagen.');
    }
}
