<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Models\Materiaaltype;
use App\Models\Reservering;
use App\Models\Veld;
use App\Services\ReserveringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * De klant van zoeken tot annuleren: formulier → route → controller → service → database → pagina.
 */
class ReserverenTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Gebruiker $klant;

    private Veld $veld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 08:00'));
        $this->seed();
        $this->klant = Gebruiker::where('rol', 'klant')->firstOrFail();
        $this->veld = Veld::where('naam', 'Padel 1')->firstOrFail();
    }

    private function gegevens(array $extra = []): array
    {
        return array_replace(['veld_id' => $this->veld->id, 'datum' => '2026-09-28', 'starttijd' => '12:00', 'duur' => 1, 'aantal_spelers' => 4, 'materiaal' => []], $extra);
    }

    public function test_zoeken_op_sport_toont_alleen_velden_van_die_sport(): void
    {
        $this->get('/reserveren?datum=2026-09-28&sporttype=zaalvoetbal')->assertOk()
            ->assertSee('<h3>Zaalvoetbal A</h3>', false)->assertSee('<h3>Zaalvoetbal C</h3>', false)->assertSee('3 van 3 velden met vrije uren')
            ->assertDontSee('<h3>Padel 1</h3>', false)->assertDontSee('<h3>Basketbalveld</h3>', false);
    }

    public function test_zoeken_met_tijdvenster_toont_alleen_blokken_binnen_dat_venster(): void
    {
        $this->get('/reserveren?datum=2026-09-28&veld_id='.$this->veld->id.'&van=18:00&tot=20:00')->assertOk()
            ->assertSee('starttijd=18%3A00', false)->assertSee('starttijd=19%3A00', false)
            ->assertDontSee('starttijd=17%3A00', false)->assertDontSee('starttijd=20%3A00', false);
    }

    public function test_fout_tijdvenster_met_eindtijd_voor_begintijd_wordt_gemeld(): void
    {
        $this->from('/reserveren')->get('/reserveren?datum=2026-09-28&van=18:00&tot=17:00')->assertRedirect('/reserveren')->assertSessionHasErrors('tot');
    }

    public function test_bezet_en_onderhoud_zijn_niet_aanklikbaar_op_de_reserveerpagina(): void
    {
        $this->get('/reserveren?datum=2026-09-29&veld_id='.$this->veld->id)->assertOk()
            ->assertSee('Bezet')->assertDontSee('starttijd=10%3A00', false)->assertSee('starttijd=11%3A00', false);
        $padel2 = Veld::where('naam', 'Padel 2')->first();
        $this->get('/reserveren?datum=2026-09-30&veld_id='.$padel2->id)->assertOk()
            ->assertSee('Onderhoud')->assertDontSee('starttijd=14%3A00', false)->assertSee('starttijd=16%3A00', false);
    }

    public function test_gast_moet_inloggen_voor_de_controlepagina(): void
    {
        $this->post('/reserveringen/controle', $this->gegevens())->assertRedirect('/inloggen');
        $this->get('/mijn-reserveringen')->assertRedirect('/inloggen');
        $this->assertDatabaseCount('reserveringen', 4);
    }

    public function test_fout_controle_of_bevestigen_zonder_gekozen_tijdsblok(): void
    {
        $this->actingAs($this->klant)->get('/reserveringen/controle')->assertRedirect('/reserveren')->assertSessionHas('melding', 'Kies eerst een veld en tijdsblok.');
        $this->post('/reserveringen/bevestigen')->assertRedirect('/reserveren');
        $this->assertDatabaseCount('reserveringen', 4);
    }

    public function test_fout_blok_dat_tijdens_het_controleren_wordt_geboekt_kan_niet_meer_worden_bevestigd(): void
    {
        $this->actingAs($this->klant)->post('/reserveringen/controle', $this->gegevens())->assertRedirect('/reserveringen/controle');
        app(ReserveringService::class)->bevestigen(Gebruiker::factory()->create(), $this->gegevens(), 2400);
        $this->get('/reserveringen/controle')->assertRedirectContains('/reserveren')->assertSessionHasErrors('starttijd');
        $this->post('/reserveringen/bevestigen')->assertRedirectContains('gekozen_veld='.$this->veld->id)->assertSessionHasErrors('starttijd');
        $this->assertSame(1, Reservering::where('veld_id', $this->veld->id)->where('datum', '2026-09-28')->count());
        $this->post('/reserveringen/bevestigen')->assertRedirect('/reserveren');
    }

    public function test_randgeval_een_en_twaalf_spelers_mogen_nul_niet(): void
    {
        $this->actingAs($this->klant);
        $this->post('/reserveringen/controle', $this->gegevens(['aantal_spelers' => 1]))->assertSessionHasNoErrors();
        $this->post('/reserveringen/controle', $this->gegevens(['aantal_spelers' => 12]))->assertSessionHasNoErrors();
        $this->post('/reserveringen/controle', $this->gegevens(['aantal_spelers' => 0]))->assertSessionHasErrors(['aantal_spelers' => 'Vul een aantal spelers in tussen 1 en 12.']);
    }

    public function test_randgeval_drie_uur_tot_precies_de_sluitingstijd(): void
    {
        $this->actingAs($this->klant)->post('/reserveringen/controle', $this->gegevens(['starttijd' => '20:00', 'duur' => 3]))->assertRedirect('/reserveringen/controle');
        $this->post('/reserveringen/bevestigen')->assertRedirect('/mijn-reserveringen');
        $this->assertDatabaseHas('reserveringen', ['veld_id' => $this->veld->id, 'starttijd' => '20:00:00', 'eindtijd' => '23:00:00', 'totaalprijs' => 90]);
    }

    public function test_randgeval_vandaag_kan_niet_meer_in_een_begonnen_uur_worden_geboekt(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 12:01'));
        $this->actingAs($this->klant)->post('/reserveringen/controle', $this->gegevens())->assertSessionHasErrors('starttijd');
        $this->post('/reserveringen/controle', $this->gegevens(['starttijd' => '13:00']))->assertSessionHasNoErrors();
    }

    public function test_fout_inactief_veld_is_niet_zichtbaar_en_niet_te_boeken(): void
    {
        $this->veld->update(['actief' => false]);
        $this->get('/velden?datum=2026-09-28')->assertDontSee('Padel 1');
        $this->get('/reserveren?datum=2026-09-28')->assertDontSee('<h3>Padel 1</h3>', false);
        $this->actingAs($this->klant)->post('/reserveringen/controle', $this->gegevens())->assertSessionHasErrors('starttijd');
    }

    public function test_mijn_reserveringen_toont_alleen_eigen_boekingen_en_annuleerknop_alleen_bij_toekomstige(): void
    {
        $ander = Gebruiker::factory()->create();
        $padel3 = Veld::where('naam', 'Padel 3')->first();
        app(ReserveringService::class)->bevestigen($ander, ['veld_id' => $padel3->id, 'datum' => '2026-09-28', 'starttijd' => '15:00', 'duur' => 1, 'aantal_spelers' => 2, 'materiaal' => []], 2400);
        $eigen = app(ReserveringService::class)->bevestigen($this->klant, $this->gegevens(), 2400);
        $pagina = $this->actingAs($this->klant)->get('/mijn-reserveringen')->assertOk();
        // De klant heeft vier boekingen uit de seeder plus deze nieuwe; de boeking van de ander (15:00) ontbreekt.
        $pagina->assertSee('12:00 – 13:00')->assertDontSee('15:00 – 16:00');
        $this->assertSame(5, substr_count($pagina->getContent(), 'Annuleer reservering'));
        $this->post('/reserveringen/'.$eigen->id.'/annuleren');
        $this->get('/mijn-reserveringen')->assertSee('Geannuleerd');
        $this->assertSame(4, substr_count($this->get('/mijn-reserveringen')->getContent(), 'Annuleer reservering'));
        $this->actingAs($ander)->get('/mijn-reserveringen')->assertSee('15:00 – 16:00')->assertDontSee('12:00 – 13:00');
    }

    public function test_fout_onbekende_reservering_annuleren_geeft_404(): void
    {
        $this->actingAs($this->klant)->post('/reserveringen/99999/annuleren')->assertNotFound()->assertSee('Pagina niet gevonden');
    }

    public function test_boeking_van_klant_is_direct_zichtbaar_in_beheer(): void
    {
        $type = Materiaaltype::where('naam', 'Racketset')->first();
        $this->actingAs($this->klant)->post('/reserveringen/controle', $this->gegevens(['materiaal' => [$type->id => 3]]));
        $this->post('/reserveringen/bevestigen')->assertRedirect('/mijn-reserveringen');
        $beheerder = Gebruiker::where('rol', 'beheerder')->first();
        $this->actingAs($beheerder)->get('/beheer/materiaal?datum=2026-09-28&starttijd=12:00')->assertSee('2 vrij');
        $this->get('/beheer/weekrooster?veld_id='.$this->veld->id.'&datum=2026-09-28')->assertSee('Geboekt');
        $this->get('/velden?datum=2026-09-28')->assertSee('13 van 14 uur vrij');
    }
}
