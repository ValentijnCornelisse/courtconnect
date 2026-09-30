<x-layout titel="Tarieven">
    <section class="pagina-kop">
        <span class="wenkbrauw">WEET WAAR JE AAN TOE BENT</span>
        <h1>Tarieven</h1>
        <p>Prijs per uur per sporttype. Betalen gebeurt op locatie. Materiaal kies je bij het reserveren.</p>
    </section>
    <div class="kaarten">
        @forelse($sporttarieven as $sporttype => $tarieven)
            <article class="kaart">
                <h2>{{ ucfirst($sporttype) }}</h2>
                @foreach($tarieven as $tarief)
                    <p>
                        {{ ucfirst($tarief['dagtype']) }}<br>
                        {{ substr($tarief['starttijd'], 0, 5) }} – {{ substr($tarief['eindtijd'], 0, 5) }}
                        <strong>
                            € {{ number_format($tarief['minimum'], 2, ',', '.') }}
                            @if($tarief['minimum'] != $tarief['maximum'])
                                – € {{ number_format($tarief['maximum'], 2, ',', '.') }}
                            @endif
                        </strong>
                    </p>
                @endforeach
                @if($tarieven->contains(fn ($tarief) => $tarief['minimum'] != $tarief['maximum']))
                    <p class="gedempt">De prijs verschilt per veld. De exacte prijs zie je bij het reserveren.</p>
                @endif
            </article>
        @empty
            <x-melding>Er zijn nog geen tarieven beschikbaar.</x-melding>
        @endforelse
    </div>
</x-layout>
