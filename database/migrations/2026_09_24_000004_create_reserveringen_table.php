<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserveringen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klant_id')->constrained('gebruikers')->restrictOnDelete();
            $table->foreignId('veld_id')->constrained('velden')->cascadeOnDelete();
            $table->date('datum');
            $table->time('starttijd');
            $table->time('eindtijd');
            $table->integer('aantal_spelers');
            $table->enum('status', ['bevestigd', 'geannuleerd'])->default('bevestigd');
            $table->decimal('totaalprijs', 6, 2);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX reserveringen_bevestigd_uniek
                 ON reserveringen (veld_id, datum, starttijd)
                 WHERE status = 'bevestigd'"
            );

            return;
        }

        DB::statement(
            "ALTER TABLE reserveringen
             ADD bevestigde_starttijd TIME
             GENERATED ALWAYS AS (CASE WHEN status = 'bevestigd' THEN starttijd ELSE NULL END) STORED"
        );

        DB::statement(
            'CREATE UNIQUE INDEX reserveringen_bevestigd_uniek
             ON reserveringen (veld_id, datum, bevestigde_starttijd)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reserveringen');
    }
};
