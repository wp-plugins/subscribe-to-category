<?php
/**
 * 
 * Class for subscribe
 * @author Daniel Söderström <info@dcweb.nu>
 * 
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) )
  die();

if( class_exists( 'STC_Subscribe' ) ) {
  $stc_subscribe = new STC_Subscribe();
}


  class STC_Subscribe {

    protected static $instance = null;
  	private $data = array();
  	private $error = array();
    private $notice = array();
    private $settings = array();
    private $post_type = 'stc';
    private $sleep_flag = 25;

    /**
     * Constructor
     *
     * @since  1.0.0
     */
  	function __construct(){
  		$this->init();
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
  	 * Init method
     *
     * @since  1.0.0 
  	 * 
     * @return [type] [description]
  	 */
  	private function init(){

      add_action( 'init', array( $this, 'register_post_type'), 99 );
      add_action( 'create_category', array( $this, 'update_subscriber_categories') );

      add_action( 'wp', array( $this, 'collect_get_data') );
  		add_action( 'wp', array( $this, 'collect_post_data') );

      add_action( 'save_post_stc', array( $this, 'save_post_stc') );
      add_action( 'admin_notices', array( $this, 'save_post_stc_error' ) );


  		add_shortcode( 'stc-subscribe', array( $this, 'stc_subscribe_render' ) );
      add_action( 'transition_post_status', array( $this, 'new_post_submit' ), 10, 3 );

      add_action( 'stc_schedule_email', array( $this, 'stc_send_email' ) );

      // save settings to array
      $this->settings = get_option( 'stc_settings' );

  	}

    /**
     * Adding a newly created category to subscribers who subscribes to all categories
     *
     * @since  1.0.0
     * 
     * @param $category_id The id for newly created category
     */
    public function update_subscriber_categories( $category_id ){

      $args = array(
        'post_type'   => 'stc',
        'post_status' => 'publish',
        'meta_key'    => '_stc_all_categories',
        'meta_value'  => '1',
      );

      $subscribers = get_posts( $args );

      if(!empty( $subscribers )){
        foreach ($subscribers as $s ) {
          
          $categories = $s->post_category;
          $categories[] = $category_id;
    
          $post_data = array(
            'ID'            => $s->ID,
            'post_category' => $categories
          );    

          wp_update_post( $post_data );        
        }
      }

    }

    /**
     * Method for printing unsubscription text on custom page
     *
     * @since  1.0.0
     */
    public function unsubscribe_html(){
      global $post;
      get_header();
    ?>
    <div id="stc-unsubscribe-wrapper" class="">
      <div class="alert alert-success text-center">
        <p><?php echo $this->notice[0]; ?></p>
        <p><a href="<?php echo get_bloginfo('url'); ?>"><?php _e( 'Take me to start page', STC_TEXTDOMAIN ); ?></a></p>
      </div>
    </div>
      <?php
      get_footer();
      exit;
    }

    /**
     * Collecting data through _GET
     *
     * @since  1.0.0
     */
    public function collect_get_data(){

      if(empty( $_GET ))
        return false;

      // unsubscription
      if(isset( $_GET['stc_unsubscribe']) && strlen( $_GET['stc_unsubscribe'] ) === 32 && ctype_xdigit( $_GET['stc_unsubscribe'] ) ){
        if(isset( $_GET['stc_user'] ) && is_email( $_GET['stc_user'] ) ){
          $this->unsubscribe_user();
          add_action( 'template_redirect', array( $this, 'unsubscribe_html' ) );
        }       

      }

      // notice on subscription
      if (isset( $_GET['stc_status'] ) && $_GET['stc_status'] == 'success' ) {
        $this->notice[] = __( 'Thanks for your subscription!', STC_TEXTDOMAIN );
        $this->notice[] = __( 'If you want to unsubscribe there is a link for unsubscription attached in the email.', STC_TEXTDOMAIN );
      }

    }

    /**
     * Unsubscribe user from subscription
     * 
     * @TODO: add contact email if something went wrong
     *
     * @since  1.0.0
     */
    private function unsubscribe_user(){
      global $wpdb;
      $meta_key = '_stc_hash';
      $meta_value = $_GET['stc_unsubscribe'];
      $stc_user = strtolower( $_GET['stc_user'] );

      $user_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $wpdb->posts AS post 
        LEFT JOIN $wpdb->postmeta AS meta ON post.ID = meta.post_id 
        WHERE meta.meta_key = %s AND meta.meta_value = %s 
        AND post.post_type = %s 
        AND post.post_title = %s
        ", $meta_key, $meta_value, $this->post_type, $stc_user )
      );

      if(empty( $user_id )){
        $notice[] = __('We are sorry but something went wrong with your unsubscription.');
        return $this->notice = $notice;
      }
    

      $subscriber_email = get_the_title( $user_id );
      wp_delete_post( $user_id );

      $notice[] = sprintf( __( 'We have successfully removed your email %s from our database.', STC_TEXTDOMAIN ), '<span class="stc-notice-email">' . $subscriber_email . '</span>' );
      
      return $this->notice = $notice;

    }

    /**
     * Listen for every new post and update post meta if post type 'post'
     *
     * @since  1.0.0
     * 
     * @param  string $old_status 
     * @param  string $new_status 
     * @param  object $post
     */
    public function new_post_submit( $old_status, $new_status, $post ){

      // bail if not the correct post type
      if( $post->post_type != 'post' )
        return false;

      // We wont send email notice if a post i updated
      if( $new_status == 'new' ){
        update_post_meta( $post->ID, '_stc_notifier_status', 'outbox' ); // updating post meta
      }
      
    }


    /**
     * Sending an email to a subscriber with a confirmation link to unsubscription
     *
     * @since  1.0.0
     * 
     * @param  int $stc_id post id for subscriber
     * @return [type]         [description]
     */
    private function send_unsubscribe_mail( $stc_id = '' ){
      
      // bail if not numeric
      if( empty( $stc_id ) || !is_numeric( $stc_id ) )
        return false;

      // get title and user hash
      $stc['email'] = get_the_title( $stc_id );
      $stc['hash'] = get_post_meta( $stc_id, '_stc_hash', true );

      // Website name to print as sender
      $website_name = get_bloginfo( 'name' );


      $email_from = $this->settings['email_from'];
      if( !is_email( $email_from ) )
        $email_from = get_option( 'admin_email' ); // set admin email if email settings is not valid

      // Email headers
      $headers  = 'MIME-Version: 1.0' . "\r\n";
      $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
      $headers .= 'From: '. $website_name.' <'.$email_from.'>' . "\r\n";

      // Setting subject
      $title = sprintf( __('Unsubscribe from %s', STC_TEXTDOMAIN),  get_bloginfo( 'name' ) );


      ob_start(); // start buffer
      $this->email_html_unsubscribe( $stc );
      $message = ob_get_contents();
      ob_get_clean();
      
      // encode subject to match åäö for some email clients
      $subject = '=?UTF-8?B?'.base64_encode( $title ).'?=';
      wp_mail( $stc['email'], $subject, $message, $headers );
  }

    /**
     * Returns the content for email unsubscription
     *
     * @since  1.0.0
     * 
     * @param  array $stc 
     * @return string
     */
    private function email_html_unsubscribe( $stc = '' ){
      if(empty( $stc ))
        return false;
      
      ?>
        <h3><?php printf( __('Unsubscribe from %s', STC_TEXTDOMAIN ), get_bloginfo( 'name' ) ); ?></h3>
        <div style="margin-top: 20px;"><a href="<?php echo get_bloginfo('url') . '/?stc_unsubscribe=' . $stc['hash'] . '&stc_user=' . $stc['email'] ?>"><?php _e('Follow this link to confirm your unsubscription', STC_TEXTDOMAIN ); ?></a></div>
      <?php

    }

    /**
     * Collect data from _POST for subscription
     *
     * @since  1.0.0
     * 
     * @return string Notice to user
     */
  	public function collect_post_data(){
  		
      // correct form submitted
  		if( isset( $_POST['action']) && $_POST['action'] == 'stc_subscribe_me' ) {

        // if there is an unsubscription event
        if( isset( $_POST['stc-unsubscribe'] ) && $_POST['stc-unsubscribe'] == 1 ){

          // check if email is valid
          if( is_email( $_POST['stc_email'] ) )
            $data['email'] = $_POST['stc_email'];
          else
            $error[] = __( 'You need to enter a valid email address', STC_TEXTDOMAIN );
          
          // check if user exists and through error if not          
          if(empty( $error )){

            $this->data = $data;
            $result = $this->subscriber_exists();

            if( empty( $result ))
              $error[] = __( 'Email address not found in database', STC_TEXTDOMAIN );
          }

          if(! empty ($error ))
            return $this->error = $error;

          $this->send_unsubscribe_mail( $result );

          $notice[] = __('We have received your request to unsubscribe from our newsfeed. Please check your email and confirm your unsubscription.', STC_TEXTDOMAIN );

          return $this->notice = $notice;
        }


				// bail if nonce fail
				if( ! isset( $_POST['stc_nonce'] ) || ! wp_verify_nonce( $_POST['stc_nonce'], 'wp_nonce_stc' ) )
   				wp_die('Error when validating nonce ...');


        // check if email is valid and save an error if not
 				$error = false;
 				if( is_email( $_POST['stc_email'] ) )
 					$data['email'] = $_POST['stc_email'];
 				else
 					$error[] = __( 'You need to enter a valid email address', STC_TEXTDOMAIN );

        
        // subscribe for all categories
        $data['all_categories'] = false;
        if( isset( $_POST['stc_all_categories']) )
          $data['all_categories'] = true;

        // is there a category selected
 				if(! empty( $_POST['stc_categories'] ))
 					$data['categories'] = $_POST['stc_categories'];
 				else
 					$error[] = __( 'You need to select some categories', STC_TEXTDOMAIN );


        // save user to subscription post type if no error
 				if(empty( $error )){
 					$this->data = $data;
 					$post_id = $this->insert_or_update_subscriber();

          $stc_hash = get_post_meta( $post_id, '_stc_hash', true );
          $url_querystring = '?stc_status=success&stc_hash=' . $stc_hash;

				}else{
 					return $this->error = $error;
 				}

				wp_redirect( get_permalink() . $url_querystring );
        exit;
			
      }
  			

  	}

  	/**
  	 * Check if subscriber already exists
     *
     * @since  1.0.0
     * 
  	 * @return int post_id
  	 */
  	private function subscriber_exists(){
  		global $wpdb;
  		$data = $this->data;
  		
  		$result = $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s", $data['email'], 'stc') );

  		if(empty( $result ))
  			return false;

  		return $result->ID;

  	}

  	/**
  	 * Update user with selected categories if user exists, else add user as new user.
     *
     * @since  1.0.0
     *  
  	 * @param  string $post_data currently not in use
  	 */
  	private function insert_or_update_subscriber( $post_data = '' ){
  		$data = $this->data;

  		if(empty( $data ))
  			$data = $post_data;

  		if(empty( $data ))
  			return false;

      // already exists, grab the post id
  		$post_id = $this->subscriber_exists();

  		$post_data = array(
  			'ID'            => $post_id,
				'post_type'     => 'stc',
			  'post_title'    => $data['email'],
			  'post_status'   => 'publish',
			  'post_author'   => 1,
			  'post_category' => $data['categories']
			);		

  		// update post if subscriber exist, else insert as new post
  		if(!empty( $post_id )){
  			$post_id = wp_update_post( $post_data );
  		}else{
  			$post_id = wp_insert_post( $post_data );
        update_post_meta( $post_id, '_stc_hash', md5( $data['email'].time() ) );
  		}

      // update post meta if the user subscribes to all categories
      if( $data['all_categories'] == true )
        update_post_meta( $post_id, '_stc_all_categories', 1 );
      else 
        delete_post_meta( $post_id, '_stc_all_categories' );

      return $post_id;
  	
  	}

    /**
     * Save post for stc post_type from admin
     *
     * @since  1.0.0
     */
    public function save_post_stc( $post_id ) {
      global $post;

      // bail for bulk actions and auto-drafts
      if(empty( $_POST )) 
        return false;

      // only trigger this from admin
      if( $_POST['post_type'] != 'stc' )
        return false;

      // Bail if we're doing an auto save  
      if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
        return false; 

      // if our current user can't edit this post, bail  
      if( !current_user_can( 'edit_post' ) ) 
        return false;  

      // get categories to for counting and comparing to user categories
      $categories = get_categories( array('hide_empty' => false ));
      $sum_of_categories = count( $categories );
      $sum_of_post_categories = count( $_POST['post_category'] ) - 1; // wp sets a dummy item in post_category, therefore -1


      // sanitize input
      $email = sanitize_email( $_POST['post_title'] );

      // is email valid 
      if(! is_email( $email ) ){
        set_transient( 'error', __( 'You need to enter a valid email address', STC_TEXTDOMAIN ) ); // set error if not valid
      } else {
        $this->data['email'] = $email;
        $post_id_match = $this->subscriber_exists();  
      }
        
      if( $post_id_match != $post_id || empty( $post_id_match )){
        set_transient('error', __('E-mail address already exists', STC_TEXTDOMAIN ) ); // set error
      }

      if( $sum_of_post_categories < 1 ){
       set_transient('error', __('No categories are selected', STC_TEXTDOMAIN ) );  // set error
      }

      $error = get_transient( 'error' );

      // if there are errors set post to draft
      if(!empty( $error )){

        remove_action('save_post_stc', array( $this, 'save_post_stc' ) );
        // update the post set it to draft
        wp_update_post( array('ID' => $post_id, 'post_status' => 'draft' ) );
        
        add_action( 'save_post_stc', array( $this, 'save_post_stc' ) );

        return false;
      }

      // no errors, continue 
      // is there a hash for user
      $hash_exists = get_post_meta( $post_id, '_stc_hash', true );
      if( empty( $hash_exists ))
        update_post_meta( $post_id, '_stc_hash', md5( $this->data['email'].time() ) );


      // check if user has all categories and update post meta if true
      if( $sum_of_categories == $sum_of_post_categories )
        update_post_meta( $post_id, '_stc_all_categories', 1 );
      else
        delete_post_meta( $post_id, '_stc_all_categories' );

    }

    /**
     * Display error in wordpress format as notice if exists
     *
     * @since  1.0.0
     */
    public function save_post_stc_error(){

      if ( get_transient( 'error' ) ) {
        $error = get_transient( 'error' );

        $error .= __( ' - this post is set to draft', STC_TEXTDOMAIN );
        printf( '<div id="message" class="error"><p><strong>%s</strong></p></div>', $error );
        delete_transient( 'error' );
      } 

    }


  	/**
  	 * Render html to subscribe to categories
     *
     * @since  1.0.0 
  	 * 
     * @return [type] [description]
     *
     * @todo add some filter 
  	 */
  	public function stc_subscribe_render(){

      //start buffering
  		ob_start();
  		$this->html_render();
  		$form = ob_get_contents();
  		ob_get_clean();
  		//$form = apply_filters( 'stc_form', $form, 'teststring' );
  		return $form;
  	}


    /**
     * Adding jQuery to footer
     *
     * @since  1.0.0
     */
    public function add_script_to_footer(){
      ?>
      <script type="text/javascript">
      jQuery(function($){
          
          $('#stc-unsubscribe-checkbox').click( function() {
            
            if( $(this).prop('checked') == true ) {
              $('.stc-categories').hide();
              $('#stc-subscribe-btn').hide();
              $('#stc-unsubscribe-btn').show();
            }else{
              $('.stc-categories').show();
              $('#stc-subscribe-btn').show();
              $('#stc-unsubscribe-btn').hide();
            }
          });

          $('#stc-all-categories').click( function() {
            if( $(this).prop('checked') == true ) {
              $('div.stc-categories-checkboxes').hide();
              $('div.stc-categories-checkboxes input[type=checkbox]').each(function () {
              $(this).prop('checked', true);
            }); 
            }else{
              $('div.stc-categories-checkboxes').show();
              $('div.stc-categories-checkboxes input[type=checkbox]').each(function () {
              $(this).prop('checked', false);
            });
            }              
            
          });

      });
      </script>

      <?php

    }

  	/**
  	 * Html for subscribe form
     *
     * @since  1.0.0
     *  
  	 * @return [type] [description]
  	 */
  	public function html_render(){

      // add hook when we have a request to render html
  		add_action('wp_footer', array( $this, 'add_script_to_footer' ), 20);
  		
      
      // getting all categories
      $args = array( 'hide_empty' => 0 );
  		$cats = get_categories( $args );


      // if error store email address in field value so user dont need to add it again
  		if(!empty( $this->error)){
  			if( isset( $_POST['stc_email']) )
  				$email = $_POST['stc_email'];
  		}

      // Is there a unsubscribe action
      $post_stc_unsubscribe = false;
      if( isset( $_POST['stc-unsubscribe'] ) && $_POST['stc-unsubscribe'] == 1 )
        $post_stc_unsubscribe = 1;

  		?>

  		<div class="stc-subscribe-wrapper well">

  			<?php if(!empty( $this->error )) : //printing error if exists ?>
  				<?php foreach( $this->error as $error ) : ?>
  					<div class="stc-error"><?php echo $error; ?></div>
  				<?php endforeach; ?>
  			<?php endif; ?>

        <?php if(!empty( $this->notice )) : //printing notice if exists ?>
          <?php foreach( $this->notice as $notice ) : ?>
            <div class="stc-notice"><?php echo $notice; ?></div>
          <?php endforeach; ?>
        <?php else: ?>

  			<form role="form" method="post">
          <div class="form-group">
  				  <label for="stc-email"><?php _e( 'E-mail Address: ', STC_TEXTDOMAIN ); ?></label>
  				  <input type="text" id="stc-email" class="form-control" name="stc_email" value="<?php echo !empty( $email ) ? $email : NULL; ?>"/>
          </div>

          <div class="checkbox">
            <label>
              <input type="checkbox" id="stc-unsubscribe-checkbox" name="stc-unsubscribe" value="1" <?php checked( '1', $post_stc_unsubscribe ); ?>>
              <?php _e( 'Unsubscribe me', STC_TEXTDOMAIN ) ?>
            </label>
          </div>

          <div class="stc-categories"<?php echo $post_stc_unsubscribe == 1 ? ' style="display:none;"' : NULL; ?>>
            <h3><?php _e('Categories', STC_TEXTDOMAIN ); ?></h3>
            <div class="checkbox">
              <label>
                <input type="checkbox" id="stc-all-categories" name="stc_all_categories" value="1">
                <?php _e('All categories', STC_TEXTDOMAIN ); ?>
              </label>
            </div>
            <div class="stc-categories-checkboxes">
    				<?php foreach ($cats as $cat ) : ?>
            <div class="checkbox">
      				<label>
      					<input type="checkbox" name="stc_categories[]" value="<?php echo $cat->cat_ID ?>">
      					<?php echo $cat->cat_name; ?>
      				</label>
            </div>
  				  <?php endforeach; ?>
          </div><!-- .stc-categories-checkboxes -->
          </div><!-- .stc-categories -->

  				<input type="hidden" name="action" value="stc_subscribe_me" />
  				<?php wp_nonce_field( 'wp_nonce_stc', 'stc_nonce', true, true ); ?>
          <button id="stc-subscribe-btn" type="submit" class="btn btn-default"<?php echo $post_stc_unsubscribe == 1 ? ' style="display:none;"' : NULL; ?>><?php _e( 'Subscribe me', STC_TEXTDOMAIN ) ?></button>
          <button id="stc-unsubscribe-btn" type="submit" class="btn btn-default"<?php echo $post_stc_unsubscribe != 1 ? ' style="display:none;"' : NULL; ?>><?php _e( 'Unsubscribe', STC_TEXTDOMAIN ) ?></button>
  			</form>
        <?php endif; ?>

  		</div><!-- .stc-subscribe-wrapper -->

  		<?php
  	}


    /**
     * On the scheduled action hook, run a function.
     *
     * @since  1.0.0
     */
    public function stc_send_email() {
      global $wpdb;

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

      // add categories to object
      $outbox = array();
      foreach ( $posts as $p ) {
        $p->categories = array();

        $cats = get_the_category( $p->ID );
        foreach( $cats as $cat ){
          $p->categories[] = $cat->term_id;
        }
        $outbox[] = $p;
      }

      if(!empty( $outbox )){
        $this->send_notifier( $outbox );
      }

    }

    /**
     * Send notifier to subscribers
     *
     * @since  1.0.0
     * 
     * @param  object $outbox
     */
    private function send_notifier( $outbox = '' ){
      $subscribers = $this->get_subscribers();

      $i = 0;
      $emails = array();
      foreach ($outbox as $post ) {
          
        // edit category value so it could be used in in_array(), we dont want a value 2 to be match with value 22
        $post_cat_compare = array();
        if(!empty( $post->categories )){
          foreach ($post->categories as $cat ) {
            $post_cat_compare[] = ':' . $cat . ':';
          }
        }


        foreach( $subscribers as $subscriber ) {      
          
          foreach( $subscriber->categories as $categories ) {
            
            // add compare signs for in_array()
            $categories = ':' . $categories . ':';

            if(in_array( $categories, $post_cat_compare )){
              $emails[$i]['subscriber_id'] = $subscriber->ID;
              $emails[$i]['hash'] = get_post_meta( $subscriber->ID, '_stc_hash', true );
              $emails[$i]['email'] = $subscriber->post_title;
              $emails[$i]['post_id'] = $post->ID;
              $emails[$i]['post'] = $post;
              $i++; 
            }                 
          
          }
        
        }
      
      }

      //remove duplicates, we will just send one email to subscriber
      $emails = array_intersect_key( $emails , array_unique( array_map('serialize' , $emails ) ) ); 

      $website_name = get_bloginfo( 'name' );
      $email_title = $this->settings['title'];      

      $email_from = $this->settings['email_from'];
      if( !is_email( $email_from ) )
        $email_from = get_option( 'admin_email' ); // set admin email if email settings is not valid

      $headers  = 'MIME-Version: 1.0' . "\r\n";
      $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
      $headers .= 'From: '. $website_name.' <'.$email_from.'>' . "\r\n";

      // loop through subscribers and send notice
      $i = 1; // loop counter
      foreach ($emails as $email ) {

        ob_start(); // start buffering and get content
        $this->email_html_content( $email );
        $message = ob_get_contents();
        ob_get_clean();

        $email_subject = $email_title;
        if( empty( $email_title ))
          $email_subject = $email['post']->post_title;

        $subject = '=?UTF-8?B?'.base64_encode( $email_subject ).'?=';

        wp_mail( $email['email'], $subject, $message, $headers );

        // sleep 2 seconds once every 25 email to prevent blacklisting
        if( $i == $sleep_flag ){
          sleep(2); // sleep for two seconds, then proceed
          $i = 1; // reset loop counter
        }

        $i++;

      }

      //update some postmeta that email is sent
      foreach ($outbox as $post ) {
        update_post_meta( $post->ID, '_stc_notifier_status', 'sent' );
        update_post_meta( $post->ID, '_stc_notifier_sent_time', mysql2date( 'Y-m-d H:i:s', time() ) );
      }
        
    }

    /**
     * Render html to email. 
     * Setting limit to content as we still want the user to click and visit our site.
     *
     * @since  1.0.0
     * 
     * @param  object $email
     */    
    private function email_html_content( $email ){
      ?>
      <h3><a href="<?php echo get_permalink( $email['post_id']) ?>"><?php echo $email['post']->post_title; ?></a></h3>
      <div><?php echo apply_filters('the_content', $this->string_cut( $email['post']->post_content, 130 ) );?></div>
      <div style="border-bottom: 1px solid #cccccc; padding-bottom: 10px;"><a href="<?php echo get_permalink( $email['post_id'] ); ?>"> <?php _e('Click here to read full story', STC_TEXTDOMAIN ); ?></a></div>
      <div style="margin-top: 20px;"><a href="<?php echo get_bloginfo('url') . '/?stc_unsubscribe=' . $email['hash'] . '&stc_user=' . $email['email']; ?>"><?php _e('Unsubscribe me', STC_TEXTDOMAIN ); ?></a></div>
      <?php
    }


    /**
     * Cut a text string closest word on a given length.
     *
     * @since  1.0.0
     * 
     * @param  string $string
     * @param  int $max_length
     * @return string
     */
    private function string_cut( $string, $max_length ){  

      // remove shortcode if there is
      $string = strip_shortcodes( $string ); 

      if( strlen( $string ) > $max_length ){  
        $string = substr( $string, 0, $max_length );  
        $pos = strrpos( $string, ' ' );  
          
        if($pos === false) {  
          return substr($string, 0, $max_length)." ... ";  
        }  
        return substr($string, 0, $pos)." ... ";  

      }else{  
        return $string;  
      }  
    }  

    /**
     * Get all subscribers with subscribed categories
     *
     * @since  1.0.0
     * 
     * @return object Subscribers
     */
    private function get_subscribers(){

      $args = array(
        'post_type'   => 'stc',
        'numberposts' => -1,
        'post_status' => 'publish'
      );

      $stc = get_posts( $args );

      $subscribers = array();
      foreach ($stc as $s) {
        $s->categories = array();

        $cats = get_the_category( $s->ID );
    
        foreach ($cats as $cat ) {
          $s->categories[] = $cat->term_id;
        }

        $subscribers[] = $s;
      }

      return $subscribers;

    }

  	/**
  	 * Register custom post type for subscribers
     *
     * @since  1.0.0 
  	 */
  	public function register_post_type(){

			$labels = array( 
			    'name' => __( 'Subscribers', STC_TEXTDOMAIN ),
			    'singular_name' => __( 'Subscribe', STC_TEXTDOMAIN ),
			    'add_new' => __( 'Add new subscriber', STC_TEXTDOMAIN ),
			    'add_new_item' => __( 'Add new subscriber', STC_TEXTDOMAIN ),
			    'edit_item' => __( 'Edit subscriber', STC_TEXTDOMAIN ),
			    'new_item' => __( 'New subscriber', STC_TEXTDOMAIN ),
			    'view_item' => __( 'Show subscriber', STC_TEXTDOMAIN ),
			    'search_items' => __( 'Search subscribers', STC_TEXTDOMAIN ),
			    'not_found' => __( 'Not found', STC_TEXTDOMAIN ),
			    'not_found_in_trash' => __( 'Nothing found in trash', STC_TEXTDOMAIN ),
			    'menu_name' => __( 'Subscribers', STC_TEXTDOMAIN ),
			);

			$args = array( 
			    'labels' => $labels,
			    'hierarchical' => true,
			    'supports' => array( 'title' ),
			    'public' => false,
          'menu_icon' => 'dashicons-groups',
			    'show_ui' => true,
			    'show_in_menu' => true,
			    'show_in_nav_menus' => true,
			    'publicly_queryable' => false,
			    'exclude_from_search' => true,
			    'has_archive' => false,
			    'query_var' => true,
			    'can_export' => true,
			    'rewrite' => true,
			    'capability_type' => 'post',
			    'taxonomies' => array( 'category' )
			);

			register_post_type( 'stc', $args );

  	}

  }

?>