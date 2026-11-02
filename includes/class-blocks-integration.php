<?php
/** WooCommerce's supported checkout asset registration. @package Patsch9Orderdate */

namespace Patsch9\Orderdate;

defined( 'ABSPATH' ) || exit;

final class BlocksIntegration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
	public function get_name() {
		return Blocks::NS;
	}

	public function initialize() {}

	public function get_script_handles() {
		return array( 'p9od-block' );
	}

	public function get_editor_script_handles() {
		return array( 'p9od-block-editor' );
	}

	public function get_script_data() {
		return array();
	}
}
