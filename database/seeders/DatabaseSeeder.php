<?php

namespace Database\Seeders;

use App\Models\Blokkade;
use App\Models\Gebruiker;
use App\Models\MateriaalReservering;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Tarief;
use App\Models\Veld;
use App\Services\PrijsCalculator;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $beheerder = Gebruiker::query()->updateOrCreate([
            'email' => 'beheerder@courtconnect.test',
        ], [
            'naam' => 'Beheerder Urban Courts',
            'wachtwoord' => Hash::make('Welkom123!'),
            'rol' => 'beheerder',
        ]);

        $klant = Gebruiker::query()->updateOrCreate([
            'email' => 'klant@courtconnect.test',
        ], [
            'naam' => 'Klant CourtConnect',
            'wachtwoord' => Hash::make('Welkom123!'),
            'rol' => 'klant',
        ]);

        $velden = collect([
            ['naam' => 'Padel 1', 'sporttype' => 'padel', 'basis' => 24.00, 'piek' => 30.00],
            ['naam' => 'Padel 2', 'sporttype' => 'padel', 'basis' => 24.00, 'piek' => 30.00],
            ['naam' => 'Padel 3', 'sporttype' => 'padel', 'basis' => 24.00, 'piek' => 30.00],
            ['naam' => 'Basketbalveld', 'sporttype' => 'basketbal', 'basis' => 18.00, 'piek' => 22.00],
            ['naam' => 'Zaalvoetbal A', 'sporttype' => 'zaalvoetbal', 'basis' => 32.00, 'piek' => 40.00],
            ['naam' => 'Zaalvoetbal B', 'sporttype' => 'zaalvoetbal', 'basis' => 32.00, 'piek' => 40.00],
            ['naam' => 'Zaalvoetbal C', 'sporttype' => 'zaalvoetbal', 'basis' => 32.00, 'piek' => 40.00],
            ['naam' => 'Multicourt', 'sporttype' => 'basketbal', 'basis' => 18.00, 'piek' => 22.00],
        ])->map(function (array $gegevens): Veld {
            $veld = Veld::query()->updateOrCreate([
                'naam' => $gegevens['naam'],
            ], [
                'naam' => $gegevens['naam'],
                'sporttype' => $gegevens['sporttype'],
                'omschrijving' => 'Modern veld voor '.$gegevens['sporttype'].' bij Urban Courts.',
                'openingstijd' => '09:00:00',
                'sluitingstijd' => '23:00:00',
                'actief' => true,
            ]);

            $veld->tarieven()->delete();

            Tarief::query()->create([
                'veld_id' => $veld->id,
                'dagtype' => 'doordeweeks',
                'starttijd' => '09:00:00',
                'eindtijd' => '17:00:00',
                'prijs_per_uur' => $gegevens['basis'],
            ]);
            Tarief::query()->create([
                'veld_id' => $veld->id,
                'dagtype' => 'doordeweeks',
                'starttijd' => '17:00:00',
                'eindtijd' => '23:00:00',
                'prijs_per_uur' => $gegevens['piek'],
            ]);
            Tarief::query()->create([
                'veld_id' => $veld->id,
                'dagtype' => 'weekend',
                'starttijd' => '09:00:00',
                'eindtijd' => '23:00:00',
                'prijs_per_uur' => $gegevens['piek'],
            ]);

            return $veld;
        });

        $materialen = collect([
            ['naam' => 'Racketset', 'sporttype' => 'padel', 'totaal_aantal' => 5, 'prijs_per_set' => 3.50],
            ['naam' => 'Ballenset padel', 'sporttype' => 'padel', 'totaal_aantal' => 4, 'prijs_per_set' => 2.00],
            ['naam' => 'Basketballen', 'sporttype' => 'basketbal', 'totaal_aantal' => 6, 'prijs_per_set' => 1.50],
            ['naam' => 'Zaalvoetbalset', 'sporttype' => 'zaalvoetbal', 'totaal_aantal' => 3, 'prijs_per_set' => 4.00],
            ['naam' => 'Doelnetten', 'sporttype' => 'zaalvoetbal', 'totaal_aantal' => 2, 'prijs_per_set' => 2.50],
            ['naam' => 'Hesjes', 'sporttype' => 'zaalvoetbal', 'totaal_aantal' => 4, 'prijs_per_set' => 1.50],
        ])->mapWithKeys(fn (array $gegevens) => [
            $gegevens['naam'] => Materiaaltype::query()->updateOrCreate(['naam' => $gegevens['naam']], $gegevens),
        ]);

        $reserveringen = [
            ['veld' => 'Padel 1', 'dagen' => 1, 'start' => '10:00:00', 'einde' => '11:00:00', 'spelers' => 4, 'materiaal' => ['Racketset' => 1, 'Ballenset padel' => 1]],
            ['veld' => 'Basketbalveld', 'dagen' => 2, 'start' => '18:00:00', 'einde' => '19:00:00', 'spelers' => 8, 'materiaal' => ['Basketballen' => 2]],
            ['veld' => 'Zaalvoetbal A', 'dagen' => 3, 'start' => '19:00:00', 'einde' => '20:00:00', 'spelers' => 10, 'materiaal' => ['Zaalvoetbalset' => 1, 'Hesjes' => 2]],
            ['veld' => 'Padel 3', 'dagen' => 5, 'start' => '11:00:00', 'einde' => '12:00:00', 'spelers' => 4, 'materiaal' => ['Racketset' => 1]],
        ];

        foreach ($reserveringen as $gegevens) {
            $reservering = Reservering::query()->updateOrCreate([
                'veld_id' => $velden->firstWhere('naam', $gegevens['veld'])->id,
                'datum' => Carbon::today()->addDays($gegevens['dagen'])->toDateString(),
                'starttijd' => $gegevens['start'],
            ], [
                'klant_id' => $klant->id,
                'veld_id' => $velden->firstWhere('naam', $gegevens['veld'])->id,
                'datum' => Carbon::today()->addDays($gegevens['dagen'])->toDateString(),
                'starttijd' => $gegevens['start'],
                'eindtijd' => $gegevens['einde'],
                'aantal_spelers' => $gegevens['spelers'],
                'status' => 'bevestigd',
                'totaalprijs' => 0,
            ]);

            $keuzes = [];
            foreach ($gegevens['materiaal'] as $materiaalnaam => $aantal) {
                $keuzes[] = ['type' => $materialen[$materiaalnaam], 'aantal' => $aantal];
            }
            $prijs = app(PrijsCalculator::class)->bereken(
                $velden->firstWhere('naam', $gegevens['veld']),
                $reservering->datum->format('Y-m-d'), $gegevens['start'], $keuzes
            );
            $reservering->update(['totaalprijs' => $prijs['totaal'] / 100]);
            foreach ($gegevens['materiaal'] as $materiaalnaam => $aantal) {
                MateriaalReservering::query()->updateOrCreate([
                    'reservering_id' => $reservering->id,
                    'materiaaltype_id' => $materialen[$materiaalnaam]->id,
                ], [
                    'reservering_id' => $reservering->id,
                    'materiaaltype_id' => $materialen[$materiaalnaam]->id,
                    'aantal' => $aantal,
                ]);
            }
        }

        Blokkade::query()->updateOrCreate([
            'veld_id' => $velden->firstWhere('naam', 'Padel 2')->id,
            'start' => Carbon::today()->addDays(2)->setTime(14, 0),
        ], [
            'veld_id' => $velden->firstWhere('naam', 'Padel 2')->id,
            'start' => Carbon::today()->addDays(2)->setTime(14, 0),
            'einde' => Carbon::today()->addDays(2)->setTime(16, 0),
            'reden' => 'Onderhoud aan glaswand',
            'aangemaakt_door' => $beheerder->id,
        ]);
    }
}
