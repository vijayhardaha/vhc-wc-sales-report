<?php
/**
 * VHC WooCommerce Sales Report setup class.
 *
 * @package VHC_WC_Sales_Report
 */

defined( 'ABSPATH' ) || die( 'Don\'t run this file directly!' );

/**
 * VHC_WC_Sales_Report Class.
 */
final class VHC_WC_Sales_Report {

	/**
	 * This class instance.
	 *
	 * @var VHC_WC_Sales_Report single instance of this class.
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Admin notices to add.
	 *
	 * @var array Array of admin notices.
	 * @since 1.0.0
	 */
	private $notices = array();

	/**
	 * Required plugins to check.
	 *
	 * @var array Array of required plugins.
	 * @since 1.0.0
	 */
	private $required_plugins = array(
		'woocommerce/woocommerce.php' => array(
			'url'  => 'https://wordpress.org/plugins/woocommerce/',
			'name' => 'WooCommerce',
		),
	);

	/**
	 * Main VHC_WC_Sales_Report Instance.
	 *
	 * Ensures only one instance of VHC_WC_Sales_Report is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @return VHC_WC_Sales_Report - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * VHC_WC_Sales_Report Constructor.
	 */
	public function __construct() {
		$this->define_constants();

		register_activation_hook( VHC_WC_SALES_REPORT_PLUGIN_FILE, array( $this, 'activation_check' ) );

		register_shutdown_function( array( $this, 'log_errors' ) );

		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		// If the environment check fails, initialize the plugin.
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
		}
	}

	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', esc_html( get_class( $this ) ) ), '1.0.0' );
	}

	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', esc_html( get_class( $this ) ) ), '1.0.0' );
	}

	/**
	 * Ensures fatal errors are logged so they can be picked up in the status report.
	 *
	 * @since 1.0.0
	 */
	public function log_errors() {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				/* translators: 1: Error Message 2: File Name and Path 3: Line Number */
				$error_message = sprintf( __( '%1$s in %2$s on line %3$s', 'vhc-wc-sales-report' ), $error['message'], $error['file'], $error['line'] ) . PHP_EOL;
				// phpcs:disable WordPress.PHP.DevelopmentFunctions
				error_log( $error_message );
				// phpcs:enable WordPress.PHP.DevelopmentFunctions
			}
		}
	}

	/**
	 * Define WC Constants.
	 *
	 * @since 1.0.0
	 */
	private function define_constants() {
		$plugin_data = get_plugin_data( VHC_WC_SALES_REPORT_PLUGIN_FILE );
		$this->define( 'VHC_WC_SALES_REPORT_ABSPATH', dirname( VHC_WC_SALES_REPORT_PLUGIN_FILE ) . '/' );
		$this->define( 'VHC_WC_SALES_REPORT_PLUGIN_BASENAME', plugin_basename( VHC_WC_SALES_REPORT_PLUGIN_FILE ) );
		$this->define( 'VHC_WC_SALES_REPORT_PLUGIN_NAME', $plugin_data['Name'] );
		$this->define( 'VHC_WC_SALES_REPORT_VERSION', $plugin_data['Version'] );
		$this->define( 'VHC_WC_SALES_REPORT_MIN_PHP_VERSION', $plugin_data['RequiresPHP'] );
		$this->define( 'VHC_WC_SALES_REPORT_MIN_WP_VERSION', $plugin_data['RequiresWP'] );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 *
	 * @since 1.0.0
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * What type of request is this?
	 *
	 * @since 1.0.0
	 * @param string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 * @since 1.0.0
	 * @link http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 */
	public function activation_check() {
		if ( ! $this->is_environment_compatible() ) {
			$this->deactivate_plugin();
			wp_die(
				sprintf(
					/* translators: %s Plugin Name */
					esc_html__(
						'%1$s could not be activated. %2$s',
						'vhc-wc-sales-report'
					),
					esc_html( VHC_WC_SALES_REPORT_PLUGIN_NAME ),
					esc_html( $this->get_environment_message() )
				)
			);
		}
	}

	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @since 1.0.0
	 */
	public function check_environment() {
		if ( ! $this->is_environment_compatible() && is_plugin_active( VHC_WC_SALES_REPORT_PLUGIN_BASENAME ) ) {
			$this->deactivate_plugin();
			$this->add_admin_notice(
				'bad_environment',
				'error',
				sprintf(
					/* translators: %s Plugin Name */
					__( '%s has been deactivated.', 'vhc-wc-sales-report' ),
					VHC_WC_SALES_REPORT_PLUGIN_NAME
				) . ' ' . $this->get_environment_message()
			);
		}
	}

	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_notices() {
		if ( ! $this->is_wp_compatible() ) {
			$this->add_admin_notice(
				'update_wordpress',
				'error',
				sprintf(
					/* translators: 1: Plugin Name 2: Minimum WP Version 3: Update Url */
					__( '%1$s requires WordPress version %2$s or higher. Please %3$supdate WordPress &raquo;%4$s', 'vhc-wc-sales-report' ),
					VHC_WC_SALES_REPORT_PLUGIN_NAME,
					VHC_WC_SALES_REPORT_MIN_WP_VERSION,
					'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
					'</a>'
				)
			);
		}

		$missing_dependencies = $this->missing_dependencies();
		if ( ! empty( $missing_dependencies ) ) {
			$this->add_admin_notice(
				'install_required_plugins',
				'error',
				sprintf(
					/* translators: 1: Plugin Name 2: Required Plugins Names */
					__( '%1$s is enabled but not effective. It requires %2$s in order to work.', 'vhc-wc-sales-report' ),
					VHC_WC_SALES_REPORT_PLUGIN_NAME,
					join( ', ', $missing_dependencies )
				)
			);
		}
	}

	/**
	 * Determines if the required plugins are compatible.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function plugins_compatible() {
		return $this->is_wp_compatible() && empty( $this->missing_dependencies() );
	}

	/**
	 * Find the missing dependency plugins names.
	 *
	 * @since 1.0.0
	 * @return Array
	 */
	private function missing_dependencies() {
		$missing_dependencies = array();
		if ( empty( $this->required_plugins ) ) {
			return $missing_dependencies;
		}

		foreach ( $this->required_plugins as $plugin_base => $plugin ) {
			if ( ! is_plugin_active( $plugin_base ) ) {
				$missing_dependencies[] = sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( $plugin['url'] ), $plugin['name'] );
			}
		}

		return $missing_dependencies;
	}

	/**
	 * Determines if the WordPress compatible.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_wp_compatible() {
		if ( ! VHC_WC_SALES_REPORT_MIN_WP_VERSION ) {
			return true;
		}
		return version_compare( get_bloginfo( 'version' ), VHC_WC_SALES_REPORT_MIN_WP_VERSION, '>=' );
	}

	/**
	 * Deactivates the plugin.
	 *
	 * @since 1.0.0
	 */
	protected function deactivate_plugin() {
		deactivate_plugins( VHC_WC_SALES_REPORT_PLUGIN_FILE );

		if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}

	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.0.0
	 * @param string $slug    The slug for the notice.
	 * @param string $class   The css class for the notice.
	 * @param string $message The notice message.
	 */
	private function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}

	/**
	 * Displays any admin notices added with VHC_WC_Sales_Report::add_admin_notice()
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		foreach ( (array) $this->notices as $notice_key => $notice ) {
			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p><?php echo wp_kses( $notice['message'], array( 'strong' => array(), 'a' => array( 'href' => array(), 'target' => array() ) ) ); // @codingStandardsIgnoreLine?></p>
			</div>
			<?php
		}
	}

	/**
	 * Determines if the server environment is compatible with this plugin.
	 * Override this method to add checks for more than just the PHP version.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_environment_compatible() {
		return version_compare( phpversion(), VHC_WC_SALES_REPORT_MIN_PHP_VERSION, '>=' );
	}

	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_environment_message() {
		return sprintf(
			/* translators: 1: Minimum PHP Version 2: Current PHP Version */
			__( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'vhc-wc-sales-report' ),
			VHC_WC_SALES_REPORT_MIN_PHP_VERSION,
			phpversion()
		);
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @since 1.0.0
	 */
	public function includes() {
		if ( $this->is_request( 'admin' ) ) {
			include_once VHC_WC_SALES_REPORT_ABSPATH . 'includes/class-vhc-wc-sales-report-admin.php';
		}
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/vhc-wc-sales-report/vhc-wc-sales-report-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/vhc-wc-sales-report-LOCALE.mo
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		if ( function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
		} else {
			$locale = is_admin() ? get_user_locale() : get_locale();
		}

		$locale = apply_filters( 'plugin_locale', $locale, 'vhc-wc-sales-report' );

		unload_textdomain( 'vhc-wc-sales-report' );
		load_textdomain( 'vhc-wc-sales-report', WP_LANG_DIR . '/vhc-wc-sales-report/vhc-wc-sales-report-' . $locale . '.mo' );
		load_plugin_textdomain( 'vhc-wc-sales-report', false, plugin_basename( dirname( VHC_WC_SALES_REPORT_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	public function init_plugin() {
		if ( ! $this->plugins_compatible() ) {
			return;
		}

		// Include required files.
		$this->includes();

		// Before init action.
		do_action( 'before_vhc_wc_sales_report_init' );

		// Set up localisation.
		$this->load_plugin_textdomain();

		// Init action.
		do_action( 'vhc_wc_sales_report_init' );
	}

	/**
	 * Get the plugin url.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', VHC_WC_SALES_REPORT_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( VHC_WC_SALES_REPORT_PLUGIN_FILE ) );
	}

	/**
	 * Get Ajax URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function ajax_url() {
		return admin_url( 'admin-ajax.php', 'relative' );
	}
}
