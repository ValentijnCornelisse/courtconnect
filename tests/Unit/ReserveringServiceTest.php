<?php

namespace Tests\Unit;

use App\Models\Gebruiker;
use App\Models\Materiaaltype;
use App\Services\ReserveringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\MaaktTestdata;
use Tests\TestCase;

class ReserveringServiceTest extends TestCase
{
    use LazilyRefreshDatabase, MaaktTestdata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 08:00'));
    }

    private function service(): ReserveringService
    {
        return app(ReserveringService::class);
    }

    private function gegevens(int $veldId, array $extra = []): array
    {
        return array_replace(['veld_id' => $veldId, 'datum' => '2026-09-28', 'starttijd' => '12:00', 'duur' => 1, 'aantal_spelers' => 4, 'materiaal' => []], $extra);
    }

    private function foutVan(callable $actie): array
    {
        try {
            $actie();
        } catch (ValidationException $fout) {
            return $fout->errors();
        }
        $this->fail('Er werd een validatiefout verwacht.');
    }

    public function test_bevestigen_slaat_reservering_met_eindtijd_prijs_en_materiaal_op(): void
    {
        $veld = $this->padelveld();
        $type = $this->racketset();
        $boeking = $this->service()->bevestigen(Gebruiker::factory()->create(), $this->gegevens($veld->id, ['duur' => 3, 'materiaal' => [$type->id => 2]]), 7200 + 700);
        $this->assertSame('15:00:00', $boeking->eindtijd);
        $this->assertSame('79.00', $boeking->fresh()->totaalprijs);
        $this->assertSame(2, $boeking->materiaalReserveringen()->value('aantal'));
    }

    public function test_materiaal_met_aantal_nul_wordt_overgeslagen(): void
    {
        $veld = $this->padelveld();
        $boeking = $this->service()->bevestigen(Gebruiker::factory()->create(), $this->gegevens($veld->id, ['materiaal' => [$this->racketset()->id => 0]]), 2400);
        $this->assertSame(0, $boeking->materiaalReserveringen()->count());
    }

    public function test_randgeval_de_laatste_set_mag_maar_een_set_te_veel_niet(): void
    {
        $veld = $this->padelveld();
        $type = $this->racketset(5);
        $this->assertSame(5, $this->service()->overzicht($this->gegevens($veld->id, ['materiaal' => [$type->id => 5]]))['materialen'][0]['aantal']);
        $this->assertArrayHasKey("materiaal.$type->id", $this->foutVan(fn () => $this->service()->overzicht($this->gegevens($veld->id, ['materiaal' => [$type->id => 6]]))));
    }

    public function test_fout_materiaal_van_een_andere_sport_wordt_geweigerd(): void
    {
        $veld = $this->padelveld();
        $ballen = Materiaaltype::create(['naam' => 'Basketballen', 'sporttype' => 'basketbal', 'totaal_aantal' => 6, 'prijs_per_set' => 1.5]);
        $this->assertArrayHasKey("materiaal.$ballen->id", $this->foutVan(fn () => $this->service()->overzicht($this->gegevens($veld->id, ['materiaal' => [$ballen->id => 1]]))));
    }

    public function test_fout_gewijzigde_prijs_moet_opnieuw_worden_gecontroleerd(): void
    {
        $veld = $this->padelveld();
        $fout = $this->foutVan(fn () => $this->service()->bevestigen(Gebruiker::factory()->create(), $this->gegevens($veld->id), 2000));
        $this->assertSame(['Het tarief is gewijzigd. Controleer de actuele prijs opnieuw.'], $fout['starttijd']);
        $this->assertDatabaseCount('reserveringen', 0);
    }

    public function test_fout_een_beheerder_kan_niet_zelf_boeken(): void
    {
        $veld = $this->padelveld();
        $this->expectException(HttpException::class);
        $this->service()->bevestigen(Gebruiker::factory()->beheerder()->create(), $this->gegevens($veld->id), 2400);
    }

    public function test_randgeval_annuleren_kan_tot_een_minuut_voor_de_start_maar_niet_op_de_starttijd(): void
    {
        $veld = $this->padelveld();
        $klant = Gebruiker::factory()->create();
        $eerste = $this->boeking($veld, '2026-09-28', '12:00:00', '13:00:00', klant: $klant);
        $tweede = $this->boeking($veld, '2026-09-28', '14:00:00', '15:00:00', klant: $klant);
        $this->travelTo(Carbon::parse('2026-09-28 11:59'));
        $this->service()->annuleren($klant, $eerste);
        $this->assertSame('geannuleerd', $eerste->fresh()->status);
        $this->travelTo(Carbon::parse('2026-09-28 14:00'));
        $this->assertArrayHasKey('reservering', $this->foutVan(fn () => $this->service()->annuleren($klant, $tweede)));
        $this->assertSame('bevestigd', $tweede->fresh()->status);
    }

    public function test_fout_annuleren_van_een_reservering_van_een_ander_geeft_403(): void
    {
        $boeking = $this->boeking($this->padelveld(), '2026-09-28', '12:00:00', '13:00:00');
        $this->expectException(HttpException::class);
        $this->service()->annuleren(Gebruiker::factory()->create(), $boeking);
    }
}
