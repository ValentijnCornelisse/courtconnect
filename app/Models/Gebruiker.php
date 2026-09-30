<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\GebruikerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Gebruiker extends Authenticatable
{
    protected $fillable = ['naam', 'email', 'wachtwoord', 'rol'];

    /** @use HasFactory<GebruikerFactory> */
    use HasFactory, Notifiable;

    protected $table = 'gebruikers';

    protected $hidden = ['wachtwoord'];

    public function getAuthPasswordName(): string
    {
        return 'wachtwoord';
    }

    public function reserveringen(): HasMany
    {
        return $this->hasMany(Reservering::class, 'klant_id');
    }

    public function blokkades(): HasMany
    {
        return $this->hasMany(Blokkade::class, 'aangemaakt_door');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wachtwoord' => 'hashed',
        ];
    }
}
