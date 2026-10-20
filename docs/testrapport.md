# Testrapport CourtConnect (K1-W4)

Dit rapport beschrijft hoe de software uit K1-W3 is getest, wat de tests laten zien en wat er nog beter kan. Alle tests staan in `tests/`, de automatische uitvoer staat in `docs/testrapport/`.

Getest op 19 oktober 2026 met PHP 8.4, PHPUnit 12.5 en Laravel 12, op SQLite (snelle standaardrun) en op MariaDB 10.11 (zoals in productie) in DDEV.

## 1. Gekozen manier van testen

| Laag | Wat wordt getest | Waar | Aantal |
| --- | --- | --- | --- |
| **Unittests** | Eén service-methode los van formulieren en pagina's: prijsberekening, beschikbaarheid, reserveren, beheerregels | `tests/Unit` | 36 |
| **Integratietests** | De hele keten: formulier → route → middleware → Form Request → controller → service → database → Blade-pagina | `tests/Feature` | 52 |
| **Procestests (gelijktijdigheid)** | Twee echte PHP-processen die tegelijk hetzelfde blok of de laatste materiaalsets boeken | `tests/Feature/GelijktijdigeReserveringTest.php` | 2 |
| **Functionele eisen** | Per eis uit K1-W3 minimaal één test die doet wat de gebruiker doet (tabel 4) | beide mappen | 15 van 15 eisen |

Bij het kiezen van de testgevallen zijn drie technieken gebruikt:

- **Equivalentieklassen**: per invoer één geldige en één of meer ongeldige waarden (bijvoorbeeld een bestaand en een niet-bestaand materiaaltype).
- **Grenswaarden**: precies op de grens en één stap erover (1 en 12 spelers wel, 0 niet; 5 van 5 sets wel, 6 niet; annuleren om 11:59 wel, om 12:00 niet).
- **Toestanden**: dezelfde actie vóór en na een andere actie (een blok dat tijdens het controleren door iemand anders wordt geboekt).

### Waarom deze aanpak

- **De regels zitten in services.** `PrijsCalculator`, `BeschikbaarheidChecker`, `ReserveringService` en `BeheerService` bevatten alle rekenregels. Die kunnen los worden getest, en bij een fout wijst de unittest meteen de methode aan. `PrijsCalculatorTest` heeft zelfs geen database nodig: het veld en de tarieven bestaan alleen in het geheugen.
- **De frontend is server-gerenderde Blade.** De gebruiker krijgt de HTML die Laravel maakt. Een integratietest stuurt een echt verzoek door de applicatie en controleert die HTML, de redirect, de foutmelding en de rijen in de database. Zo wordt in één test gecontroleerd of frontend, backend en database samenwerken. Er is maar één klein script (`public/js/courtconnect.js`, tariefregels toevoegen); hoe de server de invoer van dat script verwerkt is wel getest (zie hoofdstuk 7 voor wat niet).
- **De grootste risico's zitten in tijd, overlap en geld.** Dubbele boekingen, een verkeerde prijs of een uur te veel of te weinig zijn de fouten die een klant of het sportcentrum echt merken. Daarom zijn er veel grenswaardetests en twee echte gelijktijdigheidstests.
- **Productie gebruikt MariaDB, de snelle run SQLite.** Daarom draait dezelfde suite ook op MariaDB. Dat bleek nodig: zie bevinding B1.

## 2. Tests uitvoeren

```sh
# Snel, met een tijdelijke SQLite-database (de 2 procestests worden overgeslagen)
php artisan test --compact

# Coverage (Xdebug staat in de DDEV-container, maar is niet standaard geladen)
ddev exec "php -d zend_extension=xdebug.so -d xdebug.mode=coverage vendor/bin/phpunit --coverage-text"

# Hele suite op MariaDB, inclusief de procestests
ddev mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS courtconnect_test; GRANT ALL ON courtconnect_test.* TO 'db'@'%';"
ddev exec "DB_CONNECTION=mysql DB_HOST=db DB_DATABASE=courtconnect_test DB_USERNAME=db DB_PASSWORD=db vendor/bin/phpunit"
```

## 3. Resultaat

