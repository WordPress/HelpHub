<?php

class Debug_Bar_Console extends Debug_Bar_Panel {
	function init() {
		$this->title( 'Console' );
		add_action( 'wp_ajax_debug_bar_console', array( &$this, 'ajax' ) );
	}

	function prerender() {
		$this->set_visible( true );
	}

	function render() {
		$modes = array(
			'php' => __('PHP'),
			'sql' => __('SQL'),
		);

		$mode = 'php';

		?>
		<form id="debug-bar-console" class="debug-bar-console-mode-<?php echo esc_attr( $mode ); ?>">
		<input id="debug-bar-console-iframe-css" type="hidden"
			value="<?php echo plugins_url( 'css/iframe.dev.css', __FILE__ ); ?>" />
		<?php wp_nonce_field( 'debug_bar_console', '_wpnonce_debug_bar_console' ); ?>
		<div id="debug-bar-console-wrap">
			<ul class="debug-bar-console-tabs">
				<?php foreach ( $modes as $slug => $title ):
					$classes = 'debug-bar-console-tab';
					if ( $slug == $mode )
						$classes .= ' debug-bar-console-tab-active';
					?>
					<li class="<?php echo esc_attr( $classes ); ?>">
						<?php echo $title; ?>
						<input type="hidden" value="<?php echo $slug; ?>" />
					</li>
				<?php endforeach; ?>
			</ul>
			<div id="debug-bar-console-submit">
				<span><?php _e('Shift + Enter'); ?></span>
				<a href="#"><?php _e('Run'); ?></a>
			</div>
			<div class="debug-bar-console-panel debug-bar-console-on-php">
				<textarea id="debug-bar-console-input-php" class="debug-bar-console-input"><?php echo "<?php\n"; ?></textarea>
			</div>
			<div class="debug-bar-console-panel debug-bar-console-on-sql">
				<textarea id="debug-bar-console-input-sql" class="debug-bar-console-input"></textarea>
			</div>
		</div>
		<div id="debug-bar-console-output">
			<strong><?php _e('Output'); ?></strong>
			<div class="debug-bar-console-panel">
				<iframe></iframe>
			</div>
		</div>
		</form>
		<?php
	}

	function ajax() {
		global $wpdb;

		if ( false === check_ajax_referer( 'debug_bar_console', 'nonce', false ) )
			die();

		if ( ! is_super_admin() || ! isset( $_POST['mode'] ) )
			die();

		$data = stripslashes( $_POST['data'] );
		$mode = $_POST['mode'];

		if ( 'php' == $mode ) {
			// Trim the data
			$data = '?>' . trim( $data );

			// Do we end the string in PHP?
			$open  = strrpos( $data, '<?php' );
			$close = strrpos( $data, '?>' );

			// If we're still in PHP, ensure we end with a semicolon.
			if ( $open > $close )
				$data = rtrim( $data, ';' ) . ';';

			eval( $data );
			die();

		} elseif ( 'sql' == $mode ) {
			$data = explode( ";\n", $data );
			foreach ( $data as $query ) {
				$query = str_replace( '$wpdb->', $wpdb->prefix, $query );
				$this->print_mysql_table( $wpdb->get_results( $query, ARRAY_A ), $query );
			}
			die();
		}
	}

	function print_mysql_table( $data, $query='' ) {
		if ( empty( $data ) )
			return;

		$keys = array_keys( $data[0] );

		echo '<table class="mysql" cellpadding="0"><thead>';

		if ( ! empty( $query ) )
			echo "<tr class='query'><td colspan='" . count($keys) . "'>$query</td></tr>";

		echo '<tr>';
		foreach ( $keys as $key ) {
			echo "<th class='$key'>$key</th>";
		}
		echo '</tr></thead><tbody>';

		foreach ( $data as $row ) {
			echo '<tr>';
			foreach ( $row as $key => $value ) {
				echo "<td class='$key'>" . esc_html($value) . "</td>";
			}
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}

