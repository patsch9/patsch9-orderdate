# Sicherheit

Sicherheitslücken bitte vertraulich über GitHubs Funktion **Report a vulnerability** melden, soweit für dieses Repository verfügbar. Ist sie nicht verfügbar, den Repository-Eigentümer zunächst ohne technische Angriffsdaten über einen privaten Kontaktweg erreichen. Keine Zugangsdaten, Kundendaten oder ausnutzbaren Details in öffentlichen Issues einstellen.

Bitte Version, betroffene Funktion, Voraussetzungen, reproduzierbare Schritte und Auswirkungen angeben. Nur aktuelle Releases erhalten Korrekturen.

Schutzmaßnahmen: Capability- und Nonce-Prüfung beim Ändern von Einstellungen, validierte ISO-Kalenderdaten, begrenzte Terminlisten, spätes Escaping und erneute serverseitige Verfügbarkeitsprüfung vor dem Bestellabschluss. Bestelldaten werden über WooCommerce CRUD geschrieben. Store-API-Mutationen verwenden WooCommerces Nonce-/Cart-Token-Schutz. Keine eigenen unauthentifizierten Schreibendpunkte.
