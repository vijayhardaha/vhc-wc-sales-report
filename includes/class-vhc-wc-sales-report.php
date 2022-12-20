<?php
/**
 * Main class for plugin setup.
 *
 * @package VHC_WC_Sales_Report\Classes
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.1
	 */
	private function init_hooks() {
		register_shutdown_function( array( $this, 'log_errors' ) );

		add_action( 'admin_init', array( $this, 'init' ) );
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
	 * Include required core files used in admin and on the frontend.
	 *
	 * @since 1.0.0
	 */
	public function includes() {
		if ( is_admin() ) {
			include_once VHC_WC_SALES_REPORT_ABSPATH . 'includes/class-vhc-wc-sales-report-admin.php';
		}
	}

	/**
	 * Init when WordPress Initialises.
	 *
	 * @since 1.0.1
	 */
	public function init() {
		/**
		 * Action triggered before initialization begins.
		 */
		do_action( 'before_vhc_wc_sales_report_init' );

		// Set up localisation.
		$this->load_plugin_textdomain();

		/**
		 * Action triggered after initialization finishes.
		 */
		do_action( 'vhc_wc_sales_report_init' );
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
