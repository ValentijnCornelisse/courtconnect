# CourtConnect

Nederlandstalig schoolproject voor Urban Courts: velden reserveren voor 1 tot 3 uur, materiaal huren en beheer. Laravel 12, Blade, eigen responsive CSS en MySQL/MariaDB. De huidige Composer-lock vereist PHP 8.4.1 of hoger.

## Installatie

Met Docker Desktop:
```sh
docker compose up --build
```
Open daarna http://localhost:8000. De bestaande Docker-entrypoint installeert dependencies en voert migraties en de demoseeder uit.

Of lokaal met PHP, Composer en een lege MySQL/MariaDB-database:
```sh
composer install
cp .env.example .env
php artisan key:generate
```
Gebruik op Windows zo nodig `Copy-Item .env.example .env`. Stel vervolgens `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` en `DB_PASSWORD` in voor je eigen database.
```sh
php artisan migrate --seed
php artisan serve
```
Open de URL die Artisan toont. CSS en JavaScript staan in `public`; een Node-build is niet nodig. DDEV is ingesteld op PHP 8.4.

## Demoaccounts

| Rol | E-mail | Wachtwoord |
| --- | --- | --- |
| Beheerder | beheerder@courtconnect.test | Welkom123! |
| Klant | klant@courtconnect.test | Welkom123! |

De seeder maakt acht velden, tarieven, zes materiaaltypes, vier reserveringen en één onderhoudsblokkade in de komende week. Multicourt gebruikt sporttype basketbal. Voorbeelden openen van 09:00 tot 23:00. Piekprijzen gelden doordeweeks vanaf 17:00 en het hele weekend. De seeder is voor demodata en overschrijft bij opnieuw uitvoeren de voorbeeldtarieven en accounts.

## Demonstreren

1. Log in als beheerder en open `/beheer/weekrooster`.
2. Beheer velden en aaneensluitende tariefregels onder `/beheer/velden`.
3. Probeer een blokkade over een bestaande boeking: de melding noemt het aantal conflicten.
4. Bekijk materiaalvoorraad voor een datum en uur onder `/beheer/materiaal`.
5. Bekijk onder `/velden` per dag welke velden nog vrij zijn. Log daarna in als klant en open `/reserveren`: klik op een vrij tijdsblok en kies in het boekpaneel ernaast een duur van 1 tot 3 uur en materiaal. Kies bijvoorbeeld 16:00 met 2 uur om dal- en piektarief in één boeking te zien.
6. Gebruik **Prijs bijwerken** voor de prijsopbouw in het zijpaneel. Controleer daarna het overzicht en bevestig.
7. Annuleer onder `/mijn-reserveringen`: dit kan alleen vóór de starttijd.
8. Open als klant een beheer-URL voor de nette melding bij geweigerde toegang.

Betalen gebeurt op locatie. Contactgegevens en het verhaal over Urban Courts zijn fictief. Er zijn geen betalingen, externe API-koppelingen of notificaties.

## Opbouw en regels

- Nederlandse domeinmodellen, controllers, Form Requests en services. Alle Form Requests erven van `CourtConnectRequest`, waar de Nederlandse foutmeldingen één keer staan.
- `BeschikbaarheidChecker` berekent uurblokken vanaf de openingstijd; er is geen tijdslotentabel. Overlap gebruikt exclusieve eindtijden. Een reservering van 2 of 3 uur mag alleen als elk uur vrij is; `maximaleDuur()` bepaalt welke duurkeuzes het boekpaneel toont. Materiaal telt als vrij wanneer het in elk gekozen uur vrij is.
- `PrijsCalculator` berekent bedragen in centen en verdeelt tariefwisselingen naar minuten. Het laagste veldtarief is de basis; het verschil vormt de piektoeslag.
- `ReserveringService` vergrendelt eerst het veld en daarna gekozen materiaaltypes in ID-volgorde binnen één transactie. De controles worden bij bevestiging opnieuw uitgevoerd. Een gewijzigde prijs moet opnieuw worden gecontroleerd.
- De unieke index gebruikt in MySQL/MariaDB een gegenereerde `bevestigde_starttijd`: geannuleerde boekingen krijgen NULL, zodat opnieuw boeken mogelijk is. SQLite gebruikt een gedeeltelijke index.
- Beheer gebruikt dezelfde veld- en materiaalvergrendelingen. Verlagen onder toegezegde voorraad en blokkeren over bevestigde boekingen worden geweigerd.
- Velden met reserveringshistorie kunnen worden gedeactiveerd; verwijderen zou die historie verliezen en wordt geweigerd.
- Wachtwoorden zijn bcrypt-hashes. Autorisatie zit in middleware, wijzigingen gebruiken CSRF, foutdetails gaan naar het Laravel-log. De interface toont neutrale serverfouten.
- Tijdzone: Europe/Amsterdam. Tijdvensters blijven binnen één kalenderdag.

## Tests

```sh
php artisan test --compact
```

De normale suite gebruikt een tijdelijke SQLite-database. De twee echte procestests worden daarbij bewust overgeslagen.

Voor de gelijktijdigheidstests: maak een **aparte lege** MySQL/MariaDB-database waarvan de naam eindigt op `_test`. De procestests wissen uitsluitend die opgegeven testdatabase met `migrate:fresh`. Bijvoorbeeld in PowerShell:
```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'courtconnect_test'
$env:DB_USERNAME = 'jouw_testgebruiker'
$env:DB_PASSWORD = 'jouw_testwachtwoord'
php artisan test --compact tests/Feature/GelijktijdigeReserveringTest.php
```

Twee PHP-processen starten tegen dezelfde database terwijl de ouder het veld vergrendelt. Na vrijgave moet precies één boeking slagen. Een tweede test doet hetzelfde voor de laatste materiaalsets op verschillende velden.

Na het toevoegen van de reserveringsduur slagen met SQLite 24 tests met 127 controles (de 2 procestests worden daar overgeslagen). Eerder gecontroleerd op MariaDB 10.4, vóór de duurkeuze: 20 tests met 115 controles, inclusief beide procestests. Functionele tests dekken onder andere registratie, rollen, prijzen, voorraad, blokkades, annuleren, invoer, escaping, beheeracties en de unieke index. Chrome-weergave gecontroleerd op 390, 768 en 1440 pixels. In de browser zijn ook prijs bijwerken, controleren, bevestigen, annuleren, tariefregels toevoegen, CSRF en rolweigering gecontroleerd. Brede beheertabellen zijn horizontaal scrollbaar binnen hun kader.
