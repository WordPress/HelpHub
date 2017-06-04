<?php
    $_POST['logging'] = isset($_POST['logging']) ? trim($_POST['logging']) : 1;
?>


<!-- =========================================
VIEW: STEP 2- INPUT -->
<form id='s2-input-form' method="post" class="content-form"  data-parsley-validate="true" data-parsley-excluded="input[type=hidden], [disabled], :hidden">
<input type="hidden" name="action_ajax" value="2" />
<input type="hidden" name="action_step" value="2" />
<input type="hidden" name="archive_name"  value="<?php echo $GLOBALS['FW_PACKAGE_NAME'] ?>" />
<input type="hidden" name="logging" id="logging" value="<?php echo $_POST['logging'] ?>" />

    <div class="dupx-logfile-link"><a href="installer-log.txt?now=<?php echo $GLOBALS['NOW_DATE'] ?>" target="install_log">installer-log.txt</a></div>
	<div class="hdr-main">
        Step <span class="step">2</span> of 4: Install Database
	</div>

	<div class="s2-btngrp">
		<input id="s2-basic-btn" type="button" value="Basic" class="active" onclick="DUPX.togglePanels('basic')" />
		<input id="s2-cpnl-btn" type="button" value="cPanel" class="in-active" onclick="DUPX.togglePanels('cpanel')" />
	</div>


	<!-- =========================================
	BASIC PANEL -->
	<div id="s2-basic-pane">
		<div class="hdr-sub1" data-type="toggle" data-target="#s2-area-setup">
			<a href="javascript:void(0)"><i class="dupx-minus-square"></i> Setup</a>
		</div>
		<div id="s2-area-setup">
			<table class="dupx-opts">
				<tr>
					<td>Action:</td>
					<td>
						<select name="dbaction" id="dbaction">
							<option value="create">Create New Database</option>
							<option value="empty" selected="true">Connect and Remove All Data</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>Host:</td>
					<td>
						<table class="s2-opts-dbhost">
							<tr>
								<td><input type="text" name="dbhost" id="dbhost" required="true" value="<?php echo htmlspecialchars($GLOBALS['FW_DBHOST']); ?>" placeholder="localhost" style="width:450px" /></td>
								<td style="vertical-align:top">
									<input id="s2-dbport-btn" type="button" onclick="DUPX.togglePort()" class="s2-small-btn" value="Port: <?php echo htmlspecialchars($GLOBALS['FW_DBPORT']); ?>" />
									<input name="dbport" id="dbport" type="text" style="width:80px; display:none" value="<?php echo htmlspecialchars($GLOBALS['FW_DBPORT']); ?>" />
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td>Database:</td>
					<td>
						<input type="text" name="dbname" id="dbname"  required="true" value="<?php echo htmlspecialchars($GLOBALS['FW_DBNAME']); ?>"  placeholder="new or existing database name"  />
						 <div id="s2-warning-emptydb">
							 <label for="accept-warnings">Warning: The selected 'Action' above will remove <u>all data</u> from this database!</label>
						</div>
					</td>
				</tr>
				<tr>
					<td>User:</td>
					<td><input type="text" name="dbuser" id="dbuser" required="true" value="<?php echo htmlspecialchars($GLOBALS['FW_DBUSER']); ?>" placeholder="valid database username" /></td>
				</tr>
				<tr>
					<td>Password:</td>
					<td><input type="text" name="dbpass" id="dbpass" value="<?php echo htmlspecialchars($GLOBALS['FW_DBPASS']); ?>"  placeholder="valid database user password"   /></td>
				</tr>
			</table>
		</div>
	</div>


	<!-- =========================================
	C-PANEL PANEL -->
	<div id="s2-cpnl-pane">
		<div class="s2-gopro">
			<h2>cPanel Connectivity</h2>
			<div style="text-align: center">
				<a target="_blank" href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_install_step2&utm_campaign=duplicator_pro">Duplicator Pro</a>
				takes advantage of your hosts <br/>
				cPanel interface directly <b>from this installer!</b>
			</div>
			<b>Features Include:</b>
			<ul>
				<li>Fast cPanel Login</li>
				<li>Create New Databases</li>
				<li>Create New Database Users</li>
				<li>Preview and Select Existing Databases and Users</li>
			</ul>
			<small>
				Note: Most hosting providers do not allow applications to create new databases or database users directly from PHP.  However with the cPanel API these restrictions
				are removed opening up a robust interface for direct access to existing database resources.  You can take advantage of these great features and improve your work-flow with
				<a target="_blank" href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_install_step2&utm_campaign=duplicator_pro">Duplicator Pro!</a>
			</small>
		</div>
	</div>

    <!-- =========================================
    DIALOG: DB CONNECTION CHECK  -->
    <div id="s2-dbconn">
        <div id="s2-dbconn-status" style="display:none">
            <div style="padding: 0px 10px 10px 10px;">
                <div id="s2-dbconn-test-msg" style="min-height:80px"></div>
            </div>
            <small><input type="button" onclick="$('#s2-dbconn-status').hide(500)" class="s2-small-btn" value="Hide Message" /></small>
        </div>
    </div>


    <br/>

    <!-- ====================================
    OPTIONS
    ==================================== -->
    <div class="hdr-sub1" data-type="toggle" data-target="#s2-area-adv-opts">
        <a  href="javascript:void(0)"><i class="dupx-plus-square"></i> Options</a>
    </div>
    <div id='s2-area-adv-opts' style="display:none">
		<div class="help-target"><a href="?help#help-s2" target="_blank">[help]</a></div>
		
		<table class="dupx-opts dupx-advopts">
			<tr>
				<td>Spacing:</td>
				<td colspan="2">
					<input type="checkbox" name="dbnbsp" id="dbnbsp" value="1" /> <label for="dbnbsp">Fix non-breaking space characters</label>
				</td>
			</tr>
			<tr>
				<td style="vertical-align:top">Mode:</td>
				<td colspan="2">
					<input type="radio" name="dbmysqlmode" id="dbmysqlmode_1" checked="true" value="DEFAULT"/> <label for="dbmysqlmode_1">Default</label> &nbsp;
					<input type="radio" name="dbmysqlmode" id="dbmysqlmode_2" value="DISABLE"/> <label for="dbmysqlmode_2">Disable</label> &nbsp;
					<input type="radio" name="dbmysqlmode" id="dbmysqlmode_3" value="CUSTOM"/> <label for="dbmysqlmode_3">Custom</label> &nbsp;
					<div id="dbmysqlmode_3_view" style="display:none; padding:5px">
						<input type="text" name="dbmysqlmode_opts" value="" /><br/>
						<small>Separate additional <a href="?help#help-mysql-mode" target="_blank">sql modes</a> with commas &amp; no spaces.<br/>
							Example: <i>NO_ENGINE_SUBSTITUTION,NO_ZERO_IN_DATE,...</i>.</small>
					</div>
				</td>
			</tr>
			<tr><td style="width:130px">Charset:</td><td><input type="text" name="dbcharset" id="dbcharset" value="<?php echo $_POST['dbcharset'] ?>" /> </td></tr>
			<tr><td>Collation:</td><td><input type="text" name="dbcollate" id="dbcollate" value="<?php echo $_POST['dbcollate'] ?>" /> </tr>
		</table>
    
    </div>
    <br/><br/><br/>
    <br/><br/><br/>

    <div class="dupx-footer-buttons">
        <input type="button" onclick="DUPX.testDatabase()" class="default-btn" value="Test Database" />
        <input id="dup-step2-deploy-btn" type="button" class="default-btn" value=" Next " onclick="DUPX.confirmDeployment()" />
    </div>

