# Patsch9 Orderdate for WooCommerce

Abholtermine je Produkt: Auswahl auf der Produktseite oder als gemeinsamer Termin im klassischen WooCommerce-Checkout und im Checkout Block. Entwicklungsversion **2026.11.1-beta1**.

## Funktionsumfang

- Pro Produkt deaktivierbar oder Auswahl am Produkt / an der Kasse.
- Verfügbar ab/bis, wiederkehrende Wochentage, konkrete Abholdaten oder beides.
- Globale und produktspezifische Sperrtage, Vorlauf in Kalendertagen, täglicher Bestellschluss.
- Kalender mit gesperrten Tagen und zugänglicher alternativer Terminliste.
- Schnittmenge der Regeln aller Artikel mit Checkout-Auswahl; verständliche Fehlermeldung bei fehlendem gemeinsamen Termin.
- Verschiedene Termine desselben Produkts werden getrennte Warenkorbpositionen.
- Anzeige in Warenkorb, Bestellung, Bestellverwaltung und E-Mails; Datenspeicherung über WooCommerce CRUD für HPOS.
- Einfache und variable Produkte; Varianten erben die Regeln des Hauptprodukts.

## Einrichtung

1. WordPress ab 6.9.5, PHP ab 8.2, WooCommerce ab 10.9.4 installieren.
2. Installierbares Plugin-ZIP unter **Plugins → Installieren → Plugin hochladen** hochladen. GitHub-Quellcode-ZIPs sind keine Release-Pakete.
3. Unter **WooCommerce → Abholtermine** Buchungshorizont und globale Sperrtage einstellen.
4. Produkt bearbeiten: **Produktdaten → Abholtermine**. Auswahlort und Regeln festlegen, Produkt speichern.
5. Abholung als Versandart bei Bedarf separat in WooCommerce konfigurieren.

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

Keine Uhrzeitfenster, Kapazitätsbuchung, Filialen, Zuschläge, Erinnerungen, automatische Feiertagsberechnung oder eigene Versandmethode. Keine separaten Regeln pro Variante. Der neue Produkteditor, Product-Add-to-Cart-Blocks und Express-/Direktkauf-Integrationen erhalten in dieser Version keine eigene Produktauswahl; fehlende Termine werden serverseitig abgewiesen. Dafür die klassische Produktseite verwenden. Ein Kalenderdatum ist noch keine Kapazitätsreservierung.

## Entwicklung und Releases

- `php tests/test-rules.php`: reine Terminregeln ohne WordPress.
- `wp eval-file wp-content/plugins/patsch9-orderdate/tests/integration.php`: isolierte Testinstallation mit WooCommerce; erstellt und entfernt eigene Testdaten.
- `node --test tests/*.test.cjs`: Kalender-/Block-Verhalten.
- `python3 tools/build-release.py 2026.11.1-beta1`: ZIP mit festem Ordner `patsch9-orderdate` und SHA-256-Prüfsumme.
- Pull Requests prüfen PHP 8.2/8.3/8.4, JavaScript, Integration und das gebaute Paket mit WordPress Plugin Check. Kein identischer zweiter Lauf nach dem Merge auf main.
- Tag-Releases prüfen Herkunft aus main, synchronisieren Paketversionen und veröffentlichen ZIP + Prüfsumme. Kein WordPress.org-Deployment eingerichtet.

Vor produktivem Einsatz müssen außerdem Theme, Zahlungsarten und die eingesetzte WooCommerce-Version in einer Staging-Installation durchgespielt werden. Automatisierte Tests ersetzen diesen Shop-Abgleich nicht.

Siehe [RESEARCH.md](RESEARCH.md), [DOCUMENTATION.md](DOCUMENTATION.md), [VERSIONING.md](VERSIONING.md) und [SECURITY.md](SECURITY.md).

## Datenschutz und Lizenz

Keine externen Dienste, Telemetrie oder CDN-Ressourcen. Abholdaten sind Bestandteil der WooCommerce-Bestellungen und folgen deren Aufbewahrung und Datenschutzabläufen. Bei Deinstallation bleiben Produktregeln und Bestelldaten erhalten, damit eine Neuinstallation und historische Nachweise möglich bleiben.

GPL-2.0-or-later; siehe [LICENSE](LICENSE). WooCommerce® ist eine Marke von Automattic Inc. Unabhängige Drittanbieter-Erweiterung, ohne Verbindung zu oder Unterstützung durch Automattic.
