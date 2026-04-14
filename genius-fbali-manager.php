<?php
/**
 * Plugin Name:       Genius Fbali Manager
 * Plugin URI:        https://github.com/VOTRE-USERNAME/fbali-webhook-manager
 * Description:       Gère les champs personnalisés de taille Fbali sur les variations WooCommerce et modifie les payloads webhook.
 * Version:           1.0.1
 * Author:            Ingenius
 * Author URI:        https://ingenius.agency
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       fbali-webhook-manager
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes du plugin
define( 'FWM_VERSION',     '1.0.1' );
define( 'FWM_PLUGIN_FILE', __FILE__ );
define( 'FWM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'FWM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'FWM_GITHUB_USER', 'nathandfk' );           // ← À modifier
define( 'FWM_GITHUB_REPO', 'genius-fbali-manager' );    // ← À modifier si besoin

// Chargement des modules
require_once FWM_PLUGIN_DIR . 'includes/class-fbali-variation-field.php';
require_once FWM_PLUGIN_DIR . 'includes/class-fbali-webhook-order.php';
require_once FWM_PLUGIN_DIR . 'includes/class-fbali-webhook-product.php';
require_once FWM_PLUGIN_DIR . 'includes/class-fbali-github-updater.php';

// Démarrage
add_action( 'plugins_loaded', 'fwm_init' );
function fwm_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Fbali Webhook Manager</strong> nécessite WooCommerce. Veuillez l\'activer.</p></div>';
        } );
        return;
    }

    Fbali_Variation_Field::init();
    Fbali_Webhook_Order::init();
    Fbali_Webhook_Product::init();
}

// Updater GitHub (admin uniquement)
if ( is_admin() ) {
    add_action( 'init', function () {
        new Fbali_Github_Updater( FWM_PLUGIN_FILE, FWM_GITHUB_USER, FWM_GITHUB_REPO );
    } );
}
