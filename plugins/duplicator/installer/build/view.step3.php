<?php
	$dbh = DUPX_DB::connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpass'], $_POST['dbname'], $_POST['dbport']);

	$all_tables     = DUPX_DB::getTables($dbh);
	$active_plugins = DUPX_U::getActivePlugins($dbh);

	$old_path = $GLOBALS['FW_WPROOT'];
	$new_path = DUPX_U::setSafePath($GLOBALS['CURRENT_ROOT_PATH']);
	$new_path = ((strrpos($old_path, '/') + 1) == strlen($old_path)) ? DUPX_U::addSlash($new_path) : $new_path;
?>


<!-- =========================================
VIEW: STEP 3- INPUT -->
<form id='s3-input-form' method="post" class="content-form">

	<!--  POST PARAMS -->
	<input type="hidden" name="action_ajax"	 value="3" />
	<input type="hidden" name="action_step"	 value="3" />
	<input type="hidden" name="logging"		 value="<?php echo $_POST['logging'] ?>" />
	<input type="hidden" name="archive_name" value="<?php echo $_POST['archive_name'] ?>" />
	<input type="hidden" name="json"		 value="<?php echo $_POST['json']; ?>" />
	<input type="hidden" name="dbhost"		 value="<?php echo $_POST['dbhost'] ?>" />
	<input type="hidden" name="dbport"		 value="<?php echo $_POST['dbport'] ?>" />
	<input type="hidden" name="dbuser" 		 value="<?php echo $_POST['dbuser'] ?>" />
	<input type="hidden" name="dbpass" 		 value="<?php echo htmlentities($_POST['dbpass']) ?>" />
	<input type="hidden" name="dbname" 		 value="<?php echo $_POST['dbname'] ?>" />
	<input type="hidden" name="dbcharset" 	 value="<?php echo $_POST['dbcharset'] ?>" />
	<input type="hidden" name="dbcollate" 	 value="<?php echo $_POST['dbcollate'] ?>" />

	<div class="dupx-logfile-link"><a href="installer-log.txt?now=<?php echo $GLOBALS['NOW_DATE'] ?>" target="install_log">installer-log.txt</a></div>
	<div class="hdr-main">
		Step <span class="step">3</span> of 4: Update Data
	</div>

	<!-- ====================================
    NEW SETTINGS
    ==================================== -->
	<div class="hdr-sub1" style="margin-top:8px" data-type="toggle" data-target="#s3-new-settings">
		<a href="javascript:void(0)"><i class="dupx-minus-square"></i> New Settings</a>
	</div>
	<div id='s3-new-settings'>
		<table class="s3-table-inputs">
			<tr>
				<td style="width:80px">URL:</td>
				<td>
					<input type="text" name="url_new" id="url_new" value="<?php echo $GLOBALS['FW_URL_NEW'] ?>" />
					<a href="javascript:DUPX.getNewURL('url_new')" style="font-size:12px">get</a>
				</td>
			</tr>
			<tr>
				<td>Path:</td>
				<td><input type="text" name="path_new" id="path_new" value="<?php echo $new_path ?>" /></td>
			</tr>
			<tr>
				<td>Title:</td>
				<td><input type="text" name="blogname" id="blogname" value="<?php echo $GLOBALS['FW_BLOGNAME'] ?>" /></td>
			</tr>
		</table>
	</div>
	<br/><br/>

    <!-- ====================================
    OPTIONS
    ==================================== -->
    <div class="hdr-sub1" data-type="toggle" data-target="#s3-adv-opts">
        <a href="javascript:void(0)"><i class="dupx-plus-square"></i> Options</a>
    </div>
	<div id='s3-adv-opts' style="display:none;">
		<div class="help-target"><a href="?help#help-s3" target="_blank">[help]</a></div>
		<br/>

		<div class="hdr-sub3">New Admin Account</div>
		<div style="text-align: center; margin-top:7px">
			<i style="color:gray;font-size: 11px">This feature is optional.  If the username already exists the account will NOT be created or updated.</i>
		</div>
		<table class="s3-table-inputs">
			<tr>
				<td>Username:</td>
				<td><input type="text" name="wp_username" id="wp_username" value="" title="4 characters minimum" placeholder="(4 or more characters)" /></td>
			</tr>
			<tr>
				<td valign="top">Password:</td>
				<td><input type="text" name="wp_password" id="wp_password" value="" title="6 characters minimum"  placeholder="(6 or more characters)" /></td>
			</tr>
		</table>
		<br/><br/>

		<div class="hdr-sub3">Scan Options</div>
        <table class="s3-table-inputs">
			<tr>
				<td>Site URL:</td>
				<td>
					<input type="text" name="siteurl" id="siteurl" value="" />
					<a href="javascript:DUPX.getNewURL('siteurl')" style="font-size:12px">get</a><br/>
				</td>
			</tr> 
            <tr>
                <td>Old URL:</td>
                <td>
                    <input type="text" name="url_old" id="url_old" value="<?php echo $GLOBALS['FW_URL_OLD'] ?>" readonly="readonly"  class="readonly" />
                    <a href="javascript:DUPX.editOldURL()" id="edit_url_old" style="font-size:12px">edit</a>
                </td>
            </tr>
            <tr>
                <td>Old Path:</td>
                <td>
                    <input type="text" name="path_old" id="path_old" value="<?php echo $old_path ?>" readonly="readonly"  class="readonly" />
                    <a href="javascript:DUPX.editOldPath()" id="edit_path_old" style="font-size:12px">edit</a>
                </td>
            </tr>
        </table><br/>
        
		<table>
			<tr>
				<td style="padding-right:10px">
                    <b>Scan Tables:</b>
					<div class="s3-allnonelinks">
						<a href="javascript:void(0)" onclick="$('#tables option').prop('selected',true);">[All]</a>
						<a href="javascript:void(0)" onclick="$('#tables option').prop('selected',false);">[None]</a>
					</div><br style="clear:both" />
					<select id="tables" name="tables[]" multiple="multiple" style="width:315px; height:100px">
						<?php
						foreach( $all_tables as $table ) {
							echo '<option selected="selected" value="' . DUPX_U::escapeHTML( $table ) . '">' . $table . '</option>';
						}
						?>
					</select>

				</td>
				<td valign="top">
                    <b>Activate Plugins:</b>
					<div class="s3-allnonelinks">
						<a href="javascript:void(0)" onclick="$('#plugins option').prop('selected',true);">[All]</a>
						<a href="javascript:void(0)" onclick="$('#plugins option').prop('selected',false);">[None]</a>
					</div><br style="clear:both" />
					<select id="plugins" name="plugins[]" multiple="multiple" style="width:315px; height:100px">
						<?php
						foreach ($active_plugins as $plugin) {
							echo '<option selected="selected" value="' . DUPX_U::escapeHTML( $plugin ) . '">' . dirname($plugin) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
		</table>
		<br/>

		<input type="checkbox" name="urlextended" id="urlextended" value="1" /> <label for="urlextended">Enable Extended URL Replace <small>(Searches multiple protocols: '//', 'http', 'https')</small></label><br/>
		<input type="checkbox" name="postguid" id="postguid" value="1" /> <label for="postguid">Keep Post GUID unchanged</label><br/>
		<input type="checkbox" name="fullsearch" id="fullsearch" value="1" /> <label for="fullsearch">Enable Full Search <small>(slower to process)</small> </label><br/>
		<br/><br/>
		
		<!-- WP-CONFIG -->
		<div class="hdr-sub3">WP-Config File</div>
		<table class="dupx-opts dupx-advopts">
			<tr>
				<td>Cache:</td>
				<td style="width:125px"><input type="checkbox" name="cache_wp" id="cache_wp" <?php echo ($GLOBALS['FW_CACHE_WP']) ? "checked='checked'" : ""; ?> /> <label for="cache_wp">Keep Enabled</label></td>
				<td><input type="checkbox" name="cache_path" id="cache_path" <?php echo ($GLOBALS['FW_CACHE_PATH']) ? "checked='checked'" : ""; ?> /> <label for="cache_path">Keep Home Path</label></td>
			</tr>
			<tr>
				<td>SSL:</td>
				<td><input type="checkbox" name="ssl_admin" id="ssl_admin" <?php echo ($GLOBALS['FW_SSL_ADMIN']) ? "checked='checked'" : ""; ?> /> <label for="ssl_admin">Enforce on Admin</label></td>
				<td><input type="checkbox" name="ssl_login" id="ssl_login" <?php echo ($GLOBALS['FW_SSL_LOGIN']) ? "checked='checked'" : ""; ?> /> <label for="ssl_login">Enforce on Login</label></td>
			</tr>
		</table>
		<br/><br/><br/>
		<br/><br/>

	</div>

	<div class="dupx-footer-buttons">
		<input id="dup-step3-next"  class="default-btn" type="button" value=" Next " onclick="DUPX.runUpdate()"  />
	</div>
</form>


<!-- =========================================
VIEW: STEP 3 - AJAX RESULT 
========================================= -->
<form id='s3-result-form' method="post" class="content-form" style="display:none">

	<div class="dupx-logfile-link"><a href="installer-log.txt" target="install_log">installer-log.txt</a></div>
	<div class="hdr-main">
		Step <span class="step">3</span> of 4: Update Data
	</div>

	<!--  POST PARAMS -->
	<div class="dupx-debug">
		<input type="hidden" name="action_step"  value="4" />
		<input type="hidden" name="archive_name" value="<?php echo $_POST['archive_name'] ?>" />
		<input type="hidden" name="url_new" id="ajax-url_new"  />
		<input type="hidden" name="json"    id="ajax-json" />
		<br/>
		<input type='submit' value='manual submit'>
	</div>

	<!--  PROGRESS BAR -->
	<div id="progress-area">
		<div style="width:500px; margin:auto">
			<h3>Updating Data Replacements Please Wait...</h3>
			<div id="progress-bar"></div>
			<i>This may take several minutes</i>
		</div>
	</div>

	<!--  AJAX SYSTEM ERROR -->
	<div id="ajaxerr-area" style="display:none">
		<p>Please try again an issue has occurred.</p>
		<div style="padding: 0px 10px 10px 10px;">
			<div id="ajaxerr-data">An unknown issue has occurred with the update data setup process.  Please see the installer-log.txt file for more details.</div>
			<div style="text-align:center; margin:10px auto 0px auto">
				<input type="button"  class="default-btn" onclick='DUPX.hideErrorResult2()' value="&laquo; Try Again" /><br/><br/>
				<i style='font-size:11px'>See online help for more details at <a href='https://snapcreek.com/ticket' target='_blank'>snapcreek.com</a></i>
			</div>
		</div>
	</div>
</form>

<script>
/** 
* Timeout (10000000 = 166 minutes) */
DUPX.runUpdate = function()
{
	//Validation
	var wp_username = $.trim($("#wp_username").val()).length || 0;
	var wp_password = $.trim($("#wp_password").val()).length || 0;

	if ( $.trim($("#url_new").val()) == "" )  {alert("The 'New URL' field is required!"); return false;}
	if ( $.trim($("#siteurl").val()) == "" )  {alert("The 'Site URL' field is required!"); return false;}
	if (wp_username >= 1 && wp_username < 4) {alert("The New Admin Account 'Username' must be four or more characters"); return false;}
	if (wp_username >= 4 && wp_password < 6) {alert("The New Admin Account 'Password' must be six or more characters"); return false;}

	$.ajax({
		type: "POST",
		timeout: 1800000,
		dataType: "json",
		url: window.location.href,
		data: $('#s3-input-form').serialize(),
		beforeSend: function() {
			DUPX.showProgressBar();
			$('#s3-input-form').hide();
			$('#s3-result-form').show();
		},
		success: function(data){
			if (typeof(data) != 'undefined' && data.step3.pass == 1) {
				$("#ajax-url_new").val($("#url_new").val());
				$("#ajax-json").val(escape(JSON.stringify(data)));
				<?php if (! $GLOBALS['DUPX_DEBUG']) : ?>
					setTimeout(function(){$('#s3-result-form').submit();}, 500);
				<?php endif; ?>
				$('#progress-area').fadeOut(1000);
			} else {
				DUPX.hideProgressBar();
			}
		},
		error: function(xhr) {
			var status  = "<b>Server Code:</b> "	+ xhr.status		+ "<br/>";
			status += "<b>Status:</b> "				+ xhr.statusText	+ "<br/>";
			status += "<b>Response:</b> "			+ xhr.responseText  + "";
			status += "<hr/><b>Additional Troubleshooting Tips:</b><br/>";
			status += "- Check the <a href='installer-log.txt' target='install_log'>installer-log.txt</a> file for warnings or errors.<br/>";
			status += "- Check the web server and PHP error logs. <br/>";
			status += "- For timeout issues visit the <a href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-100-q' target='_blank'>Timeout FAQ Section</a><br/>";
			$('#ajaxerr-data').html(status);
			DUPX.hideProgressBar();
		}
	});
}

/** Returns the windows active url */
DUPX.getNewURL = function(id)
{
	var filename= window.location.pathname.split('/').pop() || 'installer.php' ;
	$("#" + id).val(window.location.href.replace(filename, ''));
}

/** Allows user to edit the package url  */
DUPX.editOldURL = function()
{
	var msg = 'This is the URL that was generated when the package was created.\n';
	msg += 'Changing this value may cause issues with the install process.\n\n';
	msg += 'Only modify  this value if you know exactly what the value should be.\n';
	msg += 'See "General Settings" in the WordPress Administrator for more details.\n\n';
	msg += 'Are you sure you want to continue?';

	if (confirm(msg)) {
		$("#url_old").removeAttr('readonly');
		$("#url_old").removeClass('readonly');
		$('#edit_url_old').hide('slow');
	}
}

/** Allows user to edit the package path  */
DUPX.editOldPath = function()
{
	var msg = 'This is the SERVER URL that was generated when the package was created.\n';
	msg += 'Changing this value may cause issues with the install process.\n\n';
	msg += 'Only modify  this value if you know exactly what the value should be.\n';
	msg += 'Are you sure you want to continue?';

	if (confirm(msg)) {
		$("#path_old").removeAttr('readonly');
		$("#path_old").removeClass('readonly');
		$('#edit_path_old').hide('slow');
	}
}

/** Go back on AJAX result view */
DUPX.hideErrorResult2 = function()
{
	$('#s3-result-form').hide();
	$('#s3-input-form').show(200);
}

//DOCUMENT LOAD
$(document).ready(function()
{
	DUPX.getNewURL('url_new');
	DUPX.getNewURL('siteurl');
	$("*[data-type='toggle']").click(DUPX.toggleClick);
	$("#wp_password").passStrength({
			shortPass: 		"top_shortPass",
			badPass:		"top_badPass",
			goodPass:		"top_goodPass",
			strongPass:		"top_strongPass",
			baseStyle:		"top_testresult",
			userid:			"#wp_username",
			messageloc:		1	});
});
</script>