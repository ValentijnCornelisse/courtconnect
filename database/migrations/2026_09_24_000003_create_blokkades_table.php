<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blokkades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veld_id')->constrained('velden')->cascadeOnDelete();
            $table->dateTime('start');
            $table->dateTime('einde');
            $table->string('reden');
            $table->foreignId('aangemaakt_door')->constrained('gebruikers')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blokkades');
    }
};
