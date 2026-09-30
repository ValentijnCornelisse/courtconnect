<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZoekenRequest;
use App\Models\Materiaaltype;
use App\Models\Veld;
use App\Services\BeschikbaarheidChecker;
use App\Services\PrijsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class VeldController extends Controller
{
    public function overzicht(ZoekenRequest $verzoek, BeschikbaarheidChecker $checker): View
    {
        $datum = $verzoek->validated('datum') ?? today()->format('Y-m-d');
        $velden = Veld::with('tarieven')->where('actief', true)->orderBy('id')->get()->map(function (Veld $veld) use ($checker, $datum) {
            $blokken = collect($checker->blokken($veld, $datum));
            $vrij = $blokken->where('status', 'vrij')->count();
            $status = match (true) {
                $vrij > 0 => ['klasse' => 'vrij', 'tekst' => 'Beschikbaar'],
                $blokken->every(fn ($blok) => $blok['status'] === 'niet boekbaar') => ['klasse' => 'bezet', 'tekst' => 'Niet boekbaar'],
                default => ['klasse' => 'bezet', 'tekst' => 'Volgeboekt'],
            };

            return ['veld' => $veld, 'status' => $status, 'vrij' => $vrij, 'totaal' => $blokken->count()];
        });

        return view('velden.overzicht', ['velden' => $velden, 'datum' => $datum, 'dagen' => $this->dagen($datum)]);
    }

    public function zoeken(ZoekenRequest $verzoek, BeschikbaarheidChecker $checker, PrijsCalculator $calculator): View
    {
        $filters = $verzoek->validated();
        $datum = $filters['datum'] ?? today()->format('Y-m-d');
        $zoekParameters = array_filter(['sporttype' => $filters['sporttype'] ?? null, 'veld_id' => $filters['veld_id'] ?? null, 'datum' => $datum, 'van' => $filters['van'] ?? null, 'tot' => $filters['tot'] ?? null]);
        $velden = Veld::with('tarieven')->where('actief', true)
            ->when($filters['veld_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['sporttype'] ?? null, fn ($query, $sport) => $query->where('sporttype', $sport))->orderBy('id')->get();
        $resultaten = $velden->map(function (Veld $veld) use ($checker, $datum, $filters) {
            $blokken = collect($checker->blokken($veld, $datum))
                ->filter(fn ($blok) => $blok['start'] >= ($filters['van'] ?? '00:00') && $blok['einde'] <= ($filters['tot'] ?? '23:59'))->values();

            return ['veld' => $veld, 'blokken' => $blokken, 'vrij' => $blokken->contains('status', 'vrij')];
        });
        $veldkeuzes = Veld::where('actief', true)->orderBy('id')->get();
        $gekozenVeld = isset($filters['gekozen_veld']) ? Veld::with('tarieven')->where('actief', true)->find($filters['gekozen_veld']) : null;
        $paneel = $this->boekpaneel($verzoek, $checker, $calculator, $gekozenVeld, $datum, $gekozenVeld ? ($filters['starttijd'] ?? null) : null);
        if ($paneel['prijs'] && ! $verzoek->user()) {
            $verzoek->session()->put('url.intended', $verzoek->fullUrl());
        }

        return view('velden.zoeken', [...compact('resultaten', 'datum', 'filters', 'zoekParameters', 'veldkeuzes', 'gekozenVeld'), ...$paneel, 'dagen' => $this->dagen($datum)]);
    }

    /**
     * Gegevens voor het boekpaneel: maximale duur, vrij materiaal en de actuele prijs.
     */
    private function boekpaneel(ZoekenRequest $verzoek, BeschikbaarheidChecker $checker, PrijsCalculator $calculator, ?Veld $veld, string $datum, ?string $starttijd): array
    {
        $concept = $verzoek->session()->get('reservering.gegevens', []);
        $conceptPast = $veld && ($concept['veld_id'] ?? null) == $veld->id && ($concept['datum'] ?? null) === $datum && ($concept['starttijd'] ?? null) === $starttijd;
        $gekozenMateriaal = old('materiaal', $conceptPast ? ($concept['materiaal'] ?? []) : []);
        $spelers = old('aantal_spelers', $conceptPast ? $concept['aantal_spelers'] : 2);
        $maximaleDuur = $veld && $starttijd ? $checker->maximaleDuur($veld, $datum, $starttijd) : 0;
        $duur = min($maximaleDuur, max(1, (int) old('duur', $verzoek->validated('duur') ?? ($conceptPast ? ($concept['duur'] ?? 1) : 1))));
        $materiaal = [];
        $prijs = null;
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
        $eindtijd = $prijs ? CarbonImmutable::parse($starttijd)->addHours($duur)->format('H:i') : null;

        return compact('starttijd', 'eindtijd', 'duur', 'maximaleDuur', 'materiaal', 'prijs', 'gekozenMateriaal', 'spelers');
    }

    /**
     * Zeven dagen voor de dagkiezer, vanaf het begin van de week maar nooit vóór vandaag.
     */
    private function dagen(string $datum): Collection
    {
        $start = CarbonImmutable::parse($datum)->startOfWeek()->max(today()->toImmutable());

        return collect(range(0, 6))->map(fn ($dag) => $start->addDays($dag));
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
