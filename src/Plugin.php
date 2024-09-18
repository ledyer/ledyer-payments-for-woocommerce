<?php
namespace Krokedil\Ledyer\Payments;

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
		return $this->session;
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

		new Assets();
	}

	/**
	 * Register the activation and deactivation hooks.
	 *
	 * @return void
	 */
	private function setup_hooks() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
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
