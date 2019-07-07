<?php
/**
* Plugin Name: Pro Sites - ThriveCart Gateway
* Plugin URI: https://github.com/preciousomonze/pro-sites-thrivecart-gateway
* Description: A thrivecart gateway for Pro Sites plugin that makes use of thrivecart webhooks for communication. <strong>Make sure you flush/resave your permalink settings after activation to work properly
* Author: Precious Omonzejele @ CodeExplorer, Panos Lyrakis @ WPMUDEV
* Author URI: https://twitter.com/preciousomonze
* Version: 1.0
* License: GPLv2 or later
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// include dependencies file
if(!class_exists('PK_Prosite_Thrivecart_Dependencies')){
    include_once dirname(__FILE__) . '/inc/class-dependencies.php';
}
define('PK_PS_BLOG_RED_SHORTCODE','ps_tc_blog_red_checkout');
/*
define('PK_PS_BLOG_URL_SHORTCODE','ps_tc_blog_url_link');
define('PK_PS_BLOG_USERNAME_SHORTCODE','ps_tc_username');
define('PK_PS_BLOG_ADMIN_URL_SHORTCODE','ps_tc_blog_admin_url_link');
define('PK_PS_BLOG_ADMIN_PASSWORD_SHORTCODE','ps_tc_blog_admin_password_link');
*/
if(PK_Prosite_Thrivecart_Dependencies::is_prosites_active()){
    // Include the main class.
    if(!class_exists('PK_Prosites_Thrivecart_Gateway')){
        include_once dirname(__FILE__) . '/inc/class-prosites-thrivecart-gateway.php';
    }
    if(!class_exists('PK_Prosites_Thrivecart_Shortcodes')){
        include_once dirname(__FILE__) . '/inc/class-shortcodes.php';
    }
    
}
else{
    function pk_prosite_thrivecart_notice(){
        echo '<div class="error"><p>';
        _e('<strong>Pro Sites - ThriveCart Gateway</strong> plugin requires <a href="https://premium.wpmudev.org/project/pro-sites/" target="_blank">Pro Sites</a> plugin to be active!', 'psts');
        echo '</p></div>';
    }
    add_action('admin_notices', 'pk_prosite_thrivecart_notice', 15);
    add_action('network_admin_notices', 'pk_prosite_thrivecart_notice', 10);
}
