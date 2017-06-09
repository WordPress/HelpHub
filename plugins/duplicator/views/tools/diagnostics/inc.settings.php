<?php
	$dbvar_maxtime  = DUP_DB::getVariable('wait_timeout');
	$dbvar_maxpacks = DUP_DB::getVariable('max_allowed_packet');
	$dbvar_maxtime  = is_null($dbvar_maxtime)  ? __("unknow", 'duplicator') : $dbvar_maxtime;
	$dbvar_maxpacks = is_null($dbvar_maxpacks) ? __("unknow", 'duplicator') : $dbvar_maxpacks;	
	
	$space = @disk_total_space(DUPLICATOR_WPROOTPATH);
	$space_free = @disk_free_space(DUPLICATOR_WPROOTPATH);
	$perc = @round((100/$space)*$space_free,2);
	$mysqldumpPath = DUP_DB::getMySqlDumpPath();
	$mysqlDumpSupport = ($mysqldumpPath) ? $mysqldumpPath : 'Path Not Found';
	
	$client_ip_address = DUP_Server::getClientIP();
?>

<!-- ==============================
SERVER SETTINGS -->	
<div class="dup-box">
<div class="dup-box-title">
	<i class="fa fa-tachometer"></i>
	<?php _e("Server Settings", 'duplicator') ?>
	<div class="dup-box-arrow"></div>
