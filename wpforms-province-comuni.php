<?php
/**
 * Plugin Name:       WPForms – Province e Comuni Italiani
 * Plugin URI:        https://github.com/CreativeMetrics/wpforms-province-comuni
 * Description:       Popola automaticamente province e comuni italiani in WPForms con selezione condizionale via AJAX.
 * Version:           1.3.0
 * Author:            CreativeMetrics
 * Author URI:        https://github.com/CreativeMetrics
 * License:           MIT
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WPFPC_VERSION',         '1.3.0' );
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
    if ( null === $tutti )               wp_send_json_error( 'Impossibile scaricare i dati.' );
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

add_action( 'wp_enqueue_scripts', 'wpfpc_enqueue_select2' );

function wpfpc_enqueue_select2(): void {
    $configs = wpfpc_get_configs();
    if ( empty( $configs ) ) return;

    // Select2 — libreria jQuery matura per dropdown ricercabili
    // CDN jsDelivr, nessuna dipendenza aggiuntiva
    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        [ 'jquery' ],
        '4.1.0',
        true
    );
}

add_action( 'wp_head', 'wpfpc_select2_style' );

function wpfpc_select2_style(): void {
    $configs = wpfpc_get_configs();
    if ( empty( $configs ) ) return;
    ?>
    <style>
    /* Select2: eredita l'aspetto dai campi WPForms nativi */

    /* Contenitore principale — uguale al select WPForms */
    .wpforms-field .select2-container { width: 100% !important; }
    .wpforms-field .select2-container--default .select2-selection--single {
        height: auto;
        min-height: 0;
        padding: 0;
        border: 1px solid #ccc;
        border-radius: inherit;
        background: inherit;
        box-shadow: none;
        font-size: inherit;
        font-family: inherit;
        color: inherit;
        display: flex;
        align-items: center;
    }

    /* Testo selezionato — centrato verticalmente, stesso padding del select WPForms */
    .wpforms-field .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal;
        padding: 0 24px 0 0;
        color: inherit;
        font-size: inherit;
        display: flex;
        align-items: center;
        width: 100%;
    }

    /* Freccia — posizionata a destra come il select nativo */
    .wpforms-field .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100%;
        top: 0;
        right: 4px;
    }

    /* Dropdown aperto — segue il bordo del tema */
    .wpforms-field .select2-container--default .select2-dropdown {
        border-color: inherit;
        border-radius: inherit;
        font-size: inherit;
        font-family: inherit;
        z-index: 99999;
    }

    /* Campo di ricerca nel dropdown */
    .wpforms-field .select2-container--default .select2-search--dropdown .select2-search__field {
        border-color: inherit;
        border-radius: inherit;
        font-size: inherit;
        font-family: inherit;
        padding: 6px 8px;
        width: 100%;
        box-sizing: border-box;
    }

    /* Opzioni nella lista */
    .wpforms-field .select2-container--default .select2-results__option {
        font-size: inherit;
        font-family: inherit;
        padding: 6px 10px;
    }

    /* Opzione evidenziata */
    .wpforms-field .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #2271b1;
        color: #fff;
    }
    </style>
    <?php
}

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
    <script>
    (function ($) {
        'use strict';

        var AJAX_URL = '<?php echo $ajax_url; ?>';
        var NONCE    = '<?php echo $nonce; ?>';
        var CONFIGS  = <?php echo wp_json_encode( $js_configs ); ?>;

        var CSS_DIS = 'opacity:0.5; pointer-events:none;';
        var CSS_ENA = 'opacity:1;   pointer-events:auto;';

        function initForm(cfg) {

            var selProv   = '[name="wpforms[fields][' + cfg.fieldProv + ']"]';
            var selCom    = '[name="wpforms[fields][' + cfg.fieldCom  + ']"]';
            var selCap    = cfg.fieldCap ? '[name="wpforms[fields][' + cfg.fieldCap + ']"]' : null;
            var wrapCom   = '.wpforms-field[data-field-id="' + cfg.fieldCom + '"]';
            var wrapCap   = cfg.fieldCap ? '.wpforms-field[data-field-id="' + cfg.fieldCap + '"]' : null;

            if ( !$(selProv).length ) return;

            function nascondi() {
                $(wrapCom).hide();
                if (wrapCap) $(wrapCap).hide();
                // Distrugge Select2 prima di modificare il select nativo
                if ($.fn.select2 && $(selCom).hasClass('select2-hidden-accessible')) {
                    $(selCom).select2('destroy');
                }
                $(selCom).html('<option value="">— Seleziona prima una provincia —</option>')
                         .attr('style', CSS_DIS);
                if (selCap) $(selCap).val('');
            }

            function caricaComuni(sigla) {
                $(selCom).html('<option value="">⏳ Caricamento...</option>')
                         .attr('style', CSS_DIS);

                $.ajax({
                    url: AJAX_URL, method: 'GET', dataType: 'json',
                    data: { action: 'wpfpc_get_comuni', nonce: NONCE, provincia: sigla },
                    success: function(resp) {
                        if (!resp.success) {
                            $(selCom).html('<option value="">⚠️ Errore caricamento</option>');
                            return;
                        }
                        var html = '<option value="">— Seleziona comune —</option>';
                        $.each(resp.data, function(i, item) {
                            html += '<option value="' + item.nome + '" data-cap="' + (item.cap || '') + '">'
                                  + item.nome + '</option>';
                        });
                        $(selCom).html(html).attr('style', CSS_ENA);
                        $(wrapCom).show();

                        // Inizializza Select2 sul campo comuni
                        // per aggiungere la ricerca integrata nel dropdown
                        if ($.fn.select2) {
                            $(selCom).select2({
                                placeholder: '— Seleziona comune —',
                                allowClear:  false,
                                width:       '100%',
                                language: {
                                    noResults:    function() { return 'Nessun comune trovato'; },
                                    searching:    function() { return 'Ricerca in corso...'; },
                                    inputTooShort: function() { return 'Digita per cercare'; }
                                }
                            });
                        }
                    },
                    error: function(xhr) {
                        $(selCom).html('<option value="">⚠️ Errore (' + xhr.status + ')</option>');
                    }
                });
            }

            // Cambio provincia
            $(document).on('change', selProv, function() {
                var sigla = $(this).val();
                if (sigla) {
                    caricaComuni(sigla);
                } else {
                    nascondi();
                }
            });

            // Cambio comune → CAP automatico
            $(document).on('change', selCom, function() {
                if (!selCap) return;
                var cap = $(this).find('option:selected').data('cap') || '';
                $(selCap).val(cap);
                if (wrapCap) cap ? $(wrapCap).show() : $(wrapCap).hide();
            });

            // Inizializzazione
            $(selProv)[0].selectedIndex = 0;
            nascondi();
        }

        $(document).ready(function() {
            $.each(CONFIGS, function(i, cfg) { initForm(cfg); });
        });

    }(jQuery));
    </script>
    <?php
}
