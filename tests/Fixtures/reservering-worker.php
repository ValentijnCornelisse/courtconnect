<?php

use App\Models\Gebruiker;
use App\Services\ReserveringService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('COURTCONNECT_TEST_DB'), true)]);
DB::purge('mysql');
$gegevens = json_decode($argv[1], true);
file_put_contents($argv[2], 'klaar');
try {
    $klant = Gebruiker::findOrFail($argv[3]);
    app(ReserveringService::class)->bevestigen($klant, $gegevens, (int) $argv[4]);
    echo 'bevestigd';
    exit(0);
} catch (ValidationException $fout) {
    echo 'geweigerd';
    exit(2);
}
