<?php
/**
 * Plugin Name: Ledyer Payments for WooCommerce
 * Plugin URI: https://krokedil.com/
 * Description: Ledyer Payments for WooCommerce.
 * Author: krokedil
 * Author URI: https://krokedil.com/
 * Version: 0.0.1
 * Text Domain: ledyer-payments-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 5.6.0
 * WC tested up to: 8.1.0
 *
 * Copyright (c) 2024 Krokedil
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Ledyer_Payments
 */

use Ledyer\Payments\Plugin;

// Just like any other plugin, add a check to prevent people from accessing the files directly. This should be added to all files in the plugin.
defined( 'ABSPATH' ) || exit;

// Following our practice of using constants, we define a few here for the plugin version, main file, path and URL. These can then be used later in the plugin when needed.
define( 'LP_VERSION', '1.0.0' );
define( 'LP_MAIN_FILE', __FILE__ );
define( 'LP_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'LP_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );


// Add in a declaration that we support HPOS. Any new plugin we develop should support this, so we add it here. Anonymous function is ok in this case, since this should not be removable.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Require the autoloader, if it does not exist fail gracefully and output an error.
 * If Debug is enabled, then log to the error log as well.
 * This will be required to automatically load all the classes in the plugin, even when not using other external packages! This is the only file that should be required in the plugin normally.
 */
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoloader ) ) {
	require $autoloader;
} else {

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( //phpcs:ignore
			sprintf(
				/* translators: 1: composer command. 2: plugin directory */
				esc_html__( 'Your installation of the Modern PHP Plugin is incomplete. Please run %1$s within the %2$s directory.', 'modern-php-plugin' ),
				'`composer install`',
				'`' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '`'
			)
		);
	}

	// Add a admin notice, use anonymous function to simplify, this does not need to be removable.
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<?php
					printf(
						/* translators: 1: composer command. 2: plugin directory */
						esc_html__( 'Your installation of the Ledyer Payments plugin is incomplete. Please run %1$s within the %2$s directory.', 'ledyer-payments-for-woocommerce' ),
						'<code>composer install</code>',
						'<code>' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '</code>'
					);
				?>
			</p>
		</div>
			<?php
		}
	);
	return;
}

$plugin = Ledyer();

// Just like we do now in our plugins we add a action for plugins_loaded to kickstart the plugins code. Here we are calling the namespace and class directly and the static method inside init.
add_action( 'plugins_loaded', array( $plugin, 'init' ) );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $plugin, 'plugin_action_links' ) );

/**
 * Get the instance of the plugin.
 *
 * @return Plugin
 */
function Ledyer() {
	return Plugin::get_instance();
}