<?php
	
	if(empty($_POST))
	{
		//F5 Refresh Check
		$redirect = admin_url('admin.php?page=duplicator&tab=new1');
		echo "<script>window.location.href = '{$redirect}'</script>";
		exit;
	}
	
	global $wp_version;
	$Package = new DUP_Package();
			
	if(isset($_POST['package-hash']))
	{
		// If someone is trying to pass the hasn into us that is illegal so stop it immediately.
		die('Unauthorized');
	}
	
	$Package->saveActive($_POST);
	$Package = DUP_Package::getActive();
	
	$mysqldump_on	 = DUP_Settings::Get('package_mysqldump') && DUP_DB::getMySqlDumpPath();
	$mysqlcompat_on  = isset($Package->Database->Compatible) && strlen($Package->Database->Compatible);
	$mysqlcompat_on  = ($mysqldump_on && $mysqlcompat_on) ? true : false;
	$dbbuild_mode    = ($mysqldump_on) ? 'mysqldump (fast)' : 'PHP (slow)';
    
    $zip_check = DUP_Util::getZipPath();
?>

<style>
	/* ============----------
	PROGRESS ARES-CHECKS */
	div#dup-progress-area {text-align:center; max-width:650px; min-height:200px; margin:0px auto 0px auto; padding:0px;}
	div.dup-progress-title {font-size:22px; padding:5px 0 20px 0; font-weight: bold}
	div#dup-msg-success {padding:5px; text-align: left}	
	div#dup-msg-success-subtitle {font-style: italic; margin:7px 0px}	
	div#dup-msg-error {color:#A62426; padding:5px; max-width: 790px;}
	div#dup-msg-error-response-text { max-height:500px; overflow-y:scroll; border:1px solid silver; border-radius:3px; padding:10px;background:#fff}
	div.dup-hdr-error-details {text-align: left; margin:20px 0}

	div.dup-panel {margin-bottom: 25px}
	div.dup-scan-filter-status {display:inline; float: right; font-size:11px; margin-right:10px; color:#AF0000; font-style: italic}
	/* 	SERVER-CHECKS */
	div.dup-scan-title {display:inline-block;  padding:1px; font-weight: bold;}
	div.dup-scan-title a {display:inline-block; min-width:200px; padding:3px; }
	div.dup-scan-title a:focus {outline: 1px solid #fff; box-shadow: none}
	div.dup-scan-title div {display:inline-block;  }
	div.dup-scan-info {display:none; padding:5px 20px 10px 10px}
	div.dup-scan-good {display:inline-block; color:green;font-weight: bold;}
	div.dup-scan-warn {display:inline-block; color:#AF0000;font-weight: bold;}
	span.dup-toggle {float:left; margin:0 2px 2px 0; }
	/*DATABASE*/
	table#dup-scan-db-details {line-height: 14px; margin:15px 0px 0px 5px;  width:98%}
	table#dup-scan-db-details td {padding:0px;}
	table#dup-scan-db-details td:first-child {font-weight: bold;  white-space: nowrap; width:90px}
	div#dup-scan-db-info {margin:0px 0px 0px 10px}
	div#data-db-tablelist {max-height: 300px; overflow-y: scroll; border: 1px dashed silver; padding: 5px; margin-top:5px}
	div#data-db-tablelist div{padding:0px 0px 0px 15px;}
	div#data-db-tablelist span{display:inline-block; min-width: 75px}
	div#data-db-size1 {display: inline-block; float:right; font-size:11px; margin-right:5px; font-style: italic}
	/*FILES */
	div#data-arc-size1 {display: inline-block; float:right; font-size:11px; margin-right:5px; font-style: italic}
	i.data-size-help { float:right; margin-right:5px; display: block; font-size: 11px}
	div#data-arc-names-data, div#data-arc-big-data	{word-wrap: break-word;font-size:10px; border:1px dashed silver; padding:5px; display: none}
		
	div#dup-scan-warning-continue {display:none; text-align: center; padding: 0 0 15px 0}
	div#dup-scan-warning-continue div.msg1 label{font-size:16px; color:maroon}
	div#dup-scan-warning-continue div.msg2 {padding:2px; line-height: 13px}
	div#dup-scan-warning-continue div.msg2 label {font-size:11px !important}
	
	/*Footer*/
	div.dup-button-footer {text-align:center; margin:0}
	button.button {font-size:15px !important; height:30px !important; font-weight:bold; padding:3px 5px 5px 5px !important;}
</style>

<!-- =========================================
TOOL BAR: STEPS -->
<table id="dup-toolbar">
	<tr valign="top">
		<td style="white-space: nowrap">
			<div id="dup-wiz">
				<div id="dup-wiz-steps">
					<div class="completed-step"><a>1-<?php _e('Setup', 'duplicator'); ?></a></div>
					<div class="active-step"><a>2-<?php _e('Scan', 'duplicator'); ?> </a></div>
					<div><a>3-<?php _e('Build', 'duplicator'); ?> </a></div>
				</div>
				<div id="dup-wiz-title">
					<?php _e('Step 2: System Scan', 'duplicator'); ?>
				</div> 
			</div>	
		</td>
		<td>
			<a id="dup-pro-create-new"  href="?page=duplicator" class="add-new-h2"><i class="fa fa-archive"></i> <?php _e('All Packages', 'duplicator'); ?></a> 
			<span> <?php _e('Create New', 'duplicator'); ?></span>
		</td>
	</tr>
</table>		
<hr class="dup-toolbar-line">


<form id="form-duplicator" method="post" action="?page=duplicator&tab=new3">
<div id="dup-progress-area">
	<!--  PROGRESS BAR -->
	<div id="dup-progress-bar-area">
		<div class="dup-progress-title"><i class="fa fa-circle-o-notch fa-spin"></i> <?php _e('Scanning Site', 'duplicator'); ?></div>
		<div id="dup-progress-bar"></div>
		<b><?php _e('Please Wait...', 'duplicator'); ?></b><br/><br/>
		<i><?php _e('Keep this window open during the scan process.', 'duplicator'); ?></i><br/>
		<i><?php _e('This can take several minutes.', 'duplicator'); ?></i><br/>
	</div>

	<!--  SUCCESS MESSAGE -->
	<div id="dup-msg-success" style="display:none">
		<div style="text-align:center">
			<div class="dup-hdr-success"><i class="fa fa-check-square-o fa-lg"></i> <?php _e('Scan Complete', 'duplicator'); ?></div>
			<div id="dup-msg-success-subtitle">
				<?php _e('Process Time:', 'duplicator'); ?> <span id="data-rpt-scantime"></span>
			</div>
		</div><br/>
		
		<!-- ================================================================
		META-BOX: SERVER
		================================================================ -->
		<div class="dup-panel">
		<div class="dup-panel-title">
			<i class="fa fa-hdd-o"></i> <?php 	_e("Server", 'duplicator');	?>
			<div style="float:right; margin:-1px 10px 0px 0px">
				<small><a href="?page=duplicator-tools&tab=diagnostics" target="_blank"><?php _e('Diagnostics', 'duplicator');?></a></small>
			</div>
		
		</div>
		<div class="dup-panel-panel">
			<!-- ============
			WEB SERVER -->
			<div>
				<div class='dup-scan-title'>
					<a><?php _e('Web Server', 'duplicator');?></a> <div id="data-srv-web-all"></div>
				</div>
				<div class='dup-scan-info dup-info-box'>
					<?php
					$web_servers = implode(', ', $GLOBALS['DUPLICATOR_SERVER_LIST']);
					echo '<span id="data-srv-web-model"></span>&nbsp;<b>' . __('Web Server', 'duplicator') . ":</b>&nbsp; '{$_SERVER['SERVER_SOFTWARE']}'";	
					echo '<small>';
					_e("Supported web servers:", 'duplicator');
					echo "{$web_servers}";
					echo '</small>';
					?>
				</div>
			</div>				
			<!-- ============
			PHP SETTINGS -->
			<div>
				<div class='dup-scan-title'>
					<a><?php _e('PHP Setup', 'duplicator');?></a> <div id="data-srv-php-all"></div>
				</div>
				<div class='dup-scan-info dup-info-box'>
					<?php 
					
					//PHP VERSION
					echo '<span id="data-srv-php-version"></span>&nbsp;<b>' . __('PHP Version', 'duplicator') . "</b> <br/>";
					echo '<small>';
					_e('The minium PHP version supported by Duplicator is 5.2.9, however it is highly recommended to use PHP 5.3 or higher for improved stability.', 'duplicator');
					echo "&nbsp;<i><a href='http://php.net/ChangeLog-5.php' target='_blank'>[" . __('details', 'duplicator') . "]</a></i>";
					echo '</small>';
					
					//OPEN_BASEDIR
					$test = ini_get("open_basedir");
					echo '<hr size="1" /><span id="data-srv-php-openbase"></span>&nbsp;<b>' . __('Open Base Dir', 'duplicator') . ":</b>&nbsp; '{$test}' <br/>";
					echo '<small>';
					_e('Issues might occur when [open_basedir] is enabled. Work with your server admin to disable this value in the php.ini file if you’re having issues building a package.', 'duplicator');
					echo "&nbsp;<i><a href='http://www.php.net/manual/en/ini.core.php#ini.open-basedir' target='_blank'>[" . __('details', 'duplicator') . "]</a></i><br/>";
					echo '</small>';
					
					//MYSQLI
					echo '<hr size="1" /><span id="data-srv-php-mysqli"></span>&nbsp;<b>' . __('MySQLi', 'duplicator') . "</b> <br/>";
					echo '<small>';
					_e('Creating the package does not require the mysqli module.  However the installer.php file requires that the PHP module mysqli be installed on the server it is deployed on.', 'duplicator');
					echo "&nbsp;<i><a href='http://php.net/manual/en/mysqli.installation.php' target='_blank'>[" . __('details', 'duplicator') . "]</a></i>";
					echo '</small>';
					
					//MAX_EXECUTION_TIME
					$test = (@set_time_limit(0)) ? 0 : ini_get("max_execution_time");
					echo '<hr size="1" /><span id="data-srv-php-maxtime"></span>&nbsp;<b>' . __('Max Execution Time', 'duplicator') . ":</b>&nbsp; '{$test}' <br/>";
					echo '<small>';
					printf(__('Issues might occur for larger packages when the [max_execution_time] value in the php.ini is too low.  The minimum recommended timeout is "%1$s" seconds or higher. An attempt is made to override this value if the server allows it.  A value of 0 (recommended) indicates that PHP has no time limits.', 'duplicator'), DUPLICATOR_SCAN_TIMEOUT);
					echo '<br/><br/>';
					_e('Note: Timeouts can also be set at the web server layer, so if the PHP max timeout passes and you still see a build interrupt messages, then your web server could be killing the process.   If you are limited on processing time, consider using the database or file filters to shrink the size of your overall package.   However use caution as excluding the wrong resources can cause your install to not work properly.', 'duplicator');
					echo "&nbsp;<i><a href='http://www.php.net/manual/en/info.configuration.php#ini.max-execution-time' target='_blank'>[" . __('details', 'duplicator')  . "]</a></i>";
                    
                    if ($zip_check != null) {
						echo '<br/><br/>';
						echo '<span style="font-weight:bold">';
						_e('Get faster builds with Duplicator Pro with access to shell_exec zip.', 'duplicator');
						echo '</span>';
						echo "&nbsp;<i><a href='https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_max_execution_time_warn&utm_campaign=duplicator_pro' target='_blank'>[" . __('details', 'duplicator') . "]</a></i>";
                    }
                    
					echo '</small>';
					

					?>
				</div>
			</div>
			<!-- ============
			WORDPRESS SETTINGS -->
			<div>
				<div class='dup-scan-title'>
					<a><?php _e('WordPress', 'duplicator');?></a> <div id="data-srv-wp-all"></div>
				</div>
				<div class='dup-scan-info dup-info-box'>
					<?php 
					//VERSION CHECK
					echo '<span id="data-srv-wp-version"></span>&nbsp;<b>' . __('WordPress Version', 'duplicator') . ":</b>&nbsp; '{$wp_version}' <br/>";
					echo '<small>';
					printf(__('It is recommended to have a version of WordPress that is greater than %1$s', 'duplicator'), DUPLICATOR_SCAN_MIN_WP);
					echo '</small>';
				
					//CORE FILES
					echo '<hr size="1" /><span id="data-srv-wp-core"></span>&nbsp;<b>' . __('Core Files', 'duplicator') . "</b> <br/>";
					echo '<small>';
					_e("If the scanner is unable to locate the wp-config.php file in the root directory, then you will need to manually copy it to its new location.", 'duplicator');
					echo '</small>';

					//CACHE DIR
					$cache_path = $cache_path = DUP_Util::safePath(WP_CONTENT_DIR) . '/cache';
					$cache_size = DUP_Util::byteSize(DUP_Util::getDirectorySize($cache_path));
					echo '<hr size="1" /><span id="data-srv-wp-cache"></span>&nbsp;<b>' . __('Cache Path', 'duplicator') . ":</b>&nbsp; '{$cache_path}' ({$cache_size}) <br/>";
					echo '<small>';
					_e("Cached data will lead to issues at install time and increases your archive size. It is recommended to empty your cache directory at build time. Use caution when removing data from the cache directory. If you have a cache plugin review the documentation for how to empty it; simply removing files might cause errors on your site. The cache size minimum threshold is currently set at ", 'duplicator');
					echo DUP_Util::byteSize(DUPLICATOR_SCAN_CACHESIZE) . '.';
					echo '</small>';
					
					//MU SITE
					if (is_multisite()) 
					{
						echo '<hr size="1" /><span><div class="dup-scan-warn"><i class="fa fa-exclamation-triangle"></i></div></span>&nbsp;<b>' . __('Multisite: Unsupported', 'duplicator') . "</b> <br/>";
						echo '<small>';
							_e('Duplicator does not officially support Multisite. However, Duplicator Pro supports duplication of a full Multisite network and also has the ability to install a Multisite subsite as a standalone site.', 'duplicator');
							echo "&nbsp;<i><a href='https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_is_mu_warn&utm_campaign=duplicator_pro' target='_blank'>[" . __('details', 'duplicator') . "]</a></i>";
						echo '</small>';
					} 
					else 
					{
						echo '<hr size="1" /><span><div class="dup-scan-good"><i class="fa fa-check"></i></div></span>&nbsp;<b>' . __('Multisite: N/A', 'duplicator') . "</b> <br/>";
						echo '<small>';
							_e('This is not a Multisite install so duplication will proceed without issue.  Duplicator does not officially support Multisite. However, Duplicator Pro supports duplication of a full Multisite network and also has the ability to install a Multisite subsite as a standalone site.', 'duplicator');
							echo "&nbsp;<i><a href='https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_is_mu_warn&utm_campaign=duplicator_pro' target='_blank'>[" . __('details', 'duplicator') . "]</a></i>";
						echo '</small>';
					}
						
					?>
				</div>
			</div>
		</div><!-- end .dup-panel -->
		</div><!-- end .dup-panel-panel -->
	
		<h2 style="font-size:18px; font-weight:bold; margin:-10px 0 5px 10px"><i class="fa fa-file-archive-o"></i>&nbsp;<?php _e('Archive', 'duplicator');?> </h2>
		
		<!-- ================================================================
		FILES
		================================================================ -->
		<div class="dup-panel">
			<div class="dup-panel-title">
				<i class="fa fa-files-o"></i>
				<?php _e("Files", 'duplicator'); ?> 
				<i class="fa fa-question-circle data-size-help"
					data-tooltip-title="<?php _e("File Size:", 'duplicator'); ?>" 
					data-tooltip="<?php _e('The files size represents only the included files before compression is applied. It does not include the size of the database script and in most cases the package size once completed will be smaller than this number.', 'duplicator'); ?>"></i>
				<div id="data-arc-size1"></div>
				<div class="dup-scan-filter-status">
					<?php
						if ($Package->Archive->ExportOnlyDB) {
							echo '<i class="fa fa-filter"></i> '; _e('Database Only', 'duplicator');
						}elseif ($Package->Archive->FilterOn) {
							echo '<i class="fa fa-filter"></i> '; _e('Enabled', 'duplicator');
						} 
					?> 
				</div>
			</div>
			<div class="dup-panel-panel">

				<!-- ============
				TOTAL SIZE -->
				<div>
					<div class='dup-scan-title'>
						<a><?php _e('Total Size', 'duplicator');?></a> <div id="data-arc-status-size"></div>
					</div>
					<div class='dup-scan-info  dup-info-box'>
						<b><?php _e('Size', 'duplicator');?>:</b> <span id="data-arc-size2"></span>  &nbsp; | &nbsp;
						<b><?php _e('File Count', 'duplicator');?>:</b> <span id="data-arc-files"></span>  &nbsp; | &nbsp;
						<b><?php _e('Directory Count', 'duplicator');?>:</b> <span id="data-arc-dirs"></span> 
						<small>
						<?php 
							printf(__('Total size represents all files minus any filters that have been setup.  The current thresholds that triggers a warning is %1$s for the total size.  Some budget hosts limit the amount of time a PHP/Web request process can run.  When working with larger sites this can cause timeout issues. Consider using a file filter in step 1 to shrink and filter the overall size of your package.', 'duplicator'), 
									DUP_Util::byteSize(DUPLICATOR_SCAN_SITE), 
									DUP_Util::byteSize(DUPLICATOR_SCAN_WARNFILESIZE));
																					
							if ($zip_check != null) {
                                echo '<br/><br/>';
								echo '<span style="font-weight:bold">';
                                _e('Package support up to 2GB available in Duplicator Pro.', 'duplicator');
                                echo '</span>';
								echo "&nbsp;<i><a href='https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_size_warn&utm_campaign=duplicator_pro' target='_blank'>[" . __('details', 'duplicator') . "]</a></i>";
							}

						?>
						</small>
					</div>
				</div>		

				<!-- ============
				FILE NAME LENGTHS -->
				<div>
					<div class='dup-scan-title'>
						<a><?php _e('Name Checks', 'duplicator');?></a> <div id="data-arc-status-names"></div>
					</div>
					<div class='dup-scan-info dup-info-box'>
						<small>
						<?php 
							_e('File or directory names may cause issues when working across different environments and servers.  Names that are over 250 characters, contain special characters (such as * ? > < : / \ |) or are unicode might cause issues in a remote enviroment.  It is recommended to remove or filter these files before building the archive if you have issues at install time.', 'duplicator');
						?>
						</small><br/>
						<a href="javascript:void(0)" onclick="jQuery('#data-arc-names-data').toggle()">[<?php _e('Show Paths', 'duplicator');?>]</a>							
						<div id="data-arc-names-data"></div>
					</div>
				</div>		

				<!-- ============
				LARGE FILES -->
				<div>
					<div class='dup-scan-title'>
						<a><?php _e('Large Files', 'duplicator');?></a> <div id="data-arc-status-big"></div>
					</div>
					<div class='dup-scan-info  dup-info-box'>
						<small>
						<?php 
							printf(__('Large files such as movies or other backuped data can cause issues with timeouts.  The current check for large files is %1$s per file.  If your having issues creating a package consider excluding these files with the files filter and manually moving them to your new location.', 'duplicator'), 
									DUP_Util::byteSize(DUPLICATOR_SCAN_WARNFILESIZE));
						?>
						</small><br/>
						<a href="javascript:void(0)" onclick="jQuery('#data-arc-big-data').toggle()">[<?php _e('Show Paths', 'duplicator');?>]</a>
						<div id="data-arc-big-data"></div>
					</div>
				</div>	
				
				<!-- ============
				VIEW FILTERS -->
				<?php if ($Package->Archive->FilterOn) : ?>
					<div>
						<div class='dup-scan-title'>
							<a style='font-weight: normal'><?php _e('Archive Details', 'duplicator');?></a> 
						</div>
						<div class='dup-scan-info  dup-info-box'>	
							<b>[<?php _e('Root Directory', 'duplicator');?>]</b><br/>
							<?php echo DUPLICATOR_WPROOTPATH;?>
							<br/><br/>
							
							<b>[<?php _e('Excluded Directories', 'duplicator');?>]</b><br/>
							<?php
								if (strlen( $Package->Archive->FilterDirs)) {
									echo str_replace(";", "<br/>", $Package->Archive->FilterDirs); 
								} else {
									_e('No directory filters have been set.', 'duplicator');
								}
							?>
							<br/>
							
							<b>[<?php _e('Excluded File Extensions', 'duplicator');?>]</b><br/>
							<?php
								if (strlen( $Package->Archive->FilterExts)) {
									echo $Package->Archive->FilterExts; 
								} else {
									_e('No file extension filters have been set.', 'duplicator');
								}
							?>	
							<small>
								<?php 
									_e('The root directory is where Duplicator starts archiving files.  The excluded sections will be skipped during the archive process.  ', 'duplicator'); 
									_e('All results are stored in a json file. ', 'duplicator');
								?>
								<a href="<?php echo DUPLICATOR_SITE_URL ?>/wp-admin/admin-ajax.php?action=duplicator_package_scan" target="dup_report"><?php _e('[view json report]', 'duplicator');?></a>														
							</small><br/>
						</div>
					</div>	
				<?php endif;  ?>	
			</div><!-- end .dup-panel -->

			<!-- ================================================================
			DATABASE
			================================================================ -->
			<div class="dup-panel-title">
				<i class="fa fa-table"></i>
				<?php _e("Database", 'duplicator');	?>
				<i class="fa fa-question-circle data-size-help"
					data-tooltip-title="<?php _e("Database Size:", 'duplicator'); ?>" 
					data-tooltip="<?php _e('The database size represents only the included tables. The process for gathering the size uses the query SHOW TABLE STATUS.  The overall size of the database file can impact the final size of the package.', 'duplicator'); ?>"></i>				
				<div id="data-db-size1"></div>
				<div class="dup-scan-filter-status">
					<?php 
						if ($Package->Database->FilterOn) {
							echo '<i class="fa fa-filter"></i> '; _e('Enabled', 'duplicator');
						} 
					?> 
				</div>
			</div>
			<div class="dup-panel-panel" id="dup-scan-db">

				<!-- ============
				TOTAL SIZE -->
				<div>
					<div class='dup-scan-title'>
						<a><?php _e('Total Size', 'duplicator');?></a>
						<div id="data-db-status-size"></div>
					</div>
					<div class='dup-scan-info  dup-info-box'>
						<b><?php _e('Size', 'duplicator');?>:</b> <span id="data-db-size2"></span> &nbsp; | &nbsp;
						<b><?php _e('Tables', 'duplicator');?>:</b> <span id="data-db-tablecount"></span> &nbsp; | &nbsp;
						<b><?php _e('Records', 'duplicator');?>:</b> <span id="data-db-rows"></span> 
						 <br/><br/>
						<?php 
							//OVERVIEW
							echo '<b>' . __('Overview:', 'duplicator') . '</b><br/>';
							printf(__('Total size and row count for all database tables are approximate values.  The thresholds that trigger warnings are %1$s OR %2$s records total for the entire database.  The larger the databases the more time it takes to process and execute.  This can cause issues with budget hosts that have cpu/memory limits, and timeout constraints.', 'duplicator'), 
									DUP_Util::byteSize(DUPLICATOR_SCAN_DB_ALL_SIZE), 
									number_format(DUPLICATOR_SCAN_DB_ALL_ROWS));
							
							//OPTIONS
							echo '<br/><br/>';
							echo '<b>' . __('Options:', 'duplicator') . '</b><br/>';
							$lnk = '<a href="maint/repair.php" target="_blank">' . __('Repair and Optimization', 'duplicator') . '</a>';
							printf(__('1. Running a %1$s on your database will help to improve the overall size, performance and efficiency of the database.', 'duplicator'), $lnk);
							echo '<br/><br/>';
							$lnk = '<a href="?page=duplicator-settings" target="_blank">' . __('Duplicator Settings', 'duplicator') . '</a>';
							printf(__('2. If your server supports shell_exec and mysqldump it is recommended to enable this option from the %1$s menu.', 'duplicator'), $lnk);
							echo '<br/><br/>';
							_e('3. Consider removing data from tables that store logging, statistical  or other non-critical information about your site.', 'duplicator');
						?>
					</div>
				</div>

				<!-- ============
				TABLE DETAILS -->
				<div>
					<div class='dup-scan-title'>
						<a><?php _e('Table Details', 'duplicator');?></a>
						<div id="data-db-status-details"></div>
					</div>
					<div class='dup-scan-info dup-info-box'>

						<?php 
							//OVERVIEW
							echo '<b>' . __('Overview:', 'duplicator') . '</b><br/>';
							printf(__('The thresholds that trigger warnings for individual tables are %1$s OR %2$s records OR tables names with upper-case characters.  The larger the table the more time it takes to process and execute.  This can cause issues with budget hosts that have cpu/memory limits, and timeout constraints.', 'duplicator'), 
									DUP_Util::byteSize(DUPLICATOR_SCAN_DB_TBL_SIZE), 
									number_format(DUPLICATOR_SCAN_DB_TBL_ROWS));
							
							//OPTIONS
							echo '<br/><br/>';
							echo '<b>' . __('Options:', 'duplicator') . '</b><br/>';
							$lnk = '<a href="maint/repair.php" target="_blank">' . __('Repair and Optimization', 'duplicator') . '</a>';
							printf(__('1. Run a %1$s on the table to improve the overall size and performance.', 'duplicator'), $lnk);
							echo '<br/><br/>';
							_e('2. Remove stale date from tables such as logging, statistical or other non-critical data.', 'duplicator');
							echo '<br/><br/>';
							$lnk = '<a href="http://dev.mysql.com/doc/refman/5.7/en/server-system-variables.html#sysvar_lower_case_table_names" target="_blank">' . __('lower_case_table_names', 'duplicator') . '</a>';
							printf(__('3. For table name case sensitivity issues either rename the table with lower case characters or be prepared to work with the %1$s system variable setting.', 'duplicator'), $lnk);
							echo '<br/><br/>';
							
							echo '<b>' . __('Tables:', 'duplicator') . '</b><br/>';
						?>
						
						
						
						<div id="dup-scan-db-info">
							<div id="data-db-tablelist"></div>
						</div>
					</div>
				</div>

				<table id="dup-scan-db-details">
					<tr><td><b><?php _e('Name:', 'duplicator');?></b></td><td><?php echo DB_NAME ;?> </td></tr>
					<tr><td><b><?php _e('Host:', 'duplicator');?></b></td><td><?php echo DB_HOST ;?> </td></tr>
					<tr>
						<td style="vertical-align: top"><b><?php _e('Build Mode:', 'duplicator');?></b></td>
						<td style="line-height:18px">
							<a href="?page=duplicator-settings" target="_blank"><?php echo $dbbuild_mode ;?></a>
							<?php if ($mysqlcompat_on) :?>
								<br/>
								<small style="font-style:italic; color:maroon">
									<i class="fa fa-exclamation-circle"></i> <?php _e('MySQL Compatibility Mode Enabled', 'duplicator'); ?>
									<a href="https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_compatible" target="_blank">[<?php _e('details', 'duplicator'); ?>]</a>
								</small>										
							<?php endif;?>
						</td>
					</tr>
				</table>	

			</div><!-- end .dup-panel -->
		</div><!-- end .dup-panel-panel -->
		
		<!-- WARNING CONTINUE -->
		<div id="dup-scan-warning-continue">
			<div class="msg1">
				
				<label for="dup-scan-warning-continue-checkbox">
					<?php _e('A warning status was detected, are you sure you want to continue?', 'duplicator');?>
				</label>
				<div style="padding:8px 0">
					<input type="checkbox" id="dup-scan-warning-continue-checkbox" onclick="Duplicator.Pack.WarningContinue(this)"/>
					<label for="dup-scan-warning-continue-checkbox"><?php _e('Yes.  Continue with the build process!', 'duplicator');?></label>
				</div>
			</div>
			<div class="msg2">
				<label for="dup-scan-warning-continue-checkbox">
					<?php 
						_e("Scan checks are not required to pass, however they could cause issues on some systems.", 'duplicator'); 
						echo '<br/>';
						_e("Please review the details for each warning by clicking on the detail link.", 'duplicator'); 
					?>
				</label>
			</div>
		</div>
		
	</div>
	
	

	<!--  ERROR MESSAGE -->
	<div id="dup-msg-error" style="display:none">
		<div class="dup-hdr-error"><i class="fa fa-exclamation-circle"></i> <?php _e('Scan Error', 'duplicator'); ?></div>
		<i><?php _e('Please try again!', 'duplicator'); ?></i><br/>
		<div class="dup-hdr-error-details">
			<b><?php _e("Server Status:", 'duplicator'); ?></b> &nbsp;
			<div id="dup-msg-error-response-status" style="display:inline-block"></div><br/>

			<b><?php _e("Error Message:", 'duplicator'); ?></b>
			<div id="dup-msg-error-response-text"></div>
		</div>
	</div>			
</div> <!-- end #dup-progress-area -->

<div class="dup-button-footer" style="display:none">
	<input type="button" value="&#9664; <?php _e("Back", 'duplicator') ?>" onclick="window.location.assign('?page=duplicator&tab=new1')" class="button button-large" />
	<input type="button" value="<?php _e("Rescan", 'duplicator') ?>" onclick="Duplicator.Pack.Rescan()" class="button button-large" />
	<input type="submit" value="<?php _e("Build", 'duplicator') ?> &#9654" class="button button-primary button-large" id="dup-build-button" />
</div>
</form>

<script>
jQuery(document).ready(function($) {
		
	/*	Performs Ajax post to create check system  */
	Duplicator.Pack.Scan = function() 
	{
		var data = {action : 'duplicator_package_scan'}
		$.ajax({
			type: "POST",
			url: ajaxurl,
			dataType: "json",
			timeout: 10000000,
			data: data,
			complete: function() {$('.dup-button-footer').show()},
			success:    function(data) { 
				Duplicator.Pack.LoadScanData(data)
				//Imacros testing required
				$('#dup-automation-imacros').html('<input type="hidden" id="dup-finished" value="done" />');
			},
			error: function(data) { 
				$('#dup-progress-bar-area').hide(); 
				var status = data.status + ' -' + data.statusText;
				$('#dup-msg-error-response-status').html(status)
				$('#dup-msg-error-response-text').html(data.responseText);
				$('#dup-msg-error').show(200);
				console.log(data);
			}
		});
	}
	
	Duplicator.Pack.Rescan = function() 
	{
		$('#dup-msg-success,#dup-msg-error,.dup-button-footer').hide();
		$('#dup-progress-bar-area').show(); 
		Duplicator.Pack.Scan();
	}
	
	Duplicator.Pack.WarningContinue = function(checkbox) 
	{
		($(checkbox).is(':checked')) 
			?	$('#dup-build-button').prop('disabled',false).addClass('button-primary')
			:	$('#dup-build-button').prop('disabled',true).removeClass('button-primary');
	}
	
	Duplicator.Pack.LoadScanStatus = function(status) 
	{
		var result;
		switch (status) {
			case false :    result = '<div class="dup-scan-warn"><i class="fa fa-exclamation-triangle"></i></div>';      break;
			case 'Warn' :   result = '<div class="dup-scan-warn"><i class="fa fa-exclamation-triangle"></i> Warn</div>'; break;
			case true :     result = '<div class="dup-scan-good"><i class="fa fa-check"></i></div>';	                 break;
			case 'Good' :   result = '<div class="dup-scan-good"><i class="fa fa-check"></i> Good</div>';                break;
			default :
				result = 'unable to read';
		}
		return result;
	}
	
	/*	Load Scan Data   */
	Duplicator.Pack.LoadScanData = function(data) 
	{
		var errMsg = "unable to read";
		$('#dup-progress-bar-area').hide(); 
		
		//****************
		//ERROR: Data object is corrupt or empty return error
		if (data == undefined || data.RPT == undefined)
		{
			var html_msg;
			html_msg  = '<?php _e("Unable to perform a full scan, please try the following actions:", 'duplicator') ?><br/><br/>';
			html_msg += '<?php _e("1. Go back and create a root path directory filter to validate the site is scan-able.", 'duplicator') ?><br/>';
			html_msg += '<?php _e("2. Continue to add/remove filters to isolate which path is causing issues.", 'duplicator') ?><br/>';
			html_msg += '<?php _e("3. This message will go away once the correct filters are applied.", 'duplicator') ?><br/><br/>';
			
			html_msg += '<?php _e("Common Issues:", 'duplicator') ?><ul>';
			html_msg += '<li><?php _e("- On some budget hosts scanning over 30k files can lead to timeout/gateway issues. Consider scanning only your main WordPress site and avoid trying to backup other external directories.", 'duplicator') ?></li>';
			html_msg += '<li><?php _e("- Symbolic link recursion can cause timeouts.  Ask your server admin if any are present in the scan path.  If they are add the full path as a filter and try running the scan again.", 'duplicator') ?></li>';
			html_msg += '</ul>';
			$('#dup-msg-error-response-status').html('Scan Path Error [<?php echo rtrim(DUPLICATOR_WPROOTPATH, '/'); ?>]');
			$('#dup-msg-error-response-text').html(html_msg);	
			$('#dup-msg-error').show(200);
			console.log('JSON Report Data:');
			console.log(data);
			return;
		}
		
		//****************
		//REPORT
		var base = $('#data-rpt-scanfile').attr('href');
		$('#data-rpt-scanfile').attr('href',  base + '&scanfile=' + data.RPT.ScanFile);
		$('#data-rpt-scantime').text(data.RPT.ScanTime || 0);
		
		//****************
		//SERVER
		$('#data-srv-web-model').html(Duplicator.Pack.LoadScanStatus(data.SRV.WEB.model));
		$('#data-srv-web-all').html(Duplicator.Pack.LoadScanStatus(data.SRV.WEB.ALL));

		$('#data-srv-php-openbase').html(Duplicator.Pack.LoadScanStatus(data.SRV.PHP.openbase));
		$('#data-srv-php-maxtime').html(Duplicator.Pack.LoadScanStatus(data.SRV.PHP.maxtime));
		$('#data-srv-php-mysqli').html(Duplicator.Pack.LoadScanStatus(data.SRV.PHP.mysqli));
		$('#data-srv-php-version').html(Duplicator.Pack.LoadScanStatus(data.SRV.PHP.version));
		$('#data-srv-php-openssl').html(Duplicator.Pack.LoadScanStatus(data.SRV.PHP.openssl));
		$('#data-srv-php-all').html(Duplicator.Pack.LoadScanStatus(data.SRV.PHP.ALL));

		$('#data-srv-wp-version').html(Duplicator.Pack.LoadScanStatus(data.SRV.WP.version));
		$('#data-srv-wp-core').html(Duplicator.Pack.LoadScanStatus(data.SRV.WP.core));
		$('#data-srv-wp-cache').html(Duplicator.Pack.LoadScanStatus(data.SRV.WP.cache));
		$('#data-srv-wp-all').html(Duplicator.Pack.LoadScanStatus(data.SRV.WP.ALL));
		
		//****************
		//DATABASE
		var html = "";
		var DB_TotalSize = 'Good';
		var DB_TableDetails = 'Good';
		var DB_TableRowMax  = <?php echo DUPLICATOR_SCAN_DB_TBL_ROWS; ?>;
		var DB_TableSizeMax = <?php echo DUPLICATOR_SCAN_DB_TBL_SIZE; ?>;
		if (data.DB.Status.Success) 
		{
			DB_TotalSize = data.DB.Status.DB_Rows == 'Warn' || data.DB.Status.DB_Size == 'Warn' ? 'Warn' : 'Good';
			DB_TableDetails = data.DB.Status.TBL_Rows == 'Warn' || data.DB.Status.TBL_Size == 'Warn' || data.DB.Status.TBL_Case == 'Warn' ? 'Warn' : 'Good';
			
			$('#data-db-status-size').html(Duplicator.Pack.LoadScanStatus(DB_TotalSize));
			$('#data-db-status-details').html(Duplicator.Pack.LoadScanStatus(DB_TableDetails));
			$('#data-db-size1').text(data.DB.Size || errMsg);
			$('#data-db-size2').text(data.DB.Size || errMsg);
			$('#data-db-rows').text(data.DB.Rows || errMsg);
			$('#data-db-tablecount').text(data.DB.TableCount || errMsg);
			//Table Details
			if (data.DB.TableList == undefined || data.DB.TableList.length == 0) {
				html = '<?php _e("Unable to report on any tables", 'duplicator') ?>';
			} else {
				$.each(data.DB.TableList, function(i) {
					html += '<b>' + i  + '</b><br/>';
					$.each(data.DB.TableList[i], function(key,val) {
						html += (key == 'Case' && val == 1) || (key == 'Rows' && val > DB_TableRowMax) || (key == 'Size' && parseInt(val) > DB_TableSizeMax)
								? '<div style="color:red"><span>' + key  + ':</span>' + val + '</div>'
								: '<div><span>' + key  + ':</span>' + val + '</div>'; 
					});
				});					
			}
			$('#data-db-tablelist').append(html);
		} else {
			html = '<?php _e("Unable to report on database stats", 'duplicator') ?>';
			$('#dup-scan-db').html(html);
		}
		
		//****************
		//ARCHIVE
		$('#data-arc-status-size').html(Duplicator.Pack.LoadScanStatus(data.ARC.Status.Size));
		$('#data-arc-status-names').html(Duplicator.Pack.LoadScanStatus(data.ARC.Status.Names));
		$('#data-arc-status-big').html(Duplicator.Pack.LoadScanStatus(data.ARC.Status.Big));
		$('#data-arc-size1').text(data.ARC.Size || errMsg);
		$('#data-arc-size2').text(data.ARC.Size || errMsg);
		$('#data-arc-files').text(data.ARC.FileCount || errMsg);
		$('#data-arc-dirs').text(data.ARC.DirCount || errMsg);
		
		//Name Checks
		html = '';
		//Dirs
		if (data.ARC.FilterInfo.Dirs.Warning !== undefined && data.ARC.FilterInfo.Dirs.Warning.length > 0) {
			$.each(data.ARC.FilterInfo.Dirs.Warning, function (key, val) {
				html += '<?php _e("DIR", 'duplicator') ?> ' + key + ':<br/>[' + val + ']<br/>';
			});
		}
		//Files
		if (data.ARC.FilterInfo.Files.Warning !== undefined && data.ARC.FilterInfo.Files.Warning.length > 0) {
			$.each(data.ARC.FilterInfo.Files.Warning, function (key, val) {
				html += '<?php _e("FILE", 'duplicator') ?> ' + key + ':<br/>[' + val + ']<br/>';
			});
		}
		html = (html.length == 0) ? '<?php _e("No name warning issues found.", 'duplicator') ?>' : html;


		$('#data-arc-names-data').html(html);

		//Large Files
		html = '<?php _e("No large files found.", 'duplicator') ?>';
		if (data.ARC.FilterInfo.Files.Size !== undefined && data.ARC.FilterInfo.Files.Size.length > 0) {
			html = '';
			$.each(data.ARC.FilterInfo.Files.Size, function (key, val) {
				html += '<?php _e("FILE", 'duplicator') ?> ' + key + ':<br/>' + val + '<br/>';
			});
		}
		$('#data-arc-big-data').html(html);
		$('#dup-msg-success').show();
		
		//Waring Check
		var warnCount = data.RPT.Warnings || 0;
		if (warnCount > 0) {
			$('#dup-scan-warning-continue').show();
			$('#dup-build-button').prop("disabled",true).removeClass('button-primary');
		} else {
			$('#dup-scan-warning-continue').hide();
			$('#dup-build-button').prop("disabled",false).addClass('button-primary');
		}
		
	}
	
	//Page Init:
	Duplicator.UI.AnimateProgressBar('dup-progress-bar');
	Duplicator.Pack.Scan();
	
	//Init: Toogle for system requirment detial links
	$('.dup-scan-title a').each(function() 
	{
		$(this).attr('href', 'javascript:void(0)');
		$(this).click({selector : '.dup-scan-info'}, Duplicator.Pack.ToggleSystemDetails);
		$(this).prepend("<span class='ui-icon ui-icon-triangle-1-e dup-toggle' />");
	});
	
});
</script>