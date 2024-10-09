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

	/**
	 * Session handler.
	 *
	 * @var Session
	 */
	private $session = null;


	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger = null;


	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = array();


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

		$this->settings = get_option( 'woocommerce_ledyer_payments_settings', array() );
	}

	/**
	 * Register the activation and deactivation hooks.
	 *
	 * @return void
	 */
	private function setup_hooks() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

		/**
		 * Override the payment categories ID.
		 *
		 * In the templates/payment-categories.php file, we insert the payment categories as unique payment methods (gateways), each with a distinct ID. When Woo process the payment, it will look for a gateway with those IDs, but they don't exist. Only 'ledyer_payments' actually exists. For this reason, we must override the payment method ID to 'ledyer_payments' when the checkout data is posted.
		 */
		add_filter(
			'woocommerce_checkout_posted_data',
			function ( $data ) {
				if ( false !== strpos( $data['payment_method'], 'ledyer_payments' ) ) {
					$data['payment_method'] = 'ledyer_payments';
				}

				return $data;
			}
		);

		// Ledyer Payments is intended for B2B customers, and therefore we require the company number to be filled in.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_field' ) );
	}

	/**
	 * Get the API gateway.
	 *
	 * @return API
	 */
	public function api() {
		return $this->api;
	}

	/**
	 * Get the session handler.
	 *
	 * @return Session
	 */
	public function session() {
		return $this->session;
	}

	/**
	 * Get the logger.
	 *
	 * @return Logger
	 */
	public function logger() {
		return $this->logger;
	}

	/**
	 * Get the value for a setting.
	 *
	 * @param string $key The setting key.
	 * @return mixed
	 */
	public function settings( $key ) {
		return $this->settings[ $key ] ?? null;
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
					'section' => 'ledyer_payments',

				),
				'admin.php'
			)
		);
	}

	/**
	 * Add the company number field to the checkout.
	 *
	 * @param array $fields The checkout fields.
	 * @return array
	 */
	public function checkout_field( $fields ) {
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( ! isset( $available_gateways['ledyer_payments'] ) ) {
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
