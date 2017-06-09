<?php
DUP_Util::hasCapability('read');

require_once(DUPLICATOR_PLUGIN_PATH . '/assets/js/javascript.php');
require_once(DUPLICATOR_PLUGIN_PATH . '/views/inc.header.php');
?>
<style>
    /*================================================
    PAGE-SUPPORT:*/
	div.dup-pro-area {
		padding:10px 70px; max-width:750px; width:90%; margin:auto; text-align:center;
		background:#fff; border-radius:20px;
		box-shadow:inset 0px 0px 67px 20px rgba(241,241,241,1);
	}
    div.dup-compare-area {width:400px;  float:left; border:1px solid #dfdfdf; border-radius:4px; margin:10px; line-height:18px;box-shadow:0 8px 6px -6px #ccc;}
	div.feature {background:#fff; padding:15px; margin:2px; text-align:center; min-height:20px}
	div.feature a {font-size:18px; font-weight:bold;}
	div.dup-compare-area div.feature div.info {display:none; padding:7px 7px 5px 7px; font-style:italic; color:#555; font-size:14px}
	div.dup-gopro-header {text-align:center; margin:5px 0 15px 0; font-size:18px; line-height:30px}
	div.dup-gopro-header b {font-size:35px}
	button.dup-check-it-btn {box-shadow:5px 5px 5px 0px #999 !important; font-size:20px !important; height:45px !important;   padding:7px 30px 7px 30px !important;   color:white!important;  background-color: #3e8f3e!important; font-weight: bold!important;
    color: white;
    font-weight: bold;}

	#comparison-table { margin-top:25px; border-spacing:0px;  width:100%}
	#comparison-table th { color:#E21906;}
	#comparison-table td, #comparison-table th { font-size:1.2rem; padding:11px; }
	#comparison-table .feature-column { text-align:left; width:46%}
	#comparison-table .check-column { text-align:center; width:27% }
	#comparison-table tr:nth-child(2n+2) {background-color:#f6f6f6; }
</style>

<div class="dup-pro-area">
	<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/logo-dpro-300x50-nosnap.png"  /> 
	<div style="font-size:18px; font-style:italic; color:gray">
		<?php _e('The simplicity of Duplicator', 'duplicator') ?>
		<?php _e('with power for the professional.', 'duplicator') ?>
	</div>

	<table id="comparison-table">
		<tr>
			<th class="feature-column"><?php _e('Feature', 'duplicator') ?></th>
			<th class="check-column"><?php _e('Free', 'duplicator') ?></th>
			<th class="check-column"><?php _e('Professional', 'duplicator') ?></th>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Backup Files & Database', 'duplicator') ?></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Directory Filters', 'duplicator') ?></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Database Table Filters', 'duplicator') ?></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Migration Wizard', 'duplicator') ?></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column">
				<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/amazon-64.png" style='height:16px; width:16px'  />  
				<?php _e('Amazon S3 Storage', 'duplicator') ?>
			</td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column">
				<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/dropbox-64.png" style='height:16px; width:16px'  /> 
				<?php _e('Dropbox Storage ', 'duplicator') ?>
			</td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column">
				<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/google_drive_64px.png" style='height:16px; width:16px'  /> 
				<?php _e('Google Drive Storage', 'duplicator') ?>
			</td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column">
				<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/ftp-64.png" style='height:16px; width:16px'  /> 
				<?php _e('Remote FTP Storage', 'duplicator') ?>
			</td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column">
				<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/cpanel-48.png" style="width:16px; height:12px" />
				<?php _e('cPanel Database API', 'duplicator') ?>
			</td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>			
		<tr>
			<td class="feature-column"><?php _e('Scheduled Backups', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>			
		<tr>
			<td class="feature-column"><?php _e('Larger Package Support', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Multisite Backup', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
	
		<tr>
			<td class="feature-column"><?php _e('Email Alerts', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('File Filters', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Custom Search & Replace', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Manual Transfers', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>		
		<tr>
			<td class="feature-column"><?php _e('Active Customer Support', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>
		<tr>
			<td class="feature-column"><?php _e('Plus Many Other Features...', 'duplicator') ?></td>
			<td class="check-column"></td>
			<td class="check-column"><i class="fa fa-check"></i></td>
		</tr>			
	</table>

	<br style="clear:both" />
	<p style="text-align:center">
		<button onclick="window.open('https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_go_pro&utm_campaign=duplicator_pro');" class="button button-large dup-check-it-btn" >
			<?php _e('Check It Out!', 'duplicator') ?>
		</button>
	</p>
	<br/><br/>
</div>
<br/><br/>