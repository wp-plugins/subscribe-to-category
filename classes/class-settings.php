<?php
/**
 * 
 * Class for the settings page
 * @author Daniel Söderström <info@dcweb.nu>
 * 
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) )
  die();
  
if( class_exists( 'STC_Settings' ) ) {
  $stc_setting = new STC_Settings();
}

  class STC_Settings {
    
    private $options; // holds the values to be used in the fields callbacks
    private $export_in_categories = array(); // holds value for filter export categories

    /**
     * Constructor
     *
     * @since  1.0.0
     */
    public function __construct() {

      // only in admin mode
      if( is_admin() ) {    
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Ajax call for sendings emails manually
        add_action( 'wp_ajax_force_run', array( $this, 'force_run' ) );

      }


    }

    /**
     * Ajax call for trigger send action manually
     * 
     * @since  1.1.0
     * 
     * @return [type] [description]
     */
    public function force_run(){
      check_ajax_referer( 'ajax_nonce', 'nonce' );
  
      $subscriber = STC_Subscribe::get_instance();
      $subscriber->stc_send_email();

      _e( 'Scheduled event successfully executed', STC_TEXTDOMAIN );

      die();
    }

    /**
     * Add options page
     * 
     * @since  1.0.0
     * 
     */
    public function add_plugin_page() {
      
      if( isset( $_POST['action'] ) && $_POST['action'] == 'export' ){
          
        // listen for filter by categories
        if( isset( $_POST['in_categories'] ) && !empty( $_POST['in_categories'] ) ){
          $this->export_in_categories = $_POST['in_categories']; 
        }
        $this->export_to_excel();
      
      }
      
      add_options_page(
        __( 'Subscribe to Category', STC_TEXTDOMAIN ), 
        __( 'Subscribe', STC_TEXTDOMAIN ), 
        'manage_options', 
        'stc-subscribe-settings', 
        array( $this, 'create_admin_page' )
      );

    }

    /**
     * Options page callback
     *
     * @since  1.0.0
     * 
     */
    public function create_admin_page() {

      // Set class property
      $this->options = get_option( 'stc_settings' );
      $time_in_seconds_i18n = strtotime( date_i18n( 'Y-m-d H:i:s' ) ) + $this->get_next_cron_time( 'stc_schedule_email' );
      $next_run = gmdate( 'Y-m-d H:i:s', $time_in_seconds_i18n ); 
      ?>
      <div class="wrap">
        <?php screen_icon(); ?>
        <h2><?php _e('Settings for subscribe to category', STC_TEXTDOMAIN ); ?></h2>       


        <table class="widefat">
          <tbody>
            <tr>
              <td class="desc"><strong><?php _e( 'Schedule: ', STC_TEXTDOMAIN ); ?></strong> <?php _e('E-mail is scheduled to be sent once every hour.', STC_TEXTDOMAIN ); ?></td>
              <td class="desc"></td>
              <td class="desc textright"><div class="stc-force-run-action"><button type="button" id="stc-force-run" class="button button-primary"><?php _e( 'Click here to run this action right now', STC_TEXTDOMAIN ); ?></button></div></td>
            </tr>
            <tr>
              <td class="desc" colspan="3"><?php printf( __('Next run is going to be <strong>%s</strong> and will include %s posts.', STC_TEXTDOMAIN ), $next_run, '<span id="stc-posts-in-que">' . $this->get_posts_in_que() . '</span>' ); ?></td>
            </tr>
          </tbody>
        </table>

        <form method="post" action="options.php">
        <?php
            // print out all hidden setting fields
            settings_fields( 'stc_option_group' );   
            do_settings_sections( 'stc-subscribe-settings' );
            do_settings_sections( 'stc-resend-settings' );
            do_settings_sections( 'stc-style-settings' );
            do_settings_sections( 'stc-deactivation-settings' );
            submit_button(); 
        ?>
        </form>
        <?php $this->export_to_excel_form(); ?>

      </div>
      <?php
    }

    /**
     * Get current posts in que to be sent
     *
     * @since  1.0.0
     * 
     * @return int sum of posts
     */
    private function get_posts_in_que(){
      
      // get posts with a post meta value in outbox
      $meta_key = '_stc_notifier_status';
      $meta_value = 'outbox';

      $args = array(
        'post_type'   => 'post',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_key'    => $meta_key,
        'meta_value'  => $meta_value
      );

      $posts = get_posts( $args );

      return count( $posts );
    }

    /**
     * Returns the time in seconds until a specified cron job is scheduled.
     *
     * @since  1.0.0
    */
    public function get_next_cron_time( $cron_name ){

      foreach( _get_cron_array() as $timestamp => $crons ){

        if( in_array( $cron_name, array_keys( $crons ) ) ){
          return $timestamp - time();
        }
      }

      return false;
    }

    /**
     * Register and add settings
     *
     * @since  1.0.0
     * 
     */
    public function register_settings(){        

        // Email settings
        add_settings_section(
            'setting_email_id', // ID
            __( 'E-mail settings', STC_TEXTDOMAIN ), // Title
            '', //array( $this, 'print_section_info' ), // Callback
            'stc-subscribe-settings' // Page
        );  

        add_settings_field(
            'stc_email_from',
            __( 'E-mail from: ', STC_TEXTDOMAIN ),
            array( $this, 'stc_email_from_callback' ), // Callback
            'stc-subscribe-settings', // Page
            'setting_email_id' // Section           
        );

        add_settings_field(
            'stc_title',
            __( 'Email subject: ', STC_TEXTDOMAIN ),
            array( $this, 'stc_title_callback' ), // Callback
            'stc-subscribe-settings', // Page
            'setting_email_id' // Section           
        );


        // Resend settings
        add_settings_section(
            'setting_resend_id', // ID
            __( 'Resend post on update', STC_TEXTDOMAIN ), // Title
            '', //array( $this, 'print_section_info' ), // Callback
            'stc-resend-settings' // Page
        );  

        add_settings_field(
            'stc_resend',
            __( 'Resend:', STC_TEXTDOMAIN ),
            array( $this, 'stc_resend_callback' ), // Callback
            'stc-resend-settings', // Page
            'setting_resend_id' // Section           
        );        


        // Styleing settings
        add_settings_section(
            'setting_style_id', // ID
            __( 'Stylesheet (CSS) settings', STC_TEXTDOMAIN ), // Title
            '', //array( $this, 'print_section_info' ), // Callback
            'stc-style-settings' // Page
        );  

        add_settings_field(
            'stc_custom_css',
            __( 'Custom CSS: ', STC_TEXTDOMAIN ),
            array( $this, 'stc_css_callback' ), // Callback
            'stc-style-settings', // Page
            'setting_style_id' // Section           
        );


        // Deactivation settings
        add_settings_section(
            'setting_deactivation_id', // ID
            __( 'On plugin deactivation', STC_TEXTDOMAIN ), // Title
            array( $this, 'section_deactivation_info' ), // Callback
            'stc-deactivation-settings' // Page
        );          

        add_settings_field(
            'stc_remove_subscribers',
            __( 'Subscribers: ', STC_TEXTDOMAIN ),
            array( $this, 'stc_remove_subscribers_callback' ), // Callback
            'stc-deactivation-settings', // Page
            'setting_deactivation_id' // Section           
        );        


        register_setting(
          'stc_option_group', // Option group
          'stc_settings', // Option name
          array( $this, 'input_validate_sanitize' ) // Callback function for validate and sanitize input values
        );

    }

    /**
     * Print outs text for deactivation info
     *
     * @since  1.0.0
     * 
     * @return [type] [description]
     */
    public function section_deactivation_info(){
      ?>
      <p><?php _e('The plugin will remove all data in database created by this plugin but there is an option regarding subscribers', STC_TEXTDOMAIN ); ?></p>
      <?php
    }

    /**
     * Sanitize setting fields
     *
     * @since  1.0.0
     * 
     * @param array $input 
     */
    public function input_validate_sanitize( $input ) {
        $output = array();

        if( isset( $input['email_from'] ) ){

          // sanitize email input
          $output['email_from'] = sanitize_email( $input['email_from'] ); 
          
          if(! empty( $input['email_from'] )){
            if ( ! is_email( $output['email_from'] ) ){
              add_settings_error( 'setting_email_id', 'invalid-email', __( 'You have entered an invalid email.', STC_TEXTDOMAIN ) );
            }
          }
        }

        if( isset( $input['title'] ) ){
          $output['title'] = $input['title'];
        }

        if( isset( $input['resend_option'] ) ){
          $output['resend_option'] = $input['resend_option'];
        }        

        if( isset( $input['exclude_css'] ) ){
          $output['exclude_css'] = $input['exclude_css'];
        }

        if( isset( $input['deactivation_remove_subscribers'] ) ){
          $output['deactivation_remove_subscribers'] = $input['deactivation_remove_subscribers'];
        }

        return $output;
    }

    /** 
     * Printing section text
     *
     * @since  1.0.0
     * 
     */
    public function print_section_info(){
      _e( 'Add your E-mail settings', STC_TEXTDOMAIN );
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_email_from_callback() {
      $default_email = get_option( 'admin_email' );
      ?>
        <input type="text" id="email_from" class="regular-text" name="stc_settings[email_from]" value="<?php echo isset( $this->options['email_from'] ) ? esc_attr( $this->options['email_from'] ) : '' ?>" />
        <p class="description"><?php printf( __( 'Enter the e-mail address for the sender, if empty the admin e-mail address %s is going to be used as sender.', STC_TEXTDOMAIN ), $default_email ); ?></p>
        <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_title_callback() {
      ?>
        <input type="text" id="email_from" class="regular-text" name="stc_settings[title]" value="<?php echo isset( $this->options['title'] ) ? esc_attr( $this->options['title'] ) : '' ?>" />
        <p class="description"><?php _e( 'Enter e-mail subject for the e-mail notification, leave empty if you wish to use post title as email subject.', STC_TEXTDOMAIN ); ?></p>
        <?php
    }


    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.2.0
     * 
     */
    public function stc_resend_callback() { 
      $options['resend_option'] = '';
      
      if( isset( $this->options['resend_option'] ) )
        $options['resend_option'] = $this->options['resend_option'];
      ?>

      <label for="resend_option"><input type="checkbox" value="1" id="resend_option" name="stc_settings[resend_option]" <?php checked( '1', $options['resend_option'] ); ?>><?php _e('Enable resend post option', STC_TEXTDOMAIN ); ?></label>
      <p class="description"><?php _e('Gives an option on edit post (in the publish panel) to resend a post on update.', STC_TEXTDOMAIN ); ?></p>
    <?php
    }    

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_css_callback() { 
      $options['exclude_css'] = '';
      
      if( isset( $this->options['exclude_css'] ) )
        $options['exclude_css'] = $this->options['exclude_css'];
      ?>

      <label for="exclude_css"><input type="checkbox" value="1" id="exclude_css" name="stc_settings[exclude_css]" <?php checked( '1', $options['exclude_css'] ); ?>><?php _e('Exclude custom CSS', STC_TEXTDOMAIN ); ?></label>
      <p class="description"><?php _e('Check this option if your theme supports Bootstrap framework or if you want to place your own CSS for Subscribe to Category in your theme.', STC_TEXTDOMAIN ); ?></p>
    <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_remove_subscribers_callback() { 
      $options['deactivation_remove_subscribers'] = '';
      
      if( isset( $this->options['deactivation_remove_subscribers'] ) )
        $options['deactivation_remove_subscribers'] = $this->options['deactivation_remove_subscribers'];
  
      ?>

      <label for="deactivation_remove_subscribers"><input type="checkbox" value="1" id="deactivation_remove_subscribers" name="stc_settings[deactivation_remove_subscribers]" <?php checked( '1', $options['deactivation_remove_subscribers'] ); ?>><?php _e('Delete all subscribers on deactivation', STC_TEXTDOMAIN ); ?></label>
    <?php
    }        

    /**
     * Form for filtering categories on export to excel
     *
     * @since  1.0.0
     * 
     */
    public function export_to_excel_form(){
      $categories = get_categories( array( 'hide_empty' => false ) ); 
      ?>
      <h3><?php _e( 'Export to excel', STC_TEXTDOMAIN ); ?></h3>
      <form method="post" action="options-general.php?page=stc-subscribe-settings">
      <table class="form-table">
        <tbody>
          <tr>
            <th scope="row"><?php _e('Filter by categories', STC_TEXTDOMAIN ); ?></th>
            <td>
              <?php if(! empty( $categories )) : ?>
                <?php foreach( $categories as $cat ) : ?>
                  <label for="<?php echo $cat->slug; ?>"><input type="checkbox" name="in_categories[]" id="<?php echo $cat->slug; ?>" value="<?php echo $cat->term_id; ?>"><?php echo $cat->name; ?></label>
                <?php endforeach; ?>
              <?php else: ?>
                <?php _e('There are no categories to list yet', STC_TEXTDOMAIN ); ?>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
      <input type="hidden" value="export" name="action">
      <input type="submit" value="Export to Excel" class="button button-primary" id="submit" name="">
      </form>
      
      <?php
    }


    /**
     * Export method for excel
     *
     * @since  1.0.0
     * 
     */
    public function export_to_excel(){

      $args = array(
        'post_type'     => 'stc',
        'post_status'   => 'publish',
        'category__in'  => $this->export_in_categories // Empty value returns all categories
      );

      $posts = get_posts( $args );

      // get category names for filtered categories to print out in excel file, if there is a filter...
      if(!empty( $this->export_in_categories)){
        
        foreach ( $this->export_in_categories as $item ) {
          $cats = get_term( $item, 'category' );
          $cats_name .= $cats->name.', ';
        }
        // remove last commasign in str
        $in_category_name = substr( $cats_name, 0, -2 );
      }

      $i = 0;
      $export = array();
      foreach ($posts as $p) {
        
        $cats = get_the_category( $p->ID ); 
        foreach ($cats as $c) {
          $c_name .= $c->name . ', ';
        }
        $in_categories = substr( $c_name, 0, -2);
        $c_name = false; // unset variable

        $export[$i]['id'] = $p->ID;
        $export[$i]['email'] = $p->post_title;
        $export[$i]['user_categories'] = $in_categories;
        $export[$i]['subscription_date'] = $p->post_date;
        
        $i++;
      }
      
      // filename for download 
      $time = date('Ymd_His'); 
      $filename = STC_SLUG . '_' . $time . '.xls';

      header("Content-Disposition: attachment; filename=\"$filename\""); 
      header("Content-Type:   application/vnd.ms-excel; ");
      header("Content-type:   application/x-msexcel; ");

      $flag = false; 
      // print out filtered categories if there is
      if(!empty( $in_category_name ))
        echo "\r\n", utf8_decode( __('Filtered by: ', STC_TEXTDOMAIN ) ) . utf8_decode( $in_category_name ); 
      
      foreach ($export as $row ) {
        if(! $flag ) { 
          // display field/column names as first row 
          echo "\r\n" . implode("\t", array_keys( $row )) . "\r\n"; 
          $flag = true; 
        } 

        array_walk($row, array($this, 'clean_data_for_excel') ); 
        echo implode("\t", array_values($row) ). "\r\n"; 
      } 

      exit;

    }
      
    /**
     * Method for cleaning data to excel
     *
     * @since  1.0.0
     * 
     */
    public function clean_data_for_excel( &$str ) { 
      $str = iconv('UTF-8', 'ISO-8859-1', $str );
      $str = preg_replace("/\t/", "\\t", $str ); 
      $str = preg_replace("/\r?\n/", "\\n", $str ); 
    } 

  }

?>