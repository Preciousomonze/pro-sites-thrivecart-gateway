<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
class PK_Prosite_Thrivecart_Dependencies{
    private static $active_plugins;
    
    public static function init() {
        self::$active_plugins = (array) get_option('active_plugins', array());
        if (is_multisite()) {
            self::$active_plugins = array_merge(self::$active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
    }
    
    /**
     * Check if prosites exist
     * @return Boolean
     */
    public static function prosites_active_check() {
        if (!self::$active_plugins) {
            self::init();
        }
        return in_array('pro-sites/pro-sites.php', self::$active_plugins) || array_key_exists('pro-sites/pro-sites.php', self::$active_plugins);
    }

    /**
     * Check if prosites is active
     * @return Boolean
     */
    public static function is_prosites_active() {
        return self::prosites_active_check();
    }
}