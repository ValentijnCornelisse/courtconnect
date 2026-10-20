<?php

namespace Tests\Unit;

use App\Models\Blokkade;
use App\Models\Gebruiker;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Veld;
use App\Services\BeschikbaarheidChecker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Losse functies van de beschikbaarheidscontrole, met alleen de gegevens die de test zelf aanmaakt.
 */
class BeschikbaarheidCheckerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private BeschikbaarheidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 08:00'));
        $this->checker = new BeschikbaarheidChecker;
    }

    private function veld(array $extra = []): Veld
    {
        return Veld::create($extra + ['naam' => 'Testveld', 'sporttype' => 'padel', 'openingstijd' => '09:00:00', 'sluitingstijd' => '23:00:00', 'actief' => true]);
    }

    private function reservering(Veld $veld, string $start, string $einde, string $status = 'bevestigd', string $datum = '2026-09-28'): Reservering
    {
        return Reservering::create(['klant_id' => Gebruiker::factory()->create()->id, 'veld_id' => $veld->id, 'datum' => $datum, 'starttijd' => $start, 'eindtijd' => $einde, 'aantal_spelers' => 2, 'status' => $status, 'totaalprijs' => 24]);
    }

    private function blokstatus(Veld $veld, string $start): string
    {
        return collect($this->checker->blokken($veld, '2026-09-28'))->firstWhere('start', $start)['status'];
    }

    public function test_open_veld_heeft_veertien_vrije_uurblokken(): void
    {
        $blokken = $this->checker->blokken($this->veld(), '2026-09-28');
        $this->assertCount(14, $blokken);
        $this->assertSame(['start' => '09:00', 'einde' => '10:00', 'status' => 'vrij'], $blokken[0]);
        $this->assertSame(['start' => '22:00', 'einde' => '23:00', 'status' => 'vrij'], $blokken[13]);
    }

    public function test_randgeval_een_half_uur_voor_sluiting_levert_geen_blok_op(): void
    {
        $this->assertCount(1, $this->checker->blokken($this->veld(['sluitingstijd' => '10:30:00']), '2026-09-28'));
    }

    public function test_uren_in_het_verleden_en_een_inactief_veld_zijn_niet_boekbaar(): void
    {
        $veld = $this->veld();
        $this->travelTo(Carbon::parse('2026-09-28 12:30'));
        $this->assertSame('niet boekbaar', $this->blokstatus($veld, '12:00'));
        $this->assertSame('vrij', $this->blokstatus($veld, '13:00'));
        $veld->update(['actief' => false]);
        $this->assertSame(['niet boekbaar'], collect($this->checker->blokken($veld, '2026-09-28'))->pluck('status')->unique()->values()->all());
    }

    public function test_alleen_bevestigde_reserveringen_maken_een_blok_bezet(): void
    {
        $veld = $this->veld();
        $this->reservering($veld, '12:00:00', '14:00:00');
        $this->reservering($veld, '15:00:00', '16:00:00', 'geannuleerd');
        $this->assertSame('bezet', $this->blokstatus($veld, '12:00'));
        $this->assertSame('bezet', $this->blokstatus($veld, '13:00'));
        $this->assertSame('vrij', $this->blokstatus($veld, '14:00'));
        $this->assertSame('vrij', $this->blokstatus($veld, '15:00'));
    }

    public function test_blokkade_raakt_elk_uur_dat_ze_overlapt_en_gaat_voor_een_boeking(): void
    {
        $veld = $this->veld();
        $this->reservering($veld, '12:00:00', '13:00:00');
        Blokkade::create(['veld_id' => $veld->id, 'reden' => 'Onderhoud', 'start' => '2026-09-28 11:30', 'einde' => '2026-09-28 12:15', 'aangemaakt_door' => Gebruiker::factory()->beheerder()->create()->id]);
        $this->assertSame('vrij', $this->blokstatus($veld, '10:00'));
        $this->assertSame('onderhoud', $this->blokstatus($veld, '11:00'));
        $this->assertSame('onderhoud', $this->blokstatus($veld, '12:00'));
        $this->assertSame('vrij', $this->blokstatus($veld, '13:00'));
    }

    public function test_randgeval_blokkade_tot_precies_het_hele_uur_laat_het_volgende_uur_vrij(): void
    {
        $veld = $this->veld();
        Blokkade::create(['veld_id' => $veld->id, 'reden' => 'Onderhoud', 'start' => '2026-09-28 10:00', 'einde' => '2026-09-28 11:00', 'aangemaakt_door' => Gebruiker::factory()->beheerder()->create()->id]);
        $this->assertSame('onderhoud', $this->blokstatus($veld, '10:00'));
        $this->assertSame('vrij', $this->blokstatus($veld, '11:00'));
    }

    public function test_is_vrij_weigert_tijden_buiten_het_rooster_en_een_te_lange_duur(): void
    {
        $veld = $this->veld();
        $this->assertTrue($this->checker->isVrij($veld, '2026-09-28', '12:00'));
        $this->assertTrue($this->checker->isVrij($veld, '2026-09-28', '12:00:00'));
        $this->assertFalse($this->checker->isVrij($veld, '2026-09-28', '12:30'));
        $this->assertFalse($this->checker->isVrij($veld, '2026-09-28', '08:00'));
        $this->assertFalse($this->checker->isVrij($veld, '2026-09-28', '23:00'));
        $this->assertTrue($this->checker->isVrij($veld, '2026-09-28', '20:00', 3));
        $this->assertFalse($this->checker->isVrij($veld, '2026-09-28', '21:00', 3));
    }

    public function test_maximale_duur_is_drie_uur_en_stopt_voor_een_bezet_uur(): void
    {
        $veld = $this->veld();
        $this->assertSame(3, $this->checker->maximaleDuur($veld, '2026-09-28', '09:00'));
        $this->reservering($veld, '14:00:00', '15:00:00');
        $this->assertSame(1, $this->checker->maximaleDuur($veld, '2026-09-28', '13:00'));
        $this->assertSame(0, $this->checker->maximaleDuur($veld, '2026-09-28', '14:00'));
        $this->assertSame(2, $this->checker->maximaleDuur($veld, '2026-09-28', '21:00'));
    }

    public function test_materiaal_vrij_neemt_het_drukste_uur_en_negeert_andere_dagen_en_annuleringen(): void
    {
        $veld = $this->veld();
        $type = Materiaaltype::create(['naam' => 'Racketset', 'sporttype' => 'padel', 'totaal_aantal' => 5, 'prijs_per_set' => 3.5]);
        $this->reservering($veld, '12:00:00', '13:00:00')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 2]);
        $this->reservering($veld, '13:00:00', '15:00:00')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 4]);
        $this->reservering($veld, '12:00:00', '13:00:00', 'bevestigd', '2026-09-29')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 5]);
        $this->reservering($veld, '15:00:00', '16:00:00', 'geannuleerd')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 5]);
        $this->assertSame(3, $this->checker->materiaalVrij($type, '2026-09-28', '12:00'));
        $this->assertSame(1, $this->checker->materiaalVrij($type, '2026-09-28', '12:00', 2));
        $this->assertSame(5, $this->checker->materiaalVrij($type, '2026-09-28', '15:00'));
    }

    public function test_randgeval_materiaal_vrij_wordt_nooit_negatief(): void
    {
        $veld = $this->veld();
        $type = Materiaaltype::create(['naam' => 'Racketset', 'sporttype' => 'padel', 'totaal_aantal' => 5, 'prijs_per_set' => 3.5]);
        $this->reservering($veld, '12:00:00', '13:00:00')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 4]);
        $type->update(['totaal_aantal' => 1]);
        $this->assertSame(0, $this->checker->materiaalVrij($type, '2026-09-28', '12:00'));
    }
}
