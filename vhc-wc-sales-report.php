<?php
/**
 * Plugin Name: VHC WooCommerce Sales Report
 * Plugin URI: https://github.com/vijayhardaha/vhc-wc-sales-report
 * Description: Generates a custom product sales report during a specified time period with a filtering option.
 * Version: 1.0.5
 * Author: Vijay Hardaha
 * Author URI: https://twitter.com/vijayhardaha/
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vhc-wc-sales-report
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.0
 * Tested up to: 6.0
 *
 * @package VHC_WC_Sales_Report
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

// Bail if WooCommerce is not active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	return;
}

define( 'VHC_WC_SALES_REPORT_PLUGIN_FILE', __FILE__ );
define( 'VHC_WC_SALES_REPORT_ABSPATH', dirname( VHC_WC_SALES_REPORT_PLUGIN_FILE ) . '/' );
define( 'VHC_WC_SALES_REPORT_PLUGIN_BASENAME', plugin_basename( VHC_WC_SALES_REPORT_PLUGIN_FILE ) );
define( 'VHC_WC_SALES_REPORT_VERSION', '1.0.5' );

// Include the main VHC_WC_Sales_Report class.
if ( ! class_exists( 'VHC_WC_Sales_Report', false ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-vhc-wc-sales-report.php';
}

/**
 * Returns the main instance of VHC_WC_Sales_Report.
 *
 * @since 1.0.0
 * @return VHC_WC_Sales_Report
 */
function vhc_wc_sales_report() {
	return VHC_WC_Sales_Report::instance();
}

// Global for backwards compatibility.
$globals['vhc_wc_sales_report'] = vhc_wc_sales_report();
