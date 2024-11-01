<?php
defined( 'ABSPATH' ) or die();

/**
 *  Add Admin Menu Panel 
 */
class EPSR_AdminMenu {
	public static function create_menu() {

		$dashboard = add_menu_page( esc_html__( 'Pro Plugins', 'effective-payment-solutions-by-rajthemes' ), esc_html__( 'Pro Plugins', 'effective-payment-solutions-by-rajthemes' ), 'manage_options', 'effective-payment-solutions-by-rajthemes', array(
			'EPSR_AdminMenu',
			'dashboard'
		), 'dashicons-buddicons-forums', 25 );
		add_action( 'admin_print_styles-' . $dashboard, array( 'EPSR_AdminMenu', 'dashboard_assets' ) );
	}

	/* Dashboard menu/submenu callback */
	public static function dashboard() {
		require_once( 'pro_dashboard.php' );
	}

	public static function dashboard_assets() {
		self::enqueue_libraries();
	}

	public static function enqueue_libraries() {

		/* Enqueue scripts */

		wp_enqueue_style( 'boostrap', WL_WEPSR_PLUGIN_URL . 'assets/css/bootstrap.min.css' );
		wp_enqueue_style( 'wl_wepsr_style', WL_WEPSR_PLUGIN_URL . 'assets/css/custom_style.css' );

		wp_enqueue_script( 'popper', WL_WEPSR_PLUGIN_URL . 'assets/js/popper.min.js', array( 'jquery' ), true, true );
		wp_enqueue_script( 'bootstrap_js', WL_WEPSR_PLUGIN_URL . 'assets/js/bootstrap.min.js', array( 'jquery' ), true, true );
	}
}