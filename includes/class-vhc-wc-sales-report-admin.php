<?php
/**
 * VHC WooCommerce Sales Report admin class.
 *
 * @package VHC_WC_Sales_Report\Classes
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( class_exists( 'VHC_WC_Sales_Report_Admin' ) ) {
	new VHC_WC_Sales_Report_Admin();
	return;
}

/**
 * VHC_WC_Sales_Report_Admin class.
 */
class VHC_WC_Sales_Report_Admin {

	/**
	 * Slug of the admin page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $screen_id = 'vhc-wc-sales-report';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Enqueue scripts & styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 99 );

		// Add menus.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Register ajax callback.
		add_action( 'wp_ajax_vhc_wc_sales_report_generate_report', array( $this, 'generate_report' ) );
		add_action( 'wp_ajax_vhc_wc_sales_report_json_search_terms', array( $this, 'json_search_terms' ) );

		// Download csv action.
		add_action( 'admin_init', array( $this, 'download_report' ) );

		// Add plugin action link.
		add_filter( 'plugin_action_links_' . VHC_WC_SALES_REPORT_PLUGIN_BASENAME, array( $this, 'plugin_manage_link' ), 10, 4 );
	}

	/**
	 * Return the plugin action links.
	 *
	 * @since 1.0.5
	 * @param array $actions An array of actions.
	 * @return array
	 */
	public function plugin_manage_link( $actions ) {
		$url = add_query_arg( array( 'page' => $this->screen_id ), admin_url( 'admin.php' ) );

		array_unshift( $actions, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View Report', 'vhc-wc-sales-report' ) . '</a>' );

		return $actions;
	}
	/**
	 * Valid screen ids for plugin scripts & styles
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_valid_screen() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		if ( empty( $screen_id ) ) {
			return false;
		}

		$matcher = '/' . $this->screen_id . '/';
		if ( preg_match( $matcher, $screen_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return the html selected attribute if stringified $value is found in array of stringified $options
	 * or if stringified $value is the same as scalar stringified $options.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int       $value     Value to find within options.
	 * @param string|int|array $options   Options to go through when looking for value.
	 *
	 * @return string
	 */
	private function selected( $value, $options ) {
		if ( is_array( $options ) ) {
			$options = array_map( 'strval', $options );
			return selected( in_array( (string) $value, $options, true ), true, false );
		}

		return selected( $value, $options, false );
	}

	/**
	 * Return the html selected attribute if stringified $value is found in array of stringified $options
	 * or if stringified $value is the same as scalar stringified $options.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int       $value   Value to find within options.
	 * @param string|int|array $options Options to go through when looking for value.
	 *
	 * @return string
	 */
	private function checked( $value, $options ) {
		if ( is_array( $options ) ) {
			$options = array_map( 'strval', $options );
			return checked( in_array( (string) $value, $options, true ), true, false );
		}

		return checked( $value, $options, false );
	}

	/**
	 * Return products taxonomies for filter.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_taxonomies() {
		// Set cache key for settings.
		$cache_key = 'vhc_wc_sales_report_product_taxonomies';

		// Get settings from cache.
		$options = wp_cache_get( $cache_key, 'vhc-wc-sales-report' );

		// Check if cache data is empty.
		if ( false === $options ) {
			// Fetch product taxonomies.
			$taxonomies = apply_filters( 'vhc_wc_sales_report_filter_taxonomies', array( 'product_cat' ), $this );

			$excluded_taxonomies = apply_filters( 'vhc_wc_sales_report_excluded_filter_taxonomies', array( 'product_type', 'product_visibility', 'product_shipping_class' ), $taxonomies );

			// Remove product type, shipping class and visibility.
			$taxonomies = empty( $taxonomies ) ? array() : array_diff( $taxonomies, $excluded_taxonomies );

			$options = array();
			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $tax ) {
					$taxonomy = get_taxonomy( $tax );

					// Check if valid taxonomy of not.
					if ( is_wp_error( $taxonomy ) || ! $taxonomy ) {
						continue;
					}

					$options[] = array(
						'id'            => $tax,
						'name'          => strtolower( $taxonomy->labels->name ),
						'singular_name' => strtolower( $taxonomy->labels->singular_name ),
						'field_id'      => 'taxonomy-' . esc_attr( $tax ) . '-ids',
						'field_name'    => 'taxonomy_' . esc_attr( $tax ) . '_ids[]',
						'setting_key'   => 'taxonomy_' . esc_attr( $tax ) . '_ids',
					);
				}
			}

			// Set settings cache.
			wp_cache_set( $cache_key, $options, 'vhc-wc-sales-report' );
		}

		return $options;
	}

	/**
	 * Return default settings array.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function default_settings() {
		$settings = array(
			'period'           => 'last-30-days',
			'start_date'       => gmdate( 'Y-m-d', strtotime( 'today midnight' ) - MONTH_IN_SECONDS ),
			'end_date'         => gmdate( 'Y-m-d', strtotime( 'today midnight' ) + DAY_IN_SECONDS - 1 ),
			'order_status'     => array( 'wc-processing', 'wc-on-hold', 'wc-completed' ),
			'billing_country'  => array(),
			'shipping_country' => array(),
			'include_products' => array(),
			'exclude_products' => array(),
			'orderby'          => 'quantity',
			'order'            => 'DESC',
			'fields'           => array( 'product_id', 'product_sku', 'product_name', 'quantity_sold', 'gross_sales' ),
			'exclude_free'     => 'no',
		);

		$taxonomies = $this->get_taxonomies();
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax ) {
				$key              = $tax['setting_key'];
				$settings[ $key ] = array();
			}
		}

		return apply_filters( 'vhc_wc_sales_report_default_settings', $settings );
	}

	/**
	 * Returns sales report settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_settings() {
		// Default settings.
		$default_settings = $this->default_settings();

		// Set cache key for settings.
		$cache_key = 'vhc_wc_sales_report_settings';

		// Get settings from cache.
		$settings = wp_cache_get( $cache_key, 'vhc-wc-sales-report' );

		// Check if cache data is empty.
		if ( false === $settings ) {
			// Fetch aved settings.
			$settings = get_option( 'vhc_wc_sales_report_settings', array() );
			// Set settings cache.
			wp_cache_set( $cache_key, $settings, 'vhc-wc-sales-report' );
		}

		// Parse settings with default settings.
		$settings = wp_parse_args( $settings, $default_settings );

		return apply_filters( 'vhc_wc_sales_report_settings', $settings );
	}

	/**
	 * Returns valid order statues.
	 *
	 * This function check the saved order status with registerd order
	 * statues and remove 'wc-' prefix from order status.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_formatted_order_status() {
		$wc_order_statuses = wc_get_order_statuses();
		$order_statuses    = $this->get_setting( 'order_status' );
		$statuses          = array();
		if ( ! empty( $order_statuses ) ) {
			foreach ( $order_statuses as $order_status ) {
				if ( isset( $wc_order_statuses[ $order_status ] ) ) {
					$statuses[] = substr( $order_status, 3 );
				}
			}
		}

		return $statuses;
	}

	/**
	 * Return reports output fields array.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_output_fields() {
		$fields = array(
			'product_id'           => __( 'Product ID', 'vhc-wc-sales-report' ),
			'product_sku'          => __( 'Product SKU', 'vhc-wc-sales-report' ),
			'product_name'         => __( 'Product Name', 'vhc-wc-sales-report' ),
			'product_categories'   => __( 'Product Categories', 'vhc-wc-sales-report' ),
			'quantity_sold'        => __( 'Quantity Sold', 'vhc-wc-sales-report' ),
			'gross_sales'          => __( 'Gross Sales', 'vhc-wc-sales-report' ),
			'gross_after_discount' => __( 'Gross Sales (After Discounts)', 'vhc-wc-sales-report' ),
		);

		return apply_filters( 'vhc_wc_sales_report_output_fields', $fields );
	}

	/**
	 * Return report periods options.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_periods_options() {
		return array(
			'custom'         => __( 'Custom date range', 'vhc-wc-sales-report' ),
			'today'          => __( 'Today orders', 'vhc-wc-sales-report' ),
			'yesterday'      => __( 'Yesterday orders', 'vhc-wc-sales-report' ),
			'last-3-days'    => __( 'Last 3 days orders (excluding today)', 'vhc-wc-sales-report' ),
			'last-7-days'    => __( 'Last 7 days orders (excluding today)', 'vhc-wc-sales-report' ),
			'last-14-days'   => __( 'Last 14 days orders (excluding today)', 'vhc-wc-sales-report' ),
			'last-30-days'   => __( 'Last 30 days orders (excluding today)', 'vhc-wc-sales-report' ),
			'this-month'     => __( 'This month orders (including today)', 'vhc-wc-sales-report' ),
			'last-month'     => __( 'Last month orders', 'vhc-wc-sales-report' ),
			'last-3-months'  => __( 'Last 3 months orders (excluding current month)', 'vhc-wc-sales-report' ),
			'last-6-months'  => __( 'Last 6 months orders (excluding current month)', 'vhc-wc-sales-report' ),
			'last-12-months' => __( 'Last 12 months orders (excluding current month)', 'vhc-wc-sales-report' ),
			'this-year'      => __( 'This year orders', 'vhc-wc-sales-report' ),
			'last-year'      => __( 'Last year orders', 'vhc-wc-sales-report' ),
			'all'            => __( 'All time', 'vhc-wc-sales-report' ),
		);
	}

	/**
	 * Prepare options for dropdown from product IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $product_ids Product IDs array.
	 * @return array
	 */
	private function prepare_product_options( $product_ids = array() ) {
		$options = array();

		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( is_object( $product ) ) {
					$options[ $product_id ] = wp_strip_all_tags( $product->get_formatted_name() );
				}
			}
		}

