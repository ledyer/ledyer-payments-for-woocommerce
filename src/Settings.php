<?php
/**
 * Class Settings
 *
 * Defines the plugin's settings.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Returns the settings fields.
	 *
	 * @static
	 * @return array List of filtered setting fields.
	 */
	public static function setting_fields() {
		$settings = array(
			'enabled'                  => array(
				'title'       => __( 'Enable', 'ledyer-payments-for-woocommerce' ),
				'label'       => __( 'Enable payment gateway', 'ledyer-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'account_settings'         => array(
				'title' => __( 'Account settings', 'ledyer-payments-for-woocommerce' ),
				'type'  => 'title',
			),
			'client_id'                => array(
				'title'             => __( 'Client ID', 'ledyer-payments-for-woocommerce' ),
				'type'              => 'text',
				'default'           => '',
				'description'       => __( 'Can be found or generated in the merchant portal (Settings → API credentials).', 'ledyer-payments-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'client_secret'            => array(
				'title'             => __( 'Client secret', 'ledyer-payments-for-woocommerce' ),
				'type'              => 'password',
				'default'           => '',
				'description'       => __( 'Can be found or generated in the merchant portal (Settings → API credentials).', 'ledyer-payments-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'off new-password',
				),
			),
			'store_id'                 => array(
				'title'             => __( 'Store ID', 'ledyer-payments-for-woocommerce' ),
				'type'              => 'text',
				'default'           => '',
				'description'       => __( 'Optional. If not set, Ledyer defaults to the first store ID. You can find the store ID on the on the settings page in merchant portal', 'ledyer-payments-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_mode'                => array(
				'title'       => __( 'Test mode', 'ledyer-payments-for-woocommerce' ),
				'label'       => 'Enable',
				'type'        => 'checkbox',
				'description' => __( 'While in test mode, the customer will NOT be charged. Test mode is useful for testing and debugging purposes.', 'ledyer-payments-for-woocommerce' ),
				'default'     => 'no',
			),
			'checkout_settings'        => array(
				'title' => __( 'Checkout settings', 'ledyer-payments-for-woocommerce' ),
				'type'  => 'title',
			),
			'company_number_placement' => array(
				'title'       => __( 'Company number field', 'ledyer-payments-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'payment_form',
				'description' => __( 'Where to display the company number field.', 'ledyer-payments-for-woocommerce' ),
				'options'     => array(
					'payment_form' => __( 'Payment form', 'ledyer-payments-for-woocommerce' ),
					'billing_form' => __( 'Billing form', 'ledyer-payments-for-woocommerce' ),
				),
			),
			'security_level'           => array(
				'title'       => __( 'Security level', 'ledyer-payments-for-woocommerce' ),
				'type'        => 'select',
				'default'     => '200',
				'description' => __( 'Refer to the <a href="https://static.ledyer.com/docs/en-US/ledyer-security_levels.pdf">documentation</a> on what these level mean. This will override the security level you have set in the merchant portal.', 'ledyer-payments-for-woocommerce' ),
				'options'     => array(
					'100' => '100',
					'110' => '110',
					'120' => '120',
					'200' => '200',
					'210' => '210',
					'220' => '220',
					'300' => '300',
				),
			),
			'troubleshooting'          => array(
				'title' => __( 'Troubleshooting', 'ledyer-payments-for-woocommerce' ),
				'type'  => 'title',
			),
			'logging'                  => array(
				'title'       => __( 'Logging', 'ledyer-payments-for-woocommerce' ),
				'label'       => 'Enable',
				'type'        => 'checkbox',
				'description' => __( 'Logging is required for troubleshooting any issues related to the plugin. It is recommended that you always have it enabled.', 'ledyer-payments-for-woocommerce' ),
				'default'     => 'yes',
			),
			'extended_logging'         => array(
				'title'       => __( 'Detailed logging', 'ledyer-payments-for-woocommerce' ),
				'label'       => __( 'Enable', 'ledyer-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable detailed logging to capture extra data. Use this only when needed for debugging hard-to-replicate issues, as it generates significantly more log entries.', 'ledyer-payments-for-woocommerce' ),
				'default'     => 'no',
			),
		);

		return apply_filters( 'ledyer_payments_settings', $settings );
	}

	/**
	 * Delete the payment gateway's access token transient.
	 *
	 * This should be called whenever the plugin settings are updated.
	 *
	 * @return void
	 */
	public static function maybe_update_access_token() {
		// Always renew the access token when the settings is updated.
		delete_transient( 'ledyer_payments_access_token' );
	}
}
