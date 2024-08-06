<?php
namespace Ledyer\Payments;

/**
 * Class Plugin
 *
 * Handles the plugins initialization.
 */
class Plugin {
	/**
	 * Plugin constructor.
	 *
	 * @return void
	 */
	public function init() {
		$this->load_dependencies();

		$this->setup_hooks();
	}

	/**
	 * Load all the plugin dependencies and add them to the container.
	 *
	 * @return void
	 */
	private function load_dependencies() {
	}

	/**
	 * Register the activation and deactivation hooks.
	 *
	 * @return void
	 */
	private function setup_hooks() {
		// Hook for plugin activation
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Hook for plugin deactivation
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Handle plugin activation.
	 *
	 * @return void
	 */
	public function activate() {
		// Do something on activation
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		// Do something on deactivation
	}
}