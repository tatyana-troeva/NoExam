<?php

/**
 * Class Inbound_Events
 * Stores action events into MySQL database table
 * Stores: form submissions events, cta link clicks, email link clicks, custom events
 *
 */
class Inbound_Events {

    /**
     * Inbound_Events constructor.
     */
    public function __construct(){
        self::add_hooks();
    }

    /**
     * Define WordPress hooks and filters
     */
    public static function add_hooks() {
        /* create events table if does not exist */
        add_action('inbound_shared_activate' , array( __CLASS__ , 'create_events_table' ));

        /* listen for cta clicks and record event to events table */
        add_action('inbound_tracked_cta_click' , array( __CLASS__ , 'store_cta_click'), 10 , 1);

        /* listen for Inbound Form submissions and record event to events table */
        add_action('inbound_store_lead_post' , array( __CLASS__ , 'store_form_submission'), 10 , 1);

        /* listen for Inbound Form submissions and record event to events table */
        add_action('inbound_email_click_event' , array( __CLASS__ , 'store_email_click'), 10 , 1);

        /* Saves all all incoming POST data as meta pairs */
        add_action('before_delete_post', array(__CLASS__, 'delete_related_events'));

        /* listen for Inbound Mailer send event and record to events table
         * I think we can pull this infromation directly from Mandril
        add_action('inbound_mandrill_send_event' , array( __CLASS__ , 'store_email_send'), 10 , 2);
        */
    }

