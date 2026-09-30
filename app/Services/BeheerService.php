<?php

namespace App\Services;

use App\Models\Blokkade;
use App\Models\Gebruiker;
use App\Models\MateriaalReservering;
use App\Models\Materiaaltype;
use App\Models\Veld;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BeheerService
{
    public function veldOpslaan(array $gegevens, ?Veld $veld = null): Veld
    {
        return DB::transaction(function () use ($gegevens, $veld) {
            if ($veld) {
                $veld = Veld::whereKey($veld->id)->lockForUpdate()->firstOrFail();
                if ($veld->reserveringen()->where('status', 'bevestigd')->where('datum', '>=', today())->exists()
                    && ($veld->sporttype !== $gegevens['sporttype'] || substr($veld->openingstijd, 0, 5) !== $gegevens['openingstijd'] || substr($veld->sluitingstijd, 0, 5) !== $gegevens['sluitingstijd'])) {
                    throw ValidationException::withMessages(['openingstijd' => 'Dit veld heeft nog reserveringen. Wijzig de sport of openingstijden pas nadat deze zijn afgehandeld.']);
                }
            }
            $veld ??= new Veld;
            $veld->fill(collect($gegevens)->except('tarieven')->all())->save();
            $veld->tarieven()->delete();
            $veld->tarieven()->createMany($gegevens['tarieven']);

            return $veld;
        });
    }

    public function veldVerwijderen(Veld $veld): void
    {
        DB::transaction(function () use ($veld) {
            $veld = Veld::whereKey($veld->id)->lockForUpdate()->firstOrFail();
            if ($veld->reserveringen()->exists()) {
                throw ValidationException::withMessages(['veld' => 'Dit veld heeft reserveringen en kan niet worden verwijderd. Zet het veld op inactief.']);
            }
            $veld->delete();
        });
    }

    public function blokkadeOpslaan(array $gegevens, Gebruiker $beheerder): void
    {
        DB::transaction(function () use ($gegevens, $beheerder) {
            $veld = Veld::whereKey($gegevens['veld_id'])->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::parse($gegevens['start']);
            $einde = CarbonImmutable::parse($gegevens['einde']);
            $aantal = $veld->reserveringen()->where('status', 'bevestigd')->whereBetween('datum', [$start->toDateString(), $einde->toDateString()])->get()
                ->filter(fn ($reservering) => CarbonImmutable::parse($reservering->datum->format('Y-m-d').' '.$reservering->starttijd) < $einde
                    && CarbonImmutable::parse($reservering->datum->format('Y-m-d').' '.$reservering->eindtijd) > $start)->count();
            if ($aantal > 0) {
                throw ValidationException::withMessages(['start' => "De blokkade is niet opgeslagen: $aantal bevestigde reservering(en) overlappen deze periode."]);
            }
            Blokkade::create($gegevens + ['aangemaakt_door' => $beheerder->id]);
        });
    }

    public function materiaalOpslaan(array $gegevens, ?Materiaaltype $materiaal = null): void
    {
        DB::transaction(function () use ($gegevens, $materiaal) {
            if ($materiaal) {
                $materiaal = Materiaaltype::whereKey($materiaal->id)->lockForUpdate()->firstOrFail();
                $toewijzingen = MateriaalReservering::with('reservering')->where('materiaaltype_id', $materiaal->id)->whereHas('reservering', fn ($query) => $query->where('status', 'bevestigd')->where('datum', '>=', today()))->get();
                if ($toewijzingen->isNotEmpty() && $gegevens['sporttype'] !== $materiaal->sporttype) {
                    throw ValidationException::withMessages(['sporttype' => 'Dit materiaal is nog toegewezen. De sport kan nu niet worden gewijzigd.']);
                }
                $momenten = [];
                foreach ($toewijzingen as $toewijzing) {
                    $reservering = $toewijzing->reservering;
                    $datum = $reservering->datum->format('Y-m-d');
                    $momenten[] = [$datum.' '.$reservering->starttijd, $toewijzing->aantal];
                    $momenten[] = [$datum.' '.$reservering->eindtijd, -$toewijzing->aantal];
                }
                usort($momenten, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
                $bezet = 0;
                foreach ($momenten as [$moment, $verschil]) {
                    $bezet += $verschil;
                    if ($bezet > $gegevens['totaal_aantal']) {
                        throw ValidationException::withMessages(['totaal_aantal' => "Er zijn al $bezet sets tegelijk gereserveerd. De voorraad kan niet zo laag worden."]);
                    }
                }
            }
            ($materiaal ?? new Materiaaltype)->fill($gegevens)->save();
        });
    }
}