| Run | Tests | Geslaagd | Overgeslagen | Mislukt | Controles (assertions) |
| --- | --- | --- | --- | --- | --- |
| SQLite | 90 | 88 | 2 (procestests) | 0 | 390 |
| MariaDB 10.11 | 90 | 90 | 0 | 0 | 404 |

De volledige lijst met testnamen en uitkomst staat in `docs/testrapport/testdox.txt`.

## 4. Coverage

### 4.1 Coverage van de functionele eisen

Alle 15 functionele eisen uit de verantwoording van K1-W3 hebben minimaal één happy flow en minimaal één fout- of randgeval: **15 van 15 = 100%** (eis: minimaal 90%).

| # | Functionele eis | Happy flow | Fout- en randgevallen | Laag |
| --- | --- | --- | --- | --- |
| 1 | Pagina Velden: beschikbaarheid per dag | `test_veldenpagina_toont_alleen_beschikbaarheid_voor_de_gekozen_dag` | `test_fout_inactief_veld_is_niet_zichtbaar_en_niet_te_boeken`, `test_veldnaam_wordt_geescaped` | integratie |
| 2 | Veld zoeken op sport, datum en tijd | `test_zoeken_op_sport_toont_alleen_velden_van_die_sport`, `test_zoeken_met_tijdvenster_toont_alleen_blokken_binnen_dat_venster` | `test_fout_tijdvenster_met_eindtijd_voor_begintijd_wordt_gemeld` | integratie |
| 3 | Beschikbare tijdsblokken bekijken | `test_open_veld_heeft_veertien_vrije_uurblokken`, `test_reserveerpagina_toont_tijdsblokken_en_boekformulier_op_dezelfde_pagina` | `test_bezet_en_onderhoud_zijn_niet_aanklikbaar_op_de_reserveerpagina`, `test_randgeval_een_half_uur_voor_sluiting_levert_geen_blok_op`, `test_randgeval_blokkade_tot_precies_het_hele_uur_laat_het_volgende_uur_vrij`, `test_uren_in_het_verleden_en_een_inactief_veld_zijn_niet_boekbaar` | unit + integratie |
| 4 | Reserveren met spelers, duur, materiaal en controle | `test_klantflow_berekent_materiaal_en_slaat_pas_na_bevestiging_op`, `test_bevestigen_slaat_reservering_met_eindtijd_prijs_en_materiaal_op` | `test_randgeval_een_en_twaalf_spelers_mogen_nul_niet`, `test_randgeval_drie_uur_tot_precies_de_sluitingstijd`, `test_duur_moet_tussen_een_en_drie_uur_en_binnen_openingstijd_vallen`, `test_fout_controle_of_bevestigen_zonder_gekozen_tijdsblok`, `test_gast_moet_inloggen_voor_de_controlepagina`, `test_verleden_verkeerde_start_en_onbekend_materiaal_worden_geweigerd` | unit + integratie |
| 5 | Mijn reserveringen en annuleren | `test_mijn_reserveringen_toont_alleen_eigen_boekingen_en_annuleerknop_alleen_bij_toekomstige` | `test_randgeval_annuleren_kan_tot_een_minuut_voor_de_start_maar_niet_op_de_starttijd`, `test_fout_annuleren_van_een_reservering_van_een_ander_geeft_403`, `test_fout_onbekende_reservering_annuleren_geeft_404` | unit + integratie |
| 6 | Registreren en inloggen | `test_registratie_negeert_meegegeven_beheerdersrol_en_logt_in`, `test_beheerder_komt_na_inloggen_op_het_weekrooster`, `test_uitloggen_beeindigt_de_sessie` | `test_fout_registreren_met_bestaand_e_mailadres_of_zwak_wachtwoord`, `test_randgeval_wachtwoord_van_precies_acht_tekens_is_genoeg`, `test_fout_na_tien_mislukte_inlogpogingen_volgt_een_pauze` | integratie |
| 7 | Velden, openingstijden en tarieven beheren | `test_beheerder_kan_velden_met_tariefregels_toevoegen_wijzigen_en_verwijderen`, `test_tariefregels_met_sleutels_uit_het_javascript_worden_geaccepteerd` | `test_fout_tarieven_met_een_gat_of_sluiting_voor_opening_worden_geweigerd`, `test_fout_veld_met_reserveringen_verwijderen_wordt_geweigerd_en_deactiveren_kan_wel`, `test_fout_openingstijd_wijzigen_met_toekomstige_reservering_wordt_geweigerd`, `test_randgeval_alleen_reserveringen_in_het_verleden_blokkeren_de_wijziging_niet` | unit + integratie |
| 8 | Onderhoudsblokkade instellen | `test_blokkade_met_bevestigde_reservering_wordt_geweigerd` (tweede deel) | `test_blokkade_telt_alle_overlappende_bevestigde_boekingen`, `test_randgeval_blokkade_die_aansluit_op_een_boeking_is_toegestaan`, `test_fout_ongeldige_blokkade_en_materiaalinvoer_worden_gemeld` | unit + integratie |
| 9 | Materiaalvoorraad per tijdsblok | `test_beheerder_kan_materiaal_toevoegen_en_wijzigen`, `test_materiaalpagina_toont_voorraad_per_gekozen_uur` | `test_randgeval_voorraad_mag_gelijk_aan_de_drukste_bezetting_maar_niet_lager`, `test_fout_sport_van_toegewezen_materiaal_kan_niet_wijzigen`, `test_voorraad_mag_niet_onder_toegewezen_aantal` | unit + integratie |
| 10 | Beheerweekrooster | `test_weekrooster_toont_geboekte_en_geblokkeerde_uren` | `test_boeking_van_klant_is_direct_zichtbaar_in_beheer` | integratie |
| 11 | Overlap van veld en materiaal voorkomen | `test_reservering_van_twee_uur_blokkeert_beide_uren_en_slaat_eindtijd_op` | `test_een_bezet_blok_en_materiaal_kunnen_niet_opnieuw_worden_geboekt`, `test_fout_blok_dat_tijdens_het_controleren_wordt_geboekt_kan_niet_meer_worden_bevestigd`, `test_randgeval_de_laatste_set_mag_maar_een_set_te_veel_niet`, `test_unieke_index_weigert_twee_bevestigde_boekingen_maar_laat_annuleringen_toe`, `test_twee_processen_kunnen_niet_hetzelfde_blok_boeken`, `test_twee_velden_kunnen_niet_dezelfde_laatste_sets_boeken` | unit + integratie + proces |
| 12 | Prijsberekening | `test_daluur_op_maandag_kost_alleen_het_basistarief`, `test_piekuur_doordeweeks_telt_het_verschil_als_piektoeslag`, `test_drie_uur_over_de_tariefgrens_wordt_per_minuut_verdeeld`, `test_materiaal_kost_een_vaste_prijs_per_set_ongeacht_de_duur` | `test_randgeval_afronding_naar_hele_centen`, `test_randgeval_totaal_mag_precies_het_maximum_zijn_maar_niet_hoger`, `test_fout_tijdsblok_zonder_tarief_wordt_geweigerd`, `test_fout_overlappende_tariefregels_geven_geen_prijs`, `test_fout_gewijzigde_prijs_moet_opnieuw_worden_gecontroleerd` | unit + integratie |
| 13 | Rollen en toegang | `test_klant_krijgt_nette_403_op_beheer_en_beheerder_op_boeken` | `test_fout_een_beheerder_kan_niet_zelf_boeken`, `test_fout_ingelogde_gebruiker_kan_niet_opnieuw_registreren_en_gast_niet_uitloggen`, `test_gast_moet_inloggen_voor_de_controlepagina` | unit + integratie |
| 14 | Meldingen | `test_uitloggen_beeindigt_de_sessie` (succesmelding) | `test_onverwachte_fout_is_neutraal_en_verkeerde_materiaalstructuur_geeft_validatiefout`, `test_sessiefout_toont_een_neutrale_foutpagina_zonder_sessie_of_errors`, `test_fout_controle_of_bevestigen_zonder_gekozen_tijdsblok` (infomelding) | integratie |
| 15 | Responsive webapp | – | – | handmatig (zie 6.15) |

