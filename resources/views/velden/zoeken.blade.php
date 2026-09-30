<x-layout titel="Reserveren">
<section class="pagina-kop"><span class="wenkbrauw">VIND JOUW SPEELMOMENT</span><h1>Reserveer jouw speeluur</h1><p>Kies een sport en dag, klik op een vrij tijdsblok en rond je reservering direct hiernaast af. Reserveer 1, 2 of 3 uur achter elkaar.</p></section>
<form class="kaart filters" method="get" action="{{ route('reserveren') }}">
<div class="form-veld"><label for="sporttype">Sport</label><select name="sporttype" id="sporttype"><option value="">Alle sporten</option>@foreach(['padel','basketbal','zaalvoetbal'] as $sport)<option value="{{ $sport }}" @selected(($filters['sporttype'] ?? '') === $sport)>{{ ucfirst($sport) }}</option>@endforeach</select></div>
<div class="form-veld"><label for="veld_id">Veld</label><select name="veld_id" id="veld_id"><option value="">Alle velden</option>@foreach($veldkeuzes as $keuze)<option value="{{ $keuze->id }}" @selected(($filters['veld_id'] ?? '') == $keuze->id)>{{ $keuze->naam }}</option>@endforeach</select></div>
<x-veld naam="datum" label="Datum" type="date" :waarde="$datum"/><x-veld naam="van" label="Van" type="time" :waarde="$filters['van'] ?? ''"/><x-veld naam="tot" label="Tot" type="time" :waarde="$filters['tot'] ?? ''"/><button class="knop">Zoek velden</button></form>
<x-dagkiezer :dagen="$dagen" :datum="$datum" route="reserveren" :parameters="\Illuminate\Support\Arr::except($zoekParameters, 'datum')"/>
<div class="twee-kolommen">
<section>
<div class="sectiekop"><h2>Kies je tijdsblok</h2><span>{{ $resultaten->where('vrij', true)->count() }} van {{ $resultaten->count() }} velden met vrije uren</span></div>
@if($resultaten->isEmpty())<x-melding>Er zijn geen velden die bij je zoekopdracht passen.</x-melding>
@elseif(!$resultaten->contains('vrij', true))<x-melding>Er zijn geen vrije tijdsblokken op deze datum en tijd. Probeer een andere dag.</x-melding>@endif
@foreach($resultaten as $resultaat)@php($veld = $resultaat['veld'])
<article class="kaart reserveerkaart {{ $gekozenVeld?->is($veld) ? 'actief' : '' }}">
<div class="reserveerkop"><div><span class="wenkbrauw">{{ strtoupper($veld->veldtype()) }}</span><h3>{{ $veld->naam }}</h3></div><span class="status {{ $resultaat['vrij'] ? 'vrij' : 'bezet' }}">{{ $resultaat['vrij'] ? 'Beschikbaar' : 'Volgeboekt' }}</span></div>
<p class="gedempt">Vanaf € {{ number_format($veld->tarieven->min('prijs_per_uur'),2,',','.') }} per uur</p>
<div class="blokken compact">@forelse($resultaat['blokken'] as $blok)
@php($gekozen = $prijs && $gekozenVeld?->is($veld) && $blok['start'] >= $starttijd && $blok['start'] < $eindtijd)
@if($blok['status'] === 'vrij')<a class="tijdblok {{ $gekozen ? 'gekozen' : 'vrij' }}" href="{{ route('reserveren', $zoekParameters + ['gekozen_veld' => $veld->id, 'starttijd' => $blok['start'], 'duur' => $gekozenVeld?->is($veld) && $duur ? $duur : 1]) }}#boekpaneel" aria-label="{{ $veld->naam }} {{ $blok['start'] }} tot {{ $blok['einde'] }}, {{ $gekozen ? 'gekozen' : 'vrij' }}">{{ $blok['start'] }}<strong>{{ $gekozen ? 'Gekozen' : 'Vrij' }}</strong></a>
@else<div class="tijdblok {{ $blok['status'] === 'onderhoud' ? 'onderhoud' : 'bezet' }}">{{ $blok['start'] }}<strong>{{ ucfirst($blok['status']) }}</strong></div>@endif
@empty<x-melding>Geen tijdsblokken binnen je gekozen tijd.</x-melding>@endforelse</div>
</article>@endforeach
</section>
<aside class="kaart boekpaneel" id="boekpaneel"><h2>Jouw reservering</h2>@if($prijs)
<p><strong>{{ $gekozenVeld->naam }}</strong><br>{{ \Carbon\CarbonImmutable::parse($datum)->format('d-m-Y') }} · {{ $starttijd }} – {{ $eindtijd }}</p>
@unless(auth()->user()?->rol === 'beheerder')
<form method="post" action="{{ route('reserveringen.voorbereiden') }}">@csrf<input type="hidden" name="veld_id" value="{{ $gekozenVeld->id }}"><input type="hidden" name="datum" value="{{ $datum }}"><input type="hidden" name="starttijd" value="{{ $starttijd }}">@foreach(\Illuminate\Support\Arr::except($zoekParameters, 'datum') as $sleutel => $waarde)<input type="hidden" name="zoek[{{ $sleutel }}]" value="{{ $waarde }}">@endforeach
<div class="form-veld"><label for="duur">Duur</label><select id="duur" name="duur" @error('duur') aria-invalid="true" aria-describedby="duur-fout" @enderror>@for($uren = 1; $uren <= $maximaleDuur; $uren++)<option value="{{ $uren }}" @selected($uren === $duur)>{{ $uren }} uur</option>@endfor</select>
@if($maximaleDuur < \App\Models\Reservering::MAXIMALE_DUUR)<small class="gedempt">Langer kan niet: het volgende uur is bezet, geblokkeerd of na sluitingstijd.</small>@endif
@error('duur')<x-melding type="fout" id="duur-fout">{{ $message }}</x-melding>@enderror</div>
<x-veld naam="aantal_spelers" label="Aantal spelers" type="number" min="1" max="12" :waarde="$spelers" required/>
<fieldset><legend>Materiaal huren <small>(optioneel)</small></legend>@forelse($materiaal as $regel)<x-veld :naam="'materiaal['.$regel['type']->id.']'" :foutsleutel="'materiaal.'.$regel['type']->id" :label="$regel['type']->naam.' · '.$regel['vrij'].' sets vrij · € '.number_format($regel['type']->prijs_per_set,2,',','.').' per set'" type="number" min="0" :max="$regel['vrij']" :waarde="$gekozenMateriaal[$regel['type']->id] ?? 0"/>@empty<p>Voor deze sport is geen materiaal beschikbaar.</p>@endforelse</fieldset>
<p class="gedempt">Wijzig je duur of materiaalkeuze? Klik op Prijs bijwerken voor de nieuwe totaalprijs.</p>@auth<button class="knop secundair breed" name="actie" value="prijs">Prijs bijwerken</button>@endauth<x-prijs :prijs="$prijs"/>
@auth<button class="knop breed">Bekijk reservering →</button>@else<a class="knop breed" href="{{ route('login') }}">Log in om te reserveren</a><p class="gedempt">Na het inloggen kom je terug bij dit tijdsblok.</p>@endauth
</form>@else<x-melding>Reserveren is beschikbaar voor klanten.</x-melding>@endunless
@else<x-melding>Klik op een vrij tijdsblok om je reservering samen te stellen.</x-melding>@endif</aside>
</div>
</x-layout>
