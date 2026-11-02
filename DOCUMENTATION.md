# Entwicklung und Architektur

## Verantwortlichkeiten

- `Rules`: strikt validierte Kalenderregeln ohne Request-/WooCommerce-Zustand; eine gemeinsame Engine für alle Auswahlorte.
- `Plugin`: Produktfelder, Warenkorb, Classic Checkout, Bestell-Snapshots und Anzeige.
- `Admin`: Produktregeln und globale Sperrtage mit Nonce-/Berechtigungsprüfung.
- `Blocks`: Checkout-Block, Store-API-Verfügbarkeit und verbindliche Store-API-Validierung.
- `tools/build-release.py`: explizite Paketdateiliste, konsistente Tag-Version und Prüfsumme.

## Daten

- Produktmeta `_p9od_rules`: Modus, Datumsspanne, Wochentage, Einzeltermine, Sperren, Vorlauf, Bestellschluss, Zeitfenstermodus und Zeitplan.
- Option `p9od_settings`: Buchungshorizont globale Sperrtage und Standardzeitfenster.
- Warenkorbdatum, Bestell-/Positionsmeta `_p9od_pickup_date`: ISO-Datum. Sichtbare Beschriftung ist übersetzbar; Speicherung ist nicht von der Sprache abhängig.
- Store-API-Namespace `patsch9-orderdate`, Checkout-Feld `date`. Beim API-Hinzufügen datierter Produkte ebenfalls `extensions["patsch9-orderdate"].date` senden. Die klassische Produktseite nutzt ein noncegeschütztes POST-Formular.

Es werden keine eigenen Tabellen oder Cronjobs angelegt. Deaktivierung/Deinstallation löschen keine Bestelldaten.

## Prüfstrategie

Reine Regeln decken ungültige Datumswerte, Schaltjahre, Sommerzeit, Grenzen, Vorlauf, Bestellschluss, Sperrpriorität und Schnittmengen ab. Integrationstests prüfen reale Produkt- und Bestellobjekte, Warenkorbtrennung, Nonces/Berechtigungen, Classic- und Store-API-Validierung sowie Metadaten. JavaScript-Tests prüfen das Kalender-/Block-Verhalten mit kontrollierter Komponentenlaufzeit.

Die Produkt-Terminauswahl verwendet die klassischen WooCommerce-Produktseiten-Hooks. Direkte Kaufwege ohne Datum werden abgewiesen. Unterstützte Varianten erben die Regeln des Hauptprodukts. Nicht unterstützte Produkttypen werden wie deaktivierte Produkte behandelt.

## Geprüfter Stand am 02.11.2026

- WordPress 7.1.4 / WooCommerce 11.2.1, lokal mit PHP 8.3 und MariaDB 10.11.
- GitHub Actions: PHP 8.2, 8.3 und 8.4; 71 Terminregel-Assertions und vier JavaScript-Komponententests.
- 47 Integrations-Assertions jeweils mit klassischer Bestellspeicherung und HPOS.
- Echter Chrome-Browser mit Storefront 4.6.2: Produktkalender, getrennte Datumswerte im Warenkorb, automatisches Mounten des Checkout Blocks, tatsächlicher Checkout-Store und klassischer Checkout.
- Strikter WordPress Plugin Check des gebauten Pakets; feste Ordnerstruktur, Tag-Versionen und SHA-256-Prüfsummen geprüft.

Die Browserprüfung validiert Auswahl und Datenübergabe. Zahlungsanbieter-spezifische Abläufe und das Theme des Zielshops bleiben Teil des manuellen Shop-Abgleichs.

## Manueller Shop-Abgleich vor einem produktiven Release

1. Produktmodus mit einfacher und variabler Ware testen; dasselbe Produkt mit zwei Daten hinzufügen.
2. Checkout-Modus mit überlappenden und nicht überlappenden Artikeln testen.
3. Gemischten Warenkorb mit deaktiviertem Produkt und Positionsterminen abschließen.
4. Sperrtage nach Warenkorb-Anlage ändern; unzulässige Termine müssen den Abschluss verhindern.
5. Classic Checkout und Checkout Block mit aktivierten Zahlungsarten prüfen.
6. Bestellverwaltung, Bestellbestätigung, HTML-/Text-E-Mail und Mein Konto prüfen; HPOS und klassische Speicherung prüfen.
7. Tastaturbedienung, Mobilansicht, Shop-Sprache, Zeitzone und Cache-Ausnahmen für Produkt-/Checkout-Nonces prüfen.

Keine Aussage über tatsächlich getestete WordPress-Versionen aus Referenzplugins übernehmen. Ein `Tested up to`-Feld wird erst nach einem dokumentierten entsprechenden Test gesetzt.

## Zeitfenster (beta2)

`time_mode` ist `inherit`, `custom` oder `off`; `time_windows` speichert Listen von `HH:MM-HH:MM` unter `*` oder ISO-Wochentagen 1–7. Ein Wochentag ersetzt den Standardplan. Laufzeitregeln lösen globale Vererbung auf, während der Editor den gespeicherten Modus beibehält. Fenster werden als Intervalle geschnitten. Angrenzende Fenster bleiben getrennt; reine Datumsauswahl schränkt Zeitfenster anderer Artikel nicht ein.

Warenkorb und Bestell-/Positionsmeta `_p9od_pickup_time` speichern die gewählte Spanne. Die Store API nimmt neben `date` auch `time` im Namespace `patsch9-orderdate` an; Cart-Daten liefern `time_required` und pro Datum eine Liste `windows`. Fehlerhafte, fehlende, abgelaufene oder nach Regeländerung unzulässige Werte verhindern den Abschluss. Wiederverwendete Bestellentwürfe entfernen veraltete gemeinsame Zeiten.

Die lokale Integration und die Browserprüfung umfassen nun Datum plus Zeitfenster; insbesondere unterschiedliche Positionstermine, gemeinsame Teilintervalle, globale Vererbung, manipulierte Requests und E-Mail-Metadaten.
