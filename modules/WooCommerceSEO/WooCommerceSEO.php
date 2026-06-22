<?php

namespace ESEO\Modules\WooCommerceSEO;

class WooCommerceSEO {

    public function init() {
        // Only run if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // We can hook into WC schema or override titles specifically for products
        add_filter( 'woocommerce_structured_data_product', [ $this, 'enhance_product_schema' ], 10, 2 );
    }

    public function enhance_product_schema( $markup, $product ) {
        if ( empty( $markup ) ) {
            return $markup;
        }

        // Add additional SEO attributes to WooCommerce default schema
        $markup['brand'] = [
            '@type' => 'Brand',
            'name'  => get_bloginfo( 'name' ) // fallback to site name
        ];

        return $markup;
    }
}
