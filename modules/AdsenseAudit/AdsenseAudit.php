<?php

namespace ESEO\Modules\AdsenseAudit;

class AdsenseAudit {

    public function init() {
        add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widget' ] );
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'eseo_adsense_audit_widget',
            'AdSense Readiness Audit',
            [ $this, 'render_widget' ]
        );
    }

    public function render_widget() {
        $has_privacy = (bool) get_option( 'wp_page_for_privacy_policy' );
        
        $about_page = get_page_by_title( 'About Us' ) ?: get_page_by_title( 'About' );
        $contact_page = get_page_by_title( 'Contact Us' ) ?: get_page_by_title( 'Contact' );

        echo '<ul style="list-style-type:none; padding:0; margin:0;">';
        echo '<li style="margin-bottom:8px;">' . ( $has_privacy ? '✅' : '❌' ) . ' Privacy Policy Page</li>';
        echo '<li style="margin-bottom:8px;">' . ( $about_page ? '✅' : '❌' ) . ' About Page</li>';
        echo '<li style="margin-bottom:8px;">' . ( $contact_page ? '✅' : '❌' ) . ' Contact Page</li>';
        echo '</ul>';
        
        if ( ! $has_privacy || ! $about_page || ! $contact_page ) {
            echo '<p style="color:#d63638; font-weight:bold;">Status: Not Ready for AdSense. Please create the missing essential pages.</p>';
        } else {
            echo '<p style="color:#00a32a; font-weight:bold;">Status: Looks Good! Essential pages are present.</p>';
        }
    }
}
