<?php
    /* Does User Belong Here */
    if ( ! defined( 'WPLPV_VERSION' ) ) {
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden' );
        exit();
    }
  
    if(!current_user_can('administrator') ){
        die( "Insuffecient Permissions" );
    }
    
    
    if (isset( $_POST['wplpv-clear-cache-nonce'] ) && wp_verify_nonce( $_POST['wplpv-clear-cache-nonce'], 'wplpv-cache-nonce' ) ) {
        WPLPV_Clear_Tracking_Cache();   
    }
  
 
    if (isset( $_POST['wplpv-save-settings-nonce'] ) && wp_verify_nonce( $_POST['wplpv-save-settings-nonce'], 'wplpv-save-nonce' ) ) {
        if ( isset( $_POST['wplpv-post-view-count']) && is_numeric( $_POST['wplpv-post-view-count'] )) {
            update_option('wplpv-post-view-count', sanitize_text_field( $_POST['wplpv-post-view-count'] ));
        }

        if ( isset( $_POST['wplpv-post-view-count-unit'] ) && ctype_alpha( $_POST['wplpv-post-view-count-unit'] ) ) {
            update_option('wplpv-post-view-count-unit', sanitize_text_field( $_POST['wplpv-post-view-count-unit'] ));
        }

        if ( isset( $_POST['wplpv-redirect-on-limit'] ) && ctype_alpha( $_POST['wplpv-redirect-on-limit'] ) ) {
            update_option('wplpv-redirect-on-limit', sanitize_text_field( $_POST['wplpv-redirect-on-limit'] ) );
        }

        if ( isset( $_POST['wplpv-redirect-location'] ) && filter_var($_POST['wplpv-redirect-location'], FILTER_VALIDATE_URL) ) {
            update_option('wplpv-redirect-location', sanitize_text_field( $_POST['wplpv-redirect-location'] ) );
        }   

        if ( isset( $_POST['wplpv-use-js'] ) && ctype_alpha( $_POST['wplpv-use-js'] ) ) {
            update_option('wplpv-use-js', sanitize_text_field( $_POST['wplpv-use-js'] ) );
        }     

        //If using PMP PRO Integration we check for updated values
        if(WPLPV_is_pmppro_present()){ 
            if(function_exists( 'pmpro_getAllLevels' )){
             
                if ( isset( $_POST['wplpv-using-pmppro'] ) && ctype_alpha( $_POST['wplpv-using-pmppro'] ) ) {
                    update_option('wplpv-using-pmppro', sanitize_text_field( $_POST['wplpv-using-pmppro'] ) );
                }   

                // Register limits settings fields.
                $levels = pmpro_getAllLevels( true, true );
                $levels[0] = new stdClass();
                $levels[0]->name = __('Non-members', 'pmpro');
                asort($levels);

                foreach($levels as $id => $level ) {
                    $tempSaveArray = array();
                    $tempSaveArray2 = array();
                    if ( isset( $_POST['wplpv_pmppro_limit_' . $id])) {
                        $tempSaveArray = $_POST['wplpv_pmppro_limit_' . $id];
                        if(is_numeric( $tempSaveArray['views'])){ 
                            $tempSaveArray2['views'] = sanitize_text_field( $tempSaveArray['views'] );
                        }
                    }

                    if(ctype_alpha( $tempSaveArray['period'])){ 
                        $tempSaveArray2['period'] = sanitize_text_field( $tempSaveArray['period'] );
                    }
                    
                    update_option('wplpv_pmppro_limit_' . $id, $tempSaveArray2);
                }
            }
        }
    }      
?>

<div class="wplpv-wrapper">
    <h2>WP Limit Post Views Settings</h2>
    </br>
    
    <form method="post" >
        <?php
            wp_nonce_field( 'wplpv-cache-nonce','wplpv-clear-cache-nonce' );
            submit_button('Clear Visitor Cache'); 
        ?>
    </form>
    
    <form method="post" >
        <?php settings_fields( 'wplpv-settings-group' ); ?>
        <?php do_settings_sections( 'wplpv-settings-group' );  ?>  

        <?php
            wp_nonce_field( 'wplpv-save-nonce','wplpv-save-settings-nonce' );
            submit_button(); 
        ?>
    </form>
</div>



<?php
    function wplpv_settings_field_limits( $level_id ) {
	$limit = get_option( 'wplpv_pmppro_limit_' . $level_id );
        ?>
            <input size="2" type="text" id="level_<?php echo $level_id; ?>_views" name="wplpv_pmppro_limit_<?php echo $level_id; ?>[views]" value="<?php echo $limit['views']; ?>">
            <?php _e( ' views per ', 'pmprolpv' ); ?>

            <select name="wplpv_pmppro_limit_<?php echo $level_id; ?>[period]" id="level_<?php echo $level_id; ?>_period">
                <option value="day" <?php selected( $limit['period'], 'day' ); ?>><?php _e( 'Day', 'wplpv' ); ?></option>
                <option value="week" <?php selected( $limit['period'], 'week' ); ?>><?php _e( 'Week', 'wplpv' ); ?></option>
                <option value="month" <?php selected( $limit['period'], 'month' ); ?>><?php _e( 'Month', 'wplpv' ); ?></option>
            </select>
        <?php
    }

    function wplpv_settings_field_yesno( $fieldname ) {
	$limit = get_option( $fieldname);
        ?>
            <select name="<?php echo $fieldname; ?>" id="<?php echo $fieldname; ?>">
                <option value="n" <?php selected( $limit, 'n' ); ?>><?php _e( 'No', 'wplpv' ); ?></option>
                <option value="y" <?php selected( $limit, 'y' ); ?>><?php _e( 'Yes', 'wplpv' ); ?></option>
            </select>
        <?php
    }  
    
    function wplpv_settings_field_text( $fieldname ) {
	$limit = get_option( $fieldname);
        ?>
            <input type="text" name="<?php echo $fieldname; ?>" value="<?php echo get_option( $fieldname ); ?>" />
        <?php
    }    
    
    function wplpv_settings_field_unit( $fieldname ) {
	$limit = get_option( $fieldname);
        ?>
            <select name="<?php echo $fieldname; ?>" id="<?php echo $fieldname; ?>">
                <option value="day" <?php selected( $limit, 'day' ); ?>><?php _e( 'Day', 'wplpv' ); ?></option>
                <option value="week" <?php selected( $limit, 'week' ); ?>><?php _e( 'Week', 'wplpv' ); ?></option>
                <option value="month" <?php selected( $limit, 'month' ); ?>><?php _e( 'Month', 'wplpv' ); ?></option>
            </select>
        <?php
    }
    
    
    function wplpv_settings_field_redirect_page() {
	global $pmpro_pages;
	$page_id = get_option('wplpv-redirect-location');

	// Default to Levels page
	if(empty($page_id)){
            $page_id = $pmpro_pages['levels'];
        }
        
        wp_dropdown_pages(array(
            'selected' => $page_id,
            'name' => 'wplpv-redirect-location'
        ));
    }