Eis 15 is geen gedrag dat PHPUnit kan meten. Tel je eis 15 als niet automatisch getest, dan is de dekking 14 van 15 = 93%.

### 4.2 Codecoverage (automatische uitvoer)

Uitvoer van `phpunit --coverage-text` met Xdebug. De run op MariaDB geeft precies dezelfde cijfers. Volledige bestanden: `docs/testrapport/coverage-sqlite.txt` en `docs/testrapport/coverage-mariadb.txt`.

```text
Code Coverage Report:
  2026-10-19 13:35:08

 Summary:
  Classes: 76.92% (20/26)
  Methods: 91.89% (68/74)
  Lines:   98.37% (363/369)

App\Http\Controllers\AuthenticatieController
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 10/ 10)
App\Http\Controllers\BeheerController
  Methods: 100.00% ( 8/ 8)   Lines: 100.00% ( 25/ 25)
App\Http\Controllers\ReserveringController
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 32/ 32)
App\Http\Controllers\VeldController
  Methods: 100.00% ( 5/ 5)   Lines: 100.00% ( 62/ 62)
App\Http\Middleware\RolMiddleware
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  2/  2)
App\Http\Requests\BlokkadeRequest
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
App\Http\Requests\CourtConnectRequest
  Methods: 100.00% ( 2/ 2)   Lines: 100.00% ( 14/ 14)
App\Http\Requests\InloggenRequest
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
App\Http\Requests\MateriaalRequest
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
App\Http\Requests\RegistrerenRequest
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
App\Http\Requests\ReserveringRequest
  Methods: 100.00% ( 2/ 2)   Lines: 100.00% (  9/  9)
App\Http\Requests\VeldRequest
  Methods: 100.00% ( 2/ 2)   Lines: 100.00% ( 21/ 21)
App\Http\Requests\ZoekenRequest
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
App\Models\Blokkade
  Methods:  66.67% ( 2/ 3)   Lines:  83.33% (  5/  6)
App\Models\Gebruiker
  Methods:  75.00% ( 3/ 4)   Lines:  83.33% (  5/  6)
App\Models\MateriaalReservering
  Methods: 100.00% ( 2/ 2)   Lines: 100.00% (  2/  2)
App\Models\Materiaaltype
  Methods:  50.00% ( 1/ 2)   Lines:  75.00% (  3/  4)
App\Models\Reservering
  Methods:  80.00% ( 4/ 5)   Lines:  87.50% (  7/  8)
App\Models\Veld
  Methods: 100.00% ( 5/ 5)   Lines: 100.00% (  7/  7)
App\Providers\AppServiceProvider
  Methods: 100.00% ( 2/ 2)   Lines: 100.00% (  2/  2)
App\Services\AuthenticatieService
  Methods: 100.00% ( 2/ 2)   Lines: 100.00% (  4/  4)
App\Services\BeheerService
  Methods: 100.00% ( 4/ 4)   Lines: 100.00% ( 49/ 49)
App\Services\BeschikbaarheidChecker
  Methods: 100.00% ( 5/ 5)   Lines: 100.00% ( 39/ 39)
App\Services\PrijsCalculator
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% ( 19/ 19)
App\Services\ReserveringService
  Methods:  75.00% ( 3/ 4)   Lines:  97.62% ( 41/ 42)
```

