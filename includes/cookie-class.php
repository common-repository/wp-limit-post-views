<?php
    /* Does User Belong Here */
    if ( ! defined( 'WPLPV_VERSION' ) ) {
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden' );
        exit();
    }
    
    /**
     * Primary Cookie Handling class.
     *
     *
     * Note: Sets Cookie, Gets Cookie Data, Prepares Cookie Data
     *
     * @since  1.0.3
     *
     * @return object Plugin instance.
     */
    class WPLPV_Cookie {
        private static $cookiename = null;    //string
        private static $cookieexpires = null; //date
        private static $cookiecontentlevellimit = null; //array
        private static $cookiecontentmonth = null; //array
        private static $cookiecontenttrackingid = null; //int
        private static $userslevelid= null; //int
        
        
        public function __construct($fnCookieName) {
            $this->cookiename = $fnCookieName;

        }


        //Cookies are not available until the next time the site loads in the browser. 
        //Arguements: n/a
        //Response: n/a
        public function WPLPV_Validate_Cookie() {
            //echo $this->WPLPV_Is_Cookie_Set($this->cookiename);
            //wp_die();
        }  


        //Sets passed cookie details and then checks to see if the cookie was successfully set.
        //Arguements: Cookie Name, Cookie String, Cookie Expiration Date
        //Response: true/false
        public function WPLPV_Set_Cookie($fnCookieStr) {
            global $wp;
            GLOBAL $wpdb;
            $current_url = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
            $postid = url_to_postid( $current_url );
            if (!is_numeric($postid)){
                $postid = 0;
            }
            $userid = get_current_user_id();
            if (!is_numeric($userid)){
                $userid = 0;
            }
            
            $visitor = array(
                'ip' => sanitize_text_field( $this->GetIP()),
                'browser' => sanitize_text_field( $this->get_browser_name($_SERVER['HTTP_USER_AGENT'])), 
                'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT']), 
                'resource' => esc_url($current_url), 
                'content_id' => sanitize_text_field( $postid ), 
                'user_id' => sanitize_text_field( $userid ),
                'level_id' => sanitize_text_field( $this->userslevelid ),
                'limit_period' => sanitize_text_field( WPLPV_VIEW_LIMIT_PERIOD)
            );
            
            $wp_prefix = $wpdb->prefix;
            $wpdb->insert( 
                $wp_prefix . "wplpv_tracking", 
                $visitor
            );
            $lastid = $wpdb->insert_id;
            
            setcookie( $this->cookiename, $fnCookieStr . ";" . $lastid, $this->WPLPV_Get_Cookie_Expiration_Date(), '/' );

            return true;
        }  


        //Checks to see if specified cookie is set or not.
        //Arguements: Cookie Name, Users Level Id
        //Response: true/false
        public function WPLPV_Is_Cookie_Set($fnCookie,$fnLevelId) {
            $this->userslevelid = $fnLevelId;
            
            //print_r($_COOKIE);
            if (!empty($_COOKIE[$this->cookiename])) {
                return true;
            } else {
                return false;
            }
        }  


        //Constructs cookie expiration date based on plugin settings
        //Arguements: n/a
        //Response: date
        public function WPLPV_Get_Cookie_Expiration_Date() {
            if ( defined( 'WPLPV_VIEW_LIMIT_PERIOD' ) ) {
                switch ( WPLPV_VIEW_LIMIT_PERIOD ) {
                    case 'day':
                        $expires = current_time( 'timestamp' ) + DAY_IN_SECONDS;
                        break;
                    case 'week':
                        $expires = current_time( 'timestamp' ) + WEEK_IN_SECONDS;
                        break;
                    case 'month':
                        $expires = current_time( 'timestamp' ) + ( DAY_IN_SECONDS * 30 );
                }
            } else {
                $expires = current_time( 'timestamp' ) + ( DAY_IN_SECONDS * 30 );
            }

            return $expires;    
        } 


        //Fetches Cookie Contents
        //Arguements: n/a
        //Response: n/a
        public function WPLPV_Get_Cookie_Contents() {
            $thismonth = date( "n", current_time('timestamp') );
            $month = $thismonth;

            //Get Cookie Data and break into managable parts
            $parts = explode( ";", $_COOKIE[$this->cookiename] );

            //Part 1: Levels    [csv current cnt by role/levels]
            //Part 2: TimeFrame [month cookie was set in]
            //Part 3: Cookie ID

            //Prepare Part 3
            if(count($parts) > 2) { // just in case
                $trackingId = $parts[2];
            }

            //Prepare Part 2
            if(count($parts) > 1) { // just in case
                $month = $parts[1];
            }

            $limitparts = explode(',', $parts[0]);
            $levellimits = array();
            $length = count($limitparts);
            for($i = 0; $i < $length; $i++) {
                if($i % 2 == 1) {
                    $levellimits[$limitparts[$i-1]] = $limitparts[$i];
                }
            }


            $this->cookiecontenttrackingid = $trackingId;
            $this->cookiecontentmonth = $month;
            $this->cookiecontentlevellimit = $levellimits;
        }


        //Prepares Cookie Contents for verification process
        //Arguements: n/a
        //Response: Array
        public function WPLPV_Prepare_Cookie_Contents() {
            $this->WPLPV_Get_Cookie_Contents();

            $thismonth = date( "n", current_time('timestamp') );
            $month = $this->cookiecontentmonth;
            $levellimits = $this->cookiecontentlevellimit;
            
            if ( !empty( $_COOKIE[$this->cookiename] ) ) {
                if ( $month === $thismonth && array_key_exists($this->userslevelid, $levellimits)) {
                    $count = $levellimits[$this->userslevelid] + 1; //same month as other views
                    $levellimits[$this->userslevelid]++;
                } elseif( $month === $thismonth) { // same month, but we haven't ticked yet.
                    $count = 1;
                    $levellimits[$this->userslevelid] = 1;
                } else {
                    $count = 1;                        //new month
                    $levellimits = array();
                    $levellimits[$this->userslevelid] = 1;
                    $month = $thismonth;
                }
            } else {
                //new user
                $count = 1;
                $levellimits = array();
                $levellimits[$this->userslevelid] = 1;
                $month = $thismonth;  
            }  


            //Prepare Response
            $responsearray = array(
                "count" => $count,
                "level_id" => $this->userslevelid,
                "levellimits" => $levellimits,
                "month" => $thismonth,
            );

            return $responsearray;
        }
        
        
        //Checks the database to see if this visitor has any previous views
        //Arguements: n/a
        //Response: string
        public function WPLPV_Get_Cookie_Occurances() {
            
            //Check Past Expiration Possibilties
            if ( defined( 'WPLPV_VIEW_LIMIT_PERIOD' ) ) {
                switch ( WPLPV_VIEW_LIMIT_PERIOD ) {
                    case 'day':
                        $expires = "1";
                        break;
                    case 'week':
                        $expires = "7";
                        break;
                    case 'month':
                        $expires = "30";
                }
            } else {
                $expires = "30";
            }
   
            global $wpdb;
            $wp_prefix = $wpdb->prefix;
            
            //Delete Old Views that are out of range
            $wpdb->query(
                $wpdb->prepare( "
                                DELETE FROM " . $wp_prefix . "wplpv_tracking
                                WHERE (timestamp < NOW() - INTERVAL 1 MONTH)
                                    OR ( timestamp < NOW() - INTERVAL %s DAY  AND limit_period = %s )
                                ",
                                $expires,
                                sanitize_text_field(WPLPV_VIEW_LIMIT_PERIOD)    
                            )                  
            );

            
            //Fetch count of views left for this visitor
            $view_count = $wpdb->get_var( $wpdb->prepare( 
                "
                    SELECT COUNT(*) as viewCnt 
                    FROM " . $wp_prefix . "wplpv_tracking
                    WHERE ip = %s  
                        AND user_agent = %s 
                ",
                sanitize_text_field( $this->GetIP()),
                sanitize_text_field( $_SERVER['HTTP_USER_AGENT'])   
            ) );
            

            return $view_count;
        }  
        
        
        //Fetches Visitors IP Address
        //Arguements: n/a
        //Response: digit
        public function GetIP(){
            foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
                if (array_key_exists($key, $_SERVER) === true){
                    foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip){
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                            return $ip;
                        }
                    }
                }
            }
        }
        
        
        //Fetches Visitors Browser Name 
        //Arguements: UserAgent
        //Response: string        
        public function get_browser_name($user_agent){
            if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
            elseif (strpos($user_agent, 'Edge')) return 'Edge';
            elseif (strpos($user_agent, 'Chrome')) return 'Chrome';
            elseif (strpos($user_agent, 'Safari')) return 'Safari';
            elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
            elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';

            return 'Other';
        }
        
        

    }
