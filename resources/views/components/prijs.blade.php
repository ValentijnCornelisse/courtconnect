@props(['prijs'])
<div class="prijs"><div><span>Basistarief (1 uur)</span><strong>€ {{ number_format($prijs['basis']/100, 2, ',', '.') }}</strong></div><div><span>Piektoeslag</span><strong>€ {{ number_format($prijs['piek']/100, 2, ',', '.') }}</strong></div>
@forelse($prijs['materiaal'] as $regel)<div><span>{{ $regel['aantal'] }} × {{ $regel['naam'] }}</span><strong>€ {{ number_format($regel['bedrag']/100, 2, ',', '.') }}</strong></div>@empty<div><span>Materiaal</span><strong>€ 0,00</strong></div>@endforelse
<div class="prijs-totaal"><span>Totaal</span><strong>€ {{ number_format($prijs['totaal']/100, 2, ',', '.') }}</strong></div></div><p class="gedempt">Betalen gebeurt op locatie.</p>
