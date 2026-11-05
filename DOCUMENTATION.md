# Entwicklung und Architektur

## Markenname und technische Identität

- Sichtbarer Plugin-Name: **Patsch9 Pickup- and Orderdate Manager**.
- Installationsslug/-ordner, Hauptdatei, Textdomain, Store-API-Namespace, Konfigurations- und Metaschlüssel sowie Präfix `p9od` bleiben **patsch9-orderdate** bzw. unverändert, damit WordPress die Erweiterung als Update erkennt und bestehende Bestellungen und Einstellungen weiter funktionieren.
- Die WooCommerce-Menüpunkte „Abholtermine“ und „Abholmanager“ bezeichnen die jeweiligen Funktionen und bleiben bewusst knapp. Der Markenname wird innerhalb der Verwaltungsseiten ausgewiesen.
- Der neue Name erscheint im Plugin-Header, der WordPress-Readme, der Repository-README und im Titel künftiger GitHub-Releases. Ältere Release-Einträge werden dadurch nicht rückwirkend geändert.

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

Es werden keine eigenen Tabellen angelegt. Der einmalige, in Batches aufgeteilte WP-Cron `p9od_index_orders` erschließt vorhandene Bestellungen für den Abholmanager. Deaktivierung/Deinstallation löschen keine Bestelldaten.

## Prüfstrategie

Reine Regeln decken ungültige Datumswerte, Schaltjahre, Sommerzeit, Grenzen, Vorlauf, Bestellschluss, Sperrpriorität und Schnittmengen ab. Integrationstests prüfen reale Produkt- und Bestellobjekte, Warenkorbtrennung, Nonces/Berechtigungen, Classic- und Store-API-Validierung sowie Metadaten. JavaScript-Tests prüfen das Kalender-/Block-Verhalten mit kontrollierter Komponentenlaufzeit.

Die Produkt-Terminauswahl verwendet die klassischen WooCommerce-Produktseiten-Hooks. Direkte Kaufwege ohne Datum werden abgewiesen. Unterstützte Varianten erben die Regeln des Hauptprodukts. Nicht unterstützte Produkttypen werden wie deaktivierte Produkte behandelt.

## Geprüfter Stand am 02.11.2026

- WordPress 7.1.4 / WooCommerce 11.2.1, lokal mit PHP 8.3 und MariaDB 10.11.
- GitHub Actions: PHP 8.2, 8.3 und 8.4; 71 Terminregel-Assertions und fünf JavaScript-Komponententests.
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


## Absoluter Bestellschluss (nach beta3)

Der absolute Bestellschluss **„Bestellbar bis“** ist unabhängig von der bisherigen Abholregel **„Bestellschluss“** (Uhrzeit, die den frühesten Abholtermin verschiebt). Einfache Produkte haben im Tab „Allgemein“ ein optionales Datum-/Uhrzeitfeld; variable Produkte haben pro Variante ein eigenes Feld. Leere Felder bedeuten keine Einschränkung. Das Feature gilt unabhängig von Abholung, Versandart, Zahlungsart oder aktiviertem Abholkalender.

- Meta: _p9od_order_deadline pro einfachem Produkt bzw. pro Variante; UTC-Unix-Zeitstempel als String. Die Eingabe YYYY-MM-DDTHH:MM wird strikt gegen die Shop-Zeitzone validiert, nicht existente Sommerzeit-Uhrzeiten werden abgewiesen. Ein Ablauf um 12:00 sperrt ab 12:00:00. Beim späteren Wechsel der Shop-Zeitzone bleibt der tatsächliche Ablaufzeitpunkt unverändert.
- Speicherung: WooCommerce CRUD in woocommerce_admin_process_product_object / woocommerce_admin_process_variation_object mit individuellen Nonces und edit_post-Berechtigung. Ungültige Eingaben ändern bestehende Meta-Werte nicht. Bei beschädigten gespeicherten Fristen wird fail closed verfahren.
- Anzeige: WooCommerce-Purchasability für einfache Produkte und Varianten sowie variation is active für die Variantenauswahl. Ein abgelaufener Artikel bleibt katalogsichtbar, ist aber nicht mehr kaufbar.
- Verbindliche Prüfungen: klassisches Add-to-Cart, Store API Add-to-Cart, Store API Mengenänderungen, klassische Warenkorbfehler, Store API Cart Errors sowie letzte Validierung beim Classic- und Block-Checkout über Plugin::errors. Die tatsächlich ausgewählte Varianten-ID wird immer gegenüber der jeweiligen Varianten-Frist geprüft, nicht gegenüber der Eltern-ID. Auch vorhandene Warenkörbe werden beim Abschluss neu validiert.
- Testfälle: Winter-/Sommerzeit, ungültige Datumswerte, Sommerzeitlücke, Pflicht-Nonce/Berechtigung, persistierte Werte, einzelne Varianten, Classic/Store-API-Hinzufügen und Checkout nach Ablauf.

## Produktanzeige und Feldlayout bei Bestellschluss

