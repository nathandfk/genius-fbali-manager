<?php
/**
 * Gère le champ personnalisé "Taille Fbali" sur chaque variation produit.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Fbali_Variation_Field {

    public static function init() {
        add_action( 'woocommerce_variation_options_pricing', [ __CLASS__, 'render_field' ], 10, 3 );
        add_action( 'save_post', [ __CLASS__, 'save_field' ], 20, 2 );
    }

    /**
     * Affiche le champ dans l'onglet de tarification de chaque variation.
     */
    public static function render_field( $loop, $variation_data, $variation ) {
        echo '<div class="form-row form-row-full fbali-custom-field">';
        woocommerce_wp_text_input( [
            'id'          => 'fbali_taille_' . $loop,
            'name'        => 'fbali_taille_' . $loop,
            'label'       => __( 'Taille Fbali (remplace pa_taille)', 'fbali-webhook-manager' ),
            'desc_tip'    => true,
            'description' => __( 'Cette taille sera utilisée à la place de pa_taille pour Fbali si renseignée.', 'fbali-webhook-manager' ),
            'value'       => get_post_meta( $variation->ID, '_fbali_taille', true ),
        ] );
        echo '</div>';
    }

    /**
     * Sauvegarde la valeur du champ lors de l'enregistrement du produit.
     */
    public static function save_field( $post_id, $post ) {
        if ( $post->post_type !== 'product' ) {
            return;
        }
        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['variable_post_id'] ) ) {
            return;
        }

        foreach ( $_POST['variable_post_id'] as $index => $variation_id ) {
            if ( isset( $_POST[ 'fbali_taille_' . $index ] ) ) {
                $valeur = sanitize_text_field( $_POST[ 'fbali_taille_' . $index ] );
                if ( ! empty( $valeur ) ) {
                    update_post_meta( (int) $variation_id, '_fbali_taille', $valeur );
                } else {
                    delete_post_meta( (int) $variation_id, '_fbali_taille' );
                }
            }
        }
    }
}