</form>


<!-- =========================================
VIEW: STEP 2 - AJAX RESULT
Auto Posts to view.step3.php
========================================= -->
<form id='s2-result-form' method="post" class="content-form" style="display:none">

    <div class="dupx-logfile-link"><a href="installer-log.txt" target="install_log">installer-log.txt</a></div>
	<div class="hdr-main">
        Step <span class="step">2</span> of 4: Install Database
	</div>

	<!--  POST PARAMS -->
	<div class="dupx-debug">
		<input type="hidden" name="action_step" value="3" />
		<input type="hidden" name="archive_name" value="<?php echo $GLOBALS['FW_PACKAGE_NAME'] ?>" />
		<input type="hidden" name="logging" id="ajax-logging"  />
		<input type="hidden" name="dbhost" id="ajax-dbhost" />
		<input type="hidden" name="dbport" id="ajax-dbport" />
		<input type="hidden" name="dbuser" id="ajax-dbuser" />
		<input type="hidden" name="dbpass" id="ajax-dbpass" />
		<input type="hidden" name="dbname" id="ajax-dbname" />
		<input type="hidden" name="json"   id="ajax-json" />
		<input type="hidden" name="dbcharset" id="ajax-dbcharset" />
		<input type="hidden" name="dbcollate" id="ajax-dbcollate" />
		<br/>
		<input type='submit' value='manual submit'>
	</div>

	<!--  PROGRESS BAR -->
	<div id="progress-area">
	    <div style="width:500px; margin:auto">
		<h3>Installing Database Please Wait...</h3>
		<div id="progress-bar"></div>
		<i>This may take several minutes</i>
	    </div>
	</div>

	<!--  AJAX SYSTEM ERROR -->
	<div id="ajaxerr-area" style="display:none">
	    <p>Please try again an issue has occurred.</p>
	    <div style="padding: 0px 10px 10px 0px;">
			<div id="ajaxerr-data">An unknown issue has occurred with the file and database setup process.  Please see the installer-log.txt file for more details.</div>
			<div style="text-align:center; margin:10px auto 0px auto">
				<input type="button" class="default-btn" onclick='DUPX.hideErrorResult()' value="&laquo; Try Again" /><br/><br/>
				<i style='font-size:11px'>See online help for more details at <a href='https://snapcreek.com/ticket' target='_blank'>snapcreek.com</a></i>
			</div>
	    </div>
	</div>
