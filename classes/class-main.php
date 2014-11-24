<?php
/**
 * 
 * STC Main class
 * @author Daniel Söderström <info@dcweb.nu>
 * 
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) )
  die();

class STC_Main {

	protected static $instance = null;
	protected $plugin_slug = 'stc';
	private $options = array();

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since  1.0.0
	 */
	private function __construct() {

		// store options in to an array
		$this->set_options();

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// load public css
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// load admin css
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		// load admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

	}

	/**
	 * Single instance of this class.
	 *
	 * @since  1.0.0
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Store options to an array
	 *
	 * @since  1.0.0
	 */
	private function set_options(){
		$this->options = get_option( 'stc_settings');
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since  1.0.0
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since  1.0.0
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();

					restore_current_blog();
				}

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}
	
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since  1.0.0
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

					restore_current_blog();

				}

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}
		

	}

	/**
	 * Add some settings when plugin is activated
	 * - Cron schedule
	 *
	 * @since  1.0.0
	 */
	private static function single_activate() {
		
		//check if event is already scheduled
	  $timestamp = wp_next_scheduled( 'stc_schedule_email' );
	  if( $timestamp == false ){
	    wp_schedule_event( time(), 'hourly', 'stc_schedule_email' );
	  }		
	}

	/**
	 * Remove some settings on deactivation
	 * - delete options
	 * - delete hook
	 *
	 * @since  1.0.0
	 */
	private static function single_deactivate() {
    global $wpdb;

    // get current options
    $options = get_option('stc_settings');


    // remove post meta for posts
    $meta_data = array(
      '_stc_notifier_sent_time',
      '_stc_notifier_status'
    );

    foreach ($meta_data as $meta) {
      $wpdb->query( 
        $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta ) 
      );      
    }
		
    // remove posts in post type stc 
    if(isset( $options['deactivation_remove_subscribers'] ) && $options['deactivation_remove_subscribers'] == 1 ) {
	    $args = array( 'post_type' => 'stc' );
	    $posts = get_posts( $args );

	    if(!empty( $posts )){
	      foreach ($posts as $post ) {
	        wp_delete_post( $post->ID, true );
	      }
	    }
    }
    	
		// delete data saved for options
		delete_option( 'stc_settings' );

		// kill hook for scheduled event
		wp_clear_scheduled_hook( 'stc_schedule_email' );

	}

	/**
	 * Get all blog ids of blogs
	 *
	 * @since  1.0.0
	 */
	private static function get_blog_ids() {
		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs WHERE archived = '0' AND spam = '0' AND deleted = '0'";
		return $wpdb->get_col( $sql );

	}

	/**
	 * Load the plugin text domain for translation
	 *
	 * @since  1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( STC_TEXTDOMAIN, false, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' ); 
	}

	/**
	 * Register and enqueue public style sheet
	 *
	 * @since  1.0.0
	 */
	public function enqueue_styles() {
		$options = $this->options;
		
		if( isset($options['exclude_css']) && $options['exclude_css'] == false ) // check options for css
			wp_enqueue_style( 'stc-style', STC_PLUGIN_URL . '/css/stc-style.css', array() );
	}


	/**
	* Register and enqueue admin style sheet
	* 
	* @since  1.1.0
	*/
	public function enqueue_admin_styles() {
		wp_enqueue_style( 'stc-admin-style', STC_PLUGIN_URL . '/css/admin-style.css', array() );
	}

	/**
	* Register and enqueue admin scripts
	* 
	* @since  1.1.0
	*/
	public function enqueue_admin_scripts() {

    wp_register_script( 'back-end-script', STC_PLUGIN_URL . '/js/admin-script.js', array( 'jquery' ), false, true );
  
    // load stc back-end script
    wp_enqueue_script( 'back-end-script' );

    wp_localize_script( 'back-end-script', 'ajax_object', array( 
      'ajaxurl'     => admin_url( 'admin-ajax.php' ),
      'ajax_nonce'  => wp_create_nonce('ajax_nonce')
      ) 
    ); // setting ajaxurl and nonce 
    		
		
	}


}
