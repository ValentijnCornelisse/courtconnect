<x-layout :titel="$veld->naam"><a class="terug" href="{{ route('reserveren') }}">← Terug naar reserveren</a><section class="pagina-kop"><span class="wenkbrauw">{{ strtoupper($veld->veldtype()) }}</span><h1>{{ $veld->naam }}</h1><p>{{ $veld->omschrijving }}</p></section>
<form method="get" class="filters"><x-veld naam="datum" label="Kies een datum" type="date" :waarde="$datum"/><button class="knop secundair">Toon dag</button></form>
<nav class="dagen" aria-label="Kies een dag">@foreach($dagen as $dag)<a class="{{ $dag->format('Y-m-d') === $datum ? 'gekozen' : '' }}" href="{{ route('velden.tijden',['veld'=>$veld,'datum'=>$dag->format('Y-m-d')]) }}">{{ $dag->locale('nl')->isoFormat('ddd') }}<strong>{{ $dag->format('d/m') }}</strong></a>@endforeach</nav>
<div class="twee-kolommen"><section><h2>Kies je tijdsblok</h2><p class="gedempt">Alle tijden zijn lokale tijden · kies je starttijd, daarna de duur (1 tot 3 uur)</p><div class="blokken">@foreach($blokken as $blok)
@php($gekozen = $prijs && $blok['start'] >= $starttijd && $blok['start'] < $eindtijd)
@if($blok['status'] === 'vrij')<a class="tijdblok {{ $gekozen ? 'gekozen' : 'vrij' }}" href="{{ route('velden.tijden',['veld'=>$veld,'datum'=>$datum,'starttijd'=>$blok['start'],'duur'=>$duur ?: 1]) }}">{{ $blok['start'] }} – {{ $blok['einde'] }}<strong>{{ $gekozen ? 'Gekozen' : 'Vrij' }}</strong></a>
@else<div class="tijdblok {{ $blok['status'] === 'onderhoud' ? 'onderhoud' : 'bezet' }}">{{ $blok['start'] }} – {{ $blok['einde'] }}<strong>{{ ucfirst($blok['status']) }}</strong></div>@endif
@endforeach</div>@if(empty($blokken))<x-melding>Er zijn geen tijdsblokken voor deze dag.</x-melding>@endif</section>
<aside class="kaart boekpaneel"><h2>Jouw reservering</h2>@if($prijs)
<p>{{ \Carbon\CarbonImmutable::parse($datum)->format('d-m-Y') }} · {{ $starttijd }} – {{ $eindtijd }}</p>
@unless(auth()->user()?->rol === 'beheerder')
<form method="post" action="{{ route('reserveringen.voorbereiden') }}">@csrf<input type="hidden" name="veld_id" value="{{ $veld->id }}"><input type="hidden" name="datum" value="{{ $datum }}"><input type="hidden" name="starttijd" value="{{ $starttijd }}">
<div class="form-veld"><label for="duur">Duur</label><select id="duur" name="duur" @error('duur') aria-invalid="true" aria-describedby="duur-fout" @enderror>@for($uren = 1; $uren <= $maximaleDuur; $uren++)<option value="{{ $uren }}" @selected($uren === $duur)>{{ $uren }} uur</option>@endfor</select>
@if($maximaleDuur < \App\Models\Reservering::MAXIMALE_DUUR)<small class="gedempt">Langer kan niet: het volgende uur is bezet, geblokkeerd of na sluitingstijd.</small>@endif
@error('duur')<x-melding type="fout" id="duur-fout">{{ $message }}</x-melding>@enderror</div>
<x-veld naam="aantal_spelers" label="Aantal spelers" type="number" min="1" max="12" :waarde="$spelers" required/>
<fieldset><legend>Materiaal huren <small>(optioneel)</small></legend>@forelse($materiaal as $regel)<x-veld :naam="'materiaal['.$regel['type']->id.']'" :foutsleutel="'materiaal.'.$regel['type']->id" :label="$regel['type']->naam.' · '.$regel['vrij'].' sets vrij · € '.number_format($regel['type']->prijs_per_set,2,',','.').' per set'" type="number" min="0" :max="$regel['vrij']" :waarde="$gekozenMateriaal[$regel['type']->id] ?? 0"/>@empty<p>Voor deze sport is geen materiaal beschikbaar.</p>@endforelse</fieldset>
<p class="gedempt">Wijzig je duur of materiaalkeuze? Klik op Prijs bijwerken voor de nieuwe totaalprijs.</p>@auth<button class="knop secundair breed" name="actie" value="prijs">Prijs bijwerken</button>@endauth<x-prijs :prijs="$prijs"/>
@auth<button class="knop breed">Bekijk reservering →</button>@else<a class="knop breed" href="{{ route('login') }}">Log in om te reserveren</a>@endauth
</form>@else<x-melding>Reserveren is beschikbaar voor klanten.</x-melding>@endunless
@else<x-melding>Kies een vrij tijdsblok om je reservering samen te stellen.</x-melding>@endif</aside></div></x-layout>
