<x-layout titel="Onze velden">
    <section class="pagina-kop">
        <span class="wenkbrauw">ONTDEK URBAN COURTS</span>
        <h1>Onze velden</h1>
        <p>Ontdek onze padelbanen, basketbalvelden, zaalvoetbalvelden en het Multicourt. Bekijk de openingstijden en kies waar je wilt spelen.</p>
        <a class="knop" href="{{ route('reserveren') }}">Zoek een beschikbaar tijdsblok →</a>
    </section>
    <div class="sectiekop">
        <h2>Een plek voor jouw sport</h2>
        <span>{{ $velden->count() }} velden</span>
    </div>
    <div class="kaarten">
        @forelse($velden as $veld)
            <article class="kaart veldkaart">
                <div class="veld-illustratie {{ $veld->sporttype }}" aria-hidden="true">
                    <div class="kleine-baan"></div>
                    <span>{{ strtoupper($veld->veldtype()) }}</span>
                </div>
                <div class="veldtekst">
                    <h2>{{ $veld->naam }}</h2>
                    <p>{{ $veld->omschrijving }}</p>
                    <p><strong>Openingstijden</strong><br>{{ substr($veld->openingstijd, 0, 5) }} – {{ substr($veld->sluitingstijd, 0, 5) }}</p>
                    <div class="veldvoet">
                        @if($veld->tarieven->isNotEmpty())
                            <span>Vanaf <strong>€ {{ number_format($veld->tarieven->min('prijs_per_uur'), 2, ',', '.') }}</strong> / uur</span>
                        @endif
                        <a class="knop secundair" href="{{ route('reserveren', ['veld_id' => $veld->id]) }}">Reserveer dit veld</a>
                    </div>
                </div>
            </article>
        @empty
            <x-melding>Er zijn momenteel geen velden beschikbaar.</x-melding>
        @endforelse
    </div>
</x-layout>