Wat niet geraakt wordt (6 regels):

- Vijf Eloquent-relaties die nergens in de applicatie worden gebruikt: `Blokkade::maker`, `Gebruiker::blokkades`, `Materiaaltype::materiaalReserveringen`, `Reservering::klant` en `Tarief::veld`. Dat is geen ontbrekende test maar ongebruikte code.
- De `catch (UniqueConstraintViolationException)` in `ReserveringService::bevestigen`. Dat is het vangnet achter de vergrendeling. Omdat de vergrendeling werkt, komt een gewone test er niet. In de procestests zou het kunnen, maar die draaien in aparte processen en tellen daardoor niet mee in de coverage.

Ter vergelijking: vóór dit testwerk was de regeldekking 91,6% (338/369) en waren `uitloggen`, de controle zonder concept en de foutpaden van `BeheerService` en `PrijsCalculator` niet getest.

## 5. Happy flows, foutsituaties en randgevallen

De nieuwe tests hebben `fout` of `randgeval` in hun naam, zodat het soort test meteen te zien is. Een selectie:

**Happy flows (normaal gebruik)**

| Test | Controleert |
| --- | --- |
| `test_klantflow_berekent_materiaal_en_slaat_pas_na_bevestiging_op` | Blok kiezen, materiaal kiezen, controle, bevestigen: pas daarna staat er een rij in de database |
| `test_boeking_van_klant_is_direct_zichtbaar_in_beheer` | Een boeking van de klant verschijnt bij de beheerder in materiaalvoorraad, weekrooster en veldoverzicht |
| `test_tariefregels_met_sleutels_uit_het_javascript_worden_geaccepteerd` | Een veld met tariefregels zoals het script ze verstuurt, en de nieuwe prijzen op de pagina Tarieven |
| `test_drie_uur_over_de_tariefgrens_wordt_per_minuut_verdeeld` | 15:00–18:00 is 2 uur dal en 1 uur piek: € 78,00 |
| `test_beheerder_komt_na_inloggen_op_het_weekrooster` | De beheerder komt na het inloggen op het juiste scherm |