    /**
     * Creates inbound_events
     */
    public static function create_events_table(){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";
        $charset_collate = '';

        if ( ! empty( $wpdb->charset ) ) {
            $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
        }
        if ( ! empty( $wpdb->collate ) ) {
            $charset_collate .= " COLLATE {$wpdb->collate}";
        }

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
			  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
			  `event_name` varchar(255) NOT NULL,
			  `page_id` mediumint(20) NOT NULL,
			  `variation_id` mediumint(9) NOT NULL,
			  `form_id` mediumint(20) NOT NULL,
			  `cta_id` mediumint(20) NOT NULL,
			  `email_id` mediumint(20) NOT NULL,
			  `lead_id` mediumint(20) NOT NULL,
			  `lead_uid` varchar(255) NOT NULL,
			  `session_id` varchar(255) NOT NULL,
			  `event_details` text NOT NULL,
			  `source` text NOT NULL,
			  `funnel` text NOT NULL,
			  `datetime` datetime NOT NULL,

			  UNIQUE KEY id (id)
			) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        /* add new columns to legacy table */
        $row = $wpdb->get_results(  "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$table_name}' AND column_name = 'source'"  );
        if(empty($row)){
            // do your stuff
            $wpdb->get_results( "ALTER TABLE {$table_name} ADD `funnel` text NOT NULL" );
            $wpdb->get_results( "ALTER TABLE {$table_name} ADD `source` text NOT NULL" );
        }
    }

    /**
     * Stores a form submission event into events table
     * @param $lead
     */
    public static function store_form_submission( $lead ){

        if (! isset($lead['raw_params']) )  {
            return;
        }

        parse_str($lead['raw_params'] , $raw_params );
        $details = array_merge($raw_params,$lead);
        $args = array(
            'event_name' => 'inbound_form_submission',
            'page_id' => $lead['page_id'],
            'variation_id' =>  $lead['variation'],
            'form_id' => (isset($raw_params['inbound_form_id'])) ? $raw_params['inbound_form_id'] : '',
            'lead_id' => $lead['id'],
            'lead_uid' => ( isset($_COOKIE['wp_lead_uid']) ? $_COOKIE['wp_lead_uid'] : '' ),
            'session_id' => '',
            'event_details' => json_encode($details),
            'datetime' => $lead['wordpress_date_time']
        );

        self::store_event($args);
    }

    /**
     * Stores cta click event into events table
     * @param $args
     */
    public static function store_cta_click( $args ) {
        $args['event_name'] = 'inbound_cta_click';
        self::store_event($args);
    }

    /**
     * Stores email send event into events table
     * @param $args
     */
    public static function store_email_send( $message , $send_at ) {

        $args = array(
            'event_name' => 'inbound_email_send',
            'email_id' => $message['metadata']['email_id'],
            'variation_id' => $message['metadata']['variation_id'],
            'lead_id' => $args['urlparams']['lead_id'],
            'lead_uid' => ( isset($_COOKIE['wp_lead_uid']) ? $_COOKIE['wp_lead_uid'] : '' ),
            'event_details' => json_encode($args['urlparams']),
            'datetime' => $args['datetime'],
            'form_id' => ''
        );

        self::store_event($args);
    }

    /**
     * Stores inbound email click event into events table
     * @param $args
     */
    public static function store_email_click( $args ){
        global $wp_query;


        $args['event_name'] = 'inbound_email_click';

        self::store_event($args);
    }

    /**
     * Stores inbound mailer unsubscribe event into events table
     * @param $args
     */
    public static function store_unsubscribe_event( $args ){
        global $wp_query;

        $args['event_name'] = 'inbound_unsubscribe';

        self::store_event($args);
    }

    public static function store_event( $args ) {
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";
        $timezone_format = 'Y-m-d G:i:s T';
        $wordpress_date_time =  date_i18n($timezone_format);

        /* event name required */
        if (!isset($args['event_name'])) {
            return;
        }

        $defaults = array(
            'page_id' => '',
            'variation_id' => '',
            'form_id' => '',
            'cta_id' => '',
            'email_id' => '',
            'lead_id' => ( isset($_COOKIE['wp_lead_id']) ? $_COOKIE['wp_lead_id'] : '' ),
            'lead_uid' => ( isset($_COOKIE['wp_lead_uid']) ? $_COOKIE['wp_lead_uid'] : '' ),
            'session_id' => '',
            'event_name' => $args['event_name'],
            'event_details' => '',
            'datetime' => $wordpress_date_time,
            'funnel' => ( isset($_SESSION['inbound_page_views']) ? $_SESSION['inbound_page_views'] : '' ),
            'source' => ( isset($_COOKIE['inbound_referral_site']) ? $_COOKIE['inbound_referral_site'] : '' )
        );


        $args = array_merge( $defaults , $args );

        /* prepare funnel array */
        if ($args['funnel']) {

            /* check if valid json or if slashes need to be stripepd out */
            if (!self::isJson($args['funnel'])) {
                $args['funnel'] = stripslashes($args['funnel']);
            }

        }

        /* unset non db ready keys */
        foreach ($args as $key => $value) {
            if (!isset($defaults[$key])) {
                unset($args[$key]);
            }
        }

        /* add event to event table */
        $wpdb->insert(
            $table_name,
            $args
        );

    }

    public static function delete_related_events( $post_id ) {
        global $wpdb;

        $post = get_post($post_id);

        $table_name = $wpdb->prefix . "inbound_events";

        switch ($post->post_type) {
            case  'inbound-email':
                $where = array(
                    'email_id' => $post_id
                );
                break;
            case  'wp-lead':
                $where = array(
                    'lead_id' => $post_id
                );
                break;
            default:
                $where = array(
                    'page_id' => $post_id
                );
                break;
        }

        $wpdb->delete( $table_name, $where, $where_format = null );

    }

    /**
     * Checks if an event has already been created. Right now inbound-mailer is the only tool leveraging this.
     * It will need to be improved to support other uses
     * @param $args
     * @return bool
     */
    public static function event_exists( $args ) {
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $defaults = array(
            'page_id' => '',
            'variation_id' => '',
            'form_id' => '',
            'cta_id' => '',
            'email_id' => '',
            'lead_id' => ( isset($_COOKIE['wp_lead_id']) ? $_COOKIE['wp_lead_id'] : '' ),
            'lead_uid' => ( isset($_COOKIE['wp_lead_uid']) ? $_COOKIE['wp_lead_uid'] : '' ),
            'session_id' => '',
            'event_name' => $args['event_name'],
            'event_details' => '',
            'datetime' => $wordpress_date_time,
            'funnel' => ( isset($_SESSION['inbound_page_views']) ? $_SESSION['inbound_page_views'] : '' ),
            'source' => ( isset($_COOKIE['inbound_referral_site']) ? $_COOKIE['inbound_referral_site'] : '' )
        );


        $args = array_merge( $defaults , $args );

        if($wpdb->get_row("SELECT * FROM $table_name WHERE event_name = '".$args['event_name']."' && email_id = '".$args['email_id']."' && lead_id = '".$args['email_id']."' && variation_id = '".$args['variation_id']."' ", ARRAY_A)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get all form submission events related to lead ID
     */
    public static function get_form_submissions( $lead_id ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $query = 'SELECT * FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" AND `event_name` = "inbound_form_submission" ORDER BY `datetime` DESC';
        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get form submission events given conditions
     *
     */
    public static function get_form_submissions_by( $nature = 'lead_id' ,  $params ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        switch ($nature) {
            case 'lead_id':
                $query = 'SELECT * FROM '.$table_name.' WHERE datetime >= "'.$params['start_date'].'" AND  datetime <= "'.$params['end_date'].'" AND `lead_id` = "'.$params['lead_id'].'" AND `event_name` = "inbound_form_submission" ORDER BY `datetime` DESC';
                break;
            case 'page_id':
                $query = 'SELECT * FROM '.$table_name.' WHERE datetime >= "'.$params['start_date'].'" AND  datetime <= "'.$params['end_date'].'" AND `page_id` = "'.$params['page_id'].'" AND `event_name` = "inbound_form_submission" ORDER BY `datetime` DESC';
                break;
        }


        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get all cta click events related to lead ID
     */
    public static function get_cta_clicks( $lead_id ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $query = 'SELECT * FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" AND `event_name` = "inbound_cta_click" ORDER BY `datetime` DESC';
        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get cta click events given conditions
     *
     */
    public static function get_cta_clicks_by( $nature = 'lead_id' ,  $params ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        switch ($nature) {
            case 'lead_id':
                $query = 'SELECT * FROM '.$table_name.' WHERE datetime >= "'.$params['start_date'].'" AND  datetime <= "'.$params['end_date'].'" AND `lead_id` = "'.$params['lead_id'].'" AND `event_name` = "inbound_cta_click" ORDER BY `datetime` DESC';
                break;
            case 'page_id':
                $query = 'SELECT * FROM '.$table_name.' WHERE datetime >= "'.$params['start_date'].'" AND  datetime <= "'.$params['end_date'].'" AND `page_id` = "'.$params['page_id'].'" AND `event_name` = "inbound_cta_click" ORDER BY `datetime` DESC';
                break;
        }

        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get all email click events related to lead ID
     */
    public static function get_email_clicks( $lead_id ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $query = 'SELECT * FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" AND `event_name` = "inbound_email_click" ORDER BY `datetime` DESC';
        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get all unsubscribe events given a lead id
     */
    public static function get_unsubscribes( $lead_id ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $query = 'SELECT * FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" AND `event_name` = "inbound_unsubscribe" ORDER BY `datetime` DESC';
        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get all unsubscribe events given an email id
     */
    public static function get_unsubscribes_by_email( $email_id, $vid = null  ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $query = 'SELECT * FROM '.$table_name.' WHERE `email_id` = "'.$email_id.'"';

        if ($vid>-1) {
            $query .= 'AND `variation_id` = "'.$vid.'"';
        }
        $query .= 'AND `event_name` = "inbound_unsubscribe"';

        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get all unsubscribe events given an email id
     */
    public static function get_unsubscribes_count_by_email_id( $email_id , $vid = null ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $query = 'SELECT count(*) FROM '.$table_name.' WHERE `email_id` = "'.$email_id.'"';

        if ($vid>-1) {
            $query .= 'AND `variation_id` = "'.$vid.'"';
        }

        $query .= 'AND `event_name` = "inbound_unsubscribe"';

        $count = $wpdb->get_var( $query , 0, 0 );

        /* return null if nothing there */
        return ($count) ? $count : 0;


    }

    /**
     * Get custom event data by lead id
     */
    public static function get_custom_event_data( $lead_id ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        $query = 'SELECT * FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" AND `event_name` NOT LIKE "inbound_%" ORDER BY `datetime` DESC';
        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }

    /**
     * Get all all custom event data by a certain indicator
     */
    public static function get_custom_event_data_by( $nature = 'lead_id' , $params ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        switch ($nature) {
            case 'lead_id':
                $query = 'SELECT * FROM '.$table_name.' WHERE `lead_id` = "'.$params['lead_id'].'" AND `event_name` NOT LIKE "inbound_%" ORDER BY `datetime` DESC';
                break;
            case 'page_id':
                $query = 'SELECT * FROM '.$table_name.' WHERE `page_id` = "'.$params['page_id'].'" AND `event_name` NOT LIKE "inbound_%" ORDER BY `datetime` DESC';
                break;
        }

        $results = $wpdb->get_results( $query , ARRAY_A );

        return $results;
    }


    /**
     * Get date of latest activity
     * @param $lead_id
     * @param string $activity
     * @return datetime or null
     */
    public static function get_last_activity($lead_id , $activity = 'any' ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        switch ($activity) {
            case 'any':
                $query = 'SELECT `datetime` FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" ORDER BY `datetime` DESC LIMIT 1';
                $results = $wpdb->get_results( $query , ARRAY_A );
                break;
            default:
                $query = 'SELECT `datetime` FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" AND `event_name` = "'.$activity.'" ORDER BY `datetime` DESC LIMIT 1';
                $results = $wpdb->get_results( $query , ARRAY_A );
                break;
        }

        /* return latest activity if recorded */
        if (isset($results[0]['datetime'])) {
            return $results[0]['datetime'];
        }

        /* return null if nothing there */
        return null;
    }

    /**
     * Get count of event activity given a lead id
     * @param $lead_id
     * @param string $activity
     * @return datetime or null
     */
    public static function get_total_activity($lead_id , $activity = 'any' ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        switch ($activity) {
            case 'any':
                $query = 'SELECT count(*) FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" ';
                break;
            default:
                $query = 'SELECT count(*) FROM '.$table_name.' WHERE `lead_id` = "'.$lead_id.'" AND `event_name` = "'.$activity.'"';
                break;
        }

        /* return latest activity if recorded */
        $count = $wpdb->get_var( $query , 0, 0 );

        /* return null if nothing there */
        return $count;
    }

    /**
     * @param int $page_id
     * @param string $activity [any or custom event type]
     * @param datetime $start_date
     * @param datetime $end_date
     */
    public static function get_page_actions($page_id , $activity = 'any' , $start_date, $end_date ){
        global $wpdb;

        $table_name = $wpdb->prefix . "inbound_events";

        switch ($activity) {
            case 'any':
                $query = 'SELECT count(*) FROM '.$table_name.' WHERE datetime >= "'.$start_date.'" AND  datetime <= "'.$end_date.'" AND `page_id` = "'.$page_id.'"';
                break;
            default:
                $query = 'SELECT count(*) FROM '.$table_name.' WHERE datetime >= "'.$start_date.'" AND  datetime <="'.$end_date.'" AND  `page_id` = "'.$page_id.'" AND `event_name` = "'.$activity.'"';
                break;
        }


        /* return latest activity if recorded */
        $count = $wpdb->get_var( $query , 0, 0 );

        /* return null if nothing there */
        return ($count) ? $count : 0;
    }

    public static function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}

new Inbound_Events();
