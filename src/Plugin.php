<?php
namespace Krokedil\Ledyer\Payments;

use Krokedil\Ledyer\Payments\Gateway;

/**
 * Class Plugin
 *
 * Handles the plugins initialization.
 */
class Plugin {
	use Traits\Singleton;

	/**
	 * API gateway.
	 *
	 * @var API
	 */
	private $api = null;
	public function api() {
		return $this->api;
	}
	/**
	 * Session handler.
	 *
	 * @var Session
	 */
	private $session = null;
	public function session() {
		return $this->session->get_session();
	}

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger = null;
	public function logger() {
		return $this->logger;
	}

	private $settings = array();
	public function settings( $key ) {
		return $this->settings[ $key ] ?? null;
	}

	/**
	 * Plugin constructor.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		$this->load_dependencies();
		$this->setup_hooks();
	}

	/**
	 * Load all the plugin dependencies and add them to the container.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$this->api     = new API();
		$this->session = new Session();
		$this->logger  = new Logger();

		new AJAX();
		new Assets();
		new Callback();

		$this->settings = get_option( 'woocommerce_' . Gateway::ID . '_settings', array() );
	}

	/**
	 * Register the activation and deactivation hooks.
	 *
	 * @return void
	 */
	private function setup_hooks() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );

		/**
		 * Override the payment categories ID.
		 *
		 * In the templates/payment-categories.php file, we insert the payment categories as unique payment methods (gateways), each with a distinct ID. When Woo process the payment, it will look for a gateway with those IDs, but they don't exist. Only the Gateway::ID actually exists. For this reason, we must override the payment method ID to Gateway::ID when the checkout data is posted.
		 */
		add_filter(
			'woocommerce_checkout_posted_data',
			function ( $data ) {
				if ( false !== strpos( $data['payment_method'], Gateway::ID ) ) {
					$data['payment_method'] = Gateway::ID;
				}

				return $data;
			}
		);

		// Ledyer Payments is intended for B2B customers, and therefore we require the company number to be filled in.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_field' ) );
	}

	/**
	 * Declare compatibility with WooCommerce features.
	 *
	 * @return void
	 */
	public function declare_wc_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			// Declare HPOS compatibility.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );

			// Declare Checkout Blocks incompatibility
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}

	/**
	 * Add plugin action links.
	 *
	 * Used for adding a quick link to the gateway settings' page on the Plugin page.
	 *
	 * @param array $links The list of actions for this plugin.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = $this->get_settings_link();
		$plugin_links  = array(
			'<a href="' . $settings_link . '">' . __( 'Settings', 'ledyer-payments-for-woocommerce' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Get the absolute URL to the plugin's settings.
	 *
	 * @return string
	 */
	public function get_settings_link() {
		return esc_url(
			add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => Gateway::ID,

				),
				'admin.php'
			)
		);
	}

	public function checkout_field( $fields ) {
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( ! isset( $available_gateways[ Gateway::ID ] ) ) {
			return $fields;
		}

		if ( ! isset( $fields['billing']['billing_company'] ) ) {
			return $fields;
		}

		$priority                                    = $fields['billing']['billing_company']['priority'];
		$billing_company_number                      = array(
			'label'    => __( 'Company number', 'ledyer-payments-for-woocommerce' ),
			'required' => true,
			'class'    => array( 'form-row-wide' ),
			'priority' => $priority + 1,
		);
		$fields['billing']['billing_company_number'] = $billing_company_number;
		return $fields;
	}

	/**
	 * Register the payment gateway.
	 *
	 * @param array $methods Payment methods.
	 * @return array.
	 */
	public function add_gateways( $methods ) {
		$methods[] = __NAMESPACE__ . '\Gateway';
		return $methods;
	}
}
