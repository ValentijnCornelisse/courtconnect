<?php

namespace Tests\Unit;

use App\Models\Gebruiker;
use App\Models\Veld;
use App\Services\BeheerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\MaaktTestdata;
use Tests\TestCase;

class BeheerServiceTest extends TestCase
{
    use LazilyRefreshDatabase, MaaktTestdata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 08:00'));
    }

    private function veldgegevens(array $extra = []): array
    {
        return array_replace(['naam' => 'Testveld', 'sporttype' => 'padel', 'omschrijving' => null, 'openingstijd' => '09:00', 'sluitingstijd' => '23:00', 'actief' => true,
            'tarieven' => [['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 20], ['dagtype' => 'weekend', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 25]]], $extra);
    }

    private function blokkade(Veld $veld, string $start, string $einde): array
    {
        return ['veld_id' => $veld->id, 'reden' => 'Onderhoud', 'start' => $start, 'einde' => $einde];
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

    public function test_veld_opslaan_vervangt_de_tariefregels(): void
    {
        $service = new BeheerService;
        $veld = $service->veldOpslaan($this->veldgegevens());
        $this->assertSame(2, $veld->tarieven()->count());
        $service->veldOpslaan($this->veldgegevens(['naam' => 'Nieuw', 'tarieven' => [['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '17:00', 'prijs_per_uur' => 20], ['dagtype' => 'doordeweeks', 'starttijd' => '17:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 26], ['dagtype' => 'weekend', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 25]]]), $veld);
        $this->assertSame(3, $veld->tarieven()->count());
        $this->assertSame('Nieuw', $veld->fresh()->naam);
    }

    public function test_fout_openingstijd_wijzigen_met_toekomstige_reservering_wordt_geweigerd(): void
    {
        $veld = $this->padelveld();
        // Een boeking later vandaag telt ook als toekomstige reservering.
        $this->boeking($veld, '2026-09-28', '20:00:00', '21:00:00');
        $this->assertArrayHasKey('openingstijd', $this->foutVan(fn () => (new BeheerService)->veldOpslaan($this->veldgegevens(['openingstijd' => '10:00']), $veld)));
        $this->assertSame('09:00:00', $veld->fresh()->openingstijd);
        (new BeheerService)->veldOpslaan($this->veldgegevens(['naam' => 'Alleen de naam']), $veld);
        $this->assertSame('Alleen de naam', $veld->fresh()->naam);
    }

    public function test_randgeval_alleen_reserveringen_in_het_verleden_blokkeren_de_wijziging_niet(): void
    {
        $veld = $this->padelveld();
        $this->boeking($veld, '2026-09-27', '12:00:00', '13:00:00');
        (new BeheerService)->veldOpslaan($this->veldgegevens(['sporttype' => 'basketbal']), $veld);
        $this->assertSame('basketbal', $veld->fresh()->sporttype);
    }

    public function test_veld_zonder_reserveringen_wordt_verwijderd_maar_met_historie_niet(): void
    {
        $service = new BeheerService;
        $leeg = $this->padelveld(['naam' => 'Leeg']);
        $service->veldVerwijderen($leeg);
        $this->assertDatabaseMissing('velden', ['id' => $leeg->id]);
        $this->assertDatabaseMissing('tarieven', ['veld_id' => $leeg->id]);
        $historie = $this->padelveld(['naam' => 'Met historie']);
        $this->boeking($historie, '2026-09-28', '12:00:00', '13:00:00', 'geannuleerd');
        $this->assertArrayHasKey('veld', $this->foutVan(fn () => $service->veldVerwijderen($historie)));
        $this->assertDatabaseHas('velden', ['id' => $historie->id]);
    }

    public function test_blokkade_telt_alle_overlappende_bevestigde_boekingen(): void
    {
        $veld = $this->padelveld();
        $this->boeking($veld, '2026-09-28', '10:00:00', '11:00:00');
        $this->boeking($veld, '2026-09-28', '12:00:00', '13:00:00');
        $this->boeking($veld, '2026-09-28', '11:00:00', '12:00:00', 'geannuleerd');
        $fout = $this->foutVan(fn () => (new BeheerService)->blokkadeOpslaan($this->blokkade($veld, '2026-09-28T09:00', '2026-09-28T14:00'), Gebruiker::factory()->beheerder()->create()));
        $this->assertSame(['De blokkade is niet opgeslagen: 2 bevestigde reservering(en) overlappen deze periode.'], $fout['start']);
    }

    public function test_randgeval_blokkade_die_aansluit_op_een_boeking_is_toegestaan(): void
    {
        $veld = $this->padelveld();
        $this->boeking($veld, '2026-09-28', '10:00:00', '11:00:00');
        (new BeheerService)->blokkadeOpslaan($this->blokkade($veld, '2026-09-28T11:00', '2026-09-28T12:00'), Gebruiker::factory()->beheerder()->create());
        $this->assertDatabaseCount('blokkades', 1);
    }

    public function test_randgeval_voorraad_mag_gelijk_aan_de_drukste_bezetting_maar_niet_lager(): void
    {
        $veld = $this->padelveld();
        $type = $this->racketset(5);
        // 12-13 en 13-14 sluiten op elkaar aan: tegelijk zijn er nooit meer dan 3 sets in gebruik.
        $this->boeking($veld, '2026-09-28', '12:00:00', '13:00:00')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 3]);
        $this->boeking($veld, '2026-09-28', '13:00:00', '14:00:00')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 3]);
        $gegevens = ['naam' => 'Racketset', 'sporttype' => 'padel', 'totaal_aantal' => 3, 'prijs_per_set' => 3.5];
        (new BeheerService)->materiaalOpslaan($gegevens, $type);
        $this->assertSame(3, $type->fresh()->totaal_aantal);
        $this->assertArrayHasKey('totaal_aantal', $this->foutVan(fn () => (new BeheerService)->materiaalOpslaan(['totaal_aantal' => 2] + $gegevens, $type)));
    }

    public function test_fout_sport_van_toegewezen_materiaal_kan_niet_wijzigen(): void
    {
        $veld = $this->padelveld();
        $type = $this->racketset();
        $this->boeking($veld, '2026-09-29', '12:00:00', '13:00:00')->materiaalReserveringen()->create(['materiaaltype_id' => $type->id, 'aantal' => 1]);
        $this->assertArrayHasKey('sporttype', $this->foutVan(fn () => (new BeheerService)->materiaalOpslaan(['naam' => 'Racketset', 'sporttype' => 'basketbal', 'totaal_aantal' => 5, 'prijs_per_set' => 3.5], $type)));
    }
}