**Foutsituaties (unhappy flows): 18 tests met `fout` in de naam, plus 10 oudere tests die ook een fout testen, onder andere**

| Test | Verwacht gedrag |
| --- | --- |
| `test_fout_blok_dat_tijdens_het_controleren_wordt_geboekt_kan_niet_meer_worden_bevestigd` | Terug naar het veld met een melding; geen tweede boeking |
| `test_fout_gewijzigde_prijs_moet_opnieuw_worden_gecontroleerd` | Prijs is tussendoor veranderd: niets opgeslagen |
| `test_fout_registreren_met_bestaand_e_mailadres_of_zwak_wachtwoord` | Vier Nederlandse foutmeldingen, geen account aangemaakt |
| `test_fout_na_tien_mislukte_inlogpogingen_volgt_een_pauze` | De elfde poging geeft 429, zelfs met het juiste wachtwoord |
| `test_fout_veld_met_reserveringen_verwijderen_wordt_geweigerd_en_deactiveren_kan_wel` | Historie blijft bewaard; het inactieve veld verdwijnt voor klanten |
| `test_fout_tarieven_met_een_gat_of_sluiting_voor_opening_worden_geweigerd` | Gat in de tarieven, sluiting vóór opening en dubbele regels worden gemeld |
| `test_fout_een_beheerder_kan_niet_zelf_boeken` | 403 |
| `test_fout_onbekende_reservering_annuleren_geeft_404` | Nette 404-pagina |

**Randgevallen, 15 tests, onder andere**

| Test | Grens |
| --- | --- |
| `test_randgeval_een_en_twaalf_spelers_mogen_nul_niet` | 1 en 12 wel, 0 niet |
| `test_randgeval_drie_uur_tot_precies_de_sluitingstijd` | 20:00–23:00 mag, 21:00 met 3 uur niet |
| `test_randgeval_annuleren_kan_tot_een_minuut_voor_de_start_maar_niet_op_de_starttijd` | 11:59 wel, 12:00 niet |
| `test_randgeval_de_laatste_set_mag_maar_een_set_te_veel_niet` | 5 van 5 wel, 6 niet |
| `test_randgeval_voorraad_mag_gelijk_aan_de_drukste_bezetting_maar_niet_lager` | Aansluitende boekingen (12–13 en 13–14) tellen niet dubbel |
| `test_randgeval_blokkade_die_aansluit_op_een_boeking_is_toegestaan` | Eindtijd is exclusief |
| `test_randgeval_totaal_mag_precies_het_maximum_zijn_maar_niet_hoger` | € 9.999,99 wel, € 10.000,00 niet |
| `test_randgeval_afronding_naar_hele_centen` | 1001,5 cent wordt 1002 |
| `test_randgeval_vandaag_kan_niet_meer_in_een_begonnen_uur_worden_geboekt` | Om 12:01 is 12:00 voorbij, 13:00 kan nog |
| `test_randgeval_wachtwoord_van_precies_acht_tekens_is_genoeg` | Minimum van 8 tekens |

## 6. Beoordeling per functionele eis

