<?php
    /* Does User Belong Here */
    if ( ! defined( 'WPLPV_VERSION' ) ) {
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden' );
        exit();
    }
    

    add_action( "wp", "WPLPV_pmpro_lpv_wp" );
    function WPLPV_pmpro_lpv_wp() {
        $fnRedirectOnLimit = get_option( 'wplpv-redirect-on-limit' );
        //$fnRedirectOnLimitLocation = get_option( 'wplpv-redirect-location' );
        //$fnUseJS = get_option( 'wplpv-use-js' );
        $fnInitPMPPro = get_option( 'wplpv-using-pmppro' );


        if ( $fnInitPMPPro === "y" && is_numeric( WPLPV_VIEW_LIMIT ) && WPLPV_VIEW_LIMIT > 0 && function_exists( "pmpro_has_membership_access" )  ) {
            /*
                If we're viewing a page that the user doesn't have access to...
                Could add extra checks here.
            */
            if ( ! pmpro_has_membership_access() ) {
                $allowAccess = false;
                
                //ignore non-posts
                $queried_object = get_queried_object();						

                // get level ID for current user
                global $current_user;
                $users_level_id = $current_user->membership_level->id;
                
                /**
                 * Filter which post types should be tracked by LPV
                 */
                $pmprolpv_post_types = apply_filters('pmprolpv_post_types', array('post'));

                //check that queried object is in the allowed post types
                if ( empty($queried_object) || empty($queried_object->post_type) || !in_array($queried_object->post_type, $pmprolpv_post_types) ) {
                    return;
                }

                $hasaccess = apply_filters('pmprolpv_has_membership_access', true, $queried_object);
                if ( false === $hasaccess ) {
                    if($fnRedirectOnLimit === 'y'){
                        pmpro_lpv_redirect();
                    } else {
                        add_filter( "pmpro_has_membership_access_filter", "__return_false" );
                    }
                }

                //PHP is going to handle cookie check and redirect
                $cookieName = 'wplpv_view_count_testR';
                $thisSessionCookie = new WPLPV_Cookie($cookieName);
                
                //check for past views
                if ( $thisSessionCookie -> WPLPV_Is_Cookie_Set($cookieName,$users_level_id)) {
                    //Get Cookie Details
                    $thisSessionCookieDetails = $thisSessionCookie -> WPLPV_Prepare_Cookie_Contents();

                    $count = $thisSessionCookieDetails["count"];
                    $levellimits = $thisSessionCookieDetails["levellimits"];
                    $month = $thisSessionCookieDetails["month"];  
                } else {
                    $month = date( "n", current_time('timestamp') );
                    $count = 1;
                } 
                
                
                //If count is not already over limit then compare against the db
                //validate against db
                if ( defined('WPLPV_VIEW_LIMIT') && $count <= WPLPV_VIEW_LIMIT ) {
                    $sessionCount = $thisSessionCookie -> WPLPV_Get_Cookie_Occurances();
                    if($sessionCount > $count){
                        $count = $sessionCount;
                    }
                }

                
                //if count is above limit, redirect, otherwise update cookie
                if ( defined('WPLPV_VIEW_LIMIT') && $count >= WPLPV_VIEW_LIMIT ) {
                    if($fnRedirectOnLimit === 'y'){
                        pmpro_lpv_redirect();
                    } else {
                        add_filter( "pmpro_has_membership_access_filter", "__return_false" );
                    }
                } else {
                    //give them access and track the view
                    $allowAccess = true;
                    //add_filter( "pmpro_has_membership_access_filter", "__return_true" );

                    // put the cookie string back together with updated values.
                    $cookiestr = "";
                    if (is_array($levellimits)) {
                        //var_dump($levellimits);
                        foreach($levellimits as $curlev => $curviews) {
                            $cookiestr .= "$curlev,$curviews";
                        }
                    } else {
                        
                    }
                    
                    //Set the cookie
                    if($thisSessionCookie -> WPLPV_Set_Cookie($cookiestr . ';' . $month)){
                        $allowAccess = true;
                    } else {
                        $allowAccess = false;
                    }
                }
                
                if($allowAccess){
                   add_filter( "pmpro_has_membership_access_filter", "__return_true" );
                } else {
                    add_filter( "pmpro_has_membership_access_filter", "__return_false" );
                }
            }
        }
    }

    /**
     * Redirect to  the configured page or the default levels page
     */
    function pmpro_lpv_redirect($redirect_url = null) {
        $page_id = get_option( 'pmprolpv_redirect_page' );

        //check to see if caller provided a redirect url
        if( empty($redirect_url) ){
            //guess not so we see if PMPPro has one on file
            if ( empty( $page_id ) ) {
                //Guess not so we send the visitor to the levels page
                $redirect_url = pmpro_url( 'levels' );
            } else {
                $redirect_url = get_the_permalink( $page_id );
            }
        }

        wp_redirect( $redirect_url );    //here is where you can change which page is redirected to
        exit;
    }
