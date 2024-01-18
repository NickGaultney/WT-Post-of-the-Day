<?php
/**
 * Main plugin class file.
 *
 * @package WordPress Plugin Template/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class WT_Post_of_the_Day {

	/**
	 * The single instance of WT_Post_of_the_Day.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * Local instance of WT_Post_of_the_Day_Admin_API
	 *
	 * @var WT_Post_of_the_Day_Admin_API|null
	 */
	public $admin = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version; //phpcs:ignore

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token; //phpcs:ignore

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for JavaScripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Database Table Name
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . "wt_potd";
	}

	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'wt_post_of_the_day';

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		add_action( 'update_option_wt_potd_category', function($old_value, $value, $option){

		    if( $old_value !== $value ){
		            $this->install();
		    }

		}, 10, 3 );

		add_action( 'update_option_wt_potd_time_central_standard_time', function($old_value, $value, $option){

		    if( $old_value !== $value ){
		    		wp_clear_scheduled_hook( 'wt_potd' );
		            $this->load_event();
		    }

		}, 10, 3 );

		// Load API for generic admin functions.
		if ( is_admin() ) {
			$this->admin = new WT_Post_of_the_Day_Admin_API();
		}

		// Handle localisation.
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
	} // End __construct ()

	/**
	 * Register post type function.
	 *
	 * @param string $post_type Post Type.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param string $description Description.
	 * @param array  $options Options array.
	 *
	 * @return bool|string|WT_Post_of_the_Day_Post_Type
	 */
	public function register_post_type( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) {
			return false;
		}

		$post_type = new WT_Post_of_the_Day_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param array  $post_types Post types to register this taxonomy for.
	 * @param array  $taxonomy_args Taxonomy arguments.
	 *
	 * @return bool|string|WT_Post_of_the_Day_Taxonomy
	 */
	public function register_taxonomy( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) {
			return false;
		}

		$taxonomy = new WT_Post_of_the_Day_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 *
	 * @access  public
	 * @return void
	 * @since   1.0.0
	 */
	public function enqueue_styles() {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function enqueue_scripts() {
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-frontend' );
	} // End enqueue_scripts ()

	/**
	 * Admin enqueue style.
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 *
	 * @access  public
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function admin_enqueue_scripts( $hook = '' ) {
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_localisation() {
		load_plugin_textdomain( 'wt-post-of-the-day', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'wt-post-of-the-day';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main WT_Post_of_the_Day Instance
	 *
	 * Ensures only one instance of WT_Post_of_the_Day is loaded or can be loaded.
	 *
	 * @param string $file File instance.
	 * @param string $version Version parameter.
	 *
	 * @return Object WT_Post_of_the_Day instance
	 * @see WT_Post_of_the_Day()
	 * @since 1.0.0
	 * @static
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of WT_Post_of_the_Day is forbidden' ) ), esc_attr( $this->_version ) );

	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of WT_Post_of_the_Day is forbidden' ) ), esc_attr( $this->_version ) );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();

		// Delete if exists to ensure fresh start
		$this->delete_cache();
		$this->delete_db();
		wp_clear_scheduled_hook( 'wt_potd' );

		$this->initialize_db();
		$this->load_posts();
		$this->load_event();
	} // End install ()

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	private function _log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

	private function delete_cache() {
		delete_transient('wt_potd_title');
		delete_transient('wt_potd_content');
	}

	private function delete_db() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . WT_Post_of_the_Day::table_name() );
	}

	private function initialize_db() {
		// This is the wordpress database variable used to access the database directly using standard SQL commands
		global $wpdb;
		// The name of our PotD table in the DB
		$table_name = WT_Post_of_the_Day::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// The sql statement for generating our DB table
		$sql = "CREATE TABLE $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  title tinytext NOT NULL,
		  content text NOT NULL,
		  cyclePosition mediumint(9) NOT NULL,
		  isActive boolean NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";

		// Can't remember what these two do
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	private function load_posts() {
		// This is the wordpress database variable used to access the database directly using standard SQL commands
		global $wpdb;
		// Get all posts from the database that are categorized as a "potd"
		$option_name = $this->settings->base . 'potd_category';
		$potd_cat = get_option($option_name);
		$data = new WP_Query( array( 'posts_per_page' => '-1', 'order'   => 'ASC', 'cat' => $potd_cat ) );  

		// Loop through all of the posts in the "potd" category and 
		// add them to our custom table for ease of use
		$count = 1; 
		if ( $data->have_posts() ) {
			while ( $data->have_posts() ) {
				$data->the_post();

				$wpdb->insert( 
				WT_Post_of_the_Day::table_name(), 
				array( 
					'title' => get_the_title(), 
					'content' => get_the_content(), 
					'cyclePosition' => $count,
					'isActive' => false,
				) 
			);

			$count += 1;
			}
		}

		// Set the first PotD as the current PotD
		$wpdb->update( WT_Post_of_the_Day::table_name(), array( 'isActive' => 1 ), array( 'cyclePosition' => 1 ) );
	}

	private function load_event() {
		if (!wp_next_scheduled('wt_potd')) {
		   $time = strtotime('today'); 									//returns today midnight
		   $time = $time + $this->get_time_offset_in_gmt(); 			// Keeping in mind this is in GMT.
		   do_action( 'qm/debug', 'Scheduling the cron post');
		   wp_schedule_event($time, 'daily', 'wt_potd');
		}
	}

	/*
		This takes the time from the options page and converts it
		from MST to GMT since WP is relative to GMT. 
	*/
	private function get_time_offset_in_gmt() {
		$option_name = $this->settings->base . 'potd_time_central_standard_time';
		$potd_time = get_option($option_name);
			
		// If there is no time set in the options page 
		if ($potd_time === false) {
			$potd_time_offset = 50400; // This is 14:00 GMT and 8:00 MST
		} else {
			$potd_time_cst = intval( $potd_time ); 
			
			// Since GMT is 6 hours ahead of MST, we need to wrap around to the next
			// day after 6:00 p.m. (19:00) CST to match proper time in GMT and not input an invalid time
			if ($potd_time_cst >= 18) {
				$potd_time_cst = $potd_time_cst - 24;
			}

			$potd_time_gmt = $potd_time_cst + 6;
			$potd_time_offset = $potd_time_gmt * 60 * 60;
		}

		return $potd_time_offset;
	}	
}