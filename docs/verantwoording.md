# Verantwoording realisatie CourtConnect (KT1-W3)

Dit document koppelt elke gebouwde functie aan de eis uit de opdracht, het deel van het ontwerp en de taak in de planning. Daarna volgen de verschillen tussen eisen, ontwerp en product, en per checklistpunt waar het bewijs staat.

> **Invullen door student:** de kolom *Planningstaak* verwijst naar de taken in jouw eigen planning. Vervang `T?` door het juiste taaknummer uit je planning.

## 1. Functies ↔ eisen ↔ ontwerp ↔ planning

### Klant (sportklant)

| Eis uit de opdracht | Gebouwde functie | Ontwerpdeel | Code | Test | Planningstaak |
| --- | --- | --- | --- | --- | --- |
| (afgeleid) Snel zien welke velden vrij zijn | Pagina *Velden*: dagkiezer met per veld alleen de beschikbaarheid voor die dag (Beschikbaar met aantal vrije uren, Volgeboekt of Niet boekbaar) | Veldkaarten, materiaal- en veldstatus met kleur én tekst | `VeldController::overzicht`, `velden/overzicht.blade.php`, `components/dagkiezer.blade.php` | `test_veldenpagina_toont_alleen_beschikbaarheid_voor_de_gekozen_dag` | T? |
| Veld zoeken op sport, datum en tijd | Pagina *Reserveren*: filters voor sport, veld, datum en tijdvenster (van/tot) plus een dagkiezer | Sportieve tijdslotkiezer | `VeldController::zoeken`, `ZoekenRequest`, `velden/zoeken.blade.php` | `test_reserveerpagina_toont_tijdsblokken_en_boekformulier_op_dezelfde_pagina` | T? |
| Beschikbare tijdsblokken bekijken | Op *Reserveren* staan per veld de uurblokken met kleur én tekst (Vrij, Bezet, Onderhoud, Niet boekbaar); een vrij blok is direct aanklikbaar | Tijdsblokgrid | `VeldController::zoeken`, `BeschikbaarheidChecker::blokken` | `test_blokkade_met_bevestigde_reservering_wordt_geweigerd` | T? |
| Reserveren met aantal spelers | Boekpaneel op dezelfde pagina met duur (1–3 uur), aantal spelers (1–12) en materiaal; controlepagina vóór bevestigen. Wie niet ingelogd is, komt na het inloggen terug bij het gekozen tijdsblok. | Tijdslotkiezer, prijsopbouw | `VeldController::boekpaneel`, `ReserveringController`, `ReserveringService`, `ReserveringRequest`, `reserveringen/*.blade.php` | `test_klantflow_berekent_materiaal_en_slaat_pas_na_bevestiging_op`, `test_controlepagina_toont_eindtijd_voor_duur_uit_formulier` | T? |
| (afgeleid) Eigen reserveringen beheren | Overzicht *Mijn reserveringen* en annuleren vóór de starttijd | Veldkaarten | `ReserveringController::mijn/annuleren`, `ReserveringService::annuleren` | `test_annuleren_geeft_blok_vrij_maar_weigert_vreemde_of_gestarte_boeking` | T? |
| (afgeleid) Account | Registreren en inloggen; nieuwe accounts krijgen altijd de rol klant | – | `AuthenticatieController`, `AuthenticatieService` | `test_registratie_negeert_meegegeven_beheerdersrol_en_logt_in`, `test_inloggen_gebruikt_nederlandse_wachtwoordkolom` | T? |

### Beheerder (centrumbeheerder)

