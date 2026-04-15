<?php
/**
 * Aggiornamento automatico da GitHub Releases.
 * Usa esclusivamente il filtro standard di WordPress — non chiama mai
 * get_site_transient() o set_site_transient() per evitare di innescare
 * i filtri di altri plugin (Yoast, Duplicator, ecc.) e crash di memoria.
 */

defined( 'ABSPATH' ) || exit;

class WPFPC_GitHub_Updater {

    private string $slug;
    private string $plugin_file;
    private string $github_user;
    private string $github_repo;
    private string $current_version;
    private string $api_url;

    public function __construct( string $plugin_file, string $github_user, string $github_repo, string $current_version ) {
        $this->plugin_file     = $plugin_file;
        $this->slug            = plugin_basename( $plugin_file );
        $this->github_user     = $github_user;
        $this->github_repo     = $github_repo;
        $this->current_version = $current_version;
        $this->api_url         = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection',             [ $this, 'fix_directory_name' ], 10, 4 );
    }

    /**
     * Recupera i dati dell'ultima release da GitHub.
     * Cache propria (transient wpfpc_github_release) — non tocca update_plugins.
     */
    private function get_release_data(): ?array {
        $cache_key = 'wpfpc_github_release';
        $cached    = get_transient( $cache_key );

        // get_transient restituisce false se non esiste, array vuoto [] se la chiamata
        // precedente è fallita (lo usiamo come "negative cache")
        if ( $cached !== false ) {
            return ! empty( $cached ) ? $cached : null;
        }

        $response = wp_remote_get( $this->api_url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            ],
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $cache_key, [], HOUR_IN_SECONDS ); // negative cache 1h
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['tag_name'] ) ) {
            set_transient( $cache_key, [], HOUR_IN_SECONDS );
            return null;
        }

        set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
        return $data;
    }

    private function build_update_object( array $release, string $latest ): object {
        $zip_url = $release['zipball_url'];
        foreach ( $release['assets'] ?? [] as $asset ) {
            if ( str_ends_with( $asset['name'], '.zip' ) ) {
                $zip_url = $asset['browser_download_url'];
                break;
            }
        }

        return (object) [
            'slug'        => dirname( $this->slug ),
            'plugin'      => $this->slug,
            'new_version' => $latest,
            'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
            'package'     => $zip_url,
            'tested'      => get_bloginfo( 'version' ),
        ];
    }

    /**
     * Aggancia il controllo al filtro standard di WordPress.
     * NON chiama mai get_site_transient / set_site_transient.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        // Assicura che il nostro plugin sia nell'array checked
        if ( ! isset( $transient->checked[ $this->slug ] ) ) {
            $transient->checked[ $this->slug ] = $this->current_version;
        }

        $release = $this->get_release_data();
        if ( ! $release ) return $transient;

        $latest = ltrim( $release['tag_name'], 'vV' );

        if ( version_compare( $latest, $this->current_version, '>' ) ) {
            $transient->response[ $this->slug ] = $this->build_update_object( $release, $latest );
        } else {
            unset( $transient->response[ $this->slug ] );
        }

        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->slug ) ) return $result;

        $release = $this->get_release_data();
        if ( ! $release ) return $result;

        $latest = ltrim( $release['tag_name'], 'vV' );

        return (object) [
            'name'          => 'WPForms – Province e Comuni Italiani',
            'slug'          => dirname( $this->slug ),
            'version'       => $latest,
            'author'        => '<a href="https://github.com/' . $this->github_user . '">CreativeMetrics</a>',
            'homepage'      => "https://github.com/{$this->github_user}/{$this->github_repo}",
            'requires'      => '6.0',
            'tested'        => get_bloginfo( 'version' ),
            'last_updated'  => $release['published_at'] ?? '',
            'sections'      => [
                'description' => 'Popola automaticamente province e comuni italiani in WPForms con combobox ricercabile, CAP automatico e validazione server.',
                'changelog'   => nl2br( esc_html( $release['body'] ?? 'Vedi le release su GitHub.' ) ),
            ],
            'download_link' => $release['zipball_url'],
        ];
    }

    public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) return $source;

        $correct = dirname( $this->slug );
        $new     = trailingslashit( $remote_source ) . $correct . '/';

        if ( $source !== $new ) {
            global $wp_filesystem;
            $wp_filesystem->move( $source, $new );
        }

        return $new;
    }
}
