<style>
    /* -----------------------------
    PACKAGE OPTS*/
    form#dup-form-opts label {line-height:22px}
    form#dup-form-opts input[type=checkbox] {margin-top:3px}
    form#dup-form-opts fieldset {border-radius:4px;  border-top:1px solid #dfdfdf;  line-height:20px}
    form#dup-form-opts fieldset{padding:10px 15px 15px 15px; min-height:275px; margin:0 10px 10px 10px}
    form#dup-form-opts textarea, input[type="text"] {width:100%}
    form#dup-form-opts textarea#filter-dirs {height:85px}
    form#dup-form-opts textarea#filter-exts {height:27px}
    textarea#package_notes {height:37px;}
	div.dup-notes-add {float:right; margin:-4px 2px 4px 0;}
    div#dup-notes-area {display:none}

    /*ARCHIVE SECTION*/
    form#dup-form-opts div.tabs-panel{max-height:550px; padding:10px; min-height:280px}
    form#dup-form-opts ul li.tabs{font-weight:bold}
    ul.category-tabs li {padding:4px 15px 4px 15px}
    select#archive-format {min-width:100px; margin:1px 0 4px 0}
    span#dup-archive-filter-file {color:#A62426; display:none}
    span#dup-archive-filter-db {color:#A62426; display:none}
    div#dup-file-filter-items, div#dup-db-filter-items {padding:5px 0;}
	div#dup-db-filter-items {font-stretch:ultra-condensed; font-family:Calibri; }
    div.dup-quick-links {font-size:11px; float:right; display:inline-block; margin-top:2px; font-style:italic}
    div.dup-tabs-opts-help {font-style:italic; font-size:11px; margin:10px 0 0 10px; color:#777}
    table#dup-dbtables td {padding:1px 15px 1px 4px}
	table.dbmysql-compatibility td{padding:2px 20px 2px 2px}
	div.dup-store-pro {font-size:12px; font-style:italic;}
	div.dup-store-pro img {height:14px; width:14px; vertical-align:text-top}
	div.dup-store-pro a {text-decoration:underline}
	span.dup-pro-text {font-style:italic; font-size:12px; color:#555; font-style:italic }
	div#dup-exportdb-items-checked, div#dup-exportdb-items-off {min-height:275px; display:none}
	div#dup-exportdb-items-checked {padding: 5px; max-width:650px}

    /*INSTALLER SECTION*/
    div.dup-installer-header-1 {font-weight:bold; padding-bottom:2px; width:100%}
    div.dup-installer-header-2 {font-weight:bold; border-bottom:1px solid #dfdfdf; padding-bottom:2px; width:100%}
    label.chk-labels {display:inline-block; margin-top:1px}
    table.dup-installer-tbl {width:97%; margin-left:20px}
	div.dup-installer-panel-optional {text-align: center; font-style: italic; font-size: 12px; color:maroon}
	
	/*TABS*/
	ul.add-menu-item-tabs li, ul.category-tabs li {padding:3px 30px 5px}
</style>

<form id="dup-form-opts" method="post" action="?page=duplicator&tab=new2" data-validate="parsley">
<input type="hidden" id="dup-form-opts-action" name="action" value="">
<div>
	<label for="package-name"><b><?php _e('Name', 'duplicator') ?>:</b> </label>
		<div class="dup-notes-add">
		<button class="button button-small" type="button" onclick="jQuery('#dup-notes-area').toggle()" title="<?php _e('Notes', 'duplicator') ?>"><i class="fa fa-pencil-square-o"></i> </button>
	</div>
	<a href="javascript:void(0)" onclick="Duplicator.Pack.ResetName()" title="<?php _e('Create a new default name', 'duplicator') ?>"><i class="fa fa-undo"></i></a> <br/>
	<input id="package-name"  name="package-name" type="text" value="<?php echo $Package->Name ?>" maxlength="40"  data-required="true" data-regexp="^[0-9A-Za-z|_]+$" /> <br/>
	<div id="dup-notes-area">
		<label><b><?php _e('Notes', 'duplicator') ?>:</b></label> <br/>
		<textarea id="package-notes" name="package-notes" maxlength="300" /><?php echo $Package->Notes ?></textarea>
	</div>
</div>
<br/>

<!-- ===================
STORAGE -->
<div class="dup-box">
	<div class="dup-box-title">
		<i class="fa fa-database"></i>&nbsp;<?php  _e("Storage", 'duplicator'); ?> 
		<div class="dup-box-arrow"></div>
	</div>			
	<div class="dup-box-panel" id="dup-pack-storage-panel" style="<?php echo $ui_css_storage ?>">
	<table class="widefat package-tbl">
		<thead>
			<tr>
				<th style='width:275px'><?php _e("Name", 'duplicator'); ?></th>
				<th style='width:100px'><?php _e("Type", 'duplicator'); ?></th>
				<th style="white-space:nowrap"><?php _e("Location", 'duplicator'); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr class="package-row">
				<td><i class="fa fa-server"></i>&nbsp;<?php  _e('Default', 'duplicator');?></td>
				<td><?php _e("Local", 'duplicator'); ?></td>
				<td><?php echo DUPLICATOR_SSDIR_PATH; ?></td>				
			</tr>
			<tr>
				<td colspan="4" style="padding:0 0 7px 7px">
					<div class="dup-store-pro"> 
						<span class="dup-pro-text">
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/amazon-64.png" /> 
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/dropbox-64.png" /> 
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/google_drive_64px.png" /> 
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/ftp-64.png" /> 
							<?php echo sprintf(__('%1$s, %2$s, %3$s, %4$s and other storage options available in', 'duplicator'), 'Amazon', 'Dropbox', 'Google Drive', 'FTP'); ?>
							<a href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_storage&utm_campaign=duplicator_pro" target="_blank"><?php _e('Professional', 'duplicator');?></a> 
							<i class="fa fa-lightbulb-o" 
								data-tooltip-title="<?php _e("Additional Storage:", 'duplicator'); ?>" 
								data-tooltip="<?php _e('Professional allows you to create a package and then store it at a custom location on this server or to a cloud '
										. 'based location such as Google Drive, Amazon, Dropbox or FTP.', 'duplicator'); ?>">
							 </i>
						</span>
					</div>                            
				</td>
			</tr>
		</tbody>
	</table>
	</div>
</div><br/>


<!-- ============================
ARCHIVE -->
<div class="dup-box">
    <div class="dup-box-title">
        <i class="fa fa-file-archive-o"></i> <?php _e('Archive', 'duplicator') ?> &nbsp;
        <span style="font-size:13px">
            <span id="dup-archive-filter-file" title="<?php _e('File filter enabled', 'duplicator') ?>"><i class="fa fa-files-o"></i> <i class="fa fa-filter"></i> &nbsp;&nbsp;</span> 
            <span id="dup-archive-filter-db" title="<?php _e('Database filter enabled', 'duplicator') ?>"><i class="fa fa-table"></i> <i class="fa fa-filter"></i></span>	
        </span>
        <div class="dup-box-arrow"></div>
    </div>		
    <div class="dup-box-panel" id="dup-pack-archive-panel" style="<?php echo $ui_css_archive ?>">
        <input type="hidden" name="archive-format" value="ZIP" />

        <!-- NESTED TABS -->
        <div data-dup-tabs='true'>
            <ul>
                <li><?php _e('Files', 'duplicator') ?></li>
                <li><?php _e('Database', 'duplicator') ?></li>
            </ul>

            <!-- TAB1:PACKAGE -->
            <div>
                <!-- FILTERS -->
                <?php
					$uploads = wp_upload_dir();
					$upload_dir = DUP_Util::safePath($uploads['basedir']);
                ?>
           
				<input type="checkbox"  id="export-onlydb" name="export-onlydb"  onclick="Duplicator.Pack.ExportOnlyDB()" <?php echo ($Package->Archive->ExportOnlyDB) ? "checked='checked'" :""; ?> />
				<label for="export-onlydb"><?php _e('Archive Only the Database', 'duplicator') ?></label>

				<div id="dup-exportdb-items-off" style="<?php echo ($Package->Archive->ExportOnlyDB) ? 'none' : 'block'; ?>">
                    <input type="checkbox" id="filter-on" name="filter-on" onclick="Duplicator.Pack.ToggleFileFilters()" <?php echo ($Package->Archive->FilterOn) ? "checked='checked'" :""; ?> />	
                    <label for="filter-on" id="filter-on-label"><?php _e("Enable File Filters", 'duplicator') ?></label>
					<i class="fa fa-question-circle" 
					   data-tooltip-title="<?php _e("File Filters:", 'duplicator'); ?>" 
					   data-tooltip="<?php _e('File filters allow you to ignore directories and file extensions.  When creating a package only include the data you '
					   . 'want and need.  This helps to improve the overall archive build time and keep your backups simple and clean.', 'duplicator'); ?>">
					</i>

					<div id="dup-file-filter-items">
						<label for="filter-dirs" title="<?php _e("Separate all filters by semicolon", 'duplicator'); ?>"><?php _e("Directories", 'duplicator') ?>:</label>
						<div class='dup-quick-links'>
							<a href="javascript:void(0)" onclick="Duplicator.Pack.AddExcludePath('<?php echo rtrim(DUPLICATOR_WPROOTPATH, '/'); ?>')">[<?php _e("root path", 'duplicator') ?>]</a>
							<a href="javascript:void(0)" onclick="Duplicator.Pack.AddExcludePath('<?php echo rtrim($upload_dir, '/'); ?>')">[<?php _e("wp-uploads", 'duplicator') ?>]</a>
							<a href="javascript:void(0)" onclick="Duplicator.Pack.AddExcludePath('<?php echo DUP_Util::safePath(WP_CONTENT_DIR); ?>/cache')">[<?php _e("cache", 'duplicator') ?>]</a>
							<a href="javascript:void(0)" onclick="jQuery('#filter-dirs').val('')"><?php _e("(clear)", 'duplicator') ?></a>
						</div>
						<textarea name="filter-dirs" id="filter-dirs" placeholder="/full_path/exclude_path1;/full_path/exclude_path2;"><?php echo str_replace(";", ";\n", esc_textarea($Package->Archive->FilterDirs)) ?></textarea><br/>
						<label class="no-select" title="<?php _e("Separate all filters by semicolon", 'duplicator'); ?>"><?php _e("File extensions", 'duplicator') ?>:</label>
						<div class='dup-quick-links'>
							<a href="javascript:void(0)" onclick="Duplicator.Pack.AddExcludeExts('avi;mov;mp4;mpeg;mpg;swf;wmv;aac;m3u;mp3;mpa;wav;wma')">[<?php _e("media", 'duplicator') ?>]</a>
							<a href="javascript:void(0)" onclick="Duplicator.Pack.AddExcludeExts('zip;rar;tar;gz;bz2;7z')">[<?php _e("archive", 'duplicator') ?>]</a>
							<a href="javascript:void(0)" onclick="jQuery('#filter-exts').val('')"><?php _e("(clear)", 'duplicator') ?></a>
						</div>
						<textarea name="filter-exts" id="filter-exts" placeholder="ext1;ext2;ext3;"><?php echo esc_textarea($Package->Archive->FilterExts); ?></textarea>

						<div class="dup-tabs-opts-help">
							<?php _e("The directory paths and extensions above will be be excluded from the archive file if enabled is checked.", 'duplicator'); ?> <br/>
							<?php _e("Use the full path for directories and semicolons to separate all items.", 'duplicator'); ?>
						</div>
						<br/>
						<span class="dup-pro-text">
							<?php echo sprintf(__('%1$s are available in', 'duplicator'), 'Individual file filters'); ?>
							<a href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_file_filters&utm_campaign=duplicator_pro" target="_blank"><?php _e('Professional', 'duplicator');?></a>
							<i class="fa fa-lightbulb-o"
								data-tooltip-title="<?php _e("File Filters:", 'duplicator'); ?>"
								data-tooltip="<?php _e('File filters allows you to select individual files and add them to an exclusion list that will filter them from the package.', 'duplicator'); ?>">
							 </i>
						</span>
					</div>
				</div>

				<div id="dup-exportdb-items-checked"  style="<?php echo ($Package->Archive->ExportOnlyDB) ? 'block' : 'none'; ?>">
					<?php 
						_e("<b>Overview:</b><br/> This advanced option excludes all files from the archive.  Only the database and a copy of the installer.php "
						. "will be included in the archive.zip file. The option can be used for backing up and moving only the database. <i>Please note that this option is currently in *Beta*.</i>", 'duplicator');
						
						echo '<br/><br/>';

						_e("<b><i class='fa fa-exclamation-circle'></i> Notice:</b><br/>  Installing only the database over an existing site may have unintended consequences.  "
						 . "Be sure to know the state of your system before installing the database without the associated files. ", 'duplicator');

						echo '<br/><br/>';

						_e("For example, if you have WordPress 4.6 on this site and you copy this sites database to a host that has WordPress 4.8 files then the source code of the files "
							. " will not be in sync with the database causing possible errors.", 'duplicator');
						
						echo '<br/><br/>';
						
						_e("This can also be true of plugins and themes.   When moving only the database be sure to know the database will be compatible with ALL source code files."
						. "  Please use this advanced feature with caution!", 'duplicator');
					?>
					<br/><br/>
				</div>

            </div>

            <!-- TAB2: DATABASE -->
            <div>					
				<table>
					<tr>
						<td colspan="2" style="padding:0 0 10px 0">
							<?php _e("Build Mode", 'duplicator') ?>:&nbsp; <a href="?page=duplicator-settings" target="settings"><?php echo $dbbuild_mode; ?></a>
						</td>
					</tr>
					<tr>
						<td><input type="checkbox" id="dbfilter-on" name="dbfilter-on" onclick="Duplicator.Pack.ToggleDBFilters()" <?php echo ($Package->Database->FilterOn) ? "checked='checked'" :""; ?> /></td>
						<td>
							<label for="dbfilter-on"><?php _e("Enable Table Filters", 'duplicator') ?> &nbsp;</label>
							<i class="fa fa-question-circle"
							   data-tooltip-title="<?php _e("Enable Table Filters:", 'duplicator'); ?>"
							   data-tooltip="<?php _e('Checked tables will not be added to the database script.  Excluding certain tables can possibly cause your site or plugins to not work correctly after install!', 'duplicator'); ?>">
							</i>
						</td>
					</tr>
				</table>
                <div id="dup-db-filter-items">
                    <a href="javascript:void(0)" id="dball" onclick="jQuery('#dup-dbtables .checkbox').prop('checked', true).trigger('click');">[ <?php _e('Include All', 'duplicator'); ?> ]</a> &nbsp; 
                    <a href="javascript:void(0)" id="dbnone" onclick="jQuery('#dup-dbtables .checkbox').prop('checked', false).trigger('click');">[ <?php _e('Exclude All', 'duplicator'); ?> ]</a>
                    <div style="white-space:nowrap">
					<?php
						$tables = $wpdb->get_results("SHOW FULL TABLES FROM `" . DB_NAME . "` WHERE Table_Type = 'BASE TABLE' ", ARRAY_N);
						$num_rows = count($tables);
						echo '<table id="dup-dbtables"><tr><td valign="top">';
						$next_row = round($num_rows / 3, 0);
						$counter = 0;
						$tableList = explode(',', $Package->Database->FilterTables);
						foreach ($tables as $table)
						{
							if (in_array($table[0], $tableList))
							{
								$checked = 'checked="checked"';
								$css = 'text-decoration:line-through';
							}
							else
							{
								$checked = '';
								$css = '';
							}
							echo "<label for='dbtables-{$table[0]}' style='{$css}'><input class='checkbox dbtable' $checked type='checkbox' name='dbtables[]' id='dbtables-{$table[0]}' value='{$table[0]}' onclick='Duplicator.Pack.ExcludeTable(this)' />&nbsp;{$table[0]}</label><br />";
							$counter++;
							if ($next_row <= $counter)
							{
								echo '</td><td valign="top">';
								$counter = 0;
							}
						}
						echo '</td></tr></table>';
					?>
                    </div>	
                </div>
				<br/>
				<?php _e("Compatibility Mode", 'duplicator') ?> &nbsp;
				<i class="fa fa-question-circle" 
				   data-tooltip-title="<?php _e("Compatibility Mode:", 'duplicator'); ?>" 
				   data-tooltip="<?php _e('This is an advanced database backwards compatibility feature that should ONLY be used if having problems installing packages.'
						   . ' If the database server version is lower than the version where the package was built then these options may help generate a script that is more compliant'
						   . ' with the older database server. It is recommended to try each option separately starting with mysql40.', 'duplicator'); ?>">
				</i> &nbsp;
				<small style="font-style:italic">
					<a href="https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_compatible" target="_blank">[<?php _e('details', 'duplicator'); ?>]</a>
				</small>
				<br/>
				
				<?php if ($dbbuild_mode == 'mysqldump') :?>
					<?php
						$modes = explode(',', $Package->Database->Compatible);
						$is_mysql40		= in_array('mysql40',	$modes);
						$is_no_table	= in_array('no_table_options',  $modes);
						$is_no_key		= in_array('no_key_options',	$modes);
						$is_no_field	= in_array('no_field_options',	$modes);
					?>
					<table class="dbmysql-compatibility">
						<tr>
							<td>
								<input type="checkbox" name="dbcompat[]" id="dbcompat-mysql40" value="mysql40" <?php echo $is_mysql40 ? 'checked="true"' :''; ?> > 
								<label for="dbcompat-mysql40"><?php _e("mysql40", 'duplicator') ?></label> 
							</td>
							<td>
								<input type="checkbox" name="dbcompat[]" id="dbcompat-no_table_options" value="no_table_options" <?php echo $is_no_table ? 'checked="true"' :''; ?>> 
								<label for="dbcompat-no_table_options"><?php _e("no_table_options", 'duplicator') ?></label>
							</td>
							<td>
								<input type="checkbox" name="dbcompat[]" id="dbcompat-no_key_options" value="no_key_options" <?php echo $is_no_key ? 'checked="true"' :''; ?>> 
								<label for="dbcompat-no_key_options"><?php _e("no_key_options", 'duplicator') ?></label>
							</td>
							<td>
								<input type="checkbox" name="dbcompat[]" id="dbcompat-no_field_options" value="no_field_options" <?php echo $is_no_field ? 'checked="true"' :''; ?>> 
								<label for="dbcompat-no_field_options"><?php _e("no_field_options", 'duplicator') ?></label>
							</td>
						</tr>					
					</table>
				<?php else :?>
					<i><?php _e("This option is only availbe with mysqldump mode.", 'duplicator'); ?></i>
				<?php endif; ?>

            </div>
        </div>		
    </div>
