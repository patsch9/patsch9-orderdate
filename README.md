# Patsch9 Orderdate for WooCommerce

Abholtermine je Produkt: Auswahl auf der Produktseite oder als gemeinsamer Termin im klassischen WooCommerce-Checkout und im Checkout Block. Entwicklungsversion **2026.11.1-beta3**.

## Funktionsumfang

- Pro Produkt deaktivierbar oder Auswahl am Produkt / an der Kasse.
- Verfügbar ab/bis, wiederkehrende Wochentage, konkrete Abholdaten oder beides.
- Globale und produktspezifische Sperrtage, Vorlauf in Kalendertagen, täglicher Bestellschluss.
- Abholzeitfenster global, je Produkt und Wochentag; gemeinsame Zeiträume im Checkout und Ausschluss bereits begonnener Fenster.
- Kalender mit gesperrten Tagen und zugänglicher alternativer Terminliste.
- Schnittmenge der Regeln aller Artikel mit Checkout-Auswahl; verständliche Fehlermeldung bei fehlendem gemeinsamen Termin.
- Verschiedene Termine desselben Produkts werden getrennte Warenkorbpositionen.
- Anzeige in Warenkorb, Bestellung, Bestellverwaltung und E-Mails; Datenspeicherung über WooCommerce CRUD für HPOS.
- Einfache und variable Produkte; Varianten erben die Regeln des Hauptprodukts.
- Zahlungsart „Vor Ort zahlen“ im klassischen Checkout und Checkout Block, nur bei Abholung.
- Abholmanager mit offenen Abholungen, Umplanung je Position und geschützter Kundenbestätigung.
- WooCommerce-Bestellstatus „Bereit zur Abholung“ und „Abgeholt“ mit eigenen Kunden-E-Mails.
- Abholorte aus der WooCommerce-Versandauswahl inklusive historischer Adresse in Verwaltung und E-Mails.

## Einrichtung

1. WordPress ab 6.9.5, PHP ab 8.2, WooCommerce ab 10.9.4 installieren.
2. Installierbares Plugin-ZIP unter **Plugins → Installieren → Plugin hochladen** hochladen. GitHub-Quellcode-ZIPs sind keine Release-Pakete.
3. Unter **WooCommerce → Abholtermine** Buchungshorizont, globale Sperrtage und optionale Zeitfenster einstellen.
4. Produkt bearbeiten: **Produktdaten → Abholtermine**. Auswahlort und Regeln festlegen, Produkt speichern.
5. Abholorte unter **WooCommerce → Einstellungen → Versand → Abholung vor Ort** konfigurieren. Im klassischen Checkout wird die Versandart „Abholung vor Ort“ aus den Versandzonen unterstützt.
6. Bei Bedarf **WooCommerce → Einstellungen → Zahlungen → Vor Ort zahlen** aktivieren.
7. Abholungen unter **WooCommerce → Abholmanager** bearbeiten; E-Mails unter **WooCommerce → Einstellungen → E-Mails** konfigurieren.

Im Checkout Block wird das Feld beim Rendern automatisch ergänzt, falls es fehlt. Die gespeicherte Kassenseite wird dabei nicht verändert. Im Blockeditor lässt sich der Block **Abholdatum** innerhalb der Checkout-Felder auch manuell platzieren.

## Verbindliche Terminregeln

- **Wochentage**: nur angekreuzte Wochentage. Keine Auswahl bedeutet keine wiederkehrenden Termine.
- **Einzeltermine**: ausschließlich die eingetragenen Kalenderdaten.
- **Kombiniert**: Wochentage **oder** Einzeltermine. Einzeltermine können zusätzliche Tage öffnen.
- Sperrtage, Verfügbar-ab/bis, Vorlauf und Buchungshorizont begrenzen alle drei Modi.
- Vorlauf zählt Kalendertage, keine Öffnungstage und keine 24-Stunden-Intervalle. Ab Bestellschluss wird ein weiterer Kalendertag addiert. Die Uhrzeit folgt der WordPress-Zeitzone.
- Am Produkt gewählte Termine gelten je Position. Der Checkout-Termin gilt nur für Positionen mit Checkout-Auswahl; diese müssen einen gemeinsamen Termin haben.
- Die Terminwahl gilt für entsprechend konfigurierte Produkte unabhängig von der gewählten Versandart. Das Plugin erzwingt selbst keine Abhol-Versandart.
- Änderungen an Regeln werden beim nächsten Checkout berücksichtigt. Historische Bestellungen behalten ihren gespeicherten Termin.

