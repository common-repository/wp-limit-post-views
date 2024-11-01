<?php
    /* Does User Belong Here */
    if ( ! defined( 'WPLPV_VERSION' ) ) {
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden' );
        exit();
    }
    
    
   //WP Limit Post Views - Activation
    function WPLPV_Activate(){
        add_option( 'wplpv-post-view-count', '', '', 'yes' );
        add_option( 'wplpv-post-view-count-unit', '', '', 'yes' );
        add_option( 'wplpv-redirect-on-limit', '', '', 'yes' );
        add_option( 'wplpv-redirect-location', '', '', 'yes' ); 
        add_option( 'wplpv-use-js', 'n', '', 'yes' ); 
        add_option( 'wplpv-using-pmppro', 'n', '', 'yes' ); 
        add_option( 'wplpv-db-version', '1.0.3', '', 'yes' ); 
        
        
        GLOBAL $wpdb;
        $wp_prefix = $wpdb->prefix;

        // The follow variables are used to define the table structure for new and upgrade installations.
        $create_tracking_table = ("CREATE TABLE " . $wp_prefix . "wplpv_tracking (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip varchar(39),
            browser varchar(40),
            user_agent varchar(2048),
            resource varchar(2048),
            content_id bigint(20),
            user_id bigint(20),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            level_id bigint(20),
            limit_period varchar(10),
            PRIMARY KEY  (ID)
        ) CHARSET=utf8");

        // This includes the dbDelta function from WordPress.
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create/update the plugin tables.
        dbDelta($create_tracking_table);

        if( !wp_next_scheduled( 'wplpv_daily' ) ) {  
           wp_schedule_event( time(), 'daily', 'wplpv_daily' );  
        }      

    }
    
    
    //#2. WP Limit Post Views - DeActivation
    function WPLPV_Deactivate(){
        delete_option( 'wplpv-post-view-count' );
        delete_option( 'wplpv-post-view-count-unit' );
        delete_option( 'wplpv-redirect-on-limit' );
        delete_option( 'wplpv-redirect-location' ); 
        delete_option( 'wplpv-use-js' ); 
        delete_option( 'wplpv-using-pmppro' );
        delete_option( 'wplpv-db-version' );
        
        // find out when the last event was scheduled
        $timestamp = wp_next_scheduled ('wplpv_daily');
        // unschedule previous event if any
        wp_unschedule_event ($timestamp, 'wplpv_daily');         
    }
    
    
    //#3. WP Limit Post Views - Initialize
    function WPLPV_Initialize(){

        //Do we need to update the db table?
        if( empty(get_option( 'wplpv-db-version' )) || get_option( 'wplpv-db-version' ) !== '1.0.5' ){
            //GLOBAL $wpdb;
            //$wp_prefix = $wpdb->prefix;

            if( !wp_next_scheduled( 'wplpv_daily' ) ) {  
               wp_schedule_event( time(), 'daily', 'wplpv_daily' );  
            } 
            
            update_option( 'wplpv-db-version', '1.0.5' ); 
        }
        
        
        
	// Set Level Limit - Check first for PMPPro
        if( WPLPV_is_pmppro_present() ){ 
            global $current_user;
            if(!empty($current_user->membership_level)){
                $level_id = $current_user->membership_level->id;
            }

            if ( empty( $level_id ) ){
                $level_id = 0;
            }

            $limit = get_option( 'wplpv_post_view_limit_' . $level_id );
            if(!empty($limit)) {
                define( 'WPLPV_VIEW_LIMIT', $limit['views'] );
                define( 'WPLPV_VIEW_LIMIT_PERIOD', $limit['period'] );
            }
        }
        
        //If still no limit set we pull the General Settings
        if ( ! defined( 'PMPRO_LPV_LIMIT' ) ) {
            define('WPLPV_VIEW_LIMIT', get_option( 'wplpv-post-view-count' ));
            define('WPLPV_VIEW_LIMIT_PERIOD', get_option( 'wplpv-post-view-count-unit' ));
	}        
        
    }  
    
    
    //#4. WP Limit Post Views - Register Admin Pages
    function WPLPV_List_Admin_Pages(){
        add_options_page('Limit Post Views', 'Limit Post Views', 'administrator', 'wp-limit-post-views', 'WPLPV_Admin_Settings_Page','dashicons-admin-generic');
    }
      
    
    //#5. WP Limit Post Views - Create Admin Settings Page
    function WPLPV_Admin_Settings_Page() {
        include WPLPV_PATH.'/views/settings.php';
    } 
    
    
    //#6. WP Limit Post Views  - Settings Link on Plugins Page
    function WPLPV_Settings_Link( $links ){ 
        $settings_link = '<a href="admin.php?page=wp-limit-post-views">Settings</a>'; 
        array_unshift( $links, $settings_link ); 
        return $links; 
    }

    
    //#7. WP Limit Post Views  - Register Settings Group
    function WPLPV_Register_Settings(){
        register_setting( 'wplpv-settings-group',  'wplpv-post-view-count' );
        register_setting( 'wplpv-settings-group', 'wplpv-post-view-count-unit' );
        register_setting( 'wplpv-settings-group', 'wplpv-redirect-on-limit' );
        register_setting( 'wplpv-settings-group', 'wplpv-redirect-location' );
        register_setting( 'wplpv-settings-group', 'wplpv-use-js' );
        register_setting( 'wplpv-settings-group', 'wplpv-using-pmppro' );
        
        add_settings_section('wplpv_settings_limits','General Settings','wplpv_settings_section_limits','wplpv-settings-group');        

            if(! WPLPV_is_pmppro_present()){ 
        
                // Register Count settings field.
                add_settings_field('wplpv-post-view-count','Post View Count Limit','wplpv_settings_field_text','wplpv-settings-group','wplpv_settings_limits','wplpv-post-view-count');
            
                // Register Count Unit settings field.
                add_settings_field('wplpv-post-view-count-unit','Post View Count Limit Unit','wplpv_settings_field_unit','wplpv-settings-group','wplpv_settings_limits','wplpv-post-view-count-unit');
                
            }
            
            // Register redirection settings field.
            add_settings_field('wplpv-redirect-on-limit','Redirect On Limit','wplpv_settings_field_yesno','wplpv-settings-group','wplpv_settings_limits','wplpv-redirect-on-limit');
        
            // Register redirect to settings field.
            add_settings_field('wplpv-redirect-location','Redirect to','wplpv_settings_field_redirect_page','wplpv-settings-group','wplpv_settings_limits');

            // Register JavaScript settings field.
            //add_settings_field('wplpv-use-js','Use JavaScript redirection','wplpv_settings_field_yesno','wplpv-settings-group','wplpv_settings_limits','wplpv-use-js');

        
        if(WPLPV_is_pmppro_present()){ 
            if(function_exists( 'pmpro_getAllLevels' )){

                // Register PMPPro settings section.
                add_settings_section('wplpv_settings_pmppro','PMP Pro Settings','wplpv_settings_section_pmppro','wplpv-settings-group');            

                    // Register PMP Pro settings field.
                    add_settings_field('wplpv-using-pmppro','Using PMP Pro','wplpv_settings_field_yesno','wplpv-settings-group','wplpv_settings_pmppro','wplpv-using-pmppro');

                    
                    // Register limits settings fields.
                    $levels = pmpro_getAllLevels( true, true );
                    $levels[0] = new stdClass();
                    $levels[0]->name = __('Non-members', 'pmpro');
                    asort($levels);

                    foreach($levels as $id => $level ) {
                        $title = $level->name;
                        add_settings_field('wplpv_post_view_limit_' . $id,$title,'wplpv_settings_field_limits','wplpv-settings-group','wplpv_settings_pmppro',$id);

                        // Register Plan Limit setting.
                        register_setting('wplpv-settings-group','wplpv_pmppro_limit_' . $id,'wplpv_settings_sanitize_limit');
                    }
        
            }
        }   
    }
    
    
     //#8. WP Limit Post Views - Is PMP Pro installed and Active  
    function WPLPV_is_pmppro_present() {
        
        if ( in_array( 'paid-memberships-pro/paid-memberships-pro.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            return true;
        } else {
            return false;
        }
    } 
    
    
    //#9. WP Limit Post Views - Clear Tracking Cache
    function WPLPV_Clear_Tracking_Cache(){
 
        global $wpdb;
        $wp_prefix = $wpdb->prefix;

        //Delete Old Views that are out of range
        $wpdb->query(
            $wpdb->prepare( "
                            DELETE FROM " . $wp_prefix . "wplpv_tracking
                            "   
                        )                  
        );

     

    }    
    
    
