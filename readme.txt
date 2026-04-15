=== WPForms – Province e Comuni Italiani ===
Contributors: CreativeMetrics
Tags: wpforms, province, comuni, italia, dropdown, cap
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
License: MIT

Popola automaticamente province e comuni italiani in WPForms con ricerca live, CAP automatico e validazione server.

== Description ==

Aggiunge ai form WPForms dropdown collegati con:
* **Province** — tutte le 107 province italiane, ordinate alfabeticamente
* **Comuni** — caricati dinamicamente via AJAX per provincia selezionata
* **Ricerca live** — campo di ricerca che filtra i comuni in tempo reale
* **CAP automatico** — popolato automaticamente alla selezione del comune
* **Validazione server** — verifica che il comune esista nella provincia indicata
* **Multi-form** — configura quanti form vuoi da WPForms → Province & Comuni
* **Aggiornamenti automatici** da GitHub Releases

== Installazione ==

1. Carica la cartella `wpforms-province-comuni` in `wp-content/plugins/`
2. Attiva il plugin dalla dashboard WordPress
3. Vai su **WPForms → Province & Comuni** e configura i tuoi form

== Changelog ==

= 1.2.0 =
* Aggiunta ricerca live nel dropdown comuni (attiva per province con più di 15 comuni)
* Aggiunto CAP automatico — si popola alla selezione del comune
* Aggiunta validazione lato server — verifica che il comune esista nella provincia
* Aggiunta colonna Field ID CAP nel pannello di configurazione
* Nuova cache v3 con struttura { nome, cap } per ogni comune

= 1.1.0 =
* Aggiunto pannello di configurazione sotto WPForms → Province & Comuni
* Supporto multi-form senza toccare il codice

= 1.0.5 =
* Fix: campo comune appare nella mail di riepilogo
* Fix: rimosso placeholder doppio nel dropdown province
* Fix: campo comuni nascosto fino alla selezione della provincia
