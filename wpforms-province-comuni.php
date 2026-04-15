<?php
/**
 * Plugin Name:       WPForms – Province e Comuni Italiani
 * Plugin URI:        https://github.com/CreativeMetrics/wpforms-province-comuni
 * Description:       Popola automaticamente province e comuni italiani in WPForms con ricerca live, CAP automatico e validazione server.
 * Version:           1.2.0
 * Author:            CreativeMetrics
 * Author URI:        https://github.com/CreativeMetrics
 * License:           MIT
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

// ─── COSTANTI ────────────────────────────────────────────────────────────────
define( 'WPFPC_VERSION',       '1.2.0' );
define( 'WPFPC_GITHUB_USER',   'CreativeMetrics' );
define( 'WPFPC_GITHUB_REPO',   'wpforms-province-comuni' );
define( 'WPFPC_COMUNI_JSON_URL',
    'https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json'
);
define( 'WPFPC_COMUNI_CACHE_KEY', 'wpfpc_tutti_comuni_v3' ); // v3 include i CAP
// ─────────────────────────────────────────────────────────────────────────────


// ─── CARICA MODULI ───────────────────────────────────────────────────────────
require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/admin.php';

add_action( 'init', function (): void {
    new WPFPC_GitHub_Updater( __FILE__, WPFPC_GITHUB_USER, WPFPC_GITHUB_REPO, WPFPC_VERSION );
} );

new WPFPC_Admin();
// ─────────────────────────────────────────────────────────────────────────────


// ─── HELPER: configurazioni ───────────────────────────────────────────────────

function wpfpc_get_configs(): array {
    return WPFPC_Admin::get_configs();
}

function wpfpc_config_for_form( int $form_id ): ?array {
    foreach ( wpfpc_get_configs() as $cfg ) {
        if ( (int) $cfg['form_id'] === $form_id ) return $cfg;
    }
    return null;
}
// ─────────────────────────────────────────────────────────────────────────────


// ═══════════════════════════════════════════════════════════════════════════
// DATI ISTAT — dizionario sigla → [ { nome, cap } ]
// Nuova struttura v3: include i CAP per ogni comune
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

        // CAP: il JSON ha un array di CAP, usiamo il primo.
        // Se il comune ha più CAP li uniamo con virgola.
        $cap_raw = $comune['cap'] ?? [];
        $cap     = is_array( $cap_raw ) ? implode( ', ', $cap_raw ) : (string) $cap_raw;

        $per_provincia[ $sigla ][] = [ 'nome' => $nome, 'cap' => $cap ];
    }

    // Ordina per nome all'interno di ogni provincia
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

    // Restituisce array di { nome, cap } — il JS usa entrambi
    wp_send_json_success( $tutti[ $provincia ] );
}


// ═══════════════════════════════════════════════════════════════════════════
// 3. VALIDAZIONE LATO SERVER
// Verifica che il comune inviato esista davvero nella provincia selezionata.
// Blocca l'invio con un errore visibile se il valore è stato manomesso.
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wpforms_process', 'wpfpc_validate_comune_server', 10, 3 );

function wpfpc_validate_comune_server( array $fields, array $entry, array $form_data ): void {

    $cfg = wpfpc_config_for_form( (int) $form_data['id'] );
    if ( ! $cfg ) return;

    $field_prov = (int) $cfg['field_prov'];
    $field_com  = (int) $cfg['field_com'];

    $provincia = strtoupper( sanitize_text_field( $entry['fields'][ $field_prov ] ?? '' ) );
    $comune    = sanitize_text_field( $entry['fields'][ $field_com ] ?? '' );

    // Se non è stato selezionato niente, la validazione required di WPForms ci pensa
    if ( empty( $provincia ) || empty( $comune ) ) return;

    $tutti = wpfpc_get_tutti_comuni();
    if ( null === $tutti ) return; // Se i dati non sono disponibili, non bloccare

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
// 5. JAVASCRIPT — ricerca live, CAP automatico, visibilità condizionale
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_footer', 'wpfpc_inline_script' );

function wpfpc_inline_script(): void {

    $configs = wpfpc_get_configs();
    if ( empty( $configs ) ) return;

    $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
    $nonce    = wp_create_nonce( 'wpfpc_nonce' );

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

        var CSS_DISABLED = 'opacity:0.5; pointer-events:none;';
        var CSS_ENABLED  = 'opacity:1;   pointer-events:auto;';

        function initForm(cfg) {
            var selProv       = '[name="wpforms[fields][' + cfg.fieldProv + ']"]';
            var selCom        = '[name="wpforms[fields][' + cfg.fieldCom  + ']"]';
            var selCap        = cfg.fieldCap ? '[name="wpforms[fields][' + cfg.fieldCap + ']"]' : null;
            var selComWrapper = '.wpforms-field[data-field-id="' + cfg.fieldCom + '"]';
            var selCapWrapper = cfg.fieldCap ? '.wpforms-field[data-field-id="' + cfg.fieldCap + '"]' : null;

            var $searchInput = null; // campo ricerca live (creato dinamicamente)

            if ( ! $(selProv).length ) return;

            // ── Ricerca live ──────────────────────────────────────────────
            function insertSearchInput() {
                if ( $searchInput ) $searchInput.remove();

                var $comWrapper = $(selComWrapper);
                if ( ! $comWrapper.length ) return;

                $searchInput = $('<input>', {
                    type:        'text',
                    placeholder: '🔍 Cerca comune...',
                    css: {
                        width:        '100%',
                        marginBottom: '6px',
                        padding:      '6px 10px',
                        border:       '1px solid #ccc',
                        borderRadius: '4px',
                        boxSizing:    'border-box',
                        fontSize:     '14px',
                    }
                });

                // Inserisce il campo prima del <select>
                $comWrapper.find('select').before( $searchInput );

                $searchInput.on('input', function () {
                    var query = $(this).val().toLowerCase().trim();
                    $(selCom).find('option').each(function () {
                        var $opt = $(this);
                        if ( $opt.val() === '' ) return; // non nascondere il placeholder
                        $opt.toggle( $opt.text().toLowerCase().indexOf(query) > -1 );
                    });
                });
            }

            // ── Reset comuni ──────────────────────────────────────────────
            function resetComuni(msg) {
                $(selCom).html('<option value="">' + msg + '</option>').attr('style', CSS_DISABLED);
                if ($searchInput) { $searchInput.val('').hide(); }
            }

            function nascondiComuni() {
                $(selComWrapper).hide();
                if (selCapWrapper) $(selCapWrapper).hide();
                resetComuni('— Seleziona prima una provincia —');
            }

            function mostraComuni() {
                $(selComWrapper).show();
            }

            // ── Carica comuni via AJAX ────────────────────────────────────
            function caricaComuni(provincia) {
                resetComuni('⏳ Caricamento comuni...');

                $.ajax({
                    url:      AJAX_URL,
                    method:   'GET',
                    dataType: 'json',
                    data: { action: 'wpfpc_get_comuni', nonce: NONCE, provincia: provincia },
                    success: function (response) {
                        if (!response.success) {
                            resetComuni('⚠️ ' + response.data);
                            return;
                        }

                        var $sel = $(selCom);
                        $sel.html('<option value="">— Seleziona comune —</option>');

                        // response.data è array di { nome, cap }
                        $.each(response.data, function (i, item) {
                            $sel.append(
                                $('<option>', {
                                    value:        item.nome,
                                    text:         item.nome,
                                    'data-cap':   item.cap || '',
                                })
                            );
                        });

                        $sel.attr('style', CSS_ENABLED);

                        // Mostra campo ricerca solo se ci sono abbastanza comuni
                        if (response.data.length > 15) {
                            insertSearchInput();
                            if ($searchInput) $searchInput.val('').show();
                        }
                    },
                    error: function (xhr) {
                        resetComuni('⚠️ Errore (' + xhr.status + '), riprova');
                    }
                });
            }

            // ── Popola CAP automaticamente ────────────────────────────────
            function aggiornaCAP() {
                if (!selCap) return;

                var $selCom = $(selCom);
                var cap = $selCom.find('option:selected').data('cap') || '';

                $(selCap).val(cap);

                // Mostra/nasconde il campo CAP
                if (selCapWrapper) {
                    cap ? $(selCapWrapper).show() : $(selCapWrapper).hide();
                }
            }

            // ── Inizializzazione ──────────────────────────────────────────
            $(selProv)[0].selectedIndex = 0;
            nascondiComuni();

            // Cambio provincia
            $(document).on('change', selProv, function () {
                var val = $(this).val();
                if (val) {
                    mostraComuni();
                    caricaComuni(val);
                } else {
                    nascondiComuni();
                }
            });

            // Cambio comune → aggiorna CAP
            $(document).on('change', selCom, function () {
                aggiornaCAP();
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
