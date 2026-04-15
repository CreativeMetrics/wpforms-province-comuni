<?php
/**
 * Plugin Name:       WPForms – Province e Comuni Italiani
 * Plugin URI:        https://github.com/CreativeMetrics/wpforms-province-comuni
 * Description:       Popola automaticamente province e comuni italiani in WPForms con selezione condizionale via AJAX. Configurabile da WPForms → Province & Comuni.
 * Version:           1.1.0
 * Author:            CreativeMetrics
 * Author URI:        https://github.com/CreativeMetrics
 * License:           MIT
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

// ─── COSTANTI ────────────────────────────────────────────────────────────────
define( 'WPFPC_VERSION',        '1.1.0' );
define( 'WPFPC_GITHUB_USER',    'CreativeMetrics' );
define( 'WPFPC_GITHUB_REPO',    'wpforms-province-comuni' );
define( 'WPFPC_COMUNI_JSON_URL',
    'https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json'
);
define( 'WPFPC_COMUNI_CACHE_KEY', 'wpfpc_tutti_comuni_v2' );
// ─────────────────────────────────────────────────────────────────────────────


// ─── CARICA MODULI ───────────────────────────────────────────────────────────
require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/admin.php';

add_action( 'init', function (): void {
    new WPFPC_GitHub_Updater( __FILE__, WPFPC_GITHUB_USER, WPFPC_GITHUB_REPO, WPFPC_VERSION );
} );

new WPFPC_Admin();
// ─────────────────────────────────────────────────────────────────────────────


// ─── HELPER: lista form configurati ──────────────────────────────────────────

function wpfpc_get_configs(): array {
    return WPFPC_Admin::get_configs();
}

/**
 * Trova la configurazione per il form corrente.
 * Restituisce [ 'form_id', 'field_prov', 'field_com', 'label' ] o null.
 */
function wpfpc_config_for_form( int $form_id ): ?array {
    foreach ( wpfpc_get_configs() as $cfg ) {
        if ( (int) $cfg['form_id'] === $form_id ) {
            return $cfg;
        }
    }
    return null;
}
// ─────────────────────────────────────────────────────────────────────────────


// ═══════════════════════════════════════════════════════════════════════════
// SCARICA E COSTRUISCE IL DIZIONARIO sigla → [comuni]
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
        if ( $nome && $sigla ) {
            $per_provincia[ $sigla ][] = $nome;
        }
    }

    foreach ( $per_provincia as &$nomi ) sort( $nomi );
    unset( $nomi );

    set_transient( WPFPC_COMUNI_CACHE_KEY, $per_provincia, 365 * DAY_IN_SECONDS );
    return $per_provincia;
}


// ═══════════════════════════════════════════════════════════════════════════
// 1. POPOLA LE PROVINCE per ogni form configurato
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
// 2. ACCETTA IL VALORE DEL COMUNE IN FASE DI SUBMIT
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'wpforms_process_filter', 'wpfpc_inject_comune_value', 10, 3 );

function wpfpc_inject_comune_value( array $fields, array $entry, array $form_data ): array {

    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return $fields;

    $field_com = (int) $cfg['field_com'];
    $comune    = isset( $entry['fields'][ $field_com ] )
        ? sanitize_text_field( $entry['fields'][ $field_com ] )
        : '';

    if ( ! empty( $comune ) && isset( $fields[ $field_com ] ) ) {
        $fields[ $field_com ]['value'] = $comune;
    }

    return $fields;
}


// ═══════════════════════════════════════════════════════════════════════════
// 3. AJAX – RESTITUISCE COMUNI PER PROVINCIA
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_wpfpc_get_comuni',        'wpfpc_get_comuni' );
add_action( 'wp_ajax_nopriv_wpfpc_get_comuni', 'wpfpc_get_comuni' );

function wpfpc_get_comuni(): void {

    check_ajax_referer( 'wpfpc_nonce', 'nonce' );

    $provincia = isset( $_GET['provincia'] ) ? strtoupper( sanitize_text_field( $_GET['provincia'] ) ) : '';
    if ( empty( $provincia ) ) wp_send_json_error( 'Provincia mancante' );

    $tutti = wpfpc_get_tutti_comuni();
    if ( null === $tutti )            wp_send_json_error( 'Impossibile scaricare i dati dei comuni.' );
    if ( empty( $tutti[$provincia] ) ) wp_send_json_error( 'Nessun comune trovato per: ' . $provincia );

    wp_send_json_success( $tutti[ $provincia ] );
}


// ═══════════════════════════════════════════════════════════════════════════
// 4. JAVASCRIPT — generato dinamicamente per tutti i form configurati
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_footer', 'wpfpc_inline_script' );

function wpfpc_inline_script(): void {

    $configs = wpfpc_get_configs();
    if ( empty( $configs ) ) return;

    $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
    $nonce    = wp_create_nonce( 'wpfpc_nonce' );

    // Costruisce l'array di configurazioni da passare al JS
    $js_configs = array_map( fn( $c ) => [
        'formId'   => (int) $c['form_id'],
        'fieldProv' => (int) $c['field_prov'],
        'fieldCom'  => (int) $c['field_com'],
    ], $configs );

    ?>
    <script>
    (function ($) {
        'use strict';

        var AJAX_URL = '<?php echo $ajax_url; ?>';
        var NONCE    = '<?php echo $nonce; ?>';
        var CONFIGS  = <?php echo wp_json_encode( array_values( $js_configs ) ); ?>;

        var CSS_DISABLED = 'opacity:0.5; pointer-events:none;';
        var CSS_ENABLED  = 'opacity:1;   pointer-events:auto;';

        function initForm( cfg ) {
            var selProv       = '[name="wpforms[fields][' + cfg.fieldProv + ']"]';
            var selCom        = '[name="wpforms[fields][' + cfg.fieldCom  + ']"]';
            var selComWrapper = '.wpforms-field[data-field-id="' + cfg.fieldCom + '"]';

            function $com() { return $(selCom); }

            function resetComuni(msg) {
                $com().html('<option value="">' + msg + '</option>').attr('style', CSS_DISABLED);
            }

            function nascondiComuni() {
                $(selComWrapper).hide();
                resetComuni('— Seleziona prima una provincia —');
            }

            function mostraComuni() {
                $(selComWrapper).show();
            }

            function caricaComuni(provincia) {
                resetComuni('⏳ Caricamento comuni...');
                $.ajax({
                    url:      AJAX_URL,
                    method:   'GET',
                    dataType: 'json',
                    data: { action: 'wpfpc_get_comuni', nonce: NONCE, provincia: provincia },
                    success: function (response) {
                        if (!response.success) { resetComuni('⚠️ ' + response.data); return; }
                        var $sel = $com();
                        $sel.html('<option value="">— Seleziona comune —</option>');
                        $.each(response.data, function (i, nome) {
                            $sel.append($('<option>', { value: nome, text: nome }));
                        });
                        $sel.attr('style', CSS_ENABLED);
                    },
                    error: function (xhr) {
                        resetComuni('⚠️ Errore (' + xhr.status + '), riprova');
                    }
                });
            }

            if (!$(selProv).length) return; // form non in questa pagina

            $(selProv)[0].selectedIndex = 0;
            nascondiComuni();

            $(document).on('change', selProv, function () {
                var val = $(this).val();
                if (val) { mostraComuni(); caricaComuni(val); }
                else     { nascondiComuni(); }
            });
        }

        $(document).ready(function () {
            $.each(CONFIGS, function(i, cfg) {
                initForm(cfg);
            });
        });

    }(jQuery));
    </script>
    <?php
}
