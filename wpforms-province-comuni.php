<?php
/**
 * Plugin Name:       WPForms – Province e Comuni Italiani
 * Plugin URI:        https://github.com/CreativeMetrics/wpforms-province-comuni
 * Description:       Popola automaticamente province e comuni italiani in WPForms con combobox ricercabile, CAP automatico e validazione server.
 * Version:           1.2.5
 * Author:            CreativeMetrics
 * Author URI:        https://github.com/CreativeMetrics
 * License:           MIT
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WPFPC_VERSION',         '1.2.4' );
define( 'WPFPC_GITHUB_USER',     'CreativeMetrics' );
define( 'WPFPC_GITHUB_REPO',     'wpforms-province-comuni' );
define( 'WPFPC_COMUNI_JSON_URL', 'https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json' );
define( 'WPFPC_COMUNI_CACHE_KEY','wpfpc_tutti_comuni_v3' );

require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/admin.php';

add_action( 'init', function (): void {
    new WPFPC_GitHub_Updater( __FILE__, WPFPC_GITHUB_USER, WPFPC_GITHUB_REPO, WPFPC_VERSION );
} );

new WPFPC_Admin();

// ── Helper ────────────────────────────────────────────────────────────────────

function wpfpc_get_configs(): array {
    return WPFPC_Admin::get_configs();
}

function wpfpc_config_for_form( int $form_id ): ?array {
    foreach ( wpfpc_get_configs() as $cfg ) {
        if ( (int) $cfg['form_id'] === $form_id ) return $cfg;
    }
    return null;
}

// ── Dati ISTAT ────────────────────────────────────────────────────────────────

function wpfpc_get_tutti_comuni(): ?array {

    $cached = get_transient( WPFPC_COMUNI_CACHE_KEY );
    if ( $cached !== false ) return $cached;

    $response = wp_remote_get( WPFPC_COMUNI_JSON_URL, [
        'timeout'    => 20,
        'user-agent' => 'WordPress/' . get_bloginfo( 'version' ),
    ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return null;

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $data ) || empty( $data ) ) return null;

    $per_provincia = [];
    foreach ( $data as $comune ) {
        $nome  = trim( $comune['nome']  ?? '' );
        $sigla = strtoupper( trim( $comune['sigla'] ?? '' ) );
        if ( ! $nome || ! $sigla ) continue;
        $cap_raw = $comune['cap'] ?? [];
        $cap     = is_array( $cap_raw ) ? implode( ', ', $cap_raw ) : (string) $cap_raw;
        $per_provincia[ $sigla ][] = [ 'nome' => $nome, 'cap' => $cap ];
    }

    foreach ( $per_provincia as &$comuni ) {
        usort( $comuni, fn( $a, $b ) => strcmp( $a['nome'], $b['nome'] ) );
    }
    unset( $comuni );

    set_transient( WPFPC_COMUNI_CACHE_KEY, $per_provincia, 365 * DAY_IN_SECONDS );
    return $per_provincia;
}

// ── 1. Popola province ────────────────────────────────────────────────────────

add_filter( 'wpforms_frontend_form_data', 'wpfpc_popola_province' );

function wpfpc_popola_province( array $form_data ): array {

    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return $form_data;

    $province = [
        'AG' => 'Agrigento',         'AL' => 'Alessandria',       'AN' => 'Ancona',
        'AO' => 'Aosta',             'AR' => 'Arezzo',            'AP' => 'Ascoli Piceno',
        'AT' => 'Asti',              'AV' => 'Avellino',          'BA' => 'Bari',
        'BT' => 'Barletta-A.-Trani', 'BL' => 'Belluno',           'BN' => 'Benevento',
        'BG' => 'Bergamo',           'BI' => 'Biella',            'BO' => 'Bologna',
        'BZ' => 'Bolzano',           'BS' => 'Brescia',           'BR' => 'Brindisi',
        'CA' => 'Cagliari',          'CL' => 'Caltanissetta',     'CB' => 'Campobasso',
        'CE' => 'Caserta',           'CT' => 'Catania',           'CZ' => 'Catanzaro',
        'CH' => 'Chieti',            'CO' => 'Como',              'CS' => 'Cosenza',
        'CR' => 'Cremona',           'KR' => 'Crotone',           'CN' => 'Cuneo',
        'EN' => 'Enna',              'FM' => 'Fermo',             'FE' => 'Ferrara',
        'FI' => 'Firenze',           'FG' => 'Foggia',            'FC' => 'Forlì-Cesena',
        'FR' => 'Frosinone',         'GE' => 'Genova',            'GO' => 'Gorizia',
        'GR' => 'Grosseto',          'IM' => 'Imperia',           'IS' => 'Isernia',
        'SP' => 'La Spezia',         'AQ' => "L'Aquila",          'LT' => 'Latina',
        'LE' => 'Lecce',             'LC' => 'Lecco',             'LI' => 'Livorno',
        'LO' => 'Lodi',              'LU' => 'Lucca',             'MC' => 'Macerata',
        'MN' => 'Mantova',           'MS' => 'Massa-Carrara',     'MT' => 'Matera',
        'ME' => 'Messina',           'MI' => 'Milano',            'MO' => 'Modena',
        'MB' => 'Monza e Brianza',   'NA' => 'Napoli',            'NO' => 'Novara',
        'NU' => 'Nuoro',             'OR' => 'Oristano',          'PD' => 'Padova',
        'PA' => 'Palermo',           'PR' => 'Parma',             'PV' => 'Pavia',
        'PG' => 'Perugia',           'PU' => 'Pesaro e Urbino',   'PE' => 'Pescara',
        'PC' => 'Piacenza',          'PI' => 'Pisa',              'PT' => 'Pistoia',
        'PN' => 'Pordenone',         'PZ' => 'Potenza',           'PO' => 'Prato',
        'RG' => 'Ragusa',            'RA' => 'Ravenna',           'RC' => 'Reggio Calabria',
        'RE' => 'Reggio Emilia',     'RI' => 'Rieti',             'RN' => 'Rimini',
        'RM' => 'Roma',              'RO' => 'Rovigo',            'SA' => 'Salerno',
        'SS' => 'Sassari',           'SV' => 'Savona',            'SI' => 'Siena',
        'SR' => 'Siracusa',          'SO' => 'Sondrio',           'TA' => 'Taranto',
        'TE' => 'Teramo',            'TR' => 'Terni',             'TO' => 'Torino',
        'TP' => 'Trapani',           'TN' => 'Trento',            'TV' => 'Treviso',
        'TS' => 'Trieste',           'UD' => 'Udine',             'VA' => 'Varese',
        'VE' => 'Venezia',           'VB' => 'Verbano-C.-Ossola', 'VC' => 'Vercelli',
        'VR' => 'Verona',            'VV' => 'Vibo Valentia',     'VI' => 'Vicenza',
        'VT' => 'Viterbo',
    ];

    asort( $province );

    $choices = [];
    $idx     = 1;
    $choices[ $idx++ ] = [ 'label' => '— Seleziona provincia —', 'value' => '', 'default' => '1' ];
    foreach ( $province as $sigla => $nome ) {
        $choices[ $idx++ ] = [ 'label' => $nome . ' (' . $sigla . ')', 'value' => $sigla, 'default' => '' ];
    }

    $fp = (int) $cfg['field_prov'];
    $fc = (int) $cfg['field_com'];

    $form_data['fields'][ $fp ]['placeholder']   = '';
    $form_data['fields'][ $fp ]['show_values']   = '1';
    $form_data['fields'][ $fp ]['choices']       = $choices;
    $form_data['fields'][ $fp ]['default_value'] = '';
    $form_data['fields'][ $fc ]['conditional_logic'] = '0';
    $form_data['fields'][ $fc ]['conditionals']      = [];

    return $form_data;
}

// ── 2. AJAX comuni ────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpfpc_get_comuni',        'wpfpc_get_comuni' );
add_action( 'wp_ajax_nopriv_wpfpc_get_comuni', 'wpfpc_get_comuni' );

function wpfpc_get_comuni(): void {
    check_ajax_referer( 'wpfpc_nonce', 'nonce' );

    $provincia = isset( $_GET['provincia'] )
        ? strtoupper( sanitize_text_field( $_GET['provincia'] ) ) : '';

    if ( empty( $provincia ) ) wp_send_json_error( 'Provincia mancante' );

    $tutti = wpfpc_get_tutti_comuni();
    if ( null === $tutti )              wp_send_json_error( 'Impossibile scaricare i dati.' );
    if ( empty( $tutti[ $provincia ] ) ) wp_send_json_error( 'Nessun comune per: ' . $provincia );

    wp_send_json_success( $tutti[ $provincia ] );
}

// ── 3. Validazione server ─────────────────────────────────────────────────────

add_action( 'wpforms_process', 'wpfpc_validate_comune_server', 10, 3 );

function wpfpc_validate_comune_server( array $fields, array $entry, array $form_data ): void {
    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return;

    $fp        = (int) $cfg['field_prov'];
    $fc        = (int) $cfg['field_com'];
    $provincia = strtoupper( sanitize_text_field( $entry['fields'][ $fp ] ?? '' ) );
    $comune    = sanitize_text_field( $entry['fields'][ $fc ] ?? '' );

    if ( empty( $provincia ) || empty( $comune ) ) return;

    $tutti = wpfpc_get_tutti_comuni();
    if ( null === $tutti ) return;

    $validi = array_column( $tutti[ $provincia ] ?? [], 'nome' );
    if ( ! in_array( $comune, $validi, true ) ) {
        wpforms()->get( 'process' )->errors[ $form_data['id'] ][ $fc ] =
            'Il comune selezionato non è valido per la provincia indicata.';
    }
}

// ── 4. Inietta comune nella mail ──────────────────────────────────────────────

add_filter( 'wpforms_process_filter', 'wpfpc_inject_comune_value', 10, 3 );

function wpfpc_inject_comune_value( array $fields, array $entry, array $form_data ): array {
    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return $fields;

    $fc     = (int) $cfg['field_com'];
    $comune = sanitize_text_field( $entry['fields'][ $fc ] ?? '' );

    if ( ! empty( $comune ) && isset( $fields[ $fc ] ) ) {
        $fields[ $fc ]['value'] = $comune;
    }
    return $fields;
}

// ── 5. Frontend JS ────────────────────────────────────────────────────────────

add_action( 'wp_footer', 'wpfpc_inline_script' );

function wpfpc_inline_script(): void {
    $configs = wpfpc_get_configs();
    if ( empty( $configs ) ) return;

    $ajax_url   = esc_url( admin_url( 'admin-ajax.php' ) );
    $nonce      = wp_create_nonce( 'wpfpc_nonce' );
    $js_configs = array_values( array_map( fn( $c ) => [
        'formId'    => (int) $c['form_id'],
        'fieldProv' => (int) $c['field_prov'],
        'fieldCom'  => (int) $c['field_com'],
        'fieldCap'  => (int) ( $c['field_cap'] ?? 0 ),
    ], $configs ) );
    ?>
    <style>
    .wpfpc-combo { position: relative; }
    .wpfpc-combo-input {
        width: 100%; padding: 8px 32px 8px 10px; border: 1px solid #8c8f94;
        border-radius: 4px; font-size: 14px; box-sizing: border-box; background: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23555' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 10px center; cursor: pointer;
    }
    .wpfpc-combo-input:focus { outline: 2px solid #2271b1; border-color: #2271b1; cursor: text; }
    .wpfpc-combo-input:disabled { opacity: .5; cursor: default; }
    .wpfpc-combo-dropdown {
        display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 99999;
        background: #fff; border: 1px solid #8c8f94; border-top: none;
        border-radius: 0 0 4px 4px; max-height: 220px; overflow-y: auto;
        box-shadow: 0 4px 10px rgba(0,0,0,.15);
    }
    .wpfpc-combo-dropdown.wpfpc-open { display: block; }
    .wpfpc-combo-option { padding: 8px 12px; cursor: pointer; font-size: 14px; }
    .wpfpc-combo-option:hover { background: #f0f5fb; }
    .wpfpc-combo-option.wpfpc-active { font-weight: 600; color: #2271b1; background: #f0f5fb; }
    .wpfpc-combo-empty { padding: 10px 12px; color: #888; font-size: 13px; font-style: italic; }
    </style>

    <script>
    (function ($) {
        'use strict';

        var AJAX_URL = '<?php echo $ajax_url; ?>';
        var NONCE    = '<?php echo $nonce; ?>';
        var CONFIGS  = <?php echo wp_json_encode( $js_configs ); ?>;

        function initForm(cfg) {

            // ── Selettori ─────────────────────────────────────────────────
            var $prov     = $('#wpforms-' + cfg.formId + '-field-' + cfg.fieldProv);
            var $comWrap  = $('.wpforms-field[data-field-id="' + cfg.fieldCom + '"]');
            var $capWrap  = cfg.fieldCap ? $('.wpforms-field[data-field-id="' + cfg.fieldCap + '"]') : null;
            var $comNative = $('select[name="wpforms[fields][' + cfg.fieldCom + ']"]');
            var $capInput  = cfg.fieldCap ? $('[name="wpforms[fields][' + cfg.fieldCap + ']"]') : null;

            // Il form non è in questa pagina
            if ( !$prov.length || !$comWrap.length ) return;

            // ── Stato ──────────────────────────────────────────────────────
            var allComuni  = [];   // [{ nome, cap }] — tutti i comuni della provincia
            var selected   = null; // { nome, cap } — comune selezionato

            // ── Costruzione combobox ───────────────────────────────────────
            // Nasconde il <select> nativo (gestisce la submission) e inserisce
            // dopo di esso un input + dropdown personalizzato.
            $comNative.hide();

            var $wrap  = $('<div class="wpfpc-combo">').insertAfter( $comNative );
            var $input = $('<input>', {
                type: 'text', class: 'wpfpc-combo-input',
                placeholder: '— Seleziona comune —', autocomplete: 'off'
            }).appendTo( $wrap );
            var $drop = $('<div class="wpfpc-combo-dropdown">').appendTo( $wrap );

            // ── Funzioni dropdown ─────────────────────────────────────────

            function renderDropdown(query) {
                $drop.empty();
                var q = (query || '').toLowerCase().trim();
                var lista = q
                    ? allComuni.filter(function(c) { return c.nome.toLowerCase().indexOf(q) !== -1; })
                    : allComuni;

                if (!lista.length) {
                    $drop.append('<div class="wpfpc-combo-empty">Nessun risultato</div>');
                    return;
                }

                $.each(lista, function(i, item) {
                    var isSelected = selected && selected.nome === item.nome;
                    $('<div>', {
                        class: 'wpfpc-combo-option' + (isSelected ? ' wpfpc-active' : ''),
                        text: item.nome
                    })
                    .data('item', item)
                    .on('mousedown', function(e) {
                        e.preventDefault(); // evita blur sull'input prima della selezione
                        scegli( $(this).data('item') );
                    })
                    .appendTo($drop);
                });
            }

            function apri() {
                renderDropdown( $input.val() );
                $drop.addClass('wpfpc-open');
            }

            function chiudi() {
                $drop.removeClass('wpfpc-open');
                // Ripristina il testo se l'utente ha digitato senza scegliere
                $input.val( selected ? selected.nome : '' );
            }

            function scegli(item) {
                selected = item;
                $input.val(item.nome);

                // Aggiorna il <select> nativo per la submission del form
                if ( !$comNative.find('option[value="' + item.nome.replace(/"/g,'&quot;') + '"]').length ) {
                    $comNative.append( $('<option>', { value: item.nome, text: item.nome }) );
                }
                $comNative.val(item.nome);

                // CAP automatico
                if ($capInput) $capInput.val(item.cap || '');

                chiudi();
            }

            function resetCombo(placeholder, disabilitato) {
                selected   = null;
                allComuni  = [];
                $comNative.val('').find('option:not(:first)').remove();
                $input.val('').prop({ placeholder: placeholder, disabled: !!disabilitato });
                $drop.removeClass('wpfpc-open').empty();
                if ($capInput) $capInput.val('');
            }

            // ── Visibilità ────────────────────────────────────────────────

            function nascondi() {
                $comWrap.hide();
                if ($capWrap) $capWrap.hide();
                resetCombo('— Seleziona prima una provincia —', false);
            }

            function mostra() {
                $comWrap.show();
            }

            // ── AJAX ──────────────────────────────────────────────────────

            function caricaComuni(provincia) {
                resetCombo('⏳ Caricamento...', true);

                $.ajax({
                    url: AJAX_URL, method: 'GET', dataType: 'json',
                    data: { action: 'wpfpc_get_comuni', nonce: NONCE, provincia: provincia },
                    success: function(resp) {
                        $input.prop('disabled', false);
                        if (!resp.success) {
                            resetCombo('⚠️ Errore caricamento', false);
                            return;
                        }
                        allComuni = resp.data; // [{ nome, cap }]
                        $input.prop('placeholder', '— Seleziona o cerca comune —');
                    },
                    error: function(xhr) {
                        $input.prop('disabled', false);
                        resetCombo('⚠️ Errore (' + xhr.status + ')', false);
                    }
                });
            }

            // ── Eventi input/dropdown ─────────────────────────────────────

            $input.on('focus click', function() {
                if (allComuni.length) apri();
            });

            $input.on('input', function() {
                if (allComuni.length) apri();
            });

            $input.on('keydown', function(e) {
                // Blocca sempre invio dentro il campo ricerca
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $vis = $drop.find('.wpfpc-combo-option:visible');
                    if ($vis.length === 1) scegli( $vis.data('item') );
                    return false;
                }
                if (e.key === 'Escape') chiudi();
            });

            $input.on('blur', function() {
                setTimeout(chiudi, 200);
            });

            $(document).on('click', function(e) {
                if (!$wrap.is(e.target) && !$wrap.has(e.target).length) chiudi();
            });

            // ── Evento cambio provincia ───────────────────────────────────

            $prov.on('change', function() {
                var val = $(this).val();
                if (val) { mostra(); caricaComuni(val); }
                else     { nascondi(); }
            });

            // ── Init: aspetta che WPForms finisca di inizializzare ────────
            // WPForms esegue il suo JS dopo il nostro (footer), quindi
            // usiamo un timeout per essere sicuri di intervenire per ultimi.
            setTimeout(function() {
                $prov[0].selectedIndex = 0;
                nascondi();
            }, 50);
        }

        $(document).ready(function() {
            $.each(CONFIGS, function(i, cfg) { initForm(cfg); });
        });

    }(jQuery));
    </script>
    <?php
}
