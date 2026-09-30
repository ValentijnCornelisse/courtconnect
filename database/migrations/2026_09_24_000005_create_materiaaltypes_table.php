<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materiaaltypes', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('sporttype', ['padel', 'basketbal', 'zaalvoetbal']);
            $table->integer('totaal_aantal');
            $table->decimal('prijs_per_set', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiaaltypes');
    }
};
