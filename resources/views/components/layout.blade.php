@props(['titel' => 'CourtConnect'])
<!doctype html>
<html lang="nl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $titel }} · CourtConnect</title><link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}"><link rel="stylesheet" href="{{ asset('css/courtconnect.css') }}"><script src="{{ asset('js/courtconnect.js') }}" defer></script></head>
<body>
<a class="skip" href="#inhoud">Direct naar inhoud</a>
<header><div class="container bovenbalk"><a class="logo" href="{{ route('home') }}"><x-logo /></a><span class="locatie">URBAN COURTS · SPORTCENTRUM</span></div>
<nav class="container navigatie" aria-label="Hoofdnavigatie">
<a href="{{ route('home') }}">Home</a><a href="{{ route('velden') }}">Velden</a>
@unless(auth()->user()?->rol === 'beheerder')<a href="{{ route('reserveren') }}">Reserveren</a>@endunless
<a href="{{ route('tarieven') }}">Tarieven</a><a href="{{ route('over-ons') }}">Over ons</a><a href="{{ route('contact') }}">Contact</a>
<div class="account">@auth
@if(auth()->user()->rol === 'beheerder')<a href="{{ route('beheer.weekrooster') }}">Beheer</a>@else<a href="{{ route('reserveringen.mijn') }}">Mijn reserveringen</a>@endif
<form method="post" action="{{ route('uitloggen') }}">@csrf<button class="knop secundair">Uitloggen</button></form>
@else<a href="{{ route('login') }}">Inloggen</a><a class="knop" href="{{ route('registreren') }}">Account aanmaken</a>@endauth</div>
</nav></header>
@auth @if(auth()->user()->rol === 'beheerder')<nav class="beheer-nav container" aria-label="Beheer"><a href="{{ route('beheer.weekrooster') }}">Weekrooster</a><a href="{{ route('beheer.velden') }}">Velden & tarieven</a><a href="{{ route('beheer.blokkades') }}">Blokkades</a><a href="{{ route('beheer.materiaal') }}">Materiaal</a></nav>@endif @endauth
<main id="inhoud" class="container">
@if(session('succes'))<x-melding type="succes">{{ session('succes') }}</x-melding>@endif
@if(session('melding'))<x-melding>{{ session('melding') }}</x-melding>@endif
@if($errors->any())<x-melding type="fout"><strong>Controleer je gegevens.</strong><ul>@foreach($errors->all() as $fout)<li>{{ $fout }}</li>@endforeach</ul></x-melding>@endif
{{ $slot }}
</main>
<footer><div class="container footer-inhoud"><a class="logo" href="{{ route('home') }}"><x-logo /></a><p>Jouw sport. Jouw moment.<br>Betalen gebeurt op locatie.</p><a href="{{ route('contact') }}">Contact met Urban Courts →</a><small>Schoolproject · fictief sportcentrum</small></div></footer>
</body></html>
