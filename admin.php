<?php
/**
 * Pannello di amministrazione — WPForms → Province & Comuni
 * Gestisce la configurazione multi-form salvata in wp_options.
 */

defined( 'ABSPATH' ) || exit;

class WPFPC_Admin {

    const OPTION_KEY = 'wpfpc_form_configs';

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_menu' ] );
        add_action( 'admin_init',    [ $this, 'handle_save' ] );
        add_action( 'admin_init',    [ $this, 'handle_tools' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

    // ── Menu sotto WPForms ───────────────────────────────────────────────────

    public function add_menu(): void {
        add_submenu_page(
            'wpforms-overview',             // parent slug di WPForms
            'Province & Comuni',            // page title
            'Province & Comuni',            // menu title
            'manage_options',               // capability
            'wpfpc-settings',               // menu slug
            [ $this, 'render_page' ]        // callback
        );
    }

    // ── Stili inline per il pannello ─────────────────────────────────────────

    public function enqueue_styles( string $hook ): void {
        if ( $hook !== 'wpforms_page_wpfpc-settings' ) return;
        wp_add_inline_style( 'wp-admin', '
            .wpfpc-wrap { max-width: 860px; }
            .wpfpc-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
            .wpfpc-table th { background: #f0f0f1; padding: 10px 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #c3c4c7; }
            .wpfpc-table td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; vertical-align: middle; }
            .wpfpc-table tr:last-child td { border-bottom: none; }
            .wpfpc-table input[type=number] { width: 90px; }
            .wpfpc-table input[type=text]   { width: 180px; }
            .wpfpc-btn-remove { color: #b32d2e; background: none; border: none; cursor: pointer; font-size: 18px; line-height: 1; padding: 0 4px; }
            .wpfpc-btn-remove:hover { color: #8b0000; }
            .wpfpc-add-row { margin-top: 4px; }
            .wpfpc-tools { margin-top: 24px; padding: 16px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
            .wpfpc-tools h3 { margin-top: 0; }
            .wpfpc-notice-inline { display:inline-block; padding: 6px 12px; border-radius: 3px; }
            .wpfpc-badge { display:inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background:#d4edda; color:#155724; }
        ' );
    }

    // ── Salvataggio form ─────────────────────────────────────────────────────

    public function handle_save(): void {
        if (
            ! isset( $_POST['wpfpc_save'] ) ||
            ! current_user_can( 'manage_options' ) ||
            ! check_admin_referer( 'wpfpc_save_settings' )
        ) return;

        $raw     = $_POST['wpfpc_forms'] ?? [];
        $configs = [];

        foreach ( $raw as $row ) {
            $form_id    = (int) ( $row['form_id']    ?? 0 );
            $field_prov = (int) ( $row['field_prov'] ?? 0 );
            $field_com  = (int) ( $row['field_com']  ?? 0 );
            $label      = sanitize_text_field( $row['label'] ?? '' );

            if ( $form_id > 0 && $field_prov > 0 && $field_com > 0 ) {
                $configs[] = compact( 'form_id', 'field_prov', 'field_com', 'label' );
            }
        }

        update_option( self::OPTION_KEY, $configs );

        wp_redirect( add_query_arg( [ 'page' => 'wpfpc-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ── Strumenti (reset cache) ───────────────────────────────────────────────

    public function handle_tools(): void {
        if ( ! isset( $_GET['wpfpc_action'] ) || ! current_user_can( 'manage_options' ) ) return;
        if ( ! check_admin_referer( 'wpfpc_tool_action' ) ) return;

        $action = sanitize_key( $_GET['wpfpc_action'] );

        if ( $action === 'reset_comuni' ) {
            delete_transient( 'wpfpc_tutti_comuni_v2' );
            $msg = 'cache_comuni_ok';
        } elseif ( $action === 'reset_github' ) {
            delete_transient( 'wpfpc_github_release' );
            $msg = 'cache_github_ok';
        } else {
            $msg = 'unknown';
        }

        wp_redirect( add_query_arg( [ 'page' => 'wpfpc-settings', 'tool' => $msg ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ── Pagina HTML ───────────────────────────────────────────────────────────

    public function render_page(): void {
        $configs = get_option( self::OPTION_KEY, [] );
        $tool_url = fn( string $action ) => wp_nonce_url(
            add_query_arg( [ 'page' => 'wpfpc-settings', 'wpfpc_action' => $action ], admin_url( 'admin.php' ) ),
            'wpfpc_tool_action'
        );
        ?>
        <div class="wrap wpfpc-wrap">
            <h1>🇮🇹 WPForms – Province & Comuni</h1>

            <?php if ( isset( $_GET['saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>✅ Configurazione salvata.</p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['tool'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>✅ <?php echo match( $_GET['tool'] ) {
                    'cache_comuni_ok' => 'Cache comuni svuotata. Verrà ri-scaricata al prossimo utilizzo.',
                    'cache_github_ok' => 'Cache aggiornamenti GitHub svuotata.',
                    default           => 'Operazione completata.',
                }; ?></p></div>
            <?php endif; ?>

            <form method="post" id="wpfpc-form">
                <?php wp_nonce_field( 'wpfpc_save_settings' ); ?>

                <h2>Form configurati</h2>
                <p>Aggiungi una riga per ogni form WPForms in cui vuoi usare il selettore provincia/comune.<br>
                   Trovi gli ID aprendo il form nell'editor: l'ID del form è nell'URL, l'ID del campo è visibile cliccando sul dropdown.</p>

                <table class="wpfpc-table widefat">
                    <thead>
                        <tr>
                            <th>Etichetta (opzionale)</th>
                            <th>Form ID</th>
                            <th>Field ID Provincia</th>
                            <th>Field ID Comune</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="wpfpc-rows">
                        <?php if ( empty( $configs ) ) : ?>
                            <?php echo $this->row_html( 0, [], true ); ?>
                        <?php else : ?>
                            <?php foreach ( $configs as $i => $cfg ) : ?>
                                <?php echo $this->row_html( $i, $cfg ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <button type="button" class="button wpfpc-add-row" id="wpfpc-add-row">+ Aggiungi form</button>

                <p class="submit" style="margin-top:20px;">
                    <button type="submit" name="wpfpc_save" class="button button-primary button-large">Salva configurazione</button>
                </p>
            </form>

            <div class="wpfpc-tools">
                <h3>🛠 Strumenti</h3>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th scope="row">Cache comuni ISTAT</th>
                        <td>
                            <?php
                            $cached = get_transient( 'wpfpc_tutti_comuni_v2' );
                            if ( $cached ) {
                                echo '<span class="wpfpc-badge">✓ In cache (' . count( $cached ) . ' province)</span> &nbsp;';
                            } else {
                                echo '<span style="color:#888;">Non ancora in cache</span> &nbsp;';
                            }
                            ?>
                            <a href="<?php echo esc_url( $tool_url( 'reset_comuni' ) ); ?>" class="button button-secondary">Svuota cache comuni</a>
                            <p class="description">I dati ISTAT vengono scaricati una volta sola e conservati per 365 giorni.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Cache aggiornamenti GitHub</th>
                        <td>
                            <?php
                            $gh = get_transient( 'wpfpc_github_release' );
                            if ( $gh && ! empty( $gh['tag_name'] ) ) {
                                echo '<span class="wpfpc-badge">✓ Ultima release: ' . esc_html( $gh['tag_name'] ) . '</span> &nbsp;';
                            } else {
                                echo '<span style="color:#888;">Non ancora verificato</span> &nbsp;';
                            }
                            ?>
                            <a href="<?php echo esc_url( $tool_url( 'reset_github' ) ); ?>" class="button button-secondary">Forza controllo aggiornamenti</a>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Versione installata</th>
                        <td><code><?php echo WPFPC_VERSION; ?></code></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php
        // Template riga nascosta per il JS
        echo '<script type="text/html" id="wpfpc-row-template">';
        echo $this->row_html( '__INDEX__', [], true );
        echo '</script>';
        ?>

        <script>
        (function() {
            var rowIndex = <?php echo max( count( $configs ), 1 ); ?>;

            document.getElementById('wpfpc-add-row').addEventListener('click', function() {
                var tpl = document.getElementById('wpfpc-row-template').innerHTML;
                tpl = tpl.replace(/__INDEX__/g, rowIndex++);
                var tbody = document.getElementById('wpfpc-rows');
                tbody.insertAdjacentHTML('beforeend', tpl);
            });

            document.getElementById('wpfpc-rows').addEventListener('click', function(e) {
                if (e.target.classList.contains('wpfpc-btn-remove')) {
                    var rows = document.querySelectorAll('#wpfpc-rows tr');
                    if (rows.length > 1) {
                        e.target.closest('tr').remove();
                    } else {
                        // Se è l'ultima riga, svuota i campi invece di rimuoverla
                        e.target.closest('tr').querySelectorAll('input').forEach(function(i){ i.value = ''; });
                    }
                }
            });
        })();
        </script>
        <?php
    }

    // ── HTML di una singola riga della tabella ────────────────────────────────

    private function row_html( int|string $i, array $cfg, bool $empty = false ): string {
        $label      = $empty ? '' : esc_attr( $cfg['label']      ?? '' );
        $form_id    = $empty ? '' : esc_attr( $cfg['form_id']    ?? '' );
        $field_prov = $empty ? '' : esc_attr( $cfg['field_prov'] ?? '' );
        $field_com  = $empty ? '' : esc_attr( $cfg['field_com']  ?? '' );

        return "
        <tr>
            <td><input type='text'   name='wpfpc_forms[{$i}][label]'      value='{$label}'      placeholder='es. Form contatti' /></td>
            <td><input type='number' name='wpfpc_forms[{$i}][form_id]'    value='{$form_id}'    placeholder='2458' min='1' /></td>
            <td><input type='number' name='wpfpc_forms[{$i}][field_prov]' value='{$field_prov}' placeholder='14'   min='1' /></td>
            <td><input type='number' name='wpfpc_forms[{$i}][field_com]'  value='{$field_com}'  placeholder='15'   min='1' /></td>
            <td><button type='button' class='wpfpc-btn-remove' title='Rimuovi'>✕</button></td>
        </tr>";
    }

    // ── Helper statico: recupera tutte le configurazioni salvate ─────────────

    public static function get_configs(): array {
        return get_option( self::OPTION_KEY, [] );
    }
}
