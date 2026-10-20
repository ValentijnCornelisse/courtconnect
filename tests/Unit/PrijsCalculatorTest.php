<?php

namespace Tests\Unit;

use App\Models\Materiaaltype;
use App\Models\Tarief;
use App\Models\Veld;
use App\Services\PrijsCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Losse functie zonder database: het veld en de tarieven bestaan alleen in het geheugen.
 */
class PrijsCalculatorTest extends TestCase
{
    private function veld(array $tarieven = [['doordeweeks', '09:00', '17:00', 24], ['doordeweeks', '17:00', '23:00', 30], ['weekend', '09:00', '23:00', 30]]): Veld
    {
        $veld = new Veld(['naam' => 'Padel 1', 'sporttype' => 'padel', 'openingstijd' => '09:00:00', 'sluitingstijd' => '23:00:00', 'actief' => true]);

        return $veld->setRelation('tarieven', collect($tarieven)->map(fn ($regel) => new Tarief(['dagtype' => $regel[0], 'starttijd' => $regel[1].':00', 'eindtijd' => $regel[2].':00', 'prijs_per_uur' => $regel[3]])));
    }

    private function materiaal(string $naam, float $prijs): Materiaaltype
    {
        return new Materiaaltype(['naam' => $naam, 'sporttype' => 'padel', 'totaal_aantal' => 5, 'prijs_per_set' => $prijs]);
    }

    public function test_daluur_op_maandag_kost_alleen_het_basistarief(): void
    {
        $this->assertSame(['uren' => 1, 'basis' => 2400, 'piek' => 0, 'materiaal' => [], 'totaal' => 2400], (new PrijsCalculator)->bereken($this->veld(), '2026-09-28', '12:00'));
    }

    public function test_piekuur_doordeweeks_telt_het_verschil_als_piektoeslag(): void
    {
        $this->assertSame(['uren' => 1, 'basis' => 2400, 'piek' => 600, 'materiaal' => [], 'totaal' => 3000], (new PrijsCalculator)->bereken($this->veld(), '2026-09-28', '18:00'));
    }

    public function test_zaterdag_en_zondag_gebruiken_het_weekendtarief(): void
    {
        $calculator = new PrijsCalculator;
        $this->assertSame(3000, $calculator->bereken($this->veld(), '2026-10-03', '10:00')['totaal']);
        $this->assertSame(3000, $calculator->bereken($this->veld(), '2026-10-04', '10:00')['totaal']);
    }

    public function test_drie_uur_over_de_tariefgrens_wordt_per_minuut_verdeeld(): void
    {
        $prijs = (new PrijsCalculator)->bereken($this->veld(), '2026-09-28', '15:00', [], 3);
        $this->assertSame(['uren' => 3, 'basis' => 7200, 'piek' => 600, 'materiaal' => [], 'totaal' => 7800], $prijs);
    }

    public function test_materiaal_kost_een_vaste_prijs_per_set_ongeacht_de_duur(): void
    {
        $keuzes = [['type' => $this->materiaal('Racketset', 3.50), 'aantal' => 2], ['type' => $this->materiaal('Ballenset padel', 2.00), 'aantal' => 1]];
        $prijs = (new PrijsCalculator)->bereken($this->veld(), '2026-09-28', '12:00', $keuzes, 2);
        $this->assertSame([['naam' => 'Racketset', 'aantal' => 2, 'bedrag' => 700], ['naam' => 'Ballenset padel', 'aantal' => 1, 'bedrag' => 200]], $prijs['materiaal']);
        $this->assertSame(4800 + 900, $prijs['totaal']);
    }

    public function test_randgeval_laatste_uur_voor_sluiting_heeft_een_tarief(): void
    {
        $this->assertSame(3000, (new PrijsCalculator)->bereken($this->veld(), '2026-09-28', '22:00')['totaal']);
    }

    public function test_randgeval_afronding_naar_hele_centen(): void
    {
        $veld = $this->veld([['doordeweeks', '09:00', '12:30', 10.01], ['doordeweeks', '12:30', '23:00', 10.02], ['weekend', '09:00', '23:00', 10]]);
        // (30 × 1001 + 30 × 1002) / 60 = 1001,5 cent: wordt naar boven afgerond.
        $this->assertSame(1002, (new PrijsCalculator)->bereken($veld, '2026-09-28', '12:00')['totaal']);
    }

    public function test_fout_tijdsblok_zonder_tarief_wordt_geweigerd(): void
    {
        $this->expectException(ValidationException::class);
        (new PrijsCalculator)->bereken($this->veld(), '2026-09-28', '23:00');
    }

    public function test_fout_overlappende_tariefregels_geven_geen_prijs(): void
    {
        $veld = $this->veld([['doordeweeks', '09:00', '18:00', 24], ['doordeweeks', '17:00', '23:00', 30], ['weekend', '09:00', '23:00', 30]]);
        try {
            (new PrijsCalculator)->bereken($veld, '2026-09-28', '17:00');
            $this->fail('Overlappende tarieven mogen geen prijs opleveren.');
        } catch (ValidationException $fout) {
            $this->assertSame(['starttijd' => ['Voor dit tijdsblok is geen eenduidig tarief ingesteld. Kies een ander tijdsblok.']], $fout->errors());
        }
    }

    public function test_randgeval_totaal_mag_precies_het_maximum_zijn_maar_niet_hoger(): void
    {
        $calculator = new PrijsCalculator;
        $this->assertSame(999999, $calculator->bereken($this->veld(), '2026-09-28', '12:00', [['type' => $this->materiaal('Duur', 9975.99), 'aantal' => 1]])['totaal']);
        $this->expectException(ValidationException::class);
        $calculator->bereken($this->veld(), '2026-09-28', '12:00', [['type' => $this->materiaal('Duur', 9976.00), 'aantal' => 1]]);
    }
}
