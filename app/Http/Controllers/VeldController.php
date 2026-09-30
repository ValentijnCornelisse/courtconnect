<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZoekenRequest;
use App\Models\Materiaaltype;
use App\Models\Veld;
use App\Services\BeschikbaarheidChecker;
use App\Services\PrijsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class VeldController extends Controller
{
    public function overzicht(): View
    {
        return view('velden.overzicht', [
            'velden' => Veld::with('tarieven')->where('actief', true)->orderBy('id')->get(),
        ]);
    }

    public function zoeken(ZoekenRequest $verzoek, BeschikbaarheidChecker $checker): View
    {
        $filters = $verzoek->validated();
        $datum = $filters['datum'] ?? today()->format('Y-m-d');
        $velden = Veld::with('tarieven')->where('actief', true)
            ->when($filters['veld_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['sporttype'] ?? null, fn ($query, $sport) => $query->where('sporttype', $sport))->orderBy('id')->get();
        $resultaten = $velden->map(function ($veld) use ($checker, $datum, $filters) {
            $vrij = collect($checker->blokken($veld, $datum))->contains(fn ($blok) => $blok['status'] === 'vrij' && $blok['start'] >= ($filters['van'] ?? '00:00') && $blok['einde'] <= ($filters['tot'] ?? '23:59'));

            return ['veld' => $veld, 'vrij' => $vrij];
        });

        $veldkeuzes = Veld::where('actief', true)->orderBy('id')->get();

        return view('velden.zoeken', compact('resultaten', 'datum', 'filters', 'veldkeuzes'));
    }

    public function tijden(ZoekenRequest $verzoek, Veld $veld, BeschikbaarheidChecker $checker, PrijsCalculator $calculator): View
    {
        abort_unless($veld->actief, 404);
        $gegevens = $verzoek->validated();
        $datum = $gegevens['datum'] ?? today()->format('Y-m-d');
        $blokken = $checker->blokken($veld, $datum);
        $starttijd = $gegevens['starttijd'] ?? null;
        $dagen = collect(range(0, 6))->map(fn ($dag) => CarbonImmutable::parse($datum)->startOfWeek()->addDays($dag));
        $materiaal = [];
        $prijs = null;
        $concept = $verzoek->session()->get('reservering.gegevens', []);
        $conceptPast = ($concept['veld_id'] ?? null) == $veld->id && ($concept['datum'] ?? null) === $datum && ($concept['starttijd'] ?? null) === $starttijd;
        $gekozenMateriaal = old('materiaal', $conceptPast ? ($concept['materiaal'] ?? []) : []);
        $spelers = old('aantal_spelers', $conceptPast ? $concept['aantal_spelers'] : 2);
        $maximaleDuur = $starttijd ? $checker->maximaleDuur($veld, $datum, $starttijd) : 0;
        $duur = min($maximaleDuur, max(1, (int) old('duur', $gegevens['duur'] ?? ($conceptPast ? ($concept['duur'] ?? 1) : 1))));
        if ($maximaleDuur > 0) {
            $materiaal = Materiaaltype::where('sporttype', $veld->sporttype)->orderBy('id')->get()->map(fn ($type) => ['type' => $type, 'vrij' => $checker->materiaalVrij($type, $datum, $starttijd, $duur)]);
            $keuzes = [];
            foreach ($materiaal as $regel) {
                $aantal = min($regel['vrij'], max(0, (int) ($gekozenMateriaal[$regel['type']->id] ?? 0)));
                $gekozenMateriaal[$regel['type']->id] = $aantal;
                if ($aantal > 0) {
                    $keuzes[] = ['type' => $regel['type'], 'aantal' => $aantal];
                }
            }
            $prijs = $calculator->bereken($veld, $datum, $starttijd, $keuzes, $duur);
        }
        $eindtijd = $starttijd ? CarbonImmutable::parse($starttijd)->addHours($duur)->format('H:i') : null;

        return view('velden.tijden', compact('veld', 'datum', 'blokken', 'starttijd', 'eindtijd', 'duur', 'maximaleDuur', 'dagen', 'materiaal', 'prijs', 'gekozenMateriaal', 'spelers'));
    }

    public function tarieven(): View
    {
        $sporttarieven = Veld::with('tarieven')->where('actief', true)->orderBy('id')->get()
            ->groupBy(fn (Veld $veld) => $veld->veldtype())
            ->map(fn ($velden) => $velden->flatMap->tarieven
                ->groupBy(fn ($tarief) => $tarief->dagtype.'|'.$tarief->starttijd.'|'.$tarief->eindtijd)
                ->map(fn ($regels) => [
                    'dagtype' => $regels->first()->dagtype,
                    'starttijd' => $regels->first()->starttijd,
                    'eindtijd' => $regels->first()->eindtijd,
                    'minimum' => $regels->min('prijs_per_uur'),
                    'maximum' => $regels->max('prijs_per_uur'),
                ])->sortBy(fn ($regel) => $regel['dagtype'].'|'.$regel['starttijd']));

        return view('tarieven', compact('sporttarieven'));
    }
}
