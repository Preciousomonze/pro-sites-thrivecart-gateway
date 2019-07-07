<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}    
    class PK_Prosites_Thrivecart_Shortcodes {
        public function __construct() {
            add_action('init', function(){
                add_shortcode( PK_PS_BLOG_RED_SHORTCODE, array( $this, 'blog_checkout_red' ) );  
            });
        }

        /**
         * redirects to checkout
         *
         * @param array $atts
         * @param string $content
         * @return string
         */
         public function blog_checkout_red($atts,$content = null){
             global $psts;
             $url = isset($_GET['domain']) ? trim($_GET['domain']) : '';
             if(empty($url))
                return '';
             $b_id = get_blog_id_from_url($url);
            $checkout_page = get_permalink( $psts->get_setting( 'checkout_page' ) );
            $checkout_page = add_query_arg( array(
                'action' => 'complete',
                'token' => '',
                'PayerID' => '',
                'bid' => $b_id
            ), $checkout_page );
            wp_safe_redirect( $checkout_page );
            exit;
         }

    }
new PK_Prosites_Thrivecart_Shortcodes();
