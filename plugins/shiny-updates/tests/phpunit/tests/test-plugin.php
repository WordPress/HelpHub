<?php

class Test_Shiny_Updates extends WP_UnitTestCase {
	public function test_plugin_is_active() {
		$this->assertTrue( function_exists( 'su_init' ) );
	}
}
