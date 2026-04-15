=== WPForms – Province e Comuni Italiani ===
Contributors: CreativeMetrics
Tags: wpforms, province, comuni, italia, dropdown
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
License: MIT

Popola automaticamente province e comuni italiani in WPForms con selezione condizionale via AJAX.

== Description ==

Aggiunge ai form WPForms due dropdown collegati:
* **Province** – tutte le 107 province italiane, ordinate alfabeticamente
* **Comuni** – caricati dinamicamente via AJAX in base alla provincia selezionata

I dati dei comuni provengono dal dataset ISTAT ufficiale (via matteocontrini/comuni-json su GitHub)
e vengono messi in cache nel database WordPress per 365 giorni.

== Installazione ==

1. Carica la cartella `wpforms-province-comuni` in `wp-content/plugins/`
2. Attiva il plugin dalla dashboard WordPress
3. Modifica le costanti in cima al file principale:
   - `WPFPC_FORM_ID` — ID del tuo form WPForms
   - `WPFPC_FIELD_PROV` — Field ID del campo Province
   - `WPFPC_FIELD_COM` — Field ID del campo Comuni

== Changelog ==

= 1.0.5 =
* Fix: campo comune ora appare correttamente nella mail di riepilogo
* Fix: rimosso placeholder doppio nel dropdown province
* Fix: il campo comuni è nascosto fino alla selezione della provincia

= 1.0.4 =
* Aggiunto sistema di aggiornamento automatico da GitHub Releases
* Refactoring completo in struttura plugin standard

= 1.0.3 =
* Fonte dati comuni migrata a dataset ISTAT ufficiale via GitHub
* Aggiunta cache transient 365 giorni per i comuni

= 1.0.2 =
* Fix: chiavi array intere per compatibilità WPForms
* Fix: hook corretto wpforms_frontend_form_data

= 1.0.1 =
* Prima versione funzionante come mu-plugin