</form>



<!-- CONFIRM DIALOG -->
<div id="dialog-confirm-content" style="display:none">
	<div style="padding:0 0 25px 0">
		<b>Run installer with these settings?</b>
	</div>

	<b>Database Settings:</b><br/>
	<table style="margin-left:20px">
		<tr>
			<td><b>Server:</b></td>
			<td><i id="dlg-dbhost"></i></td>
		</tr>
		<tr>
			<td><b>Name:</b></td>
			<td><i id="dlg-dbname"></i></td>
		</tr>
		<tr>
			<td><b>User:</b></td>
			<td><i id="dlg-dbuser"></i></td>
		</tr>
	</table>
	<br/><br/>

	<small> WARNING: Be sure these database parameters are correct! Entering the wrong information WILL overwrite an existing database.
	Make sure to have backups of all your data before proceeding.</small><br/>
</div>


<script>
/* Confirm Dialog to validate run */
DUPX.confirmDeployment = function()
{
	var $form = $('#s2-input-form');
	$form.parsley().validate();
	if (!$form.parsley().isValid()) {
		return;
	}

	$('#dlg-dbhost').html($("#dbhost").val());
	$('#dlg-dbname').html($("#dbname").val());
	$('#dlg-dbuser').html($("#dbuser").val());

	modal({
		type: 'confirm',
		title: 'Install Confirmation',
		text: $('#dialog-confirm-content').html(),
		callback: function(result)
		{
			if (result == true) {
				DUPX.runDeployment();
			}
		}
	});
}


