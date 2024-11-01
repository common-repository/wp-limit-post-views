<?php
    /*
        Plugin Name: WP Limit Post Views
        Plugin URI:  https://plugins.maennche.com/wp-limit-post-views
        Description: Limit Visitor Post Views 
        Version:     1.0.5
        Author:      Matthew Maennche
        Author URI:  https://matthew.maennche.com/
        License:     GPLv3
        License URI: https://www.gnu.org/licenses/gpl.html

        WP Limit Post Views is free software: you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation, either version 2 of the License, or
        any later version.

        WP Limit Post Views is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
        GNU General Public License for more details.

        You should have received a copy of the GNU General Public License
        along with WP Limit Post View. If not, see https://www.gnu.org/licenses/gpl.html.
    */

    if ( !function_exists( 'add_action' ) ){
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden' );
        exit();
    }

    if ( !function_exists( 'add_filter' ) ){
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden' );
        exit();
    }
  

  
    /* ***************************** Check/Set Globals *************************** */
    define( 'WPLPV_VERSION', '1.0.3' );

    if ( ! defined( 'WPLPV_FILE' ) ) {
          define( 'WPLPV_FILE', __FILE__ );
    }    
    
    if ( !defined( 'WPLPV_URL' ) ) {
        define( 'WPLPV_URL', plugin_dir_url( WPLPV_FILE ) );
    }

    if ( !defined( 'WPLPV_PATH' ) ) {
        define( 'WPLPV_PATH', plugin_dir_path( WPLPV_FILE ) );
    }    
    
    if ( !defined( 'WPLPV_LPV_LIMIT_PERIOD' ) ) {
        define( 'WPLPV_LPV_LIMIT_PERIOD', 'hour' );
    }

    if ( !defined( 'WPLPV_LPV_USE_JAVASCRIPT' ) ) {
        define( 'WPLPV_LPV_USE_JAVASCRIPT', false );
    }    
    
    global $wplpv, $wplpv_activated;
    $wplpv_activated = false;
    
    
    
    /* ***************************** Establish Hooks *************************** */
    register_activation_hook( WPLPV_FILE, 'WPLPV_Activate' );
    register_deactivation_hook( WPLPV_FILE, 'WPLPV_Deactivate' );
    add_action( 'init', 'WPLPV_Initialize' );
    add_action( 'admin_init', 'WPLPV_Register_Settings' );
    add_action( 'admin_menu', 'WPLPV_List_Admin_Pages' );
    add_filter( 'plugin_action_links_'.plugin_basename(WPLPV_FILE), 'WPLPV_Settings_Link' );

    
    
    
    /* ***************************** Include Function Files *************************** */
    require_once( WPLPV_PATH . '/includes/coreFunctions.php' );
    require_once( WPLPV_PATH . '/includes/cookie-class.php' );
    
    if(get_option( 'wplpv-using-pmppro' ) === "y"){
        require_once( WPLPV_PATH . '/includes/pmpProHandler.php' );
    }
    