# Funktionsrecherche – 02.11.2026

Herstellerdokumentation wurde zur Einordnung gelesen. Es wurde kein Quellcode fremder Plugins übernommen. Funktionsumfang kann je Edition und Version variieren.

| Vorlage | Dokumentierte Funktionen | Ableitung für Orderdate |
| --- | --- | --- |
| Tyche Product Delivery Date Pro | Terminauswahl auf Produktseiten, wiederkehrende Wochentage, Pflichtauswahl, Vorlauf | Produktmodus, Pflichtdatum und Wochentage |
| Tyche Order Delivery Date Pro | Wochentage und konkrete Daten, Zeitfenster, Bestellschluss, Tages-/Slot-Limits, Termine in E-Mails/Bestellungen | Datumsmodi, Bestellschluss, Anzeige; Slots/Kapazität später |
| WooCommerce Local Pickup Plus | Abholorte, Termine, Öffnungszeiten, Feiertage, Vorlauf, maximaler Buchungszeitraum, Terminlimits | Globale Sperrtage und Buchungshorizont; Standortverwaltung später |
| Order Delivery for WooCommerce | Checkout-Datum, Bearbeitungstage und Vorlauf, Kalendersperren | Checkout-Auswahl mit gemeinsam erfüllbaren Produktregeln |

Quellen:

- https://www.tychesoftwares.com/docs/woocommerce-product-delivery-date-pro/mandatory-selection-of-delivery-date-time-on-the-product-page/
- https://woocommerce.com/document/common-delivery-schedule-settings/
- https://woocommerce.com/document/order-delivery-date-settings/
- https://www.tychesoftwares.com/docs/woocommerce-order-delivery-date-pro-new/delivery-schedule/set-up-time-slots-for-different-weekdays-and-specific-dates/
- https://woocommerce.com/document/local-pickup-plus/
- https://woocommerce.com/document/woocommerce-order-delivery/
- https://kestrelwp.com/docs/how-to-configure-picking-days-and-lead-time-in-order-delivery-for-woocommerce/

Die Local-Pickup-Plus-Dokumentation nennt weiterhin eine Einschränkung bei Cart/Checkout Blocks. Diese Kompatibilität wurde nicht aus dem Vorbild abgeleitet: Orderdate integriert seinen eigenen Checkout Block über die dokumentierte WooCommerce-Schnittstelle.

## Technische Grundlagen

- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- https://developer.wordpress.org/plugins/security/
- https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-custom-fields/
- https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-data/
- https://developer.woocommerce.com/docs/block-development/reference/data-store/validation/

Die Actions orientieren sich am gelesenen Stand der Accounting Bridge und Rental Engine: SHA-fixierte Actions, minimale Berechtigungen, PHP-Matrix, Plugin Check, ZIP mit festem Slug, Herkunftsprüfung des Tags und Kalender-Versionsschema. Es werden keine Daten-/Migrationsschritte dieser fachlich anderen Plugins übernommen.