Beispiel: Produkt A ist mittwochs/freitags verfügbar, Produkt B nur am 13.11.2026. Bei Checkout-Auswahl wird der 13.11.2026 angeboten, sofern Vorlauf und Sperrtage ihn zulassen. Haben beide Artikel keine Überschneidung, muss die Artikelauswahl geändert oder getrennt bestellt werden.

## Grenzen der ersten Version

Keine Kapazitätsbuchung, standortspezifischen Terminregeln, Zuschläge, Erinnerungen, automatische Feiertagsberechnung oder eigene Versandmethode. Keine separaten Regeln pro Variante. Der neue Produkteditor, Product-Add-to-Cart-Blocks und Express-/Direktkauf-Integrationen erhalten in dieser Version keine eigene Produktauswahl; fehlende Termine werden serverseitig abgewiesen. Dafür die klassische Produktseite verwenden. Ein Kalenderdatum ist noch keine Kapazitätsreservierung.

## Entwicklung und Releases

- `php tests/test-rules.php`: reine Terminregeln ohne WordPress.
- `wp eval-file wp-content/plugins/patsch9-orderdate/tests/integration.php`: isolierte Testinstallation mit WooCommerce; erstellt und entfernt eigene Testdaten.
- `node --test tests/*.test.cjs`: Kalender-/Block-Verhalten.
- `python3 tools/build-release.py 2026.11.1-beta3`: ZIP mit festem Ordner `patsch9-orderdate` und SHA-256-Prüfsumme.
- Pull Requests prüfen PHP 8.2/8.3/8.4, JavaScript, Integration und das gebaute Paket mit WordPress Plugin Check. Kein identischer zweiter Lauf nach dem Merge auf main.
- Die Action baut und prüft das installierbare Paket. Vorschau-ZIPs werden nur bei manuellem Workflowstart mit `upload-preview` hochgeladen. Produktseite sowie beide Checkout-Varianten werden im echten Browser mit Storefront geprüft.
- Tag-Releases prüfen Herkunft aus main, synchronisieren Paketversionen und veröffentlichen ZIP + Prüfsumme. Kein WordPress.org-Deployment eingerichtet.

Vor produktivem Einsatz müssen außerdem Theme, Zahlungsarten und die eingesetzte WooCommerce-Version in einer Staging-Installation durchgespielt werden. Automatisierte Tests ersetzen diesen Shop-Abgleich nicht.

Siehe [RESEARCH.md](RESEARCH.md), [DOCUMENTATION.md](DOCUMENTATION.md), [VERSIONING.md](VERSIONING.md) und [SECURITY.md](SECURITY.md).

## Datenschutz und Lizenz

Keine externen Dienste, Telemetrie oder CDN-Ressourcen. Abholdaten sind Bestandteil der WooCommerce-Bestellungen und folgen deren Aufbewahrung und Datenschutzabläufen. Bei Deinstallation bleiben Produktregeln und Bestelldaten erhalten, damit eine Neuinstallation und historische Nachweise möglich bleiben.

GPL-2.0-or-later; siehe [LICENSE](LICENSE). WooCommerce® ist eine Marke von Automattic Inc. Unabhängige Drittanbieter-Erweiterung, ohne Verbindung zu oder Unterstützung durch Automattic.

## Abholzeitfenster

Unter **WooCommerce → Abholtermine** lassen sich globale Fenster wie `13:00–14:00` und `14:00–15:00` anlegen. Der Assistent verwendet Wochentag und Uhrzeitfelder. Die Liste darunter bleibt zum Bearbeiten und Löschen zugänglich. Eine Zeile `*:13:00-14:00` gilt an allen verfügbaren Tagen; `7:12:00-13:00` ersetzt diese Standardfenster am Sonntag. 1=Montag bis 7=Sonntag.

