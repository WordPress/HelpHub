<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   log-viewer
 * @author    Markus Fischbacher <fischbacher.markus@gmail.com>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/extend/plugins/log-viewer/
 * @copyright 2013 Markus Fischbacher
 */
?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<?php // dumped file messages
	if( isset( $dumped ) ) {
		if( $dumped ) : ?>
			<div id="message" class="updated">
				<p><?php _e( 'File dumped successfully.' ); ?></p>
			</div>
			<?php return;
		else :
			?>
			<div id="message" class="error">
				<p><?php _e( 'Could not dump file.' ); ?></p>
			</div>
		<?php endif; // if $dumped
	} // isset $dumped
	// end - dumped file messages
	?>

	<?php // emptied/break file messages
	if( isset( $handle ) ) {
		if( !$handle ) : ?>
			<div id="message" class="error">
				<p><?php _e( 'Could not update file.' ); ?></p>
			</div>
		<?php else : ?>
			<div id="message" class="updated">
				<p><?php _e( 'File updated successfully.' ); ?></p>
			</div>
		<?php endif;
	} // isset $handle
	// end - emptied/break file messages
	?>

	<?php if( !$files ) : ?>
		<div id="message" class="updated">
			<p><?php _e( 'No files found.' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if( !$writeable ) : ?>
		<div id="message" class="updated">
			<p><?php _e( sprintf( 'You can not edit file [%s] ( not writeable ).', $this->getCurrentFile() ) ); ?></p>
		</div>
	<?php endif;

	?>

	<?php if( $showEditSection ) : ?>

		<div class="fileedit-sub">

			<?php printf( '%1$s <strong>%2$s</strong>', __( 'Showing' ), str_replace( realpath( ABSPATH ), "", $realfile ) ) ?>

			<div class="tablenav top">

				<?php if( $writeable ) : ?>

					<div class="alignleft">
						<form method="post" action="<?php echo $this->getPageUrl(); ?>">
							<?php wp_nonce_field( 'actions_nonce', 'actions_nonce' ); ?>
							<input type="hidden" value="<?php echo $this->getCurrentFile(); ?>"
								   name="<?php echo self::$_KEYS_FILEACTION_FILE; ?>" />
							<input id="scrollto" type="hidden" value="0"
								   name="<?php echo self::$_KEYS_FILEACTION_SCROLLTO; ?>">
							<select name="<?php echo self::$_KEYS_FILEACTION_ACTION; ?>">
								<option selected="selected" value="-1"><?php _e( 'File Actions' ); ?></option>
								<option
									value="<?php echo self::$_KEYS_FILEACTIONS_DUMP; ?>"><?php _e( 'Dump' ); ?></option>
								<option
									value="<?php echo self::$_KEYS_FILEACTIONS_EMPTY; ?>"><?php _e( 'Empty' ); ?></option>
								<option
									value="<?php echo self::$_KEYS_FILEACTIONS_BREAK; ?>"><?php _e( 'Break' ); ?></option>
							</select>
							<?php submit_button( __( 'Do' ), 'button', self::$_KEYS_FILEACTION_SUBMIT, false ); ?>
						</form>
					</div>

				<?php endif; ?>

				<div class="alignright">
					<form method="post" action="<?php echo $this->getPageUrl(); ?>">
						<?php wp_nonce_field( 'viewoptions_nonce', 'viewoptions_nonce' ); ?>
						<input type="hidden" value="<?php echo $this->getCurrentFile(); ?>" name="file2" />
						<input
							title="Autorefresh page every <?php echo( User_Options::getAutoRefreshIntervall() ); ?> seconds"
							type="checkbox" value="1" <?php checked( 1 == User_Options::getAutoRefresh() ); ?>
							id="<?php echo User_Options::KEYS_AUTOREFRESH; ?>"
							name="<?php echo User_Options::KEYS_AUTOREFRESH; ?>" />
						<label
							title="Autorefresh page every <?php echo( User_Options::getAutoRefreshIntervall() ); ?> seconds"
							for="<?php echo User_Options::KEYS_AUTOREFRESH; ?>">Autorefresh</label>
						<select name="<?php echo User_Options::KEYS_LINEOUTPUTORDER; ?>">
							<option <?php selected( User_Options::LINEOUTPUTORDER_FIFO == User_Options::getLineOutputOrder() ); ?>
								value="<?php echo User_Options::LINEOUTPUTORDER_FIFO; ?>">FIFO
							</option>
							<option <?php selected( User_Options::LINEOUTPUTORDER_FILO == User_Options::getLineOutputOrder() ); ?>
								value="<?php echo User_Options::LINEOUTPUTORDER_FILO; ?>">FILO
							</option>
						</select>
						<?php submit_button( __( 'Apply' ), 'button', self::$_KEYS_VIEWFIELDS_SUBMIT, false ); ?>
					</form>
				</div>

			</div>

			<div id="templateside">
				<h3>Log Files</h3>
				<ul>
					<?php foreach( $files as $file ):
						if( $file === $this->getCurrentFile() ) {
							?>
							<li class="highlight">
						<?php
						} else {
							?>
							<li>
						<?php
						}
						?>
						<a href="<?php printf( "%s&file=%s", $this->getPageUrl(), $file ); ?>">
							<?php echo $file; ?>
						</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div id="template">
				<div>
					<?php if( !is_file( $realfile ) ) : ?>
						<div id="message" class="error">
							<p><?php _e( 'Could not load file.' ); ?></p>
						</div>
					<?php else : ?>
						<textarea id="newcontent" name="newcontent" rows="25" cols="70"
								  readonly="readonly"><?php echo $this->getCurrentFileContent(); ?></textarea>
					<?php endif; ?>
					<div>
						<h3><?php _e( 'Fileinfo' ); ?></h3>
						<dl>
							<dt><?php _e( 'Fullpath:' ); ?></dt>
							<dd><?php echo $realfile; ?></dd>
							<dt><?php _e( 'Last updated: ' ); ?></dt>
							<dd><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $realfile ) ); ?></dd>
						</dl>
					</div>
				</div>
			</div>

		</div><!-- fileedit-sub -->

	<?php endif; // showEditSection? ?>

	<?php if( User_Options::getAutoRefresh() === 1 ) : ?>
		<script type="text/javascript">
			setTimeout( "window.location.replace(document.URL);", <?php echo ( User_Options::getAutoRefreshIntervall() * 1000 ); ?> );
		</script>
	<?php endif; ?>

</div>
