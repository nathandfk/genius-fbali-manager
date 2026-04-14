<?php
/**
 * Modifie le payload webhook des commandes pour injecter les attributs Fbali.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Fbali_Webhook_Order {

    public static function init() {
        add_filter( 'woocommerce_webhook_payload', [ __CLASS__, 'modify_payload' ], 99, 4 );
    }

    public static function modify_payload( $payload, $resource, $resource_id, $webhook_id ) {
        if ( $resource !== 'order' ) {
            return $payload;
        }

        $order = wc_get_order( $resource_id );
        if ( ! $order ) {
            return $payload;
        }

        error_log( '[FWM] Order payload AVANT : ' . print_r( $payload, true ) );

        $line_items = $payload['line_items'];

        foreach ( $line_items as $line_item_id => $line_item ) {
            $meta_data    = $line_item['meta_data'];
            $variation_id = $line_item['variation_id'];
            $fbali_taille = $variation_id ? get_post_meta( $variation_id, '_fbali_taille', true ) : '';
            $parent1      = $variation_id ? wp_get_post_parent_id( $variation_id ) : 0;
            $parent2      = $fbali_taille ? wp_get_post_parent_id( $fbali_taille )  : 0;
            $new_meta_data = [];

            // --- genius_taille_fbali : taille Fbali principale ---
            $genius_product = wc_get_product( ! empty( $fbali_taille ) ? $fbali_taille : $variation_id );
            if ( $genius_product ) {
                $pa_taille       = $genius_product->get_attribute( 'pa_taille' );
                $new_meta_data[] = [
                    'id'            => ! empty( $fbali_taille ) ? $fbali_taille : $variation_id,
                    'key'           => 'genius_taille_fbali',
                    'display_key'   => 'Taille Fbali',
                    'value'         => sanitize_text_field( $pa_taille ),
                    'display_value' => $pa_taille,
                ];
            }

            // --- Traitement de chaque meta existante ---
            foreach ( $meta_data as $meta_data_item ) {
                if ( ! empty( $fbali_taille ) && $parent1 && $parent2 && $parent1 === $parent2 ) {
                    if ( $meta_data_item['key'] === 'pa_couleur' ) {
                        $color             = $meta_data_item;
                        $color['key']      = 'genius_color';
                        $color['display_key'] = 'Color';
                        $new_meta_data[]   = $meta_data_item;
                        $new_meta_data[]   = $color;

                    } elseif ( $meta_data_item['key'] === 'pa_taille' ) {
                        $label                = $meta_data_item;
                        $label['key']         = 'genius_label';
                        $label['display_key'] = 'Label';
                        $new_meta_data[]      = $meta_data_item;
                        $new_meta_data[]      = $label;

                        // genius_size : taille de la variation Fbali cible
                        $variation_fbali = wc_get_product( $fbali_taille );
                        if ( $variation_fbali ) {
                            $pa_taille_fbali = $variation_fbali->get_attribute( 'pa_taille' );
                            if ( ! empty( $pa_taille_fbali ) ) {
                                $new_meta_data[] = [
                                    'id'            => $fbali_taille,
                                    'key'           => 'genius_size',
                                    'display_key'   => 'Size',
                                    'value'         => sanitize_text_field( $pa_taille_fbali ),
                                    'display_value' => $pa_taille_fbali,
                                ];
                            }
                        }
                    } else {
                        $new_meta_data[] = $meta_data_item;
                    }
                } else {
                    $new_meta_data[] = $meta_data_item;
                }
            }

            // --- Mise à jour du line_item dans le payload ---
            if ( ! empty( $new_meta_data ) ) {
                if ( ! empty( $fbali_taille ) && $parent1 && $parent2 && $parent1 === $parent2 ) {
                    $payload['line_items'][ $line_item_id ]['variation_id'] = intval( $fbali_taille );
                }
                $payload['line_items'][ $line_item_id ]['meta_data'] = $new_meta_data;
            }
        }

        error_log( '[FWM] Order payload APRÈS : ' . print_r( $payload, true ) );

        return $payload;
    }
}
