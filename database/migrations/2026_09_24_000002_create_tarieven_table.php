<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarieven', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veld_id')->constrained('velden')->cascadeOnDelete();
            $table->enum('dagtype', ['doordeweeks', 'weekend']);
            $table->time('starttijd');
            $table->time('eindtijd');
            $table->decimal('prijs_per_uur', 6, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarieven');
    }
};