</div><br/>

<!-- ============================
INSTALLER -->
<div class="dup-box">
    <div class="dup-box-title">
        <i class="fa fa-bolt"></i> <?php _e('Installer', 'duplicator') ?>
        <div class="dup-box-arrow"></div>
    </div>			
	
    <div class="dup-box-panel" id="dup-pack-installer-panel" style="<?php echo $ui_css_installer ?>">
		
		<div class="dup-installer-panel-optional">
			<b><?php _e('All values in this section are', 'duplicator'); ?> <u><?php _e('optional', 'duplicator'); ?></u>.</b> <br/>
			<?php _e("The installer can have these fields pre-filled at install time.", 'duplicator'); ?>
            <i class="fa fa-question-circle"
					data-tooltip-title="<?php _e("MySQL Server Prefills", 'duplicator'); ?>"
					data-tooltip="<?php _e('The values in this section are NOT required! If you know ahead of time the database input fields the installer will use, then you can optionally enter them here.  Otherwise you can just enter them in at install time.', 'duplicator'); ?>">
			</i>
		</div>	
	
        <table class="dup-installer-tbl">
            <tr>
                <td colspan="2"><div class="dup-installer-header-2"><?php _e(" MySQL Server", 'duplicator') ?></div></td>
            </tr>
            <tr>
                <td style="width:130px"><?php _e("Host", 'duplicator') ?></td>
                <td><input type="text" name="dbhost" id="dbhost" value="<?php echo $Package->Installer->OptsDBHost ?>"  maxlength="200" placeholder="<?php _e('example: localhost (value is optional)', 'duplicator'); ?>"/></td>
            </tr>
			<tr>
                <td><?php _e("Host Port", 'duplicator') ?></td>
                <td><input type="text" name="dbport" id="dbport" value="<?php echo $Package->Installer->OptsDBPort ?>"  maxlength="200" placeholder="<?php _e('example: 3306 (value is optional)', 'duplicator'); ?>"/></td>
            </tr>
            <tr>
                <td><?php _e("Database", 'duplicator') ?></td>
                <td><input type="text" name="dbname" id="dbname" value="<?php echo $Package->Installer->OptsDBName ?>" maxlength="100" placeholder="<?php _e('example: DatabaseName (value is optional)', 'duplicator'); ?>" /></td>
            </tr>							
            <tr>
                <td><?php _e("User", 'duplicator') ?></td>
                <td><input type="text" name="dbuser" id="dbuser" value="<?php echo $Package->Installer->OptsDBUser ?>"  maxlength="100" placeholder="<?php _e('example: DatabaseUserName (value is optional)', 'duplicator'); ?>" /></td>
            </tr>
            <!--tr>
                <td colspan="2"><div class="dup-installer-header-2"><?php _e("Advanced Options", 'duplicator') ?></div></td>
            </tr>						
            <tr>
                <td colspan="2">
                    <table>
                        <tr>
                            <td style="width:130px"><?php _e("SSL", 'duplicator') ?></td>
                            <td style="padding-right:20px; white-space:nowrap">
                                <input type="checkbox" name="ssl-admin" id="ssl-admin" <?php echo ($Package->Installer->OptsSSLAdmin) ? "checked='checked'" :""; ?>  />
                                <label class="chk-labels" for="ssl-admin"><?php _e("Enforce on Admin", 'duplicator') ?></label>
                            </td>
                            <td>
                                <input type="checkbox" name="ssl-login" id="ssl-login" <?php echo ($Package->Installer->OptsSSLLogin) ? "checked='checked'" :""; ?>  />
                                <label class="chk-labels" for="ssl-login"><?php _e("Enforce on Logins", 'duplicator') ?></label>
                            </td>
                        </tr>
                        <tr>
                            <td><?php _e("Cache", 'duplicator') ?></td>									
                            <td style="padding-right:20px; white-space:nowrap">
                                <input type="checkbox" name="cache-wp" id="cache-wp" <?php echo ($Package->Installer->OptsCacheWP) ? "checked='checked'" :""; ?>  />
                                <label class="chk-labels" for="cache-wp"><?php _e("Keep Enabled", 'duplicator') ?></label>	
                            </td>
                            <td>
                                <input type="checkbox" name="cache-path" id="cache-path" <?php echo ($Package->Installer->OptsCachePath) ? "checked='checked'" :""; ?>  />
                                <label class="chk-labels" for="cache-path"><?php _e("Keep Home Path", 'duplicator') ?></label>			
                            </td>
                        </tr>
                    </table>
                </td>
            </tr-->
        </table><br />

        

         <input type="hidden" name="url-new" id="url-new" value=""/>

         
        <!--table class="dup-installer-tbl">
            <tr>
                <td style="width:130px"><?php _e("New URL", 'duplicator') ?></td>
                <td><input type="text" name="url-new" id="url-new" value="<?php echo $Package->Installer->OptsURLNew ?>" placeholder="http://mynewsite.com" /></td>
            </tr>
        </table-->
		
		<div style="padding:10px 0 0 12px;">
			<span class="dup-pro-text">
				<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/cpanel-48.png" style="width:16px; height:12px" />
				<?php _e("Create the database and users directly at install time with ", 'duplicator'); ?>
				<a href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_cpanel&utm_campaign=duplicator_pro" target="_blank"><?php _e('Professional', 'duplicator');?></a>
				<i class="fa fa-lightbulb-o"
					data-tooltip-title="<?php _e("cPanel Access:", 'duplicator'); ?>" 
					data-tooltip="<?php _e('If your server supports cPanel API access then you can create new databases and select existing ones with Duplicator Professional at install time.', 'duplicator'); ?>">
				</i>
			</span>
		</div>

    </div>		
