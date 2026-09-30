<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('velden', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('sporttype', ['padel', 'basketbal', 'zaalvoetbal']);
            $table->text('omschrijving')->nullable();
            $table->time('openingstijd');
            $table->time('sluitingstijd');
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('velden');
    }
};