</div>
<div class="dup-box-panel" id="dup-settings-diag-srv-panel" style="<?php echo $ui_css_srv_panel?>">
	<table class="widefat" cellspacing="0">		   
		<tr>
			<td class='dup-settings-diag-header' colspan="2"><?php _e("General", 'duplicator'); ?></td>
		</tr>
		<tr>
			<td><?php _e("Duplicator Version", 'duplicator'); ?></td>
			<td><?php echo DUPLICATOR_VERSION ?></td>
		</tr>
		<tr>
			<td><?php _e("Operating System", 'duplicator'); ?></td>
			<td><?php echo PHP_OS ?></td>
		</tr>
		<tr>
			<td><?php _e("Timezone", 'duplicator'); ?></td>
			<td><?php echo  date_default_timezone_get() ; ?> &nbsp; <small><i>This is a <a href='options-general.php'>WordPress setting</a></i></small></td>
		</tr>	
		<tr>
			<td><?php _e("Server Time", 'duplicator'); ?></td>
			<td><?php echo date("Y-m-d H:i:s"); ?></td>
		</tr>				   
		<tr>
			<td><?php _e("Web Server", 'duplicator'); ?></td>
			<td><?php echo $_SERVER['SERVER_SOFTWARE'] ?></td>
		</tr>
		<tr>
			<td><?php _e("APC Enabled", 'duplicator'); ?></td>
			<td><?php echo DUP_Util::runAPC() ? 'Yes' : 'No'  ?></td>
		</tr>					   
		<tr>
			<td><?php _e("Root Path", 'duplicator'); ?></td>
			<td><?php echo DUPLICATOR_WPROOTPATH ?></td>
		</tr>	
		<tr>
			<td><?php _e("ABSPATH", 'duplicator'); ?></td>
			<td><?php echo ABSPATH ?></td>
		</tr>			
		<tr>
			<td><?php _e("Plugins Path", 'duplicator'); ?></td>
			<td><?php echo DUP_Util::safePath(WP_PLUGIN_DIR) ?></td>
		</tr>
		<tr>
			<td><?php _e("Loaded PHP INI", 'duplicator'); ?></td>
			<td><?php echo php_ini_loaded_file() ;?></td>
		</tr>	
		<tr>
			<td><?php _e("Server IP", 'duplicator'); ?></td>
			<td><?php echo $_SERVER['SERVER_ADDR'];?></td>
		</tr>	
		<tr>
			<td><?php _e("Client IP", 'duplicator'); ?></td>
			<td><?php echo $client_ip_address;?></td>
		</tr>
		<tr>
			<td class='dup-settings-diag-header' colspan="2">WordPress</td>
		</tr>
		<tr>
			<td><?php _e("Version", 'duplicator'); ?></td>
			<td><?php echo $wp_version ?></td>
		</tr>
		<tr>
			<td><?php _e("Language", 'duplicator'); ?></td>
			<td><?php echo get_bloginfo('language') ?></td>
		</tr>	
		<tr>
			<td><?php _e("Charset", 'duplicator'); ?></td>
			<td><?php echo get_bloginfo('charset') ?></td>
		</tr>
		<tr>
			<td><?php _e("Memory Limit ", 'duplicator'); ?></td>
			<td><?php echo WP_MEMORY_LIMIT ?> (<?php _e("Max", 'duplicator'); echo '&nbsp;' . WP_MAX_MEMORY_LIMIT; ?>)</td>
		</tr>
		<tr>
			<td class='dup-settings-diag-header' colspan="2">PHP</td>
		</tr>
		<tr>
			<td><?php _e("Version", 'duplicator'); ?></td>
			<td><?php echo phpversion() ?></td>
		</tr>	
		<tr>
			<td>SAPI</td>
			<td><?php echo PHP_SAPI ?></td>
		</tr>
		<tr>
			<td><?php _e("User", 'duplicator'); ?></td>
			<td><?php echo DUP_Util::getCurrentUser(); ?></td>
		</tr>
		<tr>
			<td><?php _e("Process", 'duplicator'); ?></td>
			<td><?php echo DUP_Util::getProcessOwner(); ?></td>
		</tr>
		<tr>
			<td><a href="http://php.net/manual/en/features.safe-mode.php" target="_blank"><?php _e("Safe Mode", 'duplicator'); ?></a></td>
			<td>
			<?php echo (((strtolower(@ini_get('safe_mode')) == 'on')	  ||  (strtolower(@ini_get('safe_mode')) == 'yes') || 
						 (strtolower(@ini_get('safe_mode')) == 'true') ||  (ini_get("safe_mode") == 1 )))  
						 ? __('On', 'duplicator') : __('Off', 'duplicator'); 
			?>
			</td>
		</tr>
		<tr>
			<td><a href="http://www.php.net/manual/en/ini.core.php#ini.memory-limit" target="_blank"><?php _e("Memory Limit", 'duplicator'); ?></a></td>
			<td><?php echo @ini_get('memory_limit') ?></td>
		</tr>
		<tr>
			<td><?php _e("Memory In Use", 'duplicator'); ?></td>
			<td><?php echo size_format(@memory_get_usage(TRUE), 2) ?></td>
		</tr>
		<tr>
			<td><a href="http://www.php.net/manual/en/info.configuration.php#ini.max-execution-time" target="_blank"><?php _e("Max Execution Time", 'duplicator'); ?></a></td>
			<td><?php echo @ini_get( 'max_execution_time' ); ?></td>
		</tr>
		<tr>
			<td><a href="http://us3.php.net/shell_exec" target="_blank"><?php _e("Shell Exec", 'duplicator'); ?></a></td>
			<td><?php echo (DUP_Util::hasShellExec()) ? _e("Is Supported", 'duplicator') : _e("Not Supported", 'duplicator'); ?></td>
		</tr>            
		<tr>
			<td><?php _e("Shell Exec Zip", 'duplicator'); ?></td>
			<td><?php echo (DUP_Util::getZipPath() != null) ? _e("Is Supported", 'duplicator') : _e("Not Supported", 'duplicator'); ?></td>
		</tr>
        <tr>
            <td><a href="https://suhosin.org/stories/index.html" target="_blank"><?php _e("Suhosin Extension", 'duplicator'); ?></a></td>
            <td><?php echo extension_loaded('suhosin') ? _e("Enabled", 'duplicator') : _e("Disabled", 'duplicator'); ?></td>
        </tr>
		<tr>
			<td class='dup-settings-diag-header' colspan="2">MySQL</td>
		</tr>					   
		<tr>
			<td><?php _e("Version", 'duplicator'); ?></td>
			<td><?php echo DUP_DB::getVersion() ?></td>
		</tr>
        <tr>
			<td><?php _e("Comments", 'duplicator'); ?></td>
            <td><?php echo DUP_DB::getVariable('version_comment') ?></td>
		</tr>
		<tr>
			<td><?php _e("Charset", 'duplicator'); ?></td>
			<td><?php echo DB_CHARSET ?></td>
		</tr>
		<tr>
			<td><a href="http://dev.mysql.com/doc/refman/5.0/en/server-system-variables.html#sysvar_wait_timeout" target="_blank"><?php _e("Wait Timeout", 'duplicator'); ?></a></td>
			<td><?php echo $dbvar_maxtime ?></td>
		</tr>
		<tr>
			<td style="white-space:nowrap"><a href="http://dev.mysql.com/doc/refman/5.0/en/server-system-variables.html#sysvar_max_allowed_packet" target="_blank"><?php _e("Max Allowed Packets", 'duplicator'); ?></a></td>
			<td><?php echo $dbvar_maxpacks ?></td>
		</tr>
		<tr>
			<td><a href="http://dev.mysql.com/doc/refman/5.0/en/mysqldump.html" target="_blank"><?php _e("msyqldump Path", 'duplicator'); ?></a></td>
			<td><?php echo $mysqlDumpSupport ?></td>
		</tr>
		 <tr>
			 <td class='dup-settings-diag-header' colspan="2"><?php _e("Server Disk", 'duplicator'); ?></td>
		 </tr>
		 <tr valign="top">
			 <td><?php _e('Free space', 'hyper-cache'); ?></td>
			 <td><?php echo $perc;?>% -- <?php echo DUP_Util::byteSize($space_free);?> from <?php echo DUP_Util::byteSize($space);?><br/>
				  <small>
					  <?php _e("Note: This value is the physical servers hard-drive allocation.", 'duplicator'); ?> <br/>
					  <?php _e("On shared hosts check your control panel for the 'TRUE' disk space quota value.", 'duplicator'); ?>
				  </small>
			 </td>
		 </tr>	

	</table><br/>

</div> <!-- end .dup-box-panel -->	
</div> <!-- end .dup-box -->	
<br/>