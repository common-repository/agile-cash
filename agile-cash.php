<?php
/*
Plugin Name: Agile Cash Crypto Payments for WooCommerce

Description: WooCommerce Plug-in that allows your customers to pay with supported cryptocurrencies.
Author: Agile Cash DC

Version: 1.8.3

Copyright: © 2019 Agile Cash DC (email : agilecash@mailbox.org)
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'AGC_init_gateways');
register_activation_hook(__FILE__, 'AGC_activate');
register_deactivation_hook(__FILE__, 'AGC_deactivate');
register_uninstall_hook(__FILE__, 'AGC_uninstall');

function AGC_init_gateways(){

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    define('AGILE_CASH_PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');
    define('AGILE_CASH_CRON_JOB_URL', plugins_url('', __FILE__) . '/src/AGC_Cron.php');
    define('AGC_VERSION', '1.8.1');
    
    // Vendor
    require_once(plugin_basename('src/vendor/bcmath_Utils.php'));
    require_once(plugin_basename('src/vendor/CurveFp.php'));
    require_once(plugin_basename('src/vendor/ElectrumHelper.php'));
    require_once(plugin_basename('src/vendor/gmp_Utils.php'));
    require_once(plugin_basename('src/vendor/NumberTheory.php'));
    require_once(plugin_basename('src/vendor/Point.php'));
    require_once(plugin_basename('src/vendor/CashAddress.php'));

    // Http
    require_once(plugin_basename('src/AGC_Exchange.php'));
    require_once(plugin_basename('src/AGC_Blockchain.php'));

    // Database
    require_once(plugin_basename('src/AGC_Carousel_Repo.php'));
    require_once(plugin_basename('src/AGC_Electrum_Repo.php'));
    require_once(plugin_basename('src/AGC_Payment_Repo.php'));

    // Simple Objects
    require_once(plugin_basename('src/AGC_Cryptocurrency.php'));
    require_once(plugin_basename('src/AGC_Transaction.php'));
    
    // Business Logic
    require_once(plugin_basename('src/AGC_Cryptocurrencies.php'));
    require_once(plugin_basename('src/AGC_Carousel.php'));
    require_once(plugin_basename('src/AGC_Electrum.php'));
    require_once(plugin_basename('src/AGC_Payment.php'));

    // Misc
    require_once(plugin_basename('src/AGC_Util.php'));
    require_once(plugin_basename('src/AGC_Hooks.php'));
    require_once(plugin_basename('src/AGC_Cron.php'));    
    require_once(plugin_basename('src/AGC_Postback_Settings_Helper.php'));    

    // Core
    require_once(plugin_basename('src/AGC_Gateway.php'));
    
    add_filter ('cron_schedules', 'AGC_add_interval');

    add_action('AGC_cron_hook', 'AGC_do_cron_job');
    add_action( 'woocommerce_process_shop_order_meta', 'AGC_update_database_when_admin_changes_order_status', 10, 2 );     
    
    if (!wp_next_scheduled('AGC_cron_hook')) {
        wp_schedule_event(time(), 'minutes_2', 'AGC_cron_hook');
    }
}

function AGC_add_interval ($schedules)
{
    $schedules['seconds_5'] = array('interval'=>5, 'display'=>'debug');
    $schedules['seconds_30'] = array('interval'=>30, 'display'=>'Bi-minutely');
    $schedules['minutes_1'] = array('interval'=>60, 'display'=>'Once every 1 minute');
    $schedules['minutes_2'] = array('interval'=>120, 'display'=>'Once every 2 minutes');

    return $schedules;
}

function AGC_activate() {
    if (!wp_next_scheduled('AGC_cron_hook')) {
        wp_schedule_event(time(), 'minutes_2', 'AGC_cron_hook');
    }
    
    AGC_create_mpk_address_table();
    AGC_create_payment_table();
    AGC_create_carousel_table();    
}

function AGC_deactivate() {
    wp_clear_scheduled_hook('AGC_cron_hook');    
}

function AGC_uninstall() {
    AGC_drop_mpk_address_table();
    AGC_drop_payment_table();
    AGC_drop_carousel_table();
}

function AGC_add_gateways($methods) {
    $methods[] = 'AGC_Gateway';

    return $methods;
}

function AGC_drop_mpk_address_table() {
    global $wpdb;
    $tableName = $wpdb->prefix . 'agc_electrum_addresses';    
    
    $query = "DROP TABLE IF EXISTS `$tableName`";
    $wpdb->query($query);
}

function AGC_drop_payment_table() {
    global $wpdb;    
    $tableName = $wpdb->prefix . 'agc_payments';    
    
    $query = "DROP TABLE IF EXISTS `$tableName`";
    $wpdb->query($query);
}

function AGC_drop_carousel_table() {
    global $wpdb;    
    $tableName = $wpdb->prefix . 'agc_carousel';    
    
    $query = "DROP TABLE IF EXISTS `$tableName`";
    $wpdb->query($query);
}

function AGC_create_mpk_address_table() {
    global $wpdb;
    $tableName = $wpdb->prefix . 'agc_electrum_addresses';    
    
    $query = "CREATE TABLE IF NOT EXISTS `$tableName` 
        (
            `id` bigint(12) unsigned NOT NULL AUTO_INCREMENT,
            `mpk` char(255) NOT NULL,
            `mpk_index` bigint(20) NOT NULL DEFAULT '0',
            `address` char(255) NOT NULL,
            `cryptocurrency` char(12) NOT NULL,
            `status` char(24)  NOT NULL DEFAULT 'error',
            `total_received` decimal( 16, 8 ) NOT NULL DEFAULT '0.00000000',
            `last_checked` bigint(20) NOT NULL DEFAULT '0',
            `assigned_at` bigint(20) NOT NULL DEFAULT '0',
            `order_id` bigint(10) NULL,
            `order_amount` decimal(16, 8) NOT NULL DEFAULT '0.00000000',
    
            PRIMARY KEY (`id`),
            UNIQUE KEY `address` (`address`),
            KEY `status` (`status`),
            KEY `mpk_index` (`mpk_index`),
            KEY `mpk` (`mpk`)
        );";

    $wpdb->query($query);
}

function AGC_create_payment_table() {
    global $wpdb;
    $tableName = $wpdb->prefix . 'agc_payments';    
    
    $query = "CREATE TABLE IF NOT EXISTS `$tableName`
        (
            `id` bigint(12) unsigned NOT NULL AUTO_INCREMENT,
            `address` char(255) NOT NULL,
            `cryptocurrency` char(12) NOT NULL,
            `status` char(24)  NOT NULL DEFAULT 'error',
            `ordered_at` bigint(20) NOT NULL DEFAULT '0',
            `order_id` bigint(10) NOT NULL DEFAULT '0',
            `order_amount` decimal(32, 18) NOT NULL DEFAULT '0.000000000000000000',
            `tx_hash` char(255) NULL,
    
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_payment` (`order_id`, `order_amount`),
            KEY `status` (`status`)
        );";

    $wpdb->query($query);
}

function AGC_create_carousel_table() {
    global $wpdb;
    $tableName = $wpdb->prefix . 'agc_carousel';    

    $query = "CREATE TABLE IF NOT EXISTS `$tableName`
        (
            `id` bigint(12) unsigned NOT NULL AUTO_INCREMENT,
            `cryptocurrency` char(12) NOT NULL,
            `current_index` bigint(20) NOT NULL DEFAULT '0',
            `buffer` text NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `cryptocurrency` (`cryptocurrency`)
        );";

    $wpdb->query($query);

    require_once( plugin_basename( 'src/AGC_Cryptocurrency.php' ) );
    require_once( plugin_basename( 'src/AGC_Carousel_Repo.php' ) );
    require_once( plugin_basename( 'src/AGC_Util.php' ) );
    require_once( plugin_basename( 'src/AGC_Cryptocurrencies.php' ) );
    
    AGC_Carousel_Repo::init();

    $cryptos = AGC_Cryptocurrencies::get();
    
    $settings = get_option('woocommerce_agc_gateway_settings');

    // if we find settings here we need to initialize the databases with the admin options for carousels    
    if ($settings) {
        foreach ($cryptos as $crypto) {
            if (!$crypto->has_electrum()) {
                if (array_key_exists($crypto->get_id() . '_carousel_enabled', $settings)) {
                    if ($settings[$crypto->get_id() . '_carousel_enabled'] === 'yes') {
                            $buffer = array();
                            $buffer[] = $settings[$crypto->get_id() . '_address'];
                            $buffer[] = $settings[$crypto->get_id() . '_address2'];
                            $buffer[] = $settings[$crypto->get_id() . '_address3'];
                            $buffer[] = $settings[$crypto->get_id() . '_address4'];
                            $buffer[] = $settings[$crypto->get_id() . '_address5'];
                            
                            $repo = new AGC_Carousel_Repo();
                            $repo->set_buffer($crypto->get_id(), $buffer);
                    }
                }
            }
        }
    }
}

add_filter('woocommerce_payment_gateways', 'AGC_add_gateways');

?>