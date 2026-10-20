<?php

namespace Tests;

use App\Models\Gebruiker;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Veld;

/**
 * Kleine, eigen testdata voor de unittests: een padelveld met dal- en piektarief en losse reserveringen.
 */
trait MaaktTestdata
{
    protected function padelveld(array $extra = []): Veld
    {
        $veld = Veld::create($extra + ['naam' => 'Testveld', 'sporttype' => 'padel', 'openingstijd' => '09:00:00', 'sluitingstijd' => '23:00:00', 'actief' => true]);
        $veld->tarieven()->createMany([
            ['dagtype' => 'doordeweeks', 'starttijd' => '09:00:00', 'eindtijd' => '17:00:00', 'prijs_per_uur' => 24],
            ['dagtype' => 'doordeweeks', 'starttijd' => '17:00:00', 'eindtijd' => '23:00:00', 'prijs_per_uur' => 30],
            ['dagtype' => 'weekend', 'starttijd' => '09:00:00', 'eindtijd' => '23:00:00', 'prijs_per_uur' => 30],
        ]);

        return $veld;
    }

    protected function racketset(int $aantal = 5): Materiaaltype
    {
        return Materiaaltype::create(['naam' => 'Racketset', 'sporttype' => 'padel', 'totaal_aantal' => $aantal, 'prijs_per_set' => 3.5]);
    }

    protected function boeking(Veld $veld, string $datum, string $start, string $einde, string $status = 'bevestigd', ?Gebruiker $klant = null): Reservering
    {
        return Reservering::create(['klant_id' => ($klant ?? Gebruiker::factory()->create())->id, 'veld_id' => $veld->id, 'datum' => $datum, 'starttijd' => $start, 'eindtijd' => $einde, 'aantal_spelers' => 2, 'status' => $status, 'totaalprijs' => 24]);
    }
}
