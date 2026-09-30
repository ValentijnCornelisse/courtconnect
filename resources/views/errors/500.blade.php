<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Er ging iets mis · CourtConnect</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/courtconnect.css') }}">
</head>
<body>
    <main class="container">
        <section class="smal">
            <a class="logo" href="/"><x-logo /></a>
            <h1>Er ging iets mis</h1>
            <x-melding type="fout">Er is een onverwachte fout opgetreden. Probeer het later opnieuw.</x-melding>
            <a class="knop" href="/">Terug naar Home</a>
        </section>
    </main>
</body>
</html>
