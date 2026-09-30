<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServerfoutTest extends TestCase
{
    public function test_sessiefout_toont_een_neutrale_foutpagina_zonder_sessie_of_errors(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'ontbrekende_sessietabel']);
        $this->get('/')
            ->assertStatus(500)
            ->assertSee('Er is een onverwachte fout opgetreden.')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('Undefined variable');
    }
}
