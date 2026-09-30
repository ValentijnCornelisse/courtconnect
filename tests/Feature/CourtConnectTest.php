<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Veld;
use App\Services\BeschikbaarheidChecker;
use App\Services\PrijsCalculator;
use App\Services\ReserveringService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CourtConnectTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function voorbereiden(): array
    {
        $this->travelTo(Carbon::parse('2026-09-28 08:00'));
        $this->seed();

        return [Gebruiker::where('rol', 'klant')->first(), Veld::where('naam', 'Padel 1')->first()];
    }

    private function gegevens(Veld $veld, array $extra = []): array
    {
        return array_replace(['veld_id' => $veld->id, 'datum' => '2026-09-28', 'starttijd' => '12:00', 'aantal_spelers' => 4, 'materiaal' => []], $extra);
    }

    public function test_seeders_maken_acht_velden_en_geldige_prijzen_en_hashes(): void
    {
        [$klant] = $this->voorbereiden();
        $this->assertDatabaseCount('velden', 8);
        $this->assertDatabaseCount('materiaaltypes', 6);
        $this->assertDatabaseCount('blokkades', 1);
        $this->assertTrue(Hash::check('Welkom123!', $klant->wachtwoord));
        $this->assertSame('47.00', Reservering::whereHas('veld', fn ($query) => $query->where('naam', 'Zaalvoetbal A'))->first()->totaalprijs);
    }

    public function test_klant_krijgt_nette_403_op_beheer_en_beheerder_op_boeken(): void
    {
        [$klant] = $this->voorbereiden();
        $this->actingAs($klant)->get('/beheer/velden')->assertForbidden()->assertSee('Je hebt geen toegang tot deze pagina.');
        $this->actingAs(Gebruiker::where('rol', 'beheerder')->first())->post('/reserveringen/controle', [])->assertForbidden();
    }

    public function test_registratie_negeert_meegegeven_beheerdersrol_en_logt_in(): void
    {
        $this->post('/registreren', ['naam' => 'Nieuwe klant', 'email' => 'nieuw@example.test', 'wachtwoord' => 'Welkom123!', 'wachtwoord_confirmation' => 'Welkom123!', 'rol' => 'beheerder'])->assertRedirect('/velden');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('gebruikers', ['email' => 'nieuw@example.test', 'rol' => 'klant']);
        $this->assertTrue(Hash::check('Welkom123!', Gebruiker::where('email', 'nieuw@example.test')->first()->wachtwoord));
    }

    public function test_inloggen_gebruikt_nederlandse_wachtwoordkolom(): void
    {
        $this->voorbereiden();
        $this->post('/inloggen', ['email' => 'klant@courtconnect.test', 'wachtwoord' => 'fout'])->assertSessionHasErrors('email');
        $this->post('/inloggen', ['email' => 'klant@courtconnect.test', 'wachtwoord' => 'Welkom123!'])->assertRedirect('/velden');
        $this->assertAuthenticated();
    }

    public function test_klantflow_berekent_materiaal_en_slaat_pas_na_bevestiging_op(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        $type = Materiaaltype::where('naam', 'Racketset')->first();
        $this->actingAs($klant)->post('/reserveringen/controle', $this->gegevens($veld, ['materiaal' => [$type->id => 2]]))->assertRedirect('/reserveringen/controle');
        $this->assertDatabaseCount('reserveringen', 4);
        $this->get('/reserveringen/controle')->assertSee('31,00')->assertSee('2 × Racketset');
        $this->post('/reserveringen/bevestigen', ['totaalprijs' => 0])->assertRedirect('/mijn-reserveringen');
        $this->assertDatabaseHas('reserveringen', ['veld_id' => $veld->id, 'datum' => '2026-09-28', 'totaalprijs' => 31]);
        $this->assertDatabaseHas('materiaal_reserveringen', ['materiaaltype_id' => $type->id, 'aantal' => 2]);
    }

    public function test_een_bezet_blok_en_materiaal_kunnen_niet_opnieuw_worden_geboekt(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        $type = Materiaaltype::where('naam', 'Racketset')->first();
        $gegevens = $this->gegevens($veld, ['materiaal' => [$type->id => 5]]);
        app(ReserveringService::class)->bevestigen($klant, $gegevens, 4150);
        $this->actingAs($klant)->post('/reserveringen/controle', $gegevens)->assertSessionHasErrors('starttijd');
        $gegevens['veld_id'] = Veld::where('naam', 'Padel 2')->first()->id;
        $this->post('/reserveringen/controle', $gegevens)->assertSessionHasErrors('materiaal.'.$type->id);
        $this->assertDatabaseCount('reserveringen', 5);
    }

    public function test_annuleren_geeft_blok_vrij_maar_weigert_vreemde_of_gestarte_boeking(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        $boeking = app(ReserveringService::class)->bevestigen($klant, $this->gegevens($veld), 2400);
        $ander = Gebruiker::factory()->create();
        $this->actingAs($ander)->post('/reserveringen/'.$boeking->id.'/annuleren')->assertForbidden();
        $this->actingAs($klant)->post('/reserveringen/'.$boeking->id.'/annuleren')->assertRedirect('/mijn-reserveringen');
        $this->assertTrue(app(BeschikbaarheidChecker::class)->isVrij($veld, '2026-09-28', '12:00'));
        $nieuw = app(ReserveringService::class)->bevestigen($klant, $this->gegevens($veld), 2400);
        $this->travelTo(Carbon::parse('2026-09-28 12:01'));
        $this->post('/reserveringen/'.$nieuw->id.'/annuleren')->assertSessionHasErrors('reservering');
        $this->assertSame('bevestigd', $nieuw->fresh()->status);
    }

    public function test_prijs_splitsing_piek_weekend_en_halve_uren(): void
    {
        [, $veld] = $this->voorbereiden();
        $calculator = app(PrijsCalculator::class);
        $this->assertSame(['basis' => 2400, 'piek' => 600, 'materiaal' => [], 'totaal' => 3000], $calculator->bereken($veld, '2026-10-03', '10:00'));
        $this->assertSame(2700, $calculator->bereken($veld, '2026-09-28', '16:30')['totaal']);
    }

    public function test_blokkade_met_bevestigde_reservering_wordt_geweigerd(): void
    {
        $this->voorbereiden();
        $veld = Veld::where('naam', 'Padel 1')->first();
        $beheerder = Gebruiker::where('rol', 'beheerder')->first();
        $this->actingAs($beheerder)->post('/beheer/blokkades', ['veld_id' => $veld->id, 'reden' => 'Onderhoud', 'start' => '2026-09-29T10:30', 'einde' => '2026-09-29T12:00'])->assertSessionHasErrors('start');
        $this->assertDatabaseCount('blokkades', 1);
        $this->post('/beheer/blokkades', ['veld_id' => $veld->id, 'reden' => 'Onderhoud', 'start' => '2026-09-29T11:00', 'einde' => '2026-09-29T12:00'])->assertRedirect('/beheer/blokkades');
        $this->assertFalse(app(BeschikbaarheidChecker::class)->isVrij($veld, '2026-09-29', '11:00'));
    }

    public function test_alle_schermen_renderen_en_ongeldige_invoer_wordt_gemeld(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        foreach (['/', '/velden', '/tarieven', '/contact', '/over-ons', '/inloggen', '/registreren', '/velden/'.$veld->id.'/tijden?datum=2026-09-28&starttijd=12:00'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs($klant)->post('/reserveringen/controle', $this->gegevens($veld, ['aantal_spelers' => 13]))->assertSessionHasErrors(['aantal_spelers' => 'Vul een aantal spelers in tussen 1 en 12.']);
        $this->actingAs(Gebruiker::where('rol', 'beheerder')->first());
        foreach (['/beheer/weekrooster', '/beheer/velden', '/beheer/blokkades', '/beheer/materiaal', '/beheer/velden/'.$veld->id.'/bewerken', '/beheer/materiaal/'.Materiaaltype::first()->id.'/bewerken'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_voorraad_mag_niet_onder_toegewezen_aantal(): void
    {
        $this->voorbereiden();
        $type = Materiaaltype::where('naam', 'Racketset')->first();
        $this->actingAs(Gebruiker::where('rol', 'beheerder')->first())->put('/beheer/materiaal/'.$type->id, ['naam' => $type->naam, 'sporttype' => 'padel', 'totaal_aantal' => 0, 'prijs_per_set' => 3.5])->assertSessionHasErrors('totaal_aantal');
        $this->assertSame(5, $type->fresh()->totaal_aantal);
    }

    public function test_verleden_verkeerde_start_en_onbekend_materiaal_worden_geweigerd(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        $this->actingAs($klant)->post('/reserveringen/controle', $this->gegevens($veld, ['datum' => '2026-09-27']))->assertSessionHasErrors('datum');
        $this->post('/reserveringen/controle', $this->gegevens($veld, ['starttijd' => '12:15']))->assertSessionHasErrors('starttijd');
        $this->post('/reserveringen/controle', $this->gegevens($veld, ['materiaal' => ['onbekend' => 1]]))->assertSessionHasErrors('materiaal');
    }

    public function test_veldnaam_wordt_geescaped(): void
    {
        [, $veld] = $this->voorbereiden();
        $veld->update(['naam' => '<script>alert(1)</script>']);
        $this->get('/velden')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_beheerder_kan_velden_met_tariefregels_toevoegen_wijzigen_en_verwijderen(): void
    {
        $this->voorbereiden();
        $beheerder = Gebruiker::where('rol', 'beheerder')->first();
        $gegevens = ['naam' => 'Testveld', 'sporttype' => 'padel', 'omschrijving' => 'Een testveld', 'openingstijd' => '09:00', 'sluitingstijd' => '23:00', 'actief' => 1,
            'tarieven' => [['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 20], ['dagtype' => 'weekend', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 25]]];
        $this->actingAs($beheerder)->post('/beheer/velden', $gegevens)->assertRedirect('/beheer/velden');
        $veld = Veld::where('naam', 'Testveld')->firstOrFail();
        $this->assertSame(2, $veld->tarieven()->count());
        $gegevens['naam'] = 'Gewijzigd veld';
        $this->put('/beheer/velden/'.$veld->id, $gegevens)->assertRedirect('/beheer/velden');
        $this->assertDatabaseHas('velden', ['id' => $veld->id, 'naam' => 'Gewijzigd veld']);
        $gegevens['tarieven'][0]['starttijd'] = '10:00';
        $this->put('/beheer/velden/'.$veld->id, $gegevens)->assertSessionHasErrors('tarieven.0.starttijd');
        $this->delete('/beheer/velden/'.$veld->id)->assertRedirect('/beheer/velden');
        $this->assertDatabaseMissing('velden', ['id' => $veld->id]);
    }

    public function test_beheerder_kan_materiaal_toevoegen_en_wijzigen(): void
    {
        $this->voorbereiden();
        $this->actingAs(Gebruiker::where('rol', 'beheerder')->first());
        $gegevens = ['naam' => 'Extra set', 'sporttype' => 'padel', 'totaal_aantal' => 7, 'prijs_per_set' => 2.5];
        $this->post('/beheer/materiaal', $gegevens)->assertRedirect('/beheer/materiaal');
        $type = Materiaaltype::where('naam', 'Extra set')->firstOrFail();
        $gegevens['totaal_aantal'] = 8;
        $this->put('/beheer/materiaal/'.$type->id, $gegevens)->assertRedirect('/beheer/materiaal');
        $this->assertSame(8, $type->fresh()->totaal_aantal);
    }

    public function test_prijs_bijwerken_en_aanpassen_behouden_de_materiaalkeuze(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        $type = Materiaaltype::where('naam', 'Racketset')->first();
        $this->actingAs($klant)->post('/reserveringen/controle', $this->gegevens($veld, ['materiaal' => [$type->id => 2], 'actie' => 'prijs']))->assertRedirect();
        $this->get('/velden/'.$veld->id.'/tijden?datum=2026-09-28&starttijd=12:00')->assertSee('31,00')->assertSee('2 × Racketset');
        $this->assertDatabaseCount('reserveringen', 4);
    }

    public function test_onverwachte_fout_is_neutraal_en_verkeerde_materiaalstructuur_geeft_validatiefout(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        $this->actingAs($klant)->post('/reserveringen/controle', $this->gegevens($veld, ['materiaal' => 'geen array']))->assertSessionHasErrors('materiaal');
        Route::get('/test-serverfout', function () {
            throw new \RuntimeException('Technisch geheim');
        });
        $this->get('/test-serverfout')->assertStatus(500)->assertSee('Er is een onverwachte fout opgetreden.')->assertDontSee('Technisch geheim');
    }

    public function test_unieke_index_weigert_twee_bevestigde_boekingen_maar_laat_annuleringen_toe(): void
    {
        [$klant,$veld] = $this->voorbereiden();
        $origineel = app(ReserveringService::class)->bevestigen($klant, $this->gegevens($veld), 2400);
        $kopie = $origineel->replicate(['bevestigde_starttijd']);
        $kopie->status = 'geannuleerd';
        $kopie->save();
        $this->assertDatabaseCount('reserveringen', 6);
        $this->expectException(UniqueConstraintViolationException::class);
        $kopie->update(['status' => 'bevestigd']);
    }
}
