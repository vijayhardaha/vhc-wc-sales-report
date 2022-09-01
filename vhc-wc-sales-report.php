<?php
/**
 * Plugin Name: VHC WooCommerce Sales Report
 * Plugin URI: https://github.com/vijayhardaha/vhc-wc-sales-report
 * Description: Generates a custom product sales report during a specified time period with a filtering option.
 * Version: 1.0.0
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

defined( 'ABSPATH' ) || die( 'Don\'t run this file directly!' );

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( ! defined( 'VHC_WC_SALES_REPORT_PLUGIN_FILE' ) ) {
	define( 'VHC_WC_SALES_REPORT_PLUGIN_FILE', __FILE__ );
}

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
