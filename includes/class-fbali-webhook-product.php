<?php
/**
 * Modifie le payload webhook des produits (variations) pour injecter les attributs Fbali.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Fbali_Webhook_Product {

    public static function init() {
        add_filter( 'woocommerce_webhook_payload', [ __CLASS__, 'modify_payload' ], 99, 4 );
    }

    public static function modify_payload( $payload, $resource, $resource_id, $webhook_id ) {
        if ( $resource !== 'product' ) {
            return $payload;
        }
        if ( ! isset( $payload['type'] ) || $payload['type'] !== 'variation' ) {
            return $payload;
        }

        $current_variation_id = (int) $payload['id'];
        $parent_id            = (int) $payload['parent_id'];
        $new_attributes       = $payload['attributes'];

        $parent_product = wc_get_product( $parent_id );
        if ( ! $parent_product ) {
            return $payload;
        }

        // ------------------------------------------------------------------
        // 1. Cherche quelle variation source pointe vers la variation actuelle
        //    via le meta _fbali_taille.
        // ------------------------------------------------------------------
        $source_variation_id = null;
        foreach ( $parent_product->get_children() as $vid ) {
            $fbali_taille = get_post_meta( $vid, '_fbali_taille', true );
            if ( ! empty( $fbali_taille ) && intval( $fbali_taille ) === $current_variation_id ) {
                $source_variation_id = (int) $vid;
                break;
            }
        }

        // ------------------------------------------------------------------
        // 2. Ajoute genius_taille_fbali (taille de la source ou de l'actuelle)
        // ------------------------------------------------------------------
        $genius_product = wc_get_product( $source_variation_id ? $source_variation_id : $current_variation_id );
        if ( $genius_product ) {
            $new_attributes[] = [
                'id'     => count( $new_attributes ) + 1,
                'name'   => 'Taille Fbali',
                'slug'   => 'genius_taille_fbali',
                'option' => $genius_product->get_attribute( 'pa_taille' ),
            ];
            $payload['attributes'] = $new_attributes;
        }

        // Si aucune variation source ne pointe vers celle-ci → on s'arrête ici
        if ( ! $source_variation_id ) {
            return $payload;
        }

        $source_variation = wc_get_product( $source_variation_id );
        if ( ! $source_variation ) {
            return $payload;
        }

        error_log( '[FWM] Product payload AVANT : ' . print_r( $payload, true ) );

        $new_attributes  = $payload['attributes'];
        $source_color    = $source_variation->get_attribute( 'pa_couleur' );
        $source_label    = $source_variation->get_attribute( 'pa_taille' );
        $last_position   = count( $new_attributes );

        // ------------------------------------------------------------------
        // 3. Color (vient de la variation source)
        // ------------------------------------------------------------------
        if ( ! empty( $source_color ) ) {
            $last_position++;
            $new_attributes[] = [
                'id'     => $last_position,
                'name'   => 'Color',
                'slug'   => 'genius_color',
                'option' => $source_color,
            ];
        }

        // ------------------------------------------------------------------
        // 4. Label (vient de la variation source)
        // ------------------------------------------------------------------
        if ( ! empty( $source_label ) ) {
            $last_position++;
            $new_attributes[] = [
                'id'     => $last_position,
                'name'   => 'Label',
                'slug'   => 'genius_label',
                'option' => $source_label,
            ];
        }

        // ------------------------------------------------------------------
        // 5. Size (vient de la variation actuelle)
        // ------------------------------------------------------------------
        $current_variation = wc_get_product( $current_variation_id );
        if ( $current_variation ) {
            $size_attr = $current_variation->get_attribute( 'pa_taille' );
            if ( ! empty( $size_attr ) ) {
                $last_position++;
                $new_attributes[] = [
                    'id'     => $last_position,
                    'name'   => 'Size',
                    'slug'   => 'genius_size',
                    'option' => $size_attr,
                ];
            }
        }

        $payload['attributes'] = $new_attributes;

        error_log( '[FWM] Product payload APRÈS : ' . print_r( $payload, true ) );

        return $payload;
    }
}
