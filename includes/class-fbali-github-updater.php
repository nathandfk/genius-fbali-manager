<?php
/**
 * Mise à jour automatique du plugin depuis GitHub.
 *
 * Fonctionnement :
 *  - Compare la version installée avec le tag de la dernière release GitHub.
 *  - Si une nouvelle version existe, elle apparaît dans Tableau de bord → Mises à jour.
 *  - Le ZIP de la release est téléchargé et installé automatiquement.
 *
 * Pré-requis côté GitHub :
 *  - Le dépôt doit être PUBLIC, OU un token personnel doit être fourni.
 *  - Chaque release doit contenir un asset ZIP du plugin (ou utiliser le ZIP auto de GitHub).
 *  - Le tag de la release doit correspondre à la version du plugin (ex : "1.0.1").
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Fbali_Github_Updater {

    private string $plugin_file;
    private string $plugin_slug;
    private string $github_user;
    private string $github_repo;
    private string $github_token; // optionnel pour les dépôts privés
    private string $current_version;
    private ?object $github_response = null;

    public function __construct( string $plugin_file, string $github_user, string $github_repo, string $github_token = '' ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->github_user     = $github_user;
        $this->github_repo     = $github_repo;
        $this->github_token    = $github_token;
        $this->current_version = FWM_VERSION;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install',                 [ $this, 'post_install' ], 10, 3 );
    }

    // ------------------------------------------------------------------
    // Récupère la dernière release GitHub (mis en cache 12 h)
    // ------------------------------------------------------------------
    private function get_github_release(): ?object {
        if ( $this->github_response !== null ) {
            return $this->github_response;
        }

        $cache_key = 'fwm_github_release';
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            $this->github_response = $cached;
            return $cached;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_user,
            $this->github_repo
        );

        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            ],
        ];

        if ( ! empty( $this->github_token ) ) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }

        $response = wp_remote_get( $api_url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $body ) || ! isset( $body->tag_name ) ) {
            return null;
        }

        set_transient( $cache_key, $body, 12 * HOUR_IN_SECONDS );
        $this->github_response = $body;

        return $body;
    }

    // ------------------------------------------------------------------
    // Nettoie un tag GitHub pour en faire un numéro de version propre
    // ------------------------------------------------------------------
    private function clean_tag( string $tag ): string {
        return ltrim( $tag, 'v' );
    }

    // ------------------------------------------------------------------
    // URL du ZIP à télécharger
    // ------------------------------------------------------------------
    private function get_zip_url( object $release ): string {
        // Priorité : asset ZIP explicite dans la release
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( str_ends_with( $asset->name, '.zip' ) ) {
                    return $asset->browser_download_url;
                }
            }
        }
        // Fallback : ZIP auto-généré par GitHub
        return $release->zipball_url;
    }

    // ------------------------------------------------------------------
    // Injecte la mise à jour dans le transient WordPress
    // ------------------------------------------------------------------
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_github_release();
        if ( ! $release ) {
            return $transient;
        }

        $latest_version = $this->clean_tag( $release->tag_name );

        if ( version_compare( $latest_version, $this->current_version, '>' ) ) {
            $obj              = new stdClass();
            $obj->slug        = dirname( $this->plugin_slug );
            $obj->plugin      = $this->plugin_slug;
            $obj->new_version = $latest_version;
            $obj->url         = sprintf( 'https://github.com/%s/%s', $this->github_user, $this->github_repo );
            $obj->package     = $this->get_zip_url( $release );
            $obj->tested      = get_bloginfo( 'version' );
            $obj->requires    = '5.8';
            $obj->requires_php = '7.4';

            $transient->response[ $this->plugin_slug ] = $obj;
        }

        return $transient;
    }

    // ------------------------------------------------------------------
    // Fournit les infos de la popup "Voir les détails de la version"
    // ------------------------------------------------------------------
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $release = $this->get_github_release();
        if ( ! $release ) {
            return $result;
        }

        $info                = new stdClass();
        $info->name          = 'Fbali Webhook Manager';
        $info->slug          = dirname( $this->plugin_slug );
        $info->version       = $this->clean_tag( $release->tag_name );
        $info->author        = sprintf( '<a href="https://github.com/%s">%s</a>', $this->github_user, $this->github_user );
        $info->homepage      = sprintf( 'https://github.com/%s/%s', $this->github_user, $this->github_repo );
        $info->requires      = '5.8';
        $info->tested        = get_bloginfo( 'version' );
        $info->requires_php  = '7.4';
        $info->download_link = $this->get_zip_url( $release );
        $info->sections      = [
            'description' => $release->body ?? 'Mise à jour disponible depuis GitHub.',
            'changelog'   => '<p>' . nl2br( esc_html( $release->body ?? '' ) ) . '</p>',
        ];

        return $info;
    }

    // ------------------------------------------------------------------
    // Après installation : renomme le dossier dézippé correctement
    // ------------------------------------------------------------------
    public function post_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $response;
        }

        $plugin_dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->plugin_slug );
        $wp_filesystem->move( $result['destination'], $plugin_dir, true );
        $result['destination'] = $plugin_dir;

        if ( is_plugin_active( $this->plugin_slug ) ) {
            activate_plugin( $this->plugin_slug );
        }

        // Invalide le cache pour forcer la relecture
        delete_transient( 'fwm_github_release' );

        return $result;
    }
}