Je Produkt stehen **globale Zeitfenster**, **eigene Zeitfenster** und **nur Datum** zur Wahl. Bestehende Produkte übernehmen globale Fenster, sobald sie eingerichtet werden. Bei leerer globaler Liste bleibt die Datumsauswahl erhalten; eine leere eigene Liste schließt die Abholung für dieses Produkt.

Im Checkout werden überlappende Zeiträume ermittelt: `13:00–15:00` und `14:00–16:00` ergeben `14:00–15:00`. Tage ohne gemeinsames Fenster werden nicht angeboten. Die Uhrzeiten verwenden die WordPress-Zeitzone. Für heute sind nur Fenster mit einem noch bevorstehenden Beginn buchbar. Fenster müssen am gleichen Kalendertag enden und dürfen sich innerhalb desselben Tagesplans nicht überschneiden. Eine Auswahl reserviert keine mengenbegrenzte Kapazität.

Datum und Zeitfenster werden je Position gespeichert und in Bestellungen sowie E-Mails angezeigt. Verschiedene Fenster desselben Produkts ergeben getrennte Warenkorbpositionen. Änderungen am Zeitplan können ältere Warenkörbe ungültig machen; historische Bestelltermine bleiben erhalten.


## Abholmanager und Zahlung vor Ort

Die Übersicht enthält offene Bestellungen mit gespeicherten Abholterminen oder WooCommerce-Abholversand. Auch bereits als „Abgeschlossen“ markierte Bestellungen bleiben darin, bis sie „Abgeholt“ sind. Stornierte, erstattete, fehlgeschlagene Bestellungen und Checkout-Entwürfe erscheinen nicht. Ältere Bestellungen werden nach dem Update in Hintergrundbatches über WP-Cron eingelesen; bei deaktiviertem WP-Cron muss ein externer Cron eingerichtet sein.

„Bereit zur Abholung“ sendet die Bereitstellungs-E-Mail. „Abgeholt“ sendet die Abholbestätigung. Bei Zahlung vor Ort muss im Abholmanager der tatsächliche Zahlungseingang bestätigt werden. Die Bestellung bleibt bis dahin unbezahlt; Bestand wird nur einmal reduziert. Andere Zahlungsarten werden über ihren jeweiligen Zahlungsanbieter bzw. WooCommerce als bezahlt erfasst.

Bei **Abholung umplanen** eine Position und einen der verfügbaren Termine wählen. Der Kunde erhält eine E-Mail mit einem sieben Tage gültigen Bestätigungslink. Das Öffnen des Links ändert noch nichts; erst die Schaltfläche bestätigt. Bis dahin gilt der bisherige Termin. Ein erneuter Versand ersetzt den alten Link. Zurückziehen erhält den bisherigen Termin. Nach Kundenbestätigung erhalten Kunde und Shop eine Bestätigung; Änderungen und Versandfehler werden in den Bestellnotizen festgehalten. Je Bestellung ist ein Vorschlag gleichzeitig offen. Erst nach Bestätigung oder Zurückziehen kann die nächste Position umgeplant werden.

Die neuen Status gelten für die gesamte Bestellung. Bei mehreren Terminen ist „Abgeholt“ erst nach der vollständigen Übergabe zu setzen. Es gibt keine separate Teilabholungsverwaltung. Umplanung nutzt die aktuellen Produktregeln; gelöschte oder deaktivierte Produkte müssen zuerst wieder mit gültigen Regeln versehen werden.

WooCommerce übernimmt die Ortsauswahl im Checkout. Orderdate verwendet den dort gespeicherten Namen und die Adresse; spätere Änderungen der Abholorte schreiben alte Bestellungen nicht um. Die klassische Abholversandart besitzt keine native Filialadressliste, hier wird die konfigurierte Versandbezeichnung angezeigt. Es gibt keine zweite Ortsauswahl und keine unterschiedlichen Terminregeln je Filiale.
