<?php
/**
 * Settings class file.
 *
 * @package WordPress Plugin Template/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class WT_Post_of_the_Day_Settings {

	/**
	 * The single instance of WT_Post_of_the_Day_Settings.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	public static function get_category() {
		return get_option( 'wt_' . 'potd_category' );
	}

	public static function get_time() {
		return get_option( 'wt_' . 'potd_time_central_standard_time' );
	}

	public static function get_list() {
		return get_option( 'wt_' . 'potd_newsletter_list' );
	}

	public static function get_image() {
		return get_option( 'wt_' . 'potd_newsletter_image' );
	}

	public static function get_title() {
		return get_option( 'wt_' . 'potd_newsletter_title' );
	}

	public static function get_subtitle() {
		return get_option( 'wt_' . 'potd_newsletter_subtitle' );
	}

	/**
	 * Constructor function.
	 *
	 * @param object $parent Parent object.
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;

		$this->base = 'wt_';

		// Initialise settings.
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add settings page to menu.
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->parent->file ),
			array(
				$this,
				'add_settings_link',
			)
		);

		// Configure placement of plugin settings page. See readme for implementation.
		add_filter( $this->base . 'menu_settings', array( $this, 'configure_settings' ) );
	}

	/**
	 * Initialise settings
	 *
	 * @return void
	 */
	public function init_settings() {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_menu_item() {

		$args = $this->menu_settings();

		// Do nothing if wrong location key is set.
		if ( is_array( $args ) && isset( $args['location'] ) && function_exists( 'add_' . $args['location'] . '_page' ) ) {
			switch ( $args['location'] ) {
				case 'options':
				case 'submenu':
					$page = add_submenu_page( $args['parent_slug'], $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'] );
					break;
				case 'menu':
					$page = add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'], $args['icon_url'], $args['position'] );
					break;
				default:
					return;
			}
			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
		}
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function menu_settings() {
		return apply_filters(
			$this->base . 'menu_settings',
			array(
				'location'    => 'options', // Possible settings: options, menu, submenu.
				'parent_slug' => 'options-general.php',
				'page_title'  => __( 'PotD Settings', 'wt-post-of-the-day' ),
				'menu_title'  => __( 'PotD Settings', 'wt-post-of-the-day' ),
				'capability'  => 'manage_options',
				'menu_slug'   => $this->parent->_token . '_settings',
				'function'    => array( $this, 'settings_page' ),
				'icon_url'    => '',
				'position'    => null,
			)
		);
	}

	/**
	 * Container for settings page arguments
	 *
	 * @param array $settings Settings array.
	 *
	 * @return array
	 */
	public function configure_settings( $settings = array() ) {
		return $settings;
	}

	/**
	 * Load settings JS & CSS
	 *
	 * @return void
	 */
	public function settings_assets() {

		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
		wp_enqueue_style( 'farbtastic' );
		wp_enqueue_script( 'farbtastic' );

		// We're including the WP media scripts here because they're needed for the image upload field.
		// If you're not including an image upload then you can leave this function call out.
		wp_enqueue_media();

		wp_register_script( $this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js', array( 'farbtastic', 'jquery' ), '1.0.0', true );
		wp_enqueue_script( $this->parent->_token . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 *
	 * @param  array $links Existing links.
	 * @return array        Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __( 'Settings', 'wt-post-of-the-day' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	/**
	 * Build settings fields
	 *
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields() {
		// Get all post types
	    $post_types = get_post_types( array( 'public' => true ), 'objects' );

	    // Create an array to store post type names
	    $post_type_options = array();
	    foreach ( $post_types as $post_type ) {
	        $post_type_options[ $post_type->name ] = $post_type->label;
	    }


		$settings['standard'] = array(
			'title'       => __( 'Standard', 'wt-post-of-the-day' ),
			'description' => __( 'These are fairly standard form input fields.', 'wt-post-of-the-day' ),
			'fields'      => array(
				array(
					'id'          => 'potd_category',
					'label'       => __( 'Category', 'wt-post-of-the-day' ),
					'description' => __( 'This is the post type for all the PotD. Note: Currently does nothing', 'wt-post-of-the-day' ),
					'type'        => 'select',
					'options'     => $post_type_options, // Use the dynamically generated options
					'default'     => 'post',
					'placeholder' => __( 'Placeholder text', 'wt-post-of-the-day' ),
				),
				array(
					'id'          => 'potd_time_central_standard_time',
					'label'       => __( 'Event Time', 'wt-post-of-the-day' ),
					'description' => __( 'This is the time for the PotD event', 'wt-post-of-the-day' ),
					'type'        => 'select',
					'options'     => array(
						"0" => '0:00',
						"1" => '1:00',
						"2" => '2:00',
						"3" => '3:00',
						"4" => '4:00',
						"5" => '5:00',
						"6" => '6:00',
						"7" => '7:00',
						"8" => '8:00',
						"9" => '9:00',
						"10" => '10:00',
						"11" => '11:00',
						"12" => '12:00',
						"13" => '13:00',
						"14" => '14:00',
						"15" => '15:00',
						"16" => '16:00',
						"17" => '17:00',
						"18" => '18:00',
						"19" => '19:00',
						"20" => '20:00',
						"21" => '21:00',
						"22" => '22:00',
						"23" => '23:00',
					),
					'default'     => "14",
				),
				array(
					'id'          => 'potd_newsletter_list',
					'label'       => __( 'Newsletter List', 'wt-post-of-the-day' ),
					'description' => __( 'This is the Newsletter list that the PotD should be sent to', 'wt-post-of-the-day' ),
					'type'        => 'text',
					'default'     => '1',
					'placeholder' => __( 'Placeholder text', 'wt-post-of-the-day' ),
				),
				array(
					'id'          => 'potd_newsletter_image',
					'label'       => __( 'Newsletter image', 'wt-post-of-the-day' ),
					'description' => __( 'This is the Newsletter image that is displayed in the email. Note: Currently does nothing', 'wt-post-of-the-day' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( 'Placeholder text', 'wt-post-of-the-day' ),
				),
				array(
					'id'          => 'potd_newsletter_title',
					'label'       => __( 'Newsletter title', 'wt-post-of-the-day' ),
					'description' => __( 'This is the Newsletter title that is displayed in the email. Note: Currently does nothing', 'wt-post-of-the-day' ),
					'type'        => 'text',
					'default'     => 'Title',
					'placeholder' => __( 'Placeholder text', 'wt-post-of-the-day' ),
				),
				array(
					'id'          => 'potd_newsletter_subtitle',
					'label'       => __( 'Newsletter subtitle', 'wt-post-of-the-day' ),
					'description' => __( 'This is the Newsletter subtitle that is displayed in the email. Note: Currently does nothing', 'wt-post-of-the-day' ),
					'type'        => 'text',
					'default'     => 'subtitle',
					'placeholder' => __( 'Placeholder text', 'wt-post-of-the-day' ),
				),
			),
		);

		$settings['extra'] = array(
			'title'       => __( 'Extra', 'wt-post-of-the-day' ),
			'description' => __( 'These are some extra input fields that maybe aren\'t as common as the others.', 'wt-post-of-the-day' ),
			'fields'      => array(),
		);

		$settings = apply_filters( $this->parent->_token . '_settings_fields', $settings );

		return $settings;
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings() {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab.
			//phpcs:disable
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = $_POST['tab'];
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = $_GET['tab'];
				}
			}
			//phpcs:enable

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section !== $section ) {
					continue;
				}

				// Add section to page.
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), $this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field.
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field.
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page.
					add_settings_field(
						$field['id'],
						$field['label'],
						array( $this->parent->admin, 'display_field' ),
						$this->parent->_token . '_settings',
						$section,
						array(
							'field'  => $field,
							'prefix' => $this->base,
						)
					);
				}

				if ( ! $current_section ) {
					break;
				}
			}
		}
	}

	/**
	 * Settings section.
	 *
	 * @param array $section Array of section ids.
	 * @return void
	 */
	public function settings_section( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html; //phpcs:ignore
	}

	/**
	 * Load settings page content.
	 *
	 * @return void
	 */
	public function settings_page() {
		// *********** DEBUG HERE ****************
		//do_action( 'qm/debug', $data );
		
		// Build page HTML.
		$html      = '<div class="wrap" id="' . $this->parent->_token . '_settings">' . "\n";
			$html .= '<h2>' . __( 'PotD Settings', 'wt-post-of-the-day' ) . '</h2>' . "\n";

			$tab = '';
		//phpcs:disable
		if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
			$tab .= $_GET['tab'];
		}
		//phpcs:enable

		// Show page tabs.
		if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

			$html .= '<h2 class="nav-tab-wrapper">' . "\n";

			$c = 0;
			foreach ( $this->settings as $section => $data ) {

				// Set tab class.
				$class = 'nav-tab';
				if ( ! isset( $_GET['tab'] ) ) { //phpcs:ignore
					if ( 0 === $c ) {
						$class .= ' nav-tab-active';
					}
				} else {
					if ( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) { //phpcs:ignore
						$class .= ' nav-tab-active';
					}
				}

				// Set tab link.
				$tab_link = add_query_arg( array( 'tab' => $section ) );
				if ( isset( $_GET['settings-updated'] ) ) { //phpcs:ignore
					$tab_link = remove_query_arg( 'settings-updated', $tab_link );
				}

				// Output tab.
				$html .= '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

				++$c;
			}

			$html .= '</h2>' . "\n";
		}

			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

				// Get settings fields.
				ob_start();
				settings_fields( $this->parent->_token . '_settings' );
				do_settings_sections( $this->parent->_token . '_settings' );
				$html .= ob_get_clean();

				$html     .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings', 'wt-post-of-the-day' ) ) . '" />' . "\n";
				$html     .= '</p>' . "\n";
			$html         .= '</form>' . "\n";
		$html             .= '</div>' . "\n";

		echo $html; //phpcs:ignore
	}

	/**
	 * Main WT_Post_of_the_Day_Settings Instance
	 *
	 * Ensures only one instance of WT_Post_of_the_Day_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WT_Post_of_the_Day()
	 * @param object $parent Object instance.
	 * @return object WT_Post_of_the_Day_Settings instance
	 */
	public static function instance( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of WT_Post_of_the_Day_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of WT_Post_of_the_Day_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __wakeup()

}