| # | Eis | Oordeel | Toelichting, problemen en risico's |
| --- | --- | --- | --- |
| 1 | Velden: beschikbaarheid per dag | Werkt | Telt vrije uren goed, ook bij volledige blokkade en verleden dagen. Inactieve velden worden verborgen. |
| 2 | Zoeken op sport, datum, tijd | Werkt | Filters en tijdvenster kloppen. Een ongeldig venster wordt gemeld. |
| 3 | Tijdsblokken bekijken | Werkt | Uren die gedeeltelijk overlappen met onderhoud worden goed geblokkeerd. *Risico*: een half uur vóór sluiting is nooit boekbaar; dat is een keuze, geen fout. |
| 4 | Reserveren | Werkt | Alle grenzen van duur, spelers en materiaal kloppen. Er wordt pas opgeslagen na bevestigen. |
| 5 | Mijn reserveringen en annuleren | Werkt | Een klant ziet alleen eigen boekingen; annuleren na de start en van een ander wordt geweigerd. |
| 6 | Registreren en inloggen | Werkt | Rol komt nooit uit het formulier; rate limit werkt. *Risico*: de limiet geldt per IP-adres. Gebruikers achter hetzelfde netwerk (bijvoorbeeld school-wifi) delen die limiet. |
| 7 | Velden en tarieven beheren | Werkt, na herstel | Bevinding **B1** is gevonden en opgelost. Tarieven met gaten of overlap worden geweigerd. |
| 8 | Onderhoudsblokkade | Werkt | Telt het aantal conflicten goed en negeert annuleringen. |
| 9 | Materiaalvoorraad | Werkt, na herstel | Bevinding **B1** gold hier ook: voorraad verlagen hield geen rekening met boekingen van later vandaag. |
| 10 | Beheerweekrooster | Werkt | Alleen gecontroleerd op labels in de HTML, niet op de exacte cel per uur. |
| 11 | Overlap voorkomen | Werkt | Op MariaDB bewezen met twee echte processen: precies één boeking slaagt. Op SQLite wordt dat deel overgeslagen. |
| 12 | Prijsberekening | Werkt | Dal, piek, weekend, tariefwissel binnen een uur, afronding en maximumbedrag kloppen. |
| 13 | Rollen en toegang | Werkt | 403 voor de verkeerde rol, doorsturen naar inloggen voor gasten. |
| 14 | Meldingen | Werkt | Succes-, info- en foutmeldingen en neutrale 403/404/429/500-pagina's zonder technische details. Er zijn geen e-mailmeldingen (buiten de MVP). |
| 15 | Responsive | Niet opnieuw getest | Eerder handmatig gecontroleerd op 390, 768 en 1440 px (zie `README.md`). Voor dit rapport niet opnieuw nagelopen en niet automatisch getest. |

### Bevindingen

**B1. Boekingen van later vandaag telden niet mee bij wijzigingen in beheer (opgelost).**
`BeheerService` vergeleek de datumkolom met `today()`, een datum mét tijd (`2026-09-28 00:00:00`). SQLite vergelijkt dat als tekst, en dan is `'2026-09-28' >= '2026-09-28 00:00:00'` onwaar. Gevolg in de testdatabase: een beheerder kon de voorraad verlagen of de openingstijd wijzigen terwijl er later die dag nog geboekt was. Gevonden door `test_randgeval_voorraad_mag_gelijk_aan_de_drukste_bezetting_maar_niet_lager`. Op MariaDB ging het wel goed, omdat MariaDB de waarde als datum vergelijkt. De oplossing is `today()->toDateString()` op beide plekken in `app/Services/BeheerService.php`. De les: de snelle testdatabase gedraagt zich niet altijd als productie, en daarom draait de suite nu ook op MariaDB.

**B2. Procestests lieten gegevens achter op MariaDB (opgelost).**
De gelijktijdigheidstests committen echt, buiten de testtransactie. Op MariaDB bleven die rijen staan en faalden daarna drie andere tests met "9 rijen in plaats van 4". Dat is een fout in de testsuite, niet in de applicatie. `GelijktijdigeReserveringTest` maakt de testdatabase nu na afloop leeg.

## 7. Kritische terugblik op de tests

**Wat de tests goed controleren**

