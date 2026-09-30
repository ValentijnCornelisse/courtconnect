<?php

namespace App\Services;

use App\Models\Gebruiker;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Veld;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveringService
{
    public function __construct(private BeschikbaarheidChecker $beschikbaarheid, private PrijsCalculator $prijzen) {}

    public function overzicht(array $gegevens): array
    {
        $veld = Veld::findOrFail($gegevens['veld_id']);
        if (! $this->beschikbaarheid->isVrij($veld, $gegevens['datum'], $gegevens['starttijd'])) {
            throw ValidationException::withMessages(['starttijd' => 'Dit tijdsblok is zojuist door iemand anders gereserveerd.']);
        }
        $materialen = [];
        foreach ($gegevens['materiaal'] ?? [] as $id => $aantal) {
            if ((int) $aantal === 0) {
                continue;
            }
            $type = Materiaaltype::find($id);
            if (! $type || $type->sporttype !== $veld->sporttype || $this->beschikbaarheid->materiaalVrij($type, $gegevens['datum'], $gegevens['starttijd']) < $aantal) {
                throw ValidationException::withMessages(["materiaal.$id" => 'Dit materiaal is zojuist door iemand anders gereserveerd. Kies minder sets.']);
            }
            $materialen[] = ['type' => $type, 'aantal' => (int) $aantal];
        }

        return ['veld' => $veld, 'gegevens' => $gegevens, 'materialen' => $materialen, 'prijs' => $this->prijzen->bereken($veld, $gegevens['datum'], $gegevens['starttijd'], $materialen)];
    }

    public function bevestigen(Gebruiker $klant, array $gegevens, int $verwachtePrijs): Reservering
    {
        abort_unless($klant->rol === 'klant', 403);
        try {
            return DB::transaction(function () use ($klant, $gegevens, $verwachtePrijs) {
                Veld::whereKey($gegevens['veld_id'])->lockForUpdate()->firstOrFail();
                Materiaaltype::whereIn('id', array_keys($gegevens['materiaal'] ?? []))->orderBy('id')->lockForUpdate()->get();
                $overzicht = $this->overzicht($gegevens);
                if ($overzicht['prijs']['totaal'] !== $verwachtePrijs) {
                    throw ValidationException::withMessages(['starttijd' => 'Het tarief is gewijzigd. Controleer de actuele prijs opnieuw.']);
                }
                $reservering = Reservering::create([
                    'klant_id' => $klant->id, 'veld_id' => $gegevens['veld_id'], 'datum' => $gegevens['datum'],
                    'starttijd' => $gegevens['starttijd'],
                    'eindtijd' => CarbonImmutable::parse($gegevens['starttijd'])->addHour()->format('H:i:s'),
                    'aantal_spelers' => $gegevens['aantal_spelers'], 'status' => 'bevestigd',
                    'totaalprijs' => number_format($overzicht['prijs']['totaal'] / 100, 2, '.', ''),
                ]);
                foreach ($overzicht['materialen'] as $keuze) {
                    $reservering->materiaalReserveringen()->create(['materiaaltype_id' => $keuze['type']->id, 'aantal' => $keuze['aantal']]);
                }

                return $reservering;
            }, 5);
        } catch (UniqueConstraintViolationException $fout) {
            throw ValidationException::withMessages(['starttijd' => 'Dit tijdsblok is zojuist door iemand anders gereserveerd.']);
        }
    }

    public function annuleren(Gebruiker $klant, Reservering $reservering): void
    {
        abort_unless($reservering->klant_id === $klant->id, 403);
        DB::transaction(function () use ($reservering) {
            Veld::whereKey($reservering->veld_id)->lockForUpdate()->firstOrFail();
            $reservering = Reservering::whereKey($reservering->id)->lockForUpdate()->firstOrFail();
            if (! CarbonImmutable::parse($reservering->datum->format('Y-m-d').' '.$reservering->starttijd)->isFuture()) {
                throw ValidationException::withMessages(['reservering' => 'Je kunt alleen vóór de starttijd annuleren.']);
            }
            $reservering->update(['status' => 'geannuleerd']);
        });
    }
}
