<?php

class Log_Viewer_DebugBar_Integration
{
	/**
	 * Check and integrate to Debug-Bar Plugin as Panel
	 *
	 * @since    14.04.21
	 */
	public static function integrate_debugbar( $panels ) {
		require_once plugin_dir_path( __DIR__ ) . '/admin/class-log-viewer-admin.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-dbpanel.php';
		$myPanel = new Log_Viewer_DebugBar_Panel( plugin_dir_path( __DIR__ ) . '/views' );

		$panels[] = $myPanel;

		return $panels;
	}
}
