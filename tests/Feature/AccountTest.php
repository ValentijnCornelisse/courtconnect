<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Registreren, inloggen en uitloggen via de echte formulieren, routes en sessie.
 */
class AccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function registratie(array $extra = []): array
    {
        return array_replace(['naam' => 'Sam', 'email' => 'sam@example.test', 'wachtwoord' => 'Welkom123!', 'wachtwoord_confirmation' => 'Welkom123!'], $extra);
    }

    public function test_beheerder_komt_na_inloggen_op_het_weekrooster(): void
    {
        Gebruiker::factory()->beheerder()->create(['email' => 'baas@example.test']);
        $this->post('/inloggen', ['email' => 'baas@example.test', 'wachtwoord' => 'Welkom123!'])->assertRedirect('/beheer/weekrooster');
    }

    public function test_uitloggen_beeindigt_de_sessie(): void
    {
        $this->actingAs(Gebruiker::factory()->create())->post('/uitloggen')->assertRedirect('/')->assertSessionHas('succes', 'Je bent uitgelogd.');
        $this->assertGuest();
        $this->get('/mijn-reserveringen')->assertRedirect('/inloggen');
    }

    public function test_fout_registreren_met_bestaand_e_mailadres_of_zwak_wachtwoord(): void
    {
        Gebruiker::factory()->create(['email' => 'sam@example.test']);
        $this->post('/registreren', $this->registratie())->assertSessionHasErrors(['email' => 'Dit e-mailadres is al in gebruik.']);
        $this->post('/registreren', $this->registratie(['email' => 'nieuw@example.test', 'wachtwoord' => 'kort', 'wachtwoord_confirmation' => 'kort']))->assertSessionHasErrors(['wachtwoord' => 'Gebruik een wachtwoord van minimaal 8 tekens.']);
        $this->post('/registreren', $this->registratie(['email' => 'nieuw@example.test', 'wachtwoord_confirmation' => 'Anders123!']))->assertSessionHasErrors(['wachtwoord' => 'De wachtwoorden komen niet overeen.']);
        $this->post('/registreren', $this->registratie(['email' => 'geen-adres']))->assertSessionHasErrors(['email' => 'Vul een geldig e-mailadres in.']);
        $this->assertDatabaseCount('gebruikers', 1);
        $this->assertGuest();
    }

    public function test_randgeval_wachtwoord_van_precies_acht_tekens_is_genoeg(): void
    {
        $this->post('/registreren', $this->registratie(['wachtwoord' => 'Abcdef1!', 'wachtwoord_confirmation' => 'Abcdef1!']))->assertRedirect('/reserveren');
        $this->assertAuthenticated();
    }

    public function test_fout_na_tien_mislukte_inlogpogingen_volgt_een_pauze(): void
    {
        Gebruiker::factory()->create(['email' => 'sam@example.test']);
        for ($poging = 1; $poging <= 10; $poging++) {
            $this->post('/inloggen', ['email' => 'sam@example.test', 'wachtwoord' => 'fout'])->assertSessionHasErrors('email');
        }
        $this->post('/inloggen', ['email' => 'sam@example.test', 'wachtwoord' => 'Welkom123!'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_fout_ingelogde_gebruiker_kan_niet_opnieuw_registreren_en_gast_niet_uitloggen(): void
    {
        $this->post('/uitloggen')->assertRedirect('/inloggen');
        $this->actingAs(Gebruiker::factory()->create())->get('/registreren')->assertRedirect();
    }
}