| Eis uit de opdracht | Gebouwde functie | Ontwerpdeel | Code | Test | Planningstaak |
| --- | --- | --- | --- | --- | --- |
| Velden, openingstijden en tarieven beheren | Velden toevoegen, wijzigen, deactiveren en verwijderen, met openingstijden en tariefregels per dagtype | – | `BeheerController::velden/veldOpslaan`, `BeheerService::veldOpslaan`, `VeldRequest` | `test_beheerder_kan_velden_met_tariefregels_toevoegen_wijzigen_en_verwijderen` | T? |
| Onderhoudsblokkade instellen | Blokkade per veld met reden, start en einde; geweigerd bij overlap met bevestigde boekingen | Beheerweekrooster | `BeheerService::blokkadeOpslaan`, `BlokkadeRequest` | `test_blokkade_met_bevestigde_reservering_wordt_geweigerd` | T? |
| Materiaalvoorraad per tijdsblok beheren | Voorraad per materiaaltype, met gereserveerd en vrij per gekozen datum en uur | Materiaalstatus | `BeheerController::materiaal`, `BeheerService::materiaalOpslaan`, `BeschikbaarheidChecker::materiaalVrij` | `test_voorraad_mag_niet_onder_toegewezen_aantal`, `test_beheerder_kan_materiaal_toevoegen_en_wijzigen` | T? |
| (ontwerp) Beheerweekrooster | Weekoverzicht per veld met vrije, geboekte en geblokkeerde uren | Beheerweekrooster | `BeheerController::weekrooster`, `beheer/weekrooster.blade.php` | `test_alle_schermen_renderen_en_ongeldige_invoer_wordt_gemeld` | T? |

### Algemeen

| Eis uit de opdracht | Gebouwde functie | Code | Test | Planningstaak |
| --- | --- | --- | --- | --- |
| Overlap voor veld en materiaal voorkomen | Controle in één databasetransactie met vergrendeling van veld en materiaal (`lockForUpdate`), plus unieke index als extra vangnet. Elk uur van de gekozen duur moet vrij zijn. | `ReserveringService::bevestigen`, `BeschikbaarheidChecker::isVrij/materiaalVrij`, migratie `create_reserveringen_table` | `test_een_bezet_blok_en_materiaal_kunnen_niet_opnieuw_worden_geboekt`, `test_reservering_van_twee_uur_blokkeert_beide_uren_en_slaat_eindtijd_op`, `test_materiaal_is_vrij_als_het_in_elk_gekozen_uur_vrij_is`, `GelijktijdigeReserveringTest` (MySQL) | T? |
| Prijs op basis van duur, piekuren en extra's | Prijs in centen per minuut over de hele duur; laagste tarief = basis, verschil = piektoeslag; materiaal per set | `PrijsCalculator::bereken`, `components/prijs.blade.php` | `test_prijs_splitsing_piek_weekend_en_halve_uren`, `test_prijs_van_meerdere_uren_telt_dal_en_piek_per_uur` | T? |
| Rollen: ieder ziet alleen wat bij zijn rol hoort | Middleware `rol:klant` en `rol:beheerder` op de routes; nette 403-pagina | `RolMiddleware`, `routes/web.php`, `errors/403.blade.php` | `test_klant_krijgt_nette_403_op_beheer_en_beheerder_op_boeken` | T? |
| Duidelijke meldingen bij succes, fout, leeg en niet toegestaan | Meldingcomponent (succes/fout/info), lege-lijstmeldingen, foutpagina's 403/404/419/429/500 | `components/melding.blade.php`, `resources/views/errors` | `test_onverwachte_fout_is_neutraal_en_verkeerde_materiaalstructuur_geeft_validatiefout`, `ServerfoutTest` | T? |
| Responsive webapp | Eigen CSS met breakpoints; brede tabellen scrollen binnen hun kader | `public/css/courtconnect.css` | handmatig gecontroleerd op 390, 768 en 1440 px | T? |

## 2. Verschillen tussen eisen, ontwerp en product