- Alle rekenregels tot op de grens, met exacte bedragen in centen en exacte foutmeldingen.
- De echte keten van verzoek tot database tot HTML, inclusief middleware, validatie en sessie.
- Gelijktijdig boeken met echte processen op de productiedatabase.
- Tijd staat vast (`travelTo`), dus de tests geven elke dag dezelfde uitkomst.

**Hoe betrouwbaar zijn de tests?**

Als extra controle zijn er acht kleine fouten bewust in de code gezet (mutatietest). Elke keer is gekeken of de suite faalt; daarna is de code teruggezet.

| Bewust aangebrachte fout | Gevangen door |
| --- | --- |
| Overlapcontrole reservering `<` → `<=` | 3 tests |
| Materiaal: rustigste in plaats van drukste uur (`min` → `max`) | 5 tests |
| Prijsgrens `>` → `>=` | `test_randgeval_totaal_mag_precies_het_maximum_zijn_maar_niet_hoger` |
| Annuleren: `isFuture` → `isPast` | 3 tests |
| Maximaal 13 in plaats van 12 spelers | `test_alle_schermen_renderen_en_ongeldige_invoer_wordt_gemeld` |
| Voorraadgrens één set te ruim | 2 tests |
| Nieuwe accounts krijgen de rol beheerder | `test_registratie_negeert_meegegeven_beheerdersrol_en_logt_in` |
| Uren in het verleden blijven boekbaar | 3 tests |

8 van 8 fouten zijn gevangen. De fout in de prijsgrens werd alleen door een nieuwe randgevaltest gevangen; de oude suite had die gemist.

**Wat nog ontbreekt**

- **JavaScript in de browser.** Het toevoegen en verwijderen van tariefregels in het formulier is niet automatisch getest. Alleen de serverkant van die invoer is getest.
- **CSRF.** Laravel zet de CSRF-controle uit tijdens tests. Dat is eerder alleen handmatig in de browser gecontroleerd.
- **Weergave.** Responsive gedrag, kleurcontrast en toetsenbordbediening zijn niet automatisch getest.
- **Weekrooster.** Gecontroleerd op labels, niet per cel.
- **Belasting.** Er is niet gemeten hoe snel de pagina's blijven met veel velden of boekingen. `BeschikbaarheidChecker::materiaalVrij` doet één query per uur per materiaaltype.
- **Zomertijd.** De wissel (25 oktober 2026) valt buiten de openingstijden en is daarom niet getest.

**Beperkingen in de opzet**

- Veel integratietests gebruiken de demoseeder. Dat is realistisch, maar een wijziging in de seeder kan tests laten falen zonder dat de applicatie fout is. De unittests maken daarom hun eigen kleine testdata (`tests/MaaktTestdata.php`).
- De procestests draaien alleen als er een MariaDB/MySQL-testdatabase is ingesteld. In de standaardrun worden ze overgeslagen.

## 8. Eindconclusie en advies

CourtConnect doet wat de functionele eisen vragen. Alle 90 tests slagen op MariaDB, en de testdekking is 100% van de functionele eisen (93% als je de handmatige responsive-eis niet meetelt) en 98,4% van de coderegels. De onderdelen met het grootste risico zijn het best getest: dubbele boekingen, materiaalvoorraad en prijzen. De testronde vond één echte fout (B1), die is opgelost, en één fout in de testsuite (B2). De software is bruikbaar voor een demo en voor de afgesproken MVP.

Advies, op volgorde van belang:

1. **Draai de suite op MariaDB, niet alleen op SQLite**, bijvoorbeeld automatisch bij elke push (GitHub Actions met een MariaDB-service). Bevinding B1 laat zien waarom.
2. **Voeg browsertests toe** (Laravel Dusk of Pest browser testing) voor het tariefformulier, CSRF en de weergave op 390 px.
3. **Ruim de ongebruikte relaties op** of gebruik ze, zodat de codecoverage alleen echte code meet.
4. **Test met veel data** (bijvoorbeeld 50 velden en een jaar aan boekingen) om te zien of de beschikbaarheidspagina's snel genoeg blijven.
5. Overweeg een **rate limit per e-mailadres** in plaats van alleen per IP-adres.
