<?php
/**
 * Plugin Name:       WPForms – Province e Comuni Italiani
 * Plugin URI:        https://github.com/CreativeMetrics/wpforms-province-comuni
 * Description:       Popola automaticamente province e comuni italiani in WPForms con combobox ricercabile, CAP automatico e validazione server.
 * Version:           1.2.3.1
 * Author:            CreativeMetrics
 * Author URI:        https://github.com/CreativeMetrics
 * License:           MIT
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

// ─── COSTANTI ────────────────────────────────────────────────────────────────
define( 'WPFPC_VERSION',       '1.2.3' );
define( 'WPFPC_GITHUB_USER',   'CreativeMetrics' );
define( 'WPFPC_GITHUB_REPO',   'wpforms-province-comuni' );
define( 'WPFPC_COMUNI_JSON_URL',
    'https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json'
);
define( 'WPFPC_COMUNI_CACHE_KEY', 'wpfpc_tutti_comuni_v3' );
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/admin.php';

add_action( 'init', function (): void {
    new WPFPC_GitHub_Updater( __FILE__, WPFPC_GITHUB_USER, WPFPC_GITHUB_REPO, WPFPC_VERSION );
} );

new WPFPC_Admin();


// ─── HELPER ──────────────────────────────────────────────────────────────────

function wpfpc_get_configs(): array {
    return WPFPC_Admin::get_configs();
}

function wpfpc_config_for_form( int $form_id ): ?array {
    foreach ( wpfpc_get_configs() as $cfg ) {
        if ( (int) $cfg['form_id'] === $form_id ) return $cfg;
    }
    return null;
}


// ═══════════════════════════════════════════════════════════════════════════
// DATI ISTAT — dizionario sigla → [ { nome, cap } ]
// ═══════════════════════════════════════════════════════════════════════════

function wpfpc_get_tutti_comuni(): ?array {

    $cached = get_transient( WPFPC_COMUNI_CACHE_KEY );
    if ( $cached !== false ) return $cached;

    $response = wp_remote_get( WPFPC_COMUNI_JSON_URL, [
        'timeout'    => 20,
        'user-agent' => 'WordPress/' . get_bloginfo( 'version' ),
    ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return null;
    }

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


// ═══════════════════════════════════════════════════════════════════════════
// 1. POPOLA LE PROVINCE
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'wpforms_frontend_form_data', 'wpfpc_popola_province' );

function wpfpc_popola_province( array $form_data ): array {

    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return $form_data;

    $field_prov = (int) $cfg['field_prov'];
    $field_com  = (int) $cfg['field_com'];

    $province = [
        'AG' => 'Agrigento',           'AL' => 'Alessandria',         'AN' => 'Ancona',
        'AO' => 'Aosta',               'AR' => 'Arezzo',              'AP' => 'Ascoli Piceno',
        'AT' => 'Asti',                'AV' => 'Avellino',            'BA' => 'Bari',
        'BT' => 'Barletta-A.-Trani',   'BL' => 'Belluno',             'BN' => 'Benevento',
        'BG' => 'Bergamo',             'BI' => 'Biella',              'BO' => 'Bologna',
        'BZ' => 'Bolzano',             'BS' => 'Brescia',             'BR' => 'Brindisi',
        'CA' => 'Cagliari',            'CL' => 'Caltanissetta',       'CB' => 'Campobasso',
        'CE' => 'Caserta',             'CT' => 'Catania',             'CZ' => 'Catanzaro',
        'CH' => 'Chieti',              'CO' => 'Como',                'CS' => 'Cosenza',
        'CR' => 'Cremona',             'KR' => 'Crotone',             'CN' => 'Cuneo',
        'EN' => 'Enna',                'FM' => 'Fermo',               'FE' => 'Ferrara',
        'FI' => 'Firenze',             'FG' => 'Foggia',              'FC' => 'Forlì-Cesena',
        'FR' => 'Frosinone',           'GE' => 'Genova',              'GO' => 'Gorizia',
        'GR' => 'Grosseto',            'IM' => 'Imperia',             'IS' => 'Isernia',
        'SP' => 'La Spezia',           'AQ' => "L'Aquila",            'LT' => 'Latina',
        'LE' => 'Lecce',               'LC' => 'Lecco',               'LI' => 'Livorno',
        'LO' => 'Lodi',                'LU' => 'Lucca',               'MC' => 'Macerata',
        'MN' => 'Mantova',             'MS' => 'Massa-Carrara',       'MT' => 'Matera',
        'ME' => 'Messina',             'MI' => 'Milano',              'MO' => 'Modena',
        'MB' => 'Monza e Brianza',     'NA' => 'Napoli',              'NO' => 'Novara',
        'NU' => 'Nuoro',               'OR' => 'Oristano',            'PD' => 'Padova',
        'PA' => 'Palermo',             'PR' => 'Parma',               'PV' => 'Pavia',
        'PG' => 'Perugia',             'PU' => 'Pesaro e Urbino',     'PE' => 'Pescara',
        'PC' => 'Piacenza',            'PI' => 'Pisa',                'PT' => 'Pistoia',
        'PN' => 'Pordenone',           'PZ' => 'Potenza',             'PO' => 'Prato',
        'RG' => 'Ragusa',              'RA' => 'Ravenna',             'RC' => 'Reggio Calabria',
        'RE' => 'Reggio Emilia',       'RI' => 'Rieti',               'RN' => 'Rimini',
        'RM' => 'Roma',                'RO' => 'Rovigo',              'SA' => 'Salerno',
        'SS' => 'Sassari',             'SV' => 'Savona',              'SI' => 'Siena',
        'SR' => 'Siracusa',            'SO' => 'Sondrio',             'TA' => 'Taranto',
        'TE' => 'Teramo',              'TR' => 'Terni',               'TO' => 'Torino',
        'TP' => 'Trapani',             'TN' => 'Trento',              'TV' => 'Treviso',
        'TS' => 'Trieste',             'UD' => 'Udine',               'VA' => 'Varese',
        'VE' => 'Venezia',             'VB' => 'Verbano-C.-Ossola',   'VC' => 'Vercelli',
        'VR' => 'Verona',              'VV' => 'Vibo Valentia',       'VI' => 'Vicenza',
        'VT' => 'Viterbo',
    ];

    asort( $province );

    $choices = [];
    $idx     = 1;
    $choices[ $idx++ ] = [ 'label' => '— Seleziona provincia —', 'value' => '', 'default' => '1' ];
    foreach ( $province as $sigla => $nome ) {
        $choices[ $idx++ ] = [ 'label' => $nome . ' (' . $sigla . ')', 'value' => $sigla, 'default' => '' ];
    }

    $form_data['fields'][ $field_prov ]['placeholder']   = '';
    $form_data['fields'][ $field_prov ]['show_values']   = '1';
    $form_data['fields'][ $field_prov ]['choices']       = $choices;
    $form_data['fields'][ $field_prov ]['default_value'] = '';

    $form_data['fields'][ $field_com ]['conditional_logic'] = '0';
    $form_data['fields'][ $field_com ]['conditionals']      = [];

    return $form_data;
}


// ═══════════════════════════════════════════════════════════════════════════
// 2. AJAX — RESTITUISCE COMUNI CON CAP PER PROVINCIA
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_wpfpc_get_comuni',        'wpfpc_get_comuni' );
add_action( 'wp_ajax_nopriv_wpfpc_get_comuni', 'wpfpc_get_comuni' );

function wpfpc_get_comuni(): void {

    check_ajax_referer( 'wpfpc_nonce', 'nonce' );

    $provincia = isset( $_GET['provincia'] )
        ? strtoupper( sanitize_text_field( $_GET['provincia'] ) )
        : '';

    if ( empty( $provincia ) ) wp_send_json_error( 'Provincia mancante' );

    $tutti = wpfpc_get_tutti_comuni();
    if ( null === $tutti )             wp_send_json_error( 'Impossibile scaricare i dati dei comuni.' );
    if ( empty( $tutti[$provincia] ) ) wp_send_json_error( 'Nessun comune trovato per: ' . $provincia );

    wp_send_json_success( $tutti[ $provincia ] );
}


// ═══════════════════════════════════════════════════════════════════════════
// 3. VALIDAZIONE LATO SERVER
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wpforms_process', 'wpfpc_validate_comune_server', 10, 3 );

function wpfpc_validate_comune_server( array $fields, array $entry, array $form_data ): void {

    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return;

    $field_prov = (int) $cfg['field_prov'];
    $field_com  = (int) $cfg['field_com'];

    $provincia = strtoupper( sanitize_text_field( $entry['fields'][ $field_prov ] ?? '' ) );
    $comune    = sanitize_text_field( $entry['fields'][ $field_com ] ?? '' );

    if ( empty( $provincia ) || empty( $comune ) ) return;

    $tutti = wpfpc_get_tutti_comuni();
    if ( null === $tutti ) return;

    $nomi_validi = array_column( $tutti[ $provincia ] ?? [], 'nome' );

    if ( ! in_array( $comune, $nomi_validi, true ) ) {
        wpforms()->get( 'process' )->errors[ $form_data['id'] ][ $field_com ] =
            'Il comune selezionato non è valido per la provincia indicata.';
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// 4. INIETTA IL VALORE DEL COMUNE NELLA MAIL
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'wpforms_process_filter', 'wpfpc_inject_comune_value', 10, 3 );

function wpfpc_inject_comune_value( array $fields, array $entry, array $form_data ): array {

    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return $fields;

    $field_com = (int) $cfg['field_com'];
    $comune    = sanitize_text_field( $entry['fields'][ $field_com ] ?? '' );

    if ( ! empty( $comune ) && isset( $fields[ $field_com ] ) ) {
        $fields[ $field_com ]['value'] = $comune;
    }

    return $fields;
}


// ═══════════════════════════════════════════════════════════════════════════
// 5. JAVASCRIPT
// ═══════════════════════════════════════════════════════════════════════════

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
    .wpfpc-combo            { position:relative; width:100%; }
    .wpfpc-combo-input      { width:100%; padding:8px 32px 8px 10px; border:1px solid #8c8f94; border-radius:4px; font-size:14px; box-sizing:border-box; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23555' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") no-repeat right 10px center; cursor:pointer; }
    .wpfpc-combo-input:focus{ outline:2px solid #2271b1; border-color:#2271b1; }
    .wpfpc-combo-input:disabled { opacity:.5; cursor:default; }
    .wpfpc-combo-list       { display:none; position:absolute; top:100%; left:0; right:0; z-index:99999; background:#fff; border:1px solid #8c8f94; border-top:none; border-radius:0 0 4px 4px; max-height:220px; overflow-y:auto; box-shadow:0 4px 10px rgba(0,0,0,.15); }
    .wpfpc-combo-list.open  { display:block; }
    .wpfpc-combo-item       { padding:8px 12px; cursor:pointer; font-size:14px; }
    .wpfpc-combo-item:hover { background:#f0f5fb; }
    .wpfpc-combo-item.sel   { font-weight:600; color:#2271b1; }
    .wpfpc-combo-empty      { padding:10px 12px; color:#888; font-size:13px; font-style:italic; }
    </style>

    <script>
    (function ($) {
        'use strict';

        var AJAX_URL = '<?php echo $ajax_url; ?>';
        var NONCE    = '<?php echo $nonce; ?>';
        var CONFIGS  = <?php echo wp_json_encode( $js_configs ); ?>;

        function initForm(cfg) {

            // ── Selettori ──────────────────────────────────────────────────
            var idProv      = 'wpforms-' + cfg.formId + '-field-' + cfg.fieldProv;
            var nameCom     = 'wpforms[fields][' + cfg.fieldCom + ']';
            var nameCap     = cfg.fieldCap ? 'wpforms[fields][' + cfg.fieldCap + ']' : null;
            var wrapCom     = '.wpforms-field[data-field-id="' + cfg.fieldCom + '"]';
            var wrapCap     = cfg.fieldCap ? '.wpforms-field[data-field-id="' + cfg.fieldCap + '"]' : null;

            var $selProv    = $('#' + idProv);
            if ( ! $selProv.length ) return; // form non presente in questa pagina

            var $nativeCom  = $('[name="' + nameCom + '"]');

            // ── Stato ──────────────────────────────────────────────────────
            var comuniData  = [];   // [{ nome, cap }]
            var selectedCom = null;

            // ── Costruisce il combobox ─────────────────────────────────────
            // Nasconde il <select> nativo (serve per la submission) e
            // inserisce dopo di esso il combobox visuale.
            $nativeCom.hide();

            var $combo     = $('<div class="wpfpc-combo">');
            var $input     = $('<input>', { type:'text', class:'wpfpc-combo-input', autocomplete:'off', placeholder:'— Seleziona comune —' });
            var $list      = $('<div class="wpfpc-combo-list">');

            $combo.append($input, $list);
            $nativeCom.after($combo);

            // ── Funzioni combobox ─────────────────────────────────────────

            function renderItems(query) {
                $list.empty();
                var q = (query || '').toLowerCase().trim();
                var items = q
                    ? comuniData.filter(function(c){ return c.nome.toLowerCase().indexOf(q) !== -1; })
                    : comuniData;

                if (!items.length) {
                    $list.append('<div class="wpfpc-combo-empty">Nessun comune trovato</div>');
                    return;
                }

                $.each(items, function(i, item) {
                    var cls = 'wpfpc-combo-item' + (selectedCom === item.nome ? ' sel' : '');
                    $('<div>', { class: cls, text: item.nome, 'data-cap': item.cap || '' })
                        .appendTo($list)
                        .on('mousedown', function(e) {
                            // mousedown invece di click: evita blur prima della selezione
                            e.preventDefault();
                            pickItem(item);
                        });
                });
            }

            function openList() {
                renderItems($input.val());
                $list.addClass('open');
            }

            function closeList() {
                $list.removeClass('open');
                // Se il testo non corrisponde a nessuna scelta, ripristina
                if (selectedCom) {
                    $input.val(selectedCom);
                } else {
                    $input.val('');
                }
            }

            function pickItem(item) {
                selectedCom = item.nome;
                $input.val(item.nome);
                // Aggiorna il <select> nativo per la submission
                if (!$nativeCom.find('option[value="' + item.nome.replace(/'/g, "\\'") + '"]').length) {
                    $nativeCom.append($('<option>', { value: item.nome, text: item.nome }));
                }
                $nativeCom.val(item.nome);
                closeList();
                // CAP automatico
                if (nameCap) {
                    $('[name="' + nameCap + '"]').val(item.cap || '');
                }
            }

            function resetCombo(placeholder, disabled) {
                selectedCom = null;
                comuniData  = [];
                $nativeCom.val('').find('option:not([value=""])').remove();
                $input.val('').prop({ placeholder: placeholder || '— Seleziona comune —', disabled: !!disabled });
                $list.removeClass('open').empty();
                if (nameCap) $('[name="' + nameCap + '"]').val('');
            }

            // ── Visibilità ────────────────────────────────────────────────

            function nascondi() {
                $(wrapCom).hide();
                if (wrapCap) $(wrapCap).hide();
                resetCombo('— Seleziona prima una provincia —', false);
            }

            function mostra() {
                $(wrapCom).show();
            }

            // ── Caricamento AJAX ──────────────────────────────────────────

            function caricaComuni(provincia) {
                resetCombo('⏳ Caricamento...', true);

                $.ajax({
                    url: AJAX_URL, method: 'GET', dataType: 'json',
                    data: { action: 'wpfpc_get_comuni', nonce: NONCE, provincia: provincia },
                    success: function(response) {
                        $input.prop('disabled', false);
                        if (!response.success) {
                            resetCombo('⚠️ Errore caricamento');
                            return;
                        }
                        comuniData = response.data;
                        $input.prop('placeholder', '— Seleziona comune —');
                    },
                    error: function(xhr) {
                        $input.prop('disabled', false);
                        resetCombo('⚠️ Errore (' + xhr.status + ')');
                    }
                });
            }

            // ── Eventi combobox ───────────────────────────────────────────

            $input.on('focus click', function() {
                if (!comuniData.length) return;
                openList();
            });

            $input.on('input', function() {
                if (!comuniData.length) return;
                openList();
            });

            $input.on('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $visible = $list.find('.wpfpc-combo-item:visible');
                    if ($visible.length === 1) $visible.trigger('mousedown');
                    return false;
                }
                if (e.key === 'Escape') closeList();
            });

            $input.on('blur', function() {
                // Piccolo delay per permettere mousedown sull'item di completarsi
                setTimeout(closeList, 150);
            });

            $(document).on('click', function(e) {
                if (!$combo.is(e.target) && $combo.has(e.target).length === 0) {
                    closeList();
                }
            });

            // ── Evento cambio provincia ───────────────────────────────────

            $selProv.on('change', function() {
                var val = $(this).val();
                if (val) {
                    mostra();
                    caricaComuni(val);
                } else {
                    nascondi();
                }
            });

            // ── Inizializzazione ──────────────────────────────────────────
            // Forza il placeholder "Seleziona provincia" e nasconde i campi
            $selProv[0].selectedIndex = 0;
            nascondi();
        }

        $(document).ready(function () {
            $.each(CONFIGS, function(i, cfg) { initForm(cfg); });
        });

    }(jQuery));
    </script>
    <?php
}
