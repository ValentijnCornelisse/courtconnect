<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Models\Veld;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Beheerschermen via formulieren en routes, met de demodata uit de seeder.
 */
class BeheerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Veld $veld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 08:00'));
        $this->seed();
        $this->veld = Veld::where('naam', 'Padel 1')->firstOrFail();
        $this->actingAs(Gebruiker::where('rol', 'beheerder')->firstOrFail());
    }

    private function veldformulier(array $extra = []): array
    {
        return array_replace(['naam' => 'Padel 4', 'sporttype' => 'padel', 'omschrijving' => '', 'openingstijd' => '09:00', 'sluitingstijd' => '23:00', 'actief' => 1,
            'tarieven' => [['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 20], ['dagtype' => 'weekend', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 25]]], $extra);
    }

    public function test_weekrooster_toont_geboekte_en_geblokkeerde_uren(): void
    {
        $this->get('/beheer/weekrooster?veld_id='.$this->veld->id.'&datum=2026-09-28')->assertOk()->assertSee('28/09 – 04/10/2026')->assertSee('Geboekt');
        $padel2 = Veld::where('naam', 'Padel 2')->first();
        $this->get('/beheer/weekrooster?veld_id='.$padel2->id.'&datum=2026-09-30')->assertOk()->assertSee('Onderhoud')->assertDontSee('Geboekt');
    }

    public function test_tariefregels_met_sleutels_uit_het_javascript_worden_geaccepteerd(): void
    {
        // public/js/courtconnect.js geeft nieuwe regels Date.now() als sleutel; de volgorde in het formulier doet er niet toe.
        $tarieven = [1760000000002 => ['dagtype' => 'doordeweeks', 'starttijd' => '17:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 28], 0 => ['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '17:00', 'prijs_per_uur' => 20], 1760000000001 => ['dagtype' => 'weekend', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 25]];
        $this->post('/beheer/velden', $this->veldformulier(['tarieven' => $tarieven]))->assertRedirect('/beheer/velden')->assertSessionHasNoErrors();
        $this->assertSame(3, Veld::where('naam', 'Padel 4')->first()->tarieven()->count());
        $this->get('/tarieven')->assertSeeInOrder(['Padel', '20,00', '24,00', '28,00', '30,00']);
    }

    public function test_fout_tarieven_met_een_gat_of_sluiting_voor_opening_worden_geweigerd(): void
    {
        $gat = [['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '16:00', 'prijs_per_uur' => 20], ['dagtype' => 'doordeweeks', 'starttijd' => '17:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 28], ['dagtype' => 'weekend', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 25]];
        $this->post('/beheer/velden', $this->veldformulier(['tarieven' => $gat]))->assertSessionHasErrors(['tarieven.1.starttijd' => 'Tarieven moeten zonder overlap of gaten de volledige openingstijd vullen.']);
        $this->post('/beheer/velden', $this->veldformulier(['openingstijd' => '23:00', 'sluitingstijd' => '09:00']))->assertSessionHasErrors(['sluitingstijd' => 'De eindtijd moet na de starttijd liggen.']);
        $this->post('/beheer/velden', $this->veldformulier(['tarieven' => [['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 20], ['dagtype' => 'doordeweeks', 'starttijd' => '09:00', 'eindtijd' => '23:00', 'prijs_per_uur' => 20]]]))->assertSessionHasErrors('tarieven');
        $this->assertDatabaseMissing('velden', ['naam' => 'Padel 4']);
    }

    public function test_fout_veld_met_reserveringen_verwijderen_wordt_geweigerd_en_deactiveren_kan_wel(): void
    {
        $this->from('/beheer/velden')->delete('/beheer/velden/'.$this->veld->id)->assertRedirect('/beheer/velden')->assertSessionHasErrors('veld');
        $this->assertDatabaseHas('velden', ['id' => $this->veld->id]);
        $this->put('/beheer/velden/'.$this->veld->id, $this->veldformulier(['naam' => 'Padel 1', 'actief' => 0]))->assertRedirect('/beheer/velden');
        $this->get('/velden?datum=2026-09-28')->assertDontSee('Padel 1');
    }

    public function test_fout_ongeldige_blokkade_en_materiaalinvoer_worden_gemeld(): void
    {
        $this->post('/beheer/blokkades', ['veld_id' => $this->veld->id, 'reden' => 'Onderhoud', 'start' => '2026-09-29T12:00', 'einde' => '2026-09-29T11:00'])->assertSessionHasErrors('einde');
        $this->post('/beheer/blokkades', ['veld_id' => 99999, 'reden' => '', 'start' => 'morgen', 'einde' => ''])->assertSessionHasErrors(['veld_id', 'reden', 'start', 'einde']);
        $this->post('/beheer/materiaal', ['naam' => 'Kapot', 'sporttype' => 'tennis', 'totaal_aantal' => -1, 'prijs_per_set' => 1.999])->assertSessionHasErrors(['sporttype', 'totaal_aantal', 'prijs_per_set']);
        $this->assertDatabaseCount('blokkades', 1);
        $this->assertDatabaseMissing('materiaaltypes', ['naam' => 'Kapot']);
    }

    public function test_materiaalpagina_toont_voorraad_per_gekozen_uur(): void
    {
        // Seeder: op 29-09 om 10:00 is 1 Racketset en 1 Ballenset padel gereserveerd.
        $this->get('/beheer/materiaal?datum=2026-09-29&starttijd=10:00')->assertOk()->assertSeeInOrder(['Racketset', '4 vrij', 'Ballenset padel', '3 vrij']);
        $this->get('/beheer/materiaal?datum=2026-09-29&starttijd=11:00')->assertOk()->assertSeeInOrder(['Racketset', '5 vrij', 'Ballenset padel', '4 vrij']);
    }
}
