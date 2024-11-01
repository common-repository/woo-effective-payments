<?php
defined( 'ABSPATH' ) or die();

require_once( 'pro_menu.php' );

/* Action for creating menu pages */
add_action( 'admin_menu', array( 'EPSR_AdminMenu', 'create_menu' ) );