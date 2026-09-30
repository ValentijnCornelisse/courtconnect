<?php

use App\Http\Controllers\AuthenticatieController;
use App\Http\Controllers\BeheerController;
use App\Http\Controllers\ReserveringController;
use App\Http\Controllers\VeldController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/over-ons', 'over-ons')->name('over-ons');
Route::view('/contact', 'contact')->name('contact');
Route::get('/tarieven', [VeldController::class, 'tarieven'])->name('tarieven');

Route::middleware('guest')->group(function () {
    Route::view('/inloggen', 'auth.inloggen')->name('login');
    Route::post('/inloggen', [AuthenticatieController::class, 'inloggen'])->middleware('throttle:10,1')->name('inloggen');
    Route::view('/registreren', 'auth.registreren')->name('registreren');
    Route::post('/registreren', [AuthenticatieController::class, 'registreren'])->middleware('throttle:10,1');
});
Route::post('/uitloggen', [AuthenticatieController::class, 'uitloggen'])->middleware('auth')->name('uitloggen');
Route::get('/velden', [VeldController::class, 'overzicht'])->name('velden');
Route::get('/reserveren', [VeldController::class, 'zoeken'])->name('reserveren');

Route::middleware(['auth', 'rol:klant'])->group(function () {
    Route::post('/reserveringen/controle', [ReserveringController::class, 'controle'])->name('reserveringen.voorbereiden');
    Route::get('/reserveringen/controle', [ReserveringController::class, 'overzicht'])->name('reserveringen.controle');
    Route::post('/reserveringen/bevestigen', [ReserveringController::class, 'bevestigen'])->name('reserveringen.bevestigen');
    Route::get('/mijn-reserveringen', [ReserveringController::class, 'mijn'])->name('reserveringen.mijn');
    Route::post('/reserveringen/{reservering}/annuleren', [ReserveringController::class, 'annuleren'])->name('reserveringen.annuleren');
});
Route::prefix('beheer')->name('beheer.')->middleware(['auth', 'rol:beheerder'])->group(function () {
    Route::get('/weekrooster', [BeheerController::class, 'weekrooster'])->name('weekrooster');
    Route::get('/velden', [BeheerController::class, 'velden'])->name('velden');
    Route::get('/velden/{veld}/bewerken', [BeheerController::class, 'velden'])->name('velden.bewerken');
    Route::post('/velden', [BeheerController::class, 'veldOpslaan'])->name('velden.opslaan');
    Route::put('/velden/{veld}', [BeheerController::class, 'veldOpslaan'])->name('velden.wijzigen');
    Route::delete('/velden/{veld}', [BeheerController::class, 'veldVerwijderen'])->name('velden.verwijderen');
    Route::get('/blokkades', [BeheerController::class, 'blokkades'])->name('blokkades');
    Route::post('/blokkades', [BeheerController::class, 'blokkadeOpslaan'])->name('blokkades.opslaan');
    Route::get('/materiaal', [BeheerController::class, 'materiaal'])->name('materiaal');
    Route::get('/materiaal/{materiaal}/bewerken', [BeheerController::class, 'materiaal'])->name('materiaal.bewerken');
    Route::post('/materiaal', [BeheerController::class, 'materiaalOpslaan'])->name('materiaal.opslaan');
    Route::put('/materiaal/{materiaal}', [BeheerController::class, 'materiaalOpslaan'])->name('materiaal.wijzigen');
});
