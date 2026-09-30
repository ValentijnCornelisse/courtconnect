<?php

namespace App\Services;

use App\Models\Veld;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PrijsCalculator
{
    public function bereken(Veld $veld, string $datum, string $starttijd, array $materialen = [], int $duur = 1): array
    {
        $start = CarbonImmutable::parse("$datum $starttijd");
        $veld->loadMissing('tarieven');
        $dagtype = $start->isWeekend() ? 'weekend' : 'doordeweeks';
        $basis = (int) round((float) $veld->tarieven->min('prijs_per_uur') * 100) * $duur;
        $som = 0;
        for ($minuut = 0; $minuut < 60 * $duur; $minuut++) {
            $tijd = $start->addMinutes($minuut)->format('H:i:s');
            $regels = $veld->tarieven->filter(fn ($regel) => $regel->dagtype === $dagtype && $regel->starttijd <= $tijd && $regel->eindtijd > $tijd);
            if ($regels->count() !== 1) {
                throw ValidationException::withMessages(['starttijd' => 'Voor dit tijdsblok is geen eenduidig tarief ingesteld. Kies een ander tijdsblok.']);
            }
            $som += (int) round((float) $regels->first()->prijs_per_uur * 100);
        }
        $veldprijs = (int) round($som / 60);
        $regels = [];
        foreach ($materialen as $keuze) {
            $regels[] = ['naam' => $keuze['type']->naam, 'aantal' => $keuze['aantal'], 'bedrag' => $keuze['aantal'] * (int) round((float) $keuze['type']->prijs_per_set * 100)];
        }
        $totaal = $veldprijs + array_sum(array_column($regels, 'bedrag'));
        if ($totaal > 999999) {
            throw ValidationException::withMessages(['materiaal' => 'De totaalprijs is te hoog. Kies minder materiaal.']);
        }

        return ['uren' => $duur, 'basis' => $basis, 'piek' => $veldprijs - $basis, 'materiaal' => $regels, 'totaal' => $totaal];
    }
}