</div><br/>


<div class="dup-button-footer">
    <input type="button" value="<?php _e("Reset", 'duplicator') ?>" class="button button-large" <?php echo ($dup_tests['Success']) ? '' :'disabled="disabled"'; ?> onclick="Duplicator.Pack.ConfirmReset()" />
    <input type="submit" value="<?php _e("Next", 'duplicator') ?> &#9654;" class="button button-primary button-large" <?php echo ($dup_tests['Success']) ? '' :'disabled="disabled"'; ?> />
</div>

</form>

<!-- ==========================================
THICK-BOX DIALOGS: -->
<?php	

	$confirm1 = new DUP_UI_Dialog();
	$confirm1->title			= __('Reset Package Settings?', 'duplicator');
	$confirm1->message			= __('This will clear and reset all of the current package settings.  Would you like to continue?', 'duplicator');
	$confirm1->jscallback		= 'Duplicator.Pack.ResetSettings()';
	$confirm1->initConfirm();
?>
<script>
jQuery(document).ready(function ($) 
{
	var DUP_NAMEDEFAULT = '<?php echo $default_name ?>';
	var DUP_NAMELAST = $('#package-name').val();

	Duplicator.Pack.ExportOnlyDB = function ()
	{
		$('#dup-exportdb-items-off, #dup-exportdb-items-checked').hide();
		$("#export-onlydb").is(':checked')
			? $('#dup-exportdb-items-checked').show()
			: $('#dup-exportdb-items-off').show();
	};

	/* Enable/Disable the file filter elements */
	Duplicator.Pack.ToggleFileFilters = function () 
	{
		var $filterItems = $('#dup-file-filter-items');
		if ($("#filter-on").is(':checked')) {
			$filterItems.removeAttr('disabled').css({color:'#000'});
			$('#filter-exts,#filter-dirs').removeAttr('readonly').css({color:'#000'});
			$('#dup-archive-filter-file').show();
		} else {
			$filterItems.attr('disabled', 'disabled').css({color:'#999'});
			$('#filter-dirs, #filter-exts').attr('readonly', 'readonly').css({color:'#999'});
			$('#dup-archive-filter-file').hide();
		}
	};

	/* Appends a path to the directory filter */
	Duplicator.Pack.ToggleDBFilters = function () 
	{
		var $filterItems = $('#dup-db-filter-items');
		if ($("#dbfilter-on").is(':checked')) {
			$filterItems.removeAttr('disabled').css({color:'#000'});
			$('#dup-dbtables input').removeAttr('readonly').css({color:'#000'});
			$('#dup-archive-filter-db').show();
		} else {
			$filterItems.attr('disabled', 'disabled').css({color:'#999'});
			$('#dup-dbtables input').attr('readonly', 'readonly').css({color:'#999'});
			$('#dup-archive-filter-db').hide();
		}
	};


	/* Appends a path to the directory filter  */
	Duplicator.Pack.AddExcludePath = function (path) 
	{
		var text = $("#filter-dirs").val() + path + ';\n';
		$("#filter-dirs").val(text);
	};

	/* Appends a path to the extention filter  */
	Duplicator.Pack.AddExcludeExts = function (path) 
	{
		var text = $("#filter-exts").val() + path + ';';
		$("#filter-exts").val(text);
	};
	
	Duplicator.Pack.ConfirmReset = function () 
	{
		 <?php $confirm1->showConfirm(); ?>
	}

	Duplicator.Pack.ResetSettings = function () 
	{
		var key = 'duplicator_package_active';

		jQuery('#dup-form-opts-action').val(key);
		jQuery('#dup-form-opts').attr('action', '?page=duplicator&tab=new1')
		jQuery('#dup-form-opts').submit();
	}

	Duplicator.Pack.ResetName = function () 
	{
		var current = $('#package-name').val();
		$('#package-name').val((current == DUP_NAMELAST) ? DUP_NAMEDEFAULT :DUP_NAMELAST)
	}

	Duplicator.Pack.ExcludeTable = function (check) 
	{
		var $cb = $(check);
		if ($cb.is(":checked")) {
			$cb.closest("label").css('textDecoration', 'line-through');
		} else {
			$cb.closest("label").css('textDecoration', 'none');
		}
	}
	
	//Init:Toggle OptionTabs
	Duplicator.Pack.ToggleFileFilters();
	Duplicator.Pack.ToggleDBFilters();
	Duplicator.Pack.ExportOnlyDB();
});
</script>