| Onderwerp | Eis of ontwerp | Opgeleverd | Reden |
| --- | --- | --- | --- |
| Duur van een reservering | "Prijs op basis van duur" | Hele uren, 1 tot 3 uur achter elkaar. Het boekpaneel toont alleen de duur die past voordat een bezet of geblokkeerd uur of de sluitingstijd begint. | Hele uren houden het tijdsblokgrid overzichtelijk voor klant en beheerder. Meer dan 3 uur is voor één sportveld in de praktijk zeldzaam. |
| Openingstijden | "Velden, openingstijden en tarieven beheren" | Openingstijden per veld in plaats van één tijd voor het hele centrum | Velden kunnen zo apart eerder sluiten, bijvoorbeeld voor een training. |
| Piekuren | "Prijs op basis van piekuren" | Piekuren zijn tariefregels per dagtype (doordeweeks/weekend) met een eigen prijs. De laagste prijs is het basistarief; het verschil is de piektoeslag. | Eén model voor tarieven en piekuren; de beheerder stelt beide op één scherm in. Tariefregels moeten de openingstijd zonder gaten of overlap vullen. |
| Materiaalprijs | "Extra's" | Een vaste prijs per set per reservering, ongeacht de duur | Dit is de "eenvoudige regel" uit de technische wensen. Per uur rekenen kan later als uitbreiding. |
| Aantal spelers | "Reserveren met aantal spelers" | 1 tot 12 spelers voor elk veld | Genoeg voor padel, basketbal en zaalvoetbal. Een maximum per sport is een mogelijke uitbreiding. |
| Blokkade over bestaande boeking | "Onderhoudsblokkade instellen" | Geweigerd met een melding over het aantal conflicten; er wordt niets automatisch geannuleerd | De klant moet eerst worden ingelicht. Automatisch annuleren zonder notificatie zou onbetrouwbaar zijn. |
| Kleuren | Richting: #064E3B, #34D399, #FBBF24, #F8FAFC | Groen #1B9E4B en #116C35, lichtgrijs #F2F6F2 en geel #E7A624 voor de focusrand | Sluit aan op het CourtConnect-logo. De opdracht noemt de kleuren "een richting". |
| Betalen | Nog niet nodig | Alleen de melding "Betalen gebeurt op locatie" | Valt buiten de MVP volgens de opdracht. |

## 3. Checklist KT1-W3 met bewijs

| Checklistpunt | Bewijs |
| --- | --- |
| Genoeg geplande functies gebouwd | Alle functies uit hoofdstuk 4 van de opdracht staan in tabel 1. |
| Koppeling functie ↔ eis ↔ ontwerp ↔ taak | Tabel 1 (vul de planningstaken in). |
| Software start en is bruikbaar; demo ≤ 4 min | `README.md` (installatie en demostappen). Demovideo: *nog opnemen*. |
| Functies werken volgens de eisen | `php artisan test`: featuretests per eis (kolom Test). |
| Verschillen beschreven | Tabel 2. |
| Logische indeling en naamgeving | Nederlandse namen per laag: `Controllers`, `Requests`, `Services`, `Models`, views per onderdeel. |
| Duidelijke onderdelen, weinig dubbele code | Logica zit in services, validatie in Form Requests met de gedeelde basis `CourtConnectRequest`, en herbruikbare Blade-componenten (`veld`, `melding`, `prijs`, `layout`). |
| Invoercontrole, foutafhandeling, betrouwbare opslag | Form Requests met Nederlandse meldingen; transacties met vergrendeling; neutrale foutpagina's. |
| Beveiliging | bcrypt-hashes, Eloquent/prepared statements tegen SQL-injectie, Blade-escaping tegen XSS (`test_veldnaam_wordt_geescaped`), CSRF-tokens, rolmiddleware, eigenaarcontrole bij annuleren, rate limit op inloggen en registreren. |
| Eén centrale repository, duidelijke definitieve versie | Git-repository met branch `main` als definitieve versie. |
| Commits verspreid over de projectperiode | Zie de git-geschiedenis. |
| Duidelijke commitberichten, branches en merges | Conventionele berichten (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`); featurebranches samengevoegd met merge-commits. |