- Das einfache Produkt zeigt die lokale Frist vor dem Warenkorb-Formular über den Standard-Hook `woocommerce_single_product_summary` an; nach Ablauf oder bei beschädigtem gespeicherten Wert erscheint eine Fehlermeldung. Es werden nur Produkte mit gesetzter Frist erwähnt.
- Variationen bekommen die Nachricht über `woocommerce_available_variation` in `availability_html`. Eine abgelaufene Variante bleibt auswählbar, um den Grund anzuzeigen; der Kauf bleibt über `woocommerce_variation_is_purchasable`, die Warenkorbvalidierung und beide Checkouts gesperrt. WooCommerce-eigene Deaktivierungsgründe bleiben unverändert.
- Der Hilfetext des `datetime-local`-Feldes im allgemeinen Produkteditor steht unter dem Eingabefeld statt als vorangestelltes Symbol. Feldbreite/Abstand werden nur mit spezifischen CSS-Selektoren für die Orderdate-Frist korrigiert. Andere Produktdatenfelder und der Abholkalender bleiben unberührt.
- Browser-Regression: einfache Produkte vor/nach Frist und getrennte Variantenanzeigen nach Auswahl; PHP-Integration: Fristtexte, Varianten-Antwort und weiterhin gesperrte Kaufbarkeit.

## Abholablauf (2026.11.1-beta3)

- `PickupLocations`: WooCommerce-Versandarten `local_pickup`/`pickup_location`; native `pickup_location`-/`pickup_address`-Snapshots.
- `Gateway`/`GatewayBlocks`: offline Zahlung, beide Checkouts; serverseitiger Ausschluss gemischter Liefer-/Abholpakete. Gateway standardmäßig deaktiviert.
- `PickupManager`: Status `wc-p9od-ready`/`wc-p9od-collected`, offene Übersicht, indexiertes CRUD-Meta `_p9od_managed`, Hintergrunderschließung älterer Bestellungen.
- `_p9od_proposal`: Positions-ID, bisheriger und vorgeschlagener Termin, SHA-256 des kryptografischen Tokens und Ablaufzeit. Keine Klartexttokens im Bestellmeta. Ein Link ist maximal sieben Tage gültig; ungültige aktuelle Produktregeln verhindern ebenfalls die Annahme.
- Mutationen im Manager erfordern Nonce, `manage_woocommerce`, bestellbezogene Bearbeitungsberechtigung, unveränderten Formularstand und eine verbindungsgestützte MySQL/MariaDB-Bestellsperre. Gastbestätigung prüft Token, Nonce, offenen Auftrag und unveränderte Ausgangsdaten. GET ändert nichts.
- Statusprüfung vor Änderungen im Abholmanager, WooCommerce-Editor und REST API; eigene PHP-Integrationen müssen `PickupManager::assert_status()` vor dem Setzen eines Abholstatus aufrufen.
- `PickupEmail`: registrierte WooCommerce-E-Mailtypen, HTML-/Text-Templates mit WooCommerce-Header, Bestellpositionen, Kundendaten und Footer; Theme-Overrides über die übliche Template-Auflösung. Versandfehler stehen in den Bestellnotizen, erneuter Vorschlagsversand ist im Manager möglich.
- WooCommerce legt Zahlungseingänge weiterhin über `payment_complete()` an. Abholstatus allein setzt keine Zahlung. Beide neuen Status gehören nicht pauschal zur Liste bezahlter Status.

Prüfung: `wp eval-file wp-content/plugins/patsch9-orderdate/tests/integration-pickups.php` in einer isolierten Installation. 47 Assertions je Bestellspeicherart: Zahlung und Bestände, echte Store-API-Zahlungsabwicklung, Orts-Snapshots, Umplanung und Tokenlebenszyklus, REST-Validierung, Abgrenzung normaler Bestellungen und Erstattungen, E-Mailauslösung und HTML-/Textinhalte. Test-E-Mails werden abgefangen. Die CI führt diese Prüfung zusätzlich zu den 47 vorhandenen Integrations-Assertions jeweils mit klassischem Bestellspeicher und HPOS aus.

Zusätzliche lokale HTTP-Prüfung: 13 Assertions für Gast-GET ohne Mutation, ungültige Nonces, explizite Bestätigung, verbrauchte Links, Manager-Anmeldung, veraltete Formulare, Bereitstellung ohne Zahlung und Abholung mit ausdrücklich bestätigter Zahlung. Ein vollständiger visueller Browserlauf wurde für diese Erweiterung lokal nicht durchgeführt; die vorhandene Browser-CI bleibt aktiv.

## CI und Artefaktspeicher

Der strikte Plugin Check läuft im bestehenden Integrationstest gegen `dist/package/patsch9-orderdate`; dadurch ist keine zweite WordPress-Umgebung erforderlich. `tools/check-plugin-report.py` weist jeden Fehler und jede Warnung zurück und scheitert auch bei fehlenden oder beschädigten Reports. Der Bericht bleibt im Jobprotokoll; es ist kein Artefakt-Upload nötig. Vorschau-ZIPs werden nur auf Wunsch über den manuellen Workflowparameter `upload-preview` gespeichert. Ein erschöpftes Actions-Speicherkontingent blockiert dadurch die regulären Qualitätsprüfungen nicht.