/* Performs Ajax post to extract files and create db
 * Timeout (10000000 = 166 minutes) */
DUPX.runDeployment = function()
{
	var $form = $('#s2-input-form');
	var dbhost = $("#dbhost").val();
	var dbname = $("#dbname").val();
	var dbuser = $("#dbuser").val();

	$.ajax({
		type: "POST",
		timeout: 1800000,
		dataType: "json",
		url: window.location.href,
		data: $form.serialize(),
		beforeSend: function() {
			DUPX.showProgressBar();
			$form.hide();
			$('#s2-result-form').show();
		},
		success: function(data, textStatus, xhr){
			if (typeof(data) != 'undefined' && data.pass == 1) {
				$("#ajax-dbhost").val($("#dbhost").val());
				$("#ajax-dbport").val($("#dbport").val());
				$("#ajax-dbuser").val($("#dbuser").val());
				$("#ajax-dbpass").val($("#dbpass").val());
				$("#ajax-dbname").val($("#dbname").val());
				$("#ajax-dbcharset").val($("#dbcharset").val());
				$("#ajax-dbcollate").val($("#dbcollate").val());
				$("#ajax-logging").val($("#logging").val());
				$("#ajax-json").val(escape(JSON.stringify(data)));
				<?php if (! $GLOBALS['DUPX_DEBUG']) : ?>
					setTimeout(function() {$('#s2-result-form').submit();}, 500);
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

/**
 *  Toggles the cpanel Login area  */
DUPX.togglePanels = function (pane)
{
	$('#s2-basic-pane, #s2-cpnl-pane').hide();
	$('#s2-basic-btn, #s2-cpnl-btn').removeClass('active in-active');
	if (pane == 'basic') {
		$('#s2-basic-pane').show();
		$('#s2-basic-btn').addClass('active');
		$('#s2-cpnl-btn').addClass('in-active');
	} else {
		$('#s2-cpnl-pane').show(200);
		$('#s2-cpnl-btn').addClass('active');
		$('#s2-basic-btn').addClass('in-active');
	}
}


/** Go back on AJAX result view */
DUPX.hideErrorResult = function()
{
	$('#s2-result-form').hide();
	$('#s2-input-form').show(200);
}


/** Shows results of database connection
* Timeout (45000 = 45 secs) */
DUPX.testDatabase = function ()
{
	$.ajax({
		type: "POST",
		timeout: 45000,
		url: window.location.href + '?' + 'dbtest=1',
		data: $('#s2-input-form').serialize(),
		success: function(data){ $('#s2-dbconn-test-msg').html(data); },
		error:   function(data){ alert('An error occurred while testing the database connection!  Contact your server admin to make sure the connection inputs are correct!'); }
	});

	$('#s2-dbconn-test-msg').html("Attempting Connection.  Please wait...");
	$("#s2-dbconn-status").show(100);

}


DUPX.showDeleteWarning = function ()
{
	($('#dbaction').val() == 'empty')
		? $('#s2-warning-emptydb').show(200)
		: $('#s2-warning-emptydb').hide(200);
}


DUPX.togglePort = function ()
{
	$('#s2-dbport-btn').hide();
	$('#dbport').show();
}


//DOCUMENT LOAD
$(document).ready(function()
{
	$('#dup-s2-dialog-data').appendTo('#dup-s2-result-container');
	$("select#dbaction").click(DUPX.showDeleteWarning);
	DUPX.showDeleteWarning();

	//MySQL Mode
	$("input[name=dbmysqlmode]").click(function() {
		if ($(this).val() == 'CUSTOM') {
			$('#dbmysqlmode_3_view').show();
		} else {
			$('#dbmysqlmode_3_view').hide();
		}
	});

	if ($("input[name=dbmysqlmode]:checked").val() == 'CUSTOM') {
		$('#dbmysqlmode_3_view').show();
	}
	$("*[data-type='toggle']").click(DUPX.toggleClick);
});
</script>
