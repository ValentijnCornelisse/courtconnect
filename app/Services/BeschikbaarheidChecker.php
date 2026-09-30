<?php

namespace App\Services;

use App\Models\MateriaalReservering;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Veld;
use Carbon\CarbonImmutable;

class BeschikbaarheidChecker
{
    public function blokken(Veld $veld, string $datum): array
    {
        $start = CarbonImmutable::parse("$datum $veld->openingstijd");
        $sluiting = CarbonImmutable::parse("$datum $veld->sluitingstijd");
        $reserveringen = $veld->reserveringen()->where('datum', $datum)->where('status', 'bevestigd')->get();
        $blokkades = $veld->blokkades()->where('start', '<', $sluiting)->where('einde', '>', $start)->get();
        $blokken = [];
        while ($start->addHour() <= $sluiting) {
            $einde = $start->addHour();
            $status = 'vrij';
            if (! $veld->actief || $start->isPast()) {
                $status = 'niet boekbaar';
            }
            if ($reserveringen->contains(fn ($reservering) => $reservering->starttijd < $einde->format('H:i:s') && $reservering->eindtijd > $start->format('H:i:s'))) {
                $status = 'bezet';
            }
            if ($blokkades->contains(fn ($blokkade) => $blokkade->start < $einde && $blokkade->einde > $start)) {
                $status = 'onderhoud';
            }
            $blokken[] = ['start' => $start->format('H:i'), 'einde' => $einde->format('H:i'), 'status' => $status];
            $start = $einde;
        }

        return $blokken;
    }

    public function isVrij(Veld $veld, string $datum, string $starttijd, int $duur = 1): bool
    {
        $blokken = collect($this->blokken($veld, $datum))->values();
        $index = $blokken->search(fn ($blok) => $blok['start'] === substr($starttijd, 0, 5));
        if ($index === false) {
            return false;
        }

        return $blokken->slice($index, $duur)->where('status', 'vrij')->count() === $duur;
    }

    public function maximaleDuur(Veld $veld, string $datum, string $starttijd): int
    {
        $duur = 0;
        while ($duur < Reservering::MAXIMALE_DUUR && $this->isVrij($veld, $datum, $starttijd, $duur + 1)) {
            $duur++;
        }

        return $duur;
    }

    public function materiaalVrij(Materiaaltype $type, string $datum, string $starttijd, int $duur = 1): int
    {
        $start = CarbonImmutable::parse("$datum $starttijd");
        $vrij = $type->totaal_aantal;
        for ($uur = 0; $uur < $duur; $uur++) {
            $vrij = min($vrij, $this->materiaalVrijInUur($type, $start->addHours($uur)));
        }

        return $vrij;
    }

    private function materiaalVrijInUur(Materiaaltype $type, CarbonImmutable $start): int
    {
        $datum = $start->toDateString();
        $einde = $start->addHour();
        $gereserveerd = MateriaalReservering::where('materiaaltype_id', $type->id)
            ->whereHas('reservering', fn ($query) => $query->where('status', 'bevestigd')
                ->where(fn ($deel) => $deel->where('datum', '>', $datum)->orWhere(fn ($dag) => $dag->where('datum', $datum)->where('eindtijd', '>', $start->format('H:i:s'))))
                ->where(fn ($deel) => $deel->where('datum', '<', $einde->toDateString())->orWhere(fn ($dag) => $dag->where('datum', $einde->toDateString())->where('starttijd', '<', $einde->format('H:i:s')))))
            ->sum('aantal');

        return max(0, $type->totaal_aantal - $gereserveerd);
    }
}
