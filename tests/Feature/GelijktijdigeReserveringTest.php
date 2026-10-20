<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Veld;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GelijktijdigeReserveringTest extends TestCase
{
    private function race(bool $zelfdeVeld): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Deze echte procestest vereist een aparte MySQL/MariaDB-database met een naam eindigend op _test.');
        }
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName(), 'Gebruik uitsluitend een aparte testdatabase.');
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->seed();
        $klant = Gebruiker::where('rol', 'klant')->firstOrFail();
        $velden = Veld::where('sporttype', 'padel')->orderBy('id')->take(2)->get();
        $type = Materiaaltype::where('naam', 'Racketset')->firstOrFail();
        $datum = today()->addWeeks(2)->startOfWeek()->format('Y-m-d');
        $gegevens = ['veld_id' => $velden[0]->id, 'datum' => $datum, 'starttijd' => '12:00', 'aantal_spelers' => 2, 'materiaal' => $zelfdeVeld ? [] : [$type->id => 5]];
        $prijs = $zelfdeVeld ? 2400 : 4150;
        $tweede = array_replace($gegevens, ['veld_id' => $velden[$zelfdeVeld ? 0 : 1]->id]);
        $omgeving = ['COURTCONNECT_TEST_DB' => json_encode(config('database.connections.mysql'))];
        $bestanden = [tempnam(sys_get_temp_dir(), 'cc1'), tempnam(sys_get_temp_dir(), 'cc2')];
        $processen = [];
        DB::beginTransaction();
        try {
            Veld::whereIn('id', $velden->pluck('id'))->lockForUpdate()->get();
            foreach ([$gegevens, $tweede] as $index => $invoer) {
                $proces = new Process([PHP_BINARY, base_path('tests/Fixtures/reservering-worker.php'), json_encode($invoer), $bestanden[$index], (string) $klant->id, (string) $prijs], base_path(), $omgeving);
                $proces->setTimeout(20);
                $proces->start();
                $processen[] = $proces;
            }
            $grens = microtime(true) + 10;
            do {
                clearstatcache();
                $klaar = filesize($bestanden[0]) > 0 && filesize($bestanden[1]) > 0;
                if (! $klaar) {
                    usleep(10000);
                }
            } while (! $klaar && microtime(true) < $grens);
            $this->assertTrue($klaar, 'Beide processen moeten tegelijk actief zijn voordat het veldslot wordt vrijgegeven.');
            DB::commit();
            $uitkomsten = [];
            foreach ($processen as $proces) {
                $proces->wait();
                $this->assertContains($proces->getExitCode(), [0, 2], $proces->getErrorOutput());
                $uitkomsten[] = $proces->getOutput();
            }
            sort($uitkomsten);
            $this->assertSame(['bevestigd', 'geweigerd'], $uitkomsten);
            $this->assertSame(1, Reservering::where('datum', $datum)->where('status', 'bevestigd')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processen as $proces) {
                if ($proces->isRunning()) {
                    $proces->stop();
                }
            }
            foreach ($bestanden as $bestand) {
                unlink($bestand);
            }
            // De processen committen echt; ruim op zodat volgende tests met een lege database beginnen.
            $this->artisan('migrate:fresh', ['--force' => true]);
        }
    }

    public function test_twee_processen_kunnen_niet_hetzelfde_blok_boeken(): void
    {
        $this->race(true);
    }

    public function test_twee_velden_kunnen_niet_dezelfde_laatste_sets_boeken(): void
    {
        $this->race(false);
    }
}
