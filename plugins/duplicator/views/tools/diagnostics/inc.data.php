<?php

	$sql = "SELECT * FROM `{$wpdb->prefix}options` WHERE  `option_name` LIKE  '%duplicator_%' AND  `option_name` NOT LIKE '%duplicator_pro%' ORDER BY option_name";

?>

<!-- ==============================
OPTIONS DATA -->
<div class="dup-box">
	<div class="dup-box-title">
		<i class="fa fa-th-list"></i>
		<?php _e("Stored Data", 'duplicator'); ?>
		<div class="dup-box-arrow"></div>
	</div>
	<div class="dup-box-panel" id="dup-settings-diag-opts-panel" style="<?php echo $ui_css_opts_panel?>">
		<div style="padding:0px 20px 0px 25px">
			<h3 class="title" style="margin-left:-15px"><?php _e("Options Values", 'duplicator') ?> </h3>	

			<table class="widefat" cellspacing="0">		
				<tr>
					<th>Key</th>
					<th>Value</th>
				</tr>		
				<?php 
					foreach( $wpdb->get_results("{$sql}") as $key => $row) { ?>	
					<tr>
						<td>
							<?php 
								 echo (in_array($row->option_name, $GLOBALS['DUPLICATOR_OPTS_DELETE']))
									? "<a href='javascript:void(0)' onclick='Duplicator.Settings.ConfirmDeleteOption(this)'>{$row->option_name}</a>"
									: $row->option_name;
							?>
						</td>
						<td><textarea class="dup-opts-read" readonly="readonly"><?php echo $row->option_value?></textarea></td>
					</tr>
				<?php } ?>	
			</table>
		</div>

	</div> 
</div> 
<br/>

<!-- ==========================================
THICK-BOX DIALOGS: -->
<?php
	$confirm1 = new DUP_UI_Dialog();
	$confirm1->title			= __('Delete Option?', 'duplicator');
	$confirm1->message			= __('Delete the option value just selected?', 'duplicator');
	$confirm1->progressText	= __('Removing Option, Please Wait...', 'duplicator');
	$confirm1->jscallback		= 'Duplicator.Settings.DeleteOption()';
	$confirm1->initConfirm();
?>

<script>	
jQuery(document).ready(function($) 
{
	Duplicator.Settings.ConfirmDeleteOption = function (anchor) 
	{
		var key = $(anchor).text();
		var msg_id = '<?php echo $confirm1->getMessageID() ?>';
		var msg    = '<?php _e('Delete the option value', 'duplicator');?>' + ' [' + key + '] ?';
		jQuery('#dup-settings-form-action').val(key);
		jQuery('#' + msg_id).html(msg)
		<?php $confirm1->showConfirm(); ?>
	}
	
	
	Duplicator.Settings.DeleteOption = function () 
	{
		jQuery('#dup-settings-form').submit();
	}
});	
</script>