		return $options;
	}

	/**
	 * Prepare options for dropdown from term ids.
	 *
	 * @since 1.0.0
	 *
	 * @param array $term_ids Term ids array.
	 * @return array
	 */
	private function prepare_term_options( $term_ids = array() ) {
		$options = array();

		if ( ! empty( $term_ids ) ) {
			foreach ( $term_ids as $term_id ) {
				$term = get_term( $term_id );
				if ( ! is_wp_error( $term ) ) {
					$options[ $term->term_id ] = wp_strip_all_tags( $term->name );
				}
			}
		}

		return $options;
	}

	/**
	 * Return sales report content
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function preview_html() {
		$rows         = $this->export_body( null, true );
		$headers      = $this->export_header( null, true );
		$fields_count = count( $this->get_setting( 'fields' ) );
		ob_start();
		?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo wp_kses_post( implode( '</th><th>', $headers ) ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( ! empty( $rows ) ) {
					foreach ( $rows as $key => $values ) {
						printf( '<tr>%s</tr>', wp_kses_post( '<td>' . implode( '</th><th>', $values ) . '</td>' ) );
					}
				}
				?>
			</tbody>
		</table>
		<?php
		$content = ob_get_clean();
		return $content;
	}

	/**
	 * Outputs the report header row.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $dest     Destination path.
	 * @param boolean $return   If true then return.
	 */
	private function export_header( $dest, $return = false ) {
		$header        = array();
		$output_fields = $this->get_output_fields();
		$fields        = $this->get_setting( 'fields' );

		if ( ! empty( $fields ) & ! empty( $output_fields ) ) {
			foreach ( $fields as $field ) {
				$header[] = isset( $output_fields[ $field ] ) ? $output_fields[ $field ] : '#';
			}
		}

		if ( $return ) {
			return $header;
		}

		fputcsv( $dest, $header );
	}

	/**
	 * Generates and outputs the report body rows.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $dest     Destination path.
	 * @param boolean $return   If true then return.
	 */
	private function export_body( $dest, $return = false ) {
		global $wpdb;

		$show_all    = true;
		$query_args  = array();
		$product_ids = array();
		$settings    = $this->get_settings();

		$default_query_args = array(
			'post_type' => 'product',
			'nopaging'  => true,
			'fields'    => 'ids',
		);

		// Include products.
		if ( isset( $settings['include_products'] ) && ! empty( $settings['include_products'] ) ) {
			$query_args['post__in'] = array_map( 'absint', (array) $settings['include_products'] );
		}

		// Exclude products.
		if ( isset( $settings['exclude_products'] ) && ! empty( $settings['exclude_products'] ) ) {
			$query_args['post__not_in'] = array_map( 'absint', (array) $settings['exclude_products'] );
		}

		$taxonomies = $this->get_taxonomies();
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax ) {
				$key = $tax['setting_key'];
				if ( isset( $settings[ $key ] ) && ! empty( $settings[ $key ] ) ) {
					$query_args['tax_query'][] = array(
						'taxonomy' => $tax['id'],
						'terms'    => array_map( 'absint', $settings[ $key ] ),
						'field'    => 'term_id',
						'operator' => 'IN',
					);
				}
			}

			if ( isset( $query_args['tax_query'] ) & ! empty( $query_args['tax_query'] ) ) {
				$query_args['tax_query']['relation'] = 'AND';
			}
		}

		$query_args = apply_filters( 'vhc_wc_sales_report_query_args', $query_args, $settings );

		if ( ! empty( $query_args ) ) {
			$show_all    = false;
			$query_args  = wp_parse_args( $query_args, $default_query_args );
			$product_ids = get_posts( $query_args );
		}

		$midnight  = strtotime( 'today midnight' );
		$postnight = $midnight + DAY_IN_SECONDS - 1;

		switch ( $settings['period'] ) {
			case 'today':
				$start_date = $midnight;
				$end_date   = $postnight;
				break;
			case 'yesterday':
				$start_date = $midnight - DAY_IN_SECONDS;
				$end_date   = $start_date + DAY_IN_SECONDS - 1;
				break;
			case 'last-3-days':
				$start_date = $midnight - ( 3 * DAY_IN_SECONDS );
				$end_date   = $midnight - 1;
				break;
			case 'last-7-days':
				$start_date = $midnight - ( 7 * DAY_IN_SECONDS );
				$end_date   = $midnight - 1;
				break;
			case 'last-14-days':
				$start_date = $midnight - ( 14 * DAY_IN_SECONDS );
				$end_date   = $midnight - 1;
				break;
			case 'last-30-days':
				$start_date = $midnight - ( 30 * DAY_IN_SECONDS );
				$end_date   = $midnight - 1;
				break;
			case 'this-month':
				$start_date = strtotime( 'midnight', strtotime( 'first day of this month' ) );
				$end_date   = strtotime( 'midnight', strtotime( 'last day of this month' ) ) + DAY_IN_SECONDS - 1;
				break;
			case 'last-month':
				$start_date = strtotime( 'midnight', strtotime( 'first day of last month' ) );
				$end_date   = strtotime( 'midnight', strtotime( 'last day of last month' ) ) + DAY_IN_SECONDS - 1;
				break;
			case 'last-3-months':
				$start_date = strtotime( 'midnight', strtotime( 'first day of -3 month' ) );
				$end_date   = strtotime( 'midnight', strtotime( 'last day of last month' ) ) + DAY_IN_SECONDS - 1;
				break;
			case 'last-6-months':
				$start_date = strtotime( 'midnight', strtotime( 'first day of -6 month' ) );
				$end_date   = strtotime( 'midnight', strtotime( 'last day of last month' ) ) + DAY_IN_SECONDS - 1;
				break;
			case 'last-12-months':
				$start_date = strtotime( 'midnight', strtotime( 'first day of -12 month' ) );
				$end_date   = strtotime( 'midnight', strtotime( 'last day of last month' ) ) + DAY_IN_SECONDS - 1;
				break;
			case 'this-year':
				$start_date = strtotime( 'midnight', strtotime( gmdate( 'Y-01-01' ) ) );
				$end_date   = strtotime( 'midnight', strtotime( gmdate( 'Y-12-31' ) ) ) + DAY_IN_SECONDS - 1;
				break;
			case 'last-year':
				$last_year  = gmdate( 'Y' ) - 1;
				$start_date = strtotime( 'midnight', strtotime( gmdate( $last_year . '-01-01' ) ) );
				$end_date   = strtotime( 'midnight', strtotime( gmdate( $last_year . '-12-31' ) ) ) + DAY_IN_SECONDS - 1;
				break;
			case 'custom':
			default:
				if ( empty( $settings['start_date'] ) || empty( $settings['end_date'] ) ) {
					$start_date = $midnight - MONTH_IN_SECONDS;
					$end_date   = $postnight;
				} else {
					$start_date = strtotime( 'midnight', strtotime( $settings['start_date'] ) );
					$end_date   = strtotime( 'midnight', strtotime( $settings['end_date'] ) ) + DAY_IN_SECONDS - 1;
				}
				break;
		}

		// Assemble order by string.
		$orderby  = in_array( $settings['orderby'], array( 'product_id', 'gross', 'gross_after_discount' ), true ) ? $settings['orderby'] : 'quantity';
		$orderby .= ' ' . $settings['order'];

		// Create a new WC_Admin_Report object.
		include_once WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php';

		$wc_report             = new WC_Admin_Report();
		$wc_report->start_date = $start_date;
		$wc_report->end_date   = $end_date;

		$where_meta = array();

		if ( ! $show_all ) {
			$where_meta[] = array(
				'meta_key'   => '_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $product_ids, // phpcs:ignore WordPress.DB.SlowDBQuery
				'operator'   => 'IN',
				'type'       => 'order_item_meta',
			);
		}

		if ( ! empty( $settings['exclude_free'] ) ) {
			$where_meta[] = array(
				'meta_key'   => '_line_total', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => 0, // phpcs:ignore WordPress.DB.SlowDBQuery
				'operator'   => '!=',
				'type'       => 'order_item_meta',
			);
		}

		if ( ! empty( $settings['billing_country'] ) ) {
			$where_meta[] = array(
				'meta_key'   => '_billing_country', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => (array) $settings['billing_country'], // phpcs:ignore WordPress.DB.SlowDBQuery
				'operator'   => 'IN',
				'type'       => 'meta',
			);
		}

		if ( ! empty( $settings['shipping_country'] ) ) {
			$where_meta[] = array(
				'meta_key'   => '_shipping_country', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => (array) $settings['shipping_country'], // phpcs:ignore WordPress.DB.SlowDBQuery
				'operator'   => 'IN',
				'type'       => 'meta',
			);
		}

		// Prevent plugins from overriding the order status filter.
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'get_formatted_order_status' ), 9999 );

		// Remove the action hook 'woocommerce_reports_get_order_report_query' and its associated function 'wuoc_reports_get_order_report_query'.
		remove_action( 'woocommerce_reports_get_order_report_query', 'wuoc_reports_get_order_report_query' );

		// Based on woocoommerce/includes/admin/reports/class-wc-report-sales-by-product.php.
		$sold_products = $wc_report->get_order_report_data(
			array(
				'data'         => array(
					'_product_id'    => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => '',
						'name'            => 'product_id',
					),
					'_qty'           => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => 'SUM',
						'name'            => 'quantity',
					),
					'_line_subtotal' => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => 'SUM',
						'name'            => 'gross',
					),
					'_line_total'    => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => 'SUM',
						'name'            => 'gross_after_discount',
					),
				),
				'query_type'   => 'get_results',
				'group_by'     => 'product_id',
				'where_meta'   => $where_meta,
				'order_by'     => $orderby,
				'limit'        => '',
				'nocache'      => true,
				'filter_range' => 'all' !== $settings['period'],
				'order_types'  => wc_get_order_types( 'order-count' ),
				'order_status' => $this->get_formatted_order_status(),
			)
		);

		// Remove report order statuses filter.
		remove_filter( 'woocommerce_reports_order_statuses', array( $this, 'get_formatted_order_status' ), 9999 );

		if ( $return ) {
			$rows = array();
		}

		// Output report rows.
		foreach ( $sold_products as $product ) {
			$row        = array();
			$product_id = $product->product_id;

			if ( ! empty( $settings['fields'] ) ) {
				foreach ( $settings['fields'] as $field ) {
					switch ( $field ) {
						case 'product_id':
							$row[] = $product_id;
							break;
						case 'variation_id':
							$row[] = empty( $product->variation_id ) ? '' : $product->variation_id;
							break;
						case 'product_sku':
							$row[] = get_post_meta( $product_id, '_sku', true );
							break;
						case 'product_name':
							$row[] = html_entity_decode( get_the_title( $product_id ) );
							break;
						case 'product_categories':
							$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
							$row[]      = is_wp_error( $categories ) ? '' : join( ', ', $categories );
							break;
						case 'quantity_sold':
							$row[] = $product->quantity;
							break;
						case 'gross_sales':
							$row[] = number_format( $product->gross, 2 );
							break;
						case 'gross_after_discount':
							$row[] = number_format( $product->gross_after_discount, 2 );
							break;
						default:
							$row[] = apply_filters( 'vhc_wc_sales_report_custom_fields_data', '', $field, $product_id, $product );
							break;
					}
				}
			}

			if ( $return ) {
				$rows[] = $row;
			} else {
				fputcsv( $dest, $row );
			}
		}

		if ( $return ) {
			return $rows;
		}
	}

	/**
	 * Returns sales report settings by setting key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Settings key.
	 * @return array
	 */
	public function get_setting( $key = '' ) {
		// Get settings.
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}

	/**
	 * Render text input field.
	 *
	 * @since 1.0.0
	 * @param array $args Field args.
	 */
	public function render_text_field( $args ) {
		$default_args = array(
			'id'                => '',
			'name'              => '',
			'type'              => 'text',
			'class'             => '',
			'value'             => '',
			'placeholder'       => '',
			'custom_attributes' => array(),
		);

		$field                           = wp_parse_args( $args, $default_args );
		$field_attributes                = (array) $field['custom_attributes'];
		$field_attributes['id']          = 'setting-' . $field['id'];
		$field_attributes['name']        = $field['name'];
		$field_attributes['type']        = $field['type'];
		$field_attributes['class']       = $field['class'];
		$field_attributes['placeholder'] = $field['placeholder'];
		$field_attributes['value']       = $field['value'];

		$wrapper_attributes          = array();
		$wrapper_attributes['id']    = 'setting-row-' . $field['id'];
		$wrapper_attributes['class'] = 'setting-row';
		?>
		<div <?php echo wc_implode_html_attributes( $wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="setting-label">
				<label><?php echo esc_html( $field['label'] ); ?></label>
			</div>
			<div class="setting-field">
				<input <?php echo wc_implode_html_attributes( $field_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />

				<?php if ( ! empty( $field['desc'] ) ) : ?>
				<p class="desc"><?php echo esc_html( $field['desc'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render dropdown field.
	 *
	 * @since 1.0.0
	 * @param array $args Field args.
	 */
	public function render_dropdown_field( $args ) {
		$default_args = array(
			'id'                => '',
			'name'              => '',
			'class'             => 'enhanced-select',
			'value'             => array(),
			'options'           => array(),
			'custom_attributes' => array(),
		);

		$field                       = wp_parse_args( $args, $default_args );
		$wrapper_attributes          = array();
		$wrapper_attributes['id']    = 'setting-row-' . $field['id'];
		$wrapper_attributes['class'] = 'setting-row';
		?>
		<div <?php echo wc_implode_html_attributes( $wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="setting-label">
				<label><?php echo esc_html( $field['label'] ); ?></label>
			</div>
			<div class="setting-field">
				<?php $this->render_dropdown( $field ); ?>
				<?php if ( ! empty( $field['desc'] ) ) : ?>
				<p class="desc"><?php echo esc_html( $field['desc'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render dropdown element.
	 *
	 * @since 1.0.0
	 * @param array $args Element args.
	 */
	public function render_dropdown( $args ) {
		$default_args = array(
			'id'                => '',
			'name'              => '',
			'class'             => 'enhanced-select',
			'value'             => array(),
			'options'           => array(),
			'custom_attributes' => array(),
		);

		$field                     = wp_parse_args( $args, $default_args );
		$field_attributes          = (array) $field['custom_attributes'];
		$field_attributes['id']    = 'setting-' . $field['id'];
		$field_attributes['name']  = $field['name'];
		$field_attributes['class'] = $field['class'];
		?>
		<select <?php echo wc_implode_html_attributes( $field_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			foreach ( $field['options'] as $key => $value ) {
				echo '<option value="' . esc_attr( $key ) . '"' . $this->selected( $key, $field['value'] ) . '>' . esc_html( $value ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</select>
		<?php
	}

	/**
	 * Render radio field.
	 *
	 * @since 1.0.0
	 * @param array $args Field args.
	 */
	public function render_radio_field( $args ) {
		$default_args = array(
			'id'                => '',
			'name'              => '',
			'class'             => '',
			'value'             => '',
			'options'           => array(),
			'custom_attributes' => array(),
		);

		$field                       = wp_parse_args( $args, $default_args );
		$wrapper_attributes          = array();
		$wrapper_attributes['id']    = 'setting-row-' . $field['id'];
		$wrapper_attributes['class'] = 'setting-row';
		?>
		<div <?php echo wc_implode_html_attributes( $wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="setting-label">
				<label><?php echo esc_html( $field['label'] ); ?></label>
			</div>
			<div class="setting-field">
				<?php
				foreach ( $field['options'] as $key => $value ) {
					$label_attributes          = array();
					$label_attributes['class'] = 'radio-field';
					$label_attributes['for']   = 'setting-' . $field['id'] . '-' . $key;

					$field_attributes          = (array) $field['custom_attributes'];
					$field_attributes['id']    = 'setting-' . $field['id'] . '-' . $key;
					$field_attributes['name']  = $field['name'];
					$field_attributes['class'] = $field['class'];
					$field_attributes['value'] = esc_attr( $key );

					echo '<label ' . wc_implode_html_attributes( $label_attributes ) . '><input type="radio" ' . wc_implode_html_attributes( $field_attributes ) . ' ' . $this->checked( $key, $field['value'], false ) . ' />' . esc_html( $value ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>

				<?php if ( ! empty( $field['desc'] ) ) : ?>
				<p class="desc"><?php echo esc_html( $field['desc'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render checkbox field.
	 *
	 * @since 1.0.0
	 * @param array $args Field args.
	 */
	public function render_checkbox_field( $args ) {
		$default_args = array(
			'id'                => '',
			'name'              => '',
			'class'             => '',
			'value'             => '',
			'options'           => array(),
			'custom_attributes' => array(),
		);

		$field                       = wp_parse_args( $args, $default_args );
		$wrapper_attributes          = array();
		$wrapper_attributes['id']    = 'setting-row-' . $field['id'];
		$wrapper_attributes['class'] = 'setting-row';
		?>
		<div <?php echo wc_implode_html_attributes( $wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="setting-label">
				<label><?php echo esc_html( $field['label'] ); ?></label>
			</div>
			<div class="setting-field">
				<?php
				foreach ( $field['options'] as $key => $value ) {
					$label_attributes          = array();
					$label_attributes['class'] = 'checkbox-field';
					$label_attributes['for']   = 'setting-' . $field['id'] . '-' . $key;

					$field_attributes          = (array) $field['custom_attributes'];
					$field_attributes['id']    = 'setting-' . $field['id'] . '-' . $key;
					$field_attributes['name']  = $field['name'];
					$field_attributes['class'] = $field['class'];
					$field_attributes['value'] = esc_attr( $key );

					echo '<label ' . wc_implode_html_attributes( $label_attributes ) . '><input type="checkbox" ' . wc_implode_html_attributes( $field_attributes ) . ' ' . $this->checked( $key, $field['value'], false ) . ' />' . esc_html( $value ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>

				<?php if ( ! empty( $field['desc'] ) ) : ?>
				<p class="desc"><?php echo esc_html( $field['desc'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		if ( $this->is_valid_screen() ) {
			$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$version = VHC_WC_SALES_REPORT_VERSION;

			// Array of styles to remove.
			$styles_to_remove = array(
				'woocommerce_admin',
				'woocommerce_admin_styles',
				'wp-dark-mode-admin',
			);

			// Loop through each style and deregister/dequeue it.
			foreach ( $styles_to_remove as $style ) {
				wp_deregister_style( $style );
				wp_dequeue_style( $style );
			}

			// Enqueue styles.
			wp_enqueue_style( 'vhc-wc-sales-report', vhc_wc_sales_report()->plugin_url() . '/assets/css/admin' . $suffix . '.css', array(), $version );

			// Array of scripts to remove.
			$scripts_to_remove = array(
				'wc-enhanced-select',
				'woo-variation-swatches-admin',
				'select2',
			);

			// Loop through each script and deregister/dequeue it.
			foreach ( $scripts_to_remove as $script ) {
				wp_deregister_script( $script );
				wp_dequeue_script( $script );
			}

			// Enqueue scripts.
			wp_enqueue_script( 'vhc-wc-sales-report-select2', vhc_wc_sales_report()->plugin_url() . '/assets/js/select2' . $suffix . '.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker' ), $version, true );
			wp_enqueue_script( 'vhc-wc-sales-report-enhancedselect', vhc_wc_sales_report()->plugin_url() . '/assets/js/enhancedselect' . $suffix . '.js', array( 'vhc-wc-sales-report-select2' ), $version, true );
			wp_enqueue_script( 'vhc-wc-sales-report-datatable', vhc_wc_sales_report()->plugin_url() . '/assets/js/datatable' . $suffix . '.js', array( 'vhc-wc-sales-report-enhancedselect' ), $version, true );
			wp_enqueue_script( 'vhc-wc-sales-report', vhc_wc_sales_report()->plugin_url() . '/assets/js/admin' . $suffix . '.js', array( 'vhc-wc-sales-report-datatable', 'vhc-wc-sales-report-enhancedselect' ), $version, true );

			// Localize scripts.
			wp_localize_script(
				'vhc-wc-sales-report-enhancedselect',
				'vhc_wc_sales_report_select_params',
				array(
					'i18n_no_matches'           => _x( 'No matches found', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_ajax_error'           => _x( 'Loading failed', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_input_too_short_1'    => _x( 'Please enter 1 or more characters', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_input_too_long_1'     => _x( 'Please delete 1 character', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_input_too_long_n'     => _x( 'Please delete %qty% characters', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_selection_too_long_1' => _x( 'You can only select 1 item', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_selection_too_long_n' => _x( 'You can only select %qty% items', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_load_more'            => _x( 'Loading more results&hellip;', 'enhanced select', 'vhc-wc-sales-report' ),
					'i18n_searching'            => _x( 'Searching&hellip;', 'enhanced select', 'vhc-wc-sales-report' ),
					'ajax_url'                  => admin_url( 'admin-ajax.php' ),
					'search_products_nonce'     => wp_create_nonce( 'search-products' ),
					'search_terms_nonce'        => wp_create_nonce( 'search-terms' ),
				)
			);
			wp_localize_script(
				'vhc-wc-sales-report',
				'vhc_wc_sales_report_params',
				array(
					'i18n_generating_report' => __( 'Please wait, Generating report&hellip;', 'vhc-wc-sales-report' ),
					'i18n_generating_csv'    => __( 'Please wait, Generating csv file&hellip;', 'vhc-wc-sales-report' ),
					'i18n_csv_downloaded'    => __( 'Report file downloaded successfully.', 'vhc-wc-sales-report' ),
					'i18n_something_wrong'   => __( 'Sorry, something went wrong, please try again or reload the page.', 'vhc-wc-sales-report' ),
					'ajax_url'               => admin_url( 'admin-ajax.php' ),
				)
			);
		}
	}

	/**
	 * Add menu items.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		add_submenu_page( 'woocommerce', __( 'WooCommerce Sales Report', 'vhc-wc-sales-report' ), __( 'Sales Report', 'vhc-wc-sales-report' ), 'view_woocommerce_reports', $this->screen_id, array( $this, 'display_admin_page' ) );
	}

	/**
	 * Display admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_admin_page() {
		?>
		<div class="wrap vhc-wc-sr-container" id="vhc-wc-sr-container">

			<h1 class="page-title"><?php esc_html_e( 'WooCommerce Sales Report', 'vhc-wc-sales-report' ); ?></h1>

			<form method="post" action="" class="vhc-wc-sr-form">
				<input type="hidden" name="action" value="vhc_wc_sales_report_generate_report" />
				<input type="hidden" name="download" value="0" />
				<?php wp_nonce_field( 'vhc_wc_sales_report_nonce' ); ?>

				<?php do_action( 'vhc_wc_sales_report_form_start', $this ); ?>

				<?php
				$this->render_dropdown_field(
					array(
						'id'      => 'period',
						'name'    => 'period',
						'label'   => __( 'Report period', 'vhc-wc-sales-report' ),
						'desc'    => __( 'Choose the report orders period.', 'vhc-wc-sales-report' ),
						'value'   => $this->get_setting( 'period' ),
						'options' => $this->get_periods_options(),
					)
				);
				?>

				<div id="setting-row-date-range" class="setting-row">
					<div class="setting-label">
						<label><?php esc_html_e( 'Date range', 'vhc-wc-sales-report' ); ?></label>
					</div>
					<div class="setting-field">
						<div class="input-group">
							<div>
								<span for="setting-start-date" class="label"><?php esc_html_e( 'Start date', 'vhc-wc-sales-report' ); ?></span>
								<input id="setting-start-date" type="text" class="datepicker" name="start_date" value="<?php echo esc_attr( $this->get_setting( 'start_date' ) ); ?>" placeholder="YYYY-MM-DD" readonly />
							</div>
							<div>
								<span for="setting-end-date" class="label"><?php esc_html_e( 'End date', 'vhc-wc-sales-report' ); ?></span>
								<input id="setting-end-date" type="text" class="datepicker" name="end_date" value="<?php echo esc_attr( $this->get_setting( 'end_date' ) ); ?>" placeholder="YYYY-MM-DD" readonly />
							</div>
						</div>
						<p class="desc"><?php esc_html_e( 'Choose the custom date range for the report orders period.', 'vhc-wc-sales-report' ); ?></p>
					</div>
				</div>

				<?php
				$this->render_dropdown_field(
					array(
						'id'                => 'order-status',
						'name'              => 'order_status[]',
						'label'             => __( 'Order status', 'vhc-wc-sales-report' ),
						'desc'              => __( 'Choose the order statuses to be included in report.', 'vhc-wc-sales-report' ),
						'value'             => $this->get_setting( 'order_status' ),
						'options'           => wc_get_order_statuses(),
						'custom_attributes' => array( 'multiple' => 'multiple' ),
					)
				);

				$this->render_dropdown_field(
					array(
						'id'                => 'billing-country',
						'name'              => 'billing_country[]',
						'label'             => __( 'Billing country', 'vhc-wc-sales-report' ),
						'desc'              => __( 'Choose the billing country from which order products to be included in report.', 'vhc-wc-sales-report' ),
						'value'             => $this->get_setting( 'billing_country' ),
						'options'           => WC()->countries->get_countries(),
						'custom_attributes' => array( 'multiple' => 'multiple' ),
					)
				);

				$this->render_dropdown_field(
					array(
						'id'                => 'shipping-country',
						'name'              => 'shipping_country[]',
						'label'             => __( 'Shipping country', 'vhc-wc-sales-report' ),
						'desc'              => __( 'Choose the shipping country from which order products to be included in report.', 'vhc-wc-sales-report' ),
						'value'             => $this->get_setting( 'shipping_country' ),
						'options'           => WC()->countries->get_countries(),
						'custom_attributes' => array( 'multiple' => 'multiple' ),
					)
				);

				do_action( 'vhc_wc_sales_report_form_before_terms_filters', $this );

				$taxonomies = $this->get_taxonomies();
				if ( ! empty( $taxonomies ) ) {
					foreach ( $taxonomies as $tax ) {
						$this->render_dropdown_field(
							array(
								'id'                => $tax['field_id'],
								'name'              => $tax['field_name'],
								'class'             => 'term-search',
								'label'             => sprintf( /* translators: %s Taxonomy name */ esc_html__( 'Filter by %s', 'vhc-wc-sales-report' ), esc_html( $tax['name'] ) ),
								'desc'              => sprintf( /* translators: %s Taxonomy name */esc_html__( 'Choose the %s to be used to filter the report.', 'vhc-wc-sales-report' ), esc_html( $tax['name'] ) ),
								'value'             => $this->get_setting( $tax['setting_key'] ),
								'options'           => $this->prepare_term_options( $this->get_setting( $tax['setting_key'] ) ),
								'custom_attributes' => array(
									'multiple'         => 'multiple',
									'data-placeholder' => sprintf( /* translators: %s Taxonomy singular name */__( 'Search for a %s&hellip;', 'vhc-wc-sales-report' ), esc_attr( $tax['singular_name'] ) ),
									'data-taxonomy'    => esc_attr( $tax['id'] ),
								),
							)
						);
					}
				}

				do_action( 'vhc_wc_sales_report_form_after_terms_filters', $this );

				$this->render_dropdown_field(
					array(
						'id'                => 'include-products',
						'name'              => 'include_products[]',
						'class'             => 'product-search',
						'label'             => __( 'Include products', 'vhc-wc-sales-report' ),
						'desc'              => __( 'Choose the products to be included in the report.', 'vhc-wc-sales-report' ),
						'value'             => $this->get_setting( 'include_products' ),
						'options'           => $this->prepare_product_options( $this->get_setting( 'include_products' ) ),
						'custom_attributes' => array(
							'multiple'         => 'multiple',
							'data-placeholder' => __( 'Search for a product&hellip;', 'vhc-wc-sales-report' ),
							'data-action'      => 'woocommerce_json_search_products_and_variations',
						),
					)
				);

				$this->render_dropdown_field(
					array(
						'id'                => 'exclude-products',
						'name'              => 'exclude_products[]',
						'class'             => 'product-search',
						'label'             => __( 'Exclude products', 'vhc-wc-sales-report' ),
						'desc'              => __( 'Choose the products to be excluded from the report.', 'vhc-wc-sales-report' ),
						'value'             => $this->get_setting( 'exclude_products' ),
						'options'           => $this->prepare_product_options( $this->get_setting( 'exclude_products' ) ),
						'custom_attributes' => array(
							'multiple'         => 'multiple',
							'data-placeholder' => __( 'Search for a product&hellip;', 'vhc-wc-sales-report' ),
							'data-action'      => 'woocommerce_json_search_products_and_variations',
						),
					)
				);

				$this->render_radio_field(
					array(
						'id'      => 'exclude-free',
						'name'    => 'exclude_free',
						'label'   => __( 'Exclude free products', 'vhc-wc-sales-report' ),
						'desc'    => __( 'Choose if free products should be included or excluded.', 'vhc-wc-sales-report' ),
						'value'   => $this->get_setting( 'exclude_free' ),
						'options' => array(
							'yes' => __( 'Yes', 'vhc-wc-sales-report' ),
							'no'  => __( 'No', 'vhc-wc-sales-report' ),
						),
					)
				);

				do_action( 'vhc_wc_sales_report_form_before_sortby', $this );
				?>

				<div id="setting-sort-by" class="setting-row">
					<div class="setting-label">
						<label><?php esc_html_e( 'Sort by', 'vhc-wc-sales-report' ); ?></label>
					</div>
					<div class="setting-field">
						<div class="input-group">
							<div>
								<?php
								$this->render_dropdown(
									array(
										'id'      => 'setting-orderby',
										'name'    => 'orderby',
										'value'   => $this->get_setting( 'orderby' ),
										'options' => array(
											'product_id' => __( 'Product ID', 'vhc-wc-sales-report' ),
											'quantity'   => __( 'Quantity Sold', 'vhc-wc-sales-report' ),
											'gross'      => __( 'Gross Sales', 'vhc-wc-sales-report' ),
											'gross_after_discount' => __( 'Gross Sales (After Discounts)', 'vhc-wc-sales-report' ),
										),
									)
								);
								?>
							</div>
							<div>
								<?php
									$this->render_dropdown(
										array(
											'id'      => 'setting-order',
											'name'    => 'order',
											'value'   => $this->get_setting( 'order' ),
											'options' => array(
												'ASC'  => __( 'Ascending', 'vhc-wc-sales-report' ),
												'DESC' => __( 'Descending', 'vhc-wc-sales-report' ),
											),
										)
									);
								?>
							</div>
						</div>
						<p class="desc"><?php esc_html_e( 'Choose the report order field & order type.', 'vhc-wc-sales-report' ); ?></p>
					</div>
				</div>

				<?php
				do_action( 'vhc_wc_sales_report_form_before_report_fields', $this );

				$this->render_dropdown_field(
					array(
						'id'                => 'report-fields',
						'name'              => 'fields[]',
						'label'             => __( 'Report fields', 'vhc-wc-sales-report' ),
						'desc'              => __( 'Choose the fields to be displayed in the report.', 'vhc-wc-sales-report' ),
						'value'             => (array) $this->get_setting( 'fields' ),
						'options'           => $this->get_output_fields(),
						'custom_attributes' => array( 'multiple' => 'multiple' ),
					)
				);

				do_action( 'vhc_wc_sales_report_form_end', $this );
				?>

				<div class="setting-submit">
					<button class="btn view-report" type="button"><?php esc_html_e( 'View Report', 'vhc-wc-sales-report' ); ?></button>
					<button class="btn btn-primary download-report" type="button"><?php esc_html_e( 'Download Report', 'vhc-wc-sales-report' ); ?></button>
					<a class="btn download-url hidden" target="_blank" href="#">&nbsp;</a>
				</div>

			</form>

			<div class="report-preview"></div>
		</div>
		<?php
	}

	/**
	 * Search for categories and return json.
	 *
	 * @since 1.0.0
	 */
	public function json_search_terms() {
		ob_start();

		check_ajax_referer( 'search-terms', 'security' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( -1 );
		}

		$search_text = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$taxonomy    = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( ! $search_text || empty( $taxonomy ) ) {
			wp_die();
		}

		$found_terms = array();
		$args        = array(
			'taxonomy'   => array( $taxonomy ),
			'orderby'    => 'id',
			'order'      => 'ASC',
			'hide_empty' => true,
			'fields'     => 'all',
			'name__like' => $search_text,
		);

		$terms = get_terms( $args );

		if ( $terms ) {
			foreach ( $terms as $term ) {
				$term->formatted_name = '';

				if ( $term->parent ) {
					$ancestors = array_reverse( get_ancestors( $term->term_id, $taxonomy ) );
					foreach ( $ancestors as $ancestor ) {
						$ancestor_term = get_term( $ancestor, $taxonomy );
						if ( $ancestor_term ) {
							$term->formatted_name .= $ancestor_term->name . ' > ';
						}
					}
				}

				$term->formatted_name         .= $term->name;
				$found_terms[ $term->term_id ] = $term->formatted_name;
			}
		}

		wp_send_json( $found_terms );
	}

	/**
	 * Generate report on ajax call.
	 *
	 * @since 1.0.0
	 */
	public function generate_report() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'vhc_wc_sales_report_nonce' ) ) {

			if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
				wp_send_json_error();
			}

			// Save the static settings.
			$settings = array(
				'period'           => isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '',
				'start_date'       => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
				'end_date'         => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
				'order_status'     => isset( $_POST['order_status'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['order_status'] ) ) : array(),
				'billing_country'  => isset( $_POST['billing_country'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['billing_country'] ) ) : array(),
				'shipping_country' => isset( $_POST['shipping_country'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['shipping_country'] ) ) : array(),
				'include_products' => isset( $_POST['include_products'] ) ? array_map( 'absint', wp_unslash( $_POST['include_products'] ) ) : array(),
				'exclude_products' => isset( $_POST['exclude_products'] ) ? array_map( 'absint', wp_unslash( $_POST['exclude_products'] ) ) : array(),
				'orderby'          => isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : '',
				'order'            => isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : '',
				'fields'           => isset( $_POST['fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) ) : array(),
				'exclude_free'     => isset( $_POST['exclude_free'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_free'] ) ) : '',
			);

			// Save the taxonomies terms dyanmically.
			$taxonomies = $this->get_taxonomies();
			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $tax ) {
					$key              = $tax['setting_key'];
					$settings[ $key ] = isset( $_POST[ $key ] ) ? array_map( 'absint', wp_unslash( $_POST[ $key ] ) ) : array();
				}
			}

			$settings = apply_filters( 'vhc_wc_sales_report_save_settings', $settings );

			// Save settings.
			update_option( 'vhc_wc_sales_report_settings', $settings );

			// Set settings cache.
			wp_cache_set( 'vhc_wc_sales_report_settings', $settings, 'vhc-wc-sales-report' );

			if ( isset( $_POST['download'] ) && absint( wp_unslash( $_POST['download'] ) ) === 0 ) {
				wp_send_json_success( array( 'html' => $this->preview_html() ) );
			} else {
				$url_args = array(
					'page'     => $this->screen_id,
					'download' => 1,
					'_wpnonce' => wp_create_nonce( 'vhc_wc_sales_report_download_nonce' ),
				);

				$download_url = add_query_arg( $url_args, admin_url( 'admin.php' ) );
				wp_send_json_success( array( 'download_url' => $download_url ) );
			}
		}

		wp_send_json_error();
	}

	/**
	 * Download csv sales report.
	 *
	 * @since 1.0.0
	 */
	public function download_report() {
		global $pagenow;

		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && $_GET['page'] === $this->screen_id && isset( $_GET['download'] ) && 1 === absint( wp_unslash( $_GET['download'] ) ) ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'vhc_wc_sales_report_download_nonce' ) ) {
				if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
					return;
				}

				$settings = $this->get_settings();
				// Check if no fields are selected or if not downloading.
				if ( empty( $settings['fields'] ) ) {
					return;
				}

				$current_date   = date_i18n( 'Y-m-d' );
				$file_name_args = array(
					'WooCommerce Sales Report',
					$current_date,
				);

				// Assemble the filename for the report download.
				$filename = sanitize_title( implode( '-', $file_name_args ) ) . '.csv';

				// Send headers.
				header( 'Content-Type: text/csv' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

				// Output the report header row (if applicable) and body.
				$stdout = fopen( 'php://output', 'w' );

				$this->export_header( $stdout );
				$this->export_body( $stdout );

				exit;
			}
		}
	}
}

new VHC_WC_Sales_Report_Admin();
