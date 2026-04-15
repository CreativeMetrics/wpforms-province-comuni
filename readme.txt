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

Configurabile da **WPForms → Province & Comuni** senza toccare il codice.
Supporta più form contemporaneamente.

== Installazione ==

1. Carica la cartella `wpforms-province-comuni` in `wp-content/plugins/`
2. Attiva il plugin dalla dashboard WordPress
3. Vai su **WPForms → Province & Comuni** e configura i tuoi form

== Changelog ==

= 1.1.0 =
* Aggiunto pannello di configurazione sotto WPForms → Province & Comuni
* Supporto multi-form: configura quanti form vuoi dalla UI
* Rimossi ID hardcoded dal codice

= 1.0.5 =
* Fix: campo comune appare nella mail di riepilogo
* Fix: rimosso placeholder doppio nel dropdown province
* Fix: campo comuni nascosto fino alla selezione della provincia

= 1.0.4 =
* Aggiunto sistema di aggiornamento automatico da GitHub Releases

= 1.0.3 =
* Fonte dati comuni migrata a dataset ISTAT ufficiale via GitHub
