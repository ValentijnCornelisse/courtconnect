<x-layout titel="Onze velden">
    <section class="pagina-kop">
        <span class="wenkbrauw">ONTDEK URBAN COURTS</span>
        <h1>Onze velden</h1>
        <p>Kies een dag en zie meteen welke velden die dag nog vrije uren hebben. Reserveren doe je via <a href="{{ route('reserveren', ['datum' => $datum]) }}">Reserveren</a>.</p>
    </section>
    <form method="get" action="{{ route('velden') }}" class="filters">
        <x-veld naam="datum" label="Kies een dag" type="date" :waarde="$datum"/>
        <button class="knop secundair">Toon beschikbaarheid</button>
    </form>
    <x-dagkiezer :dagen="$dagen" :datum="$datum" route="velden"/>
    <div class="sectiekop">
        <h2>Beschikbaarheid op {{ \Carbon\CarbonImmutable::parse($datum)->locale('nl')->isoFormat('dddd D MMMM') }}</h2>
        <span>{{ $velden->where('vrij', '>', 0)->count() }} van {{ $velden->count() }} velden beschikbaar</span>
    </div>
    <div class="kaarten">
        @forelse($velden as $regel)
            @php($veld = $regel['veld'])
            <article class="kaart veldkaart">
                <div class="veld-illustratie {{ $veld->sporttype }}" aria-hidden="true">
                    <div class="kleine-baan"></div>
                    <span>{{ strtoupper($veld->veldtype()) }}</span>
                </div>
                <div class="veldtekst">
                    <span class="status {{ $regel['status']['klasse'] }}">{{ $regel['status']['tekst'] }}</span>
                    <h2>{{ $veld->naam }}</h2>
                    <p>{{ $veld->omschrijving }}</p>
                    <p class="gedempt">
                        @if($regel['vrij'] > 0)
                            {{ $regel['vrij'] }} van {{ $regel['totaal'] }} uur vrij
                        @elseif($regel['status']['tekst'] === 'Volgeboekt')
                            Alle uren zijn geboekt of geblokkeerd
                        @else
                            Deze dag kan niet meer worden geboekt
                        @endif
                        · open {{ substr($veld->openingstijd, 0, 5) }} – {{ substr($veld->sluitingstijd, 0, 5) }}
                    </p>
                </div>
            </article>
        @empty
            <x-melding>Er zijn momenteel geen velden beschikbaar.</x-melding>
        @endforelse
    </div>
</x-layout>
