<?php
/**
 * Plugin Name:       WPForms – Province e Comuni Italiani
 * Plugin URI:        https://github.com/CreativeMetrics/wpforms-province-comuni
 * Description:       Popola automaticamente province e comuni italiani in WPForms con ricerca live, CAP automatico e validazione server.
 * Version:           1.2.3
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

        // ── Stili combobox ────────────────────────────────────────────────
        var comboCSS = [
            '.wpfpc-combobox { position:relative; width:100%; }',
            '.wpfpc-combobox-input {',
            '  width:100%; padding:8px 32px 8px 10px; border:1px solid #8c8f94;',
            '  border-radius:4px; font-size:14px; box-sizing:border-box;',
            '  background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23555' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") no-repeat right 10px center;',
            '  cursor:pointer;',
            '}',
            '.wpfpc-combobox-input:focus { outline:2px solid #2271b1; border-color:#2271b1; cursor:text; }',
            '.wpfpc-combobox-list {',
            '  display:none; position:absolute; top:100%; left:0; right:0; z-index:9999;',
            '  background:#fff; border:1px solid #8c8f94; border-top:none;',
            '  border-radius:0 0 4px 4px; max-height:220px; overflow-y:auto;',
            '  box-shadow:0 4px 8px rgba(0,0,0,.12);',
            '}',
            '.wpfpc-combobox-list.open { display:block; }',
            '.wpfpc-combobox-item {',
            '  padding:8px 12px; cursor:pointer; font-size:14px;',
            '}',
            '.wpfpc-combobox-item:hover, .wpfpc-combobox-item.highlighted { background:#f0f5fb; }',
            '.wpfpc-combobox-item.selected { font-weight:600; color:#2271b1; }',
            '.wpfpc-combobox-empty { padding:10px 12px; color:#888; font-size:13px; font-style:italic; }',
        ].join('');

        $('<style>').text(comboCSS).appendTo('head');

        function initForm(cfg) {
            var selProv       = '[name="wpforms[fields][' + cfg.fieldProv + ']"]';
            var selCom        = '[name="wpforms[fields][' + cfg.fieldCom  + ']"]';
            var selCap        = cfg.fieldCap ? '[name="wpforms[fields][' + cfg.fieldCap + ']"]' : null;
            var selComWrapper = '.wpforms-field[data-field-id="' + cfg.fieldCom + '"]';
            var selCapWrapper = cfg.fieldCap ? '.wpforms-field[data-field-id="' + cfg.fieldCap + '"]' : null;

            if ( ! $(selProv).length ) return;

            // Dati comuni in memoria per il combobox
            var comuniData  = []; // array di { nome, cap }
            var selectedCom = null;

            // ── Costruisce il combobox custom ─────────────────────────────
            // Il <select> nativo rimane nascosto per gestire la submission del form.
            // Il combobox è puramente presentazionale.
            var $nativeSelect = $(selCom);
            var $comboWrap, $comboInput, $comboList;

            function buildCombobox() {
                if ( $comboWrap ) $comboWrap.remove();

                $comboWrap  = $('<div class="wpfpc-combobox">');
                $comboInput = $('<input>', {
                    type:        'text',
                    class:       'wpfpc-combobox-input',
                    placeholder: '— Seleziona comune —',
                    autocomplete:'off',
                    readonly:    true,
                });
                $comboList = $('<div class="wpfpc-combobox-list">');

                $comboWrap.append($comboInput, $comboList);
                $nativeSelect.hide().after($comboWrap);

                // Apre la lista al click
                $comboInput.on('click focus', function () {
                    $comboInput.prop('readonly', false);
                    renderList('');
                    $comboList.addClass('open');
                    $comboInput.select();
                });

                // Filtra mentre si digita
                $comboInput.on('input', function () {
                    renderList( $(this).val() );
                    $comboList.addClass('open');
                });

                // Blocca submit su Invio
                $comboInput.on('keydown', function (e) {
                    if (e.key === 'Enter' || e.keyCode === 13) {
                        e.preventDefault();
                        e.stopPropagation();
                        // Se c'è un solo elemento visibile, selezionalo
                        var $visible = $comboList.find('.wpfpc-combobox-item:visible');
                        if ($visible.length === 1) $visible.trigger('click');
                        return false;
                    }
                    if (e.key === 'Escape') {
                        closeList();
                    }
                });

                // Chiudi cliccando fuori
                $(document).on('click.wpfpc', function (e) {
                    if (!$comboWrap.is(e.target) && $comboWrap.has(e.target).length === 0) {
                        closeList();
                    }
                });
            }

            function renderList(query) {
                $comboList.empty();
                var q = query.toLowerCase().trim();
                var filtered = q
                    ? comuniData.filter(function(c){ return c.nome.toLowerCase().indexOf(q) > -1; })
                    : comuniData;

                if (!filtered.length) {
                    $comboList.append('<div class="wpfpc-combobox-empty">Nessun comune trovato</div>');
                    return;
                }

                $.each(filtered, function(i, item) {
                    var $item = $('<div>', {
                        class: 'wpfpc-combobox-item' + (selectedCom && selectedCom.nome === item.nome ? ' selected' : ''),
                        text:  item.nome,
                    });
                    $item.on('click', function () {
                        selectComune(item);
                    });
                    $comboList.append($item);
                });
            }

            function selectComune(item) {
                selectedCom = item;
                $comboInput.val(item.nome).prop('readonly', true);
                // Aggiorna il <select> nativo (usato per la submission del form)
                $nativeSelect.val(item.nome);
                if (!$nativeSelect.find('option[value="' + item.nome + '"]').length) {
                    $nativeSelect.append($('<option>', { value: item.nome, 'data-cap': item.cap || '', text: item.nome }));
                }
                $nativeSelect.val(item.nome).trigger('change');
                closeList();
                aggiornaCAP(item);
            }

            function closeList() {
                $comboList.removeClass('open');
                // Se il testo non corrisponde a una scelta valida, ripristina
                if (selectedCom && $comboInput.val() !== selectedCom.nome) {
                    $comboInput.val(selectedCom.nome);
                } else if (!selectedCom) {
                    $comboInput.val('');
                }
                $comboInput.prop('readonly', true);
            }

            function resetCombobox(placeholder) {
                selectedCom = null;
                comuniData  = [];
                if ($comboInput) {
                    $comboInput.val('').prop('placeholder', placeholder).prop('readonly', true);
                }
                if ($comboList) $comboList.empty().removeClass('open');
                $nativeSelect.val('').html('<option value=""></option>');
            }

            // ── CAP automatico ────────────────────────────────────────────
            function aggiornaCAP(item) {
                if (!selCap) return;
                var cap = item ? (item.cap || '') : '';
                $(selCap).val(cap);
                if (selCapWrapper) {
                    cap ? $(selCapWrapper).show() : $(selCapWrapper).hide();
                }
            }

            // ── Visibilità campo comuni ───────────────────────────────────
            function nascondiComuni() {
                $(selComWrapper).hide();
                if (selCapWrapper) $(selCapWrapper).hide();
                resetCombobox('— Seleziona prima una provincia —');
            }

            function mostraComuni() {
                $(selComWrapper).show();
            }

            // ── Carica comuni via AJAX ────────────────────────────────────
            function caricaComuni(provincia) {
                resetCombobox('⏳ Caricamento...');
                if ($comboInput) $comboInput.prop('disabled', true);

                $.ajax({
                    url:      AJAX_URL,
                    method:   'GET',
                    dataType: 'json',
                    data: { action: 'wpfpc_get_comuni', nonce: NONCE, provincia: provincia },
                    success: function (response) {
                        if ($comboInput) $comboInput.prop('disabled', false);
                        if (!response.success) {
                            resetCombobox('⚠️ Errore caricamento');
                            return;
                        }
                        comuniData = response.data; // [{ nome, cap }]
                        resetCombobox('— Seleziona comune —');
                    },
                    error: function (xhr) {
                        if ($comboInput) $comboInput.prop('disabled', false);
                        resetCombobox('⚠️ Errore (' + xhr.status + ')');
                    }
                });
            }

            // ── Inizializzazione ──────────────────────────────────────────
            $(selProv)[0].selectedIndex = 0;
            buildCombobox();
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
