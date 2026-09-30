<?php

namespace Database\Factories;

use App\Models\Gebruiker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Gebruiker> */
class GebruikerFactory extends Factory
{
    protected static ?string $wachtwoord;

    public function definition(): array
    {
        return ['naam' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'wachtwoord' => static::$wachtwoord ??= Hash::make('Welkom123!'), 'rol' => 'klant'];
    }

    public function beheerder(): static
    {
        return $this->state(fn () => ['rol' => 'beheerder']);
    }
}
