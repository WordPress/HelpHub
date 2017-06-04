<?php
/**
 * Represents the view for a Debug Bar Panel.
 *
 * Only showing default debug.log with a link to full page.
 *
 * @since     14.04.21
 *
 * @package   log-viewer
 * @author    Markus Fischbacher <fischbacher.markus@gmail.com>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/extend/plugins/log-viewer/
 * @copyright 2013 Markus Fischbacher
 */
?>

<div class="wrap">

	<?php if( $showEditSection ) : ?>

	<div class="fileedit-sub">

		<div id="templateside">
			<?php printf( '%1$s <strong>%2$s</strong>', __( 'Showing' ), str_replace( realpath( ABSPATH ), "", $realfile ) ) ?>
			<a class="button-secondary" title="Open full view" href="<?php echo Files_View_Page::getPageUrl(); ?>">Open full view</a>
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

	<?php endif; ?>

</div>

