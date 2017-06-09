<?php

	$_POST['archive_name'] = isset($_POST['archive_name']) ? $_POST['archive_name'] : '';
	$admin_base		= basename($GLOBALS['FW_WPLOGIN_URL']);
	$admin_redirect = rtrim($_POST['url_new'], "/") . "/wp-admin/admin.php?page=duplicator-tools&tab=cleanup&package={$_POST['archive_name']}";
	$admin_redirect = urlencode($admin_redirect);
	$admin_login	= rtrim($_POST['url_new'], '/') . "/{$admin_base}?redirect_to={$admin_redirect}";
?>

<script>
	/** Posts to page to remove install files */
	DUPX.removeInstallerFiles = function()
    {
		var msg = "You will now be redirected to the cleanup page.\nSelect 'Delete Reserved Files' to remove installer files.";
		 if (confirm(msg)) {
			var nurl = '<?php echo rtrim($_POST['url_new'], "/"); ?>/wp-admin/admin.php?page=duplicator-tools&tab=cleanup';
			window.open(nurl, "_blank");
		}
	};
	DUPX.getAdminLogin = function()
    {
		window.open('<?php echo $admin_login; ?>', 'wp-admin');
	};
</script>


<!-- =========================================
VIEW: STEP 4 - INPUT -->
<form id='s4-input-form' method="post" class="content-form" style="line-height:20px">
	<input type="hidden" name="url_new" id="url_new" value="<?php echo rtrim($_POST['url_new'], "/"); ?>" />
	<div class="dupx-logfile-link"><a href="installer-log.txt?now=<?php echo $GLOBALS['NOW_DATE'] ?>" target="install_log">installer-log.txt</a></div>

	<div class="hdr-main">
        Step <span class="step">4</span> of 4: Test
	</div><br />

	<div class="hdr-sub1">
		<div class="s4-final-title">Final Steps</div>
	</div>

	<table class="s4-final-step">
		<!--tr>
			<td><a class="s4-final-btns" href='<?php echo rtrim($_POST['url_new'], "/") . '?now=' . $GLOBALS['NOW_DATE']; ?>' target='_blank'>Test Site</a></td>
			<td><i>Validate all pages, links images and plugins</i></td>
		</tr>
		<tr>
			<td><a class="s4-final-btns" href="javascript:void(0)" onclick="DUPX.removeInstallerFiles('<?php echo $_POST['archive_name'] ?>')">Security Cleanup</a></td>
			<td><i>Validate installer files are removed (requires login)</i></td>
		</tr-->
		<tr>
			<td><a class="s4-final-btns" href="javascript:void(0)" onclick="DUPX.getAdminLogin()">Site Login</a></td>
			<td><i>Login to the administrator section to finalize the setup</i></td>
		</tr>
		<tr>
			<td><a class="s4-final-btns" href="javascript:void(0)" onclick="$('#dup-step3-install-report').toggle(400)">Show Report</a></td>
			<td>
				<i id="dup-step3-install-report-count">
					<span data-bind="with: status.step2">Install Results: (<span data-bind="text: query_errs"></span>)</span> &nbsp;
					<span data-bind="with: status.step3">Replace Results: (<span data-bind="text: err_all"></span>)</span> &nbsp; &nbsp;
					<span data-bind="with: status.step3" style="color:#888"><b>General Notices:</b> (<span data-bind="text: warn_all"></span>)</span>
				</i>
			</td>
		</tr>
	</table><br/>

	<div class="s4-go-back">
		<i>To re-install <a href="javascript:history.go(-3)">start over at step 1</a>.</i><br/>
		<i>The .htaccess file was reset.  Resave plugins that write to this file.</i>
	</div>

	<!-- ========================
	INSTALL REPORT -->
	<div id="dup-step3-install-report" style='display:none'>
		<table class='s4-report-results' style="width:100%">
			<tr><th colspan="4">Database Results</th></tr>
			<tr style="font-weight:bold">
				<td style="width:150px"></td>
				<td>Tables</td>
				<td>Rows</td>
				<td>Cells</td>
			</tr>
			<tr data-bind="with: status.step2">
				<td>Created</td>
				<td><span data-bind="text: table_count"></span></td>
				<td><span data-bind="text: table_rows"></span></td>
				<td>n/a</td>
			</tr>
			<tr data-bind="with: status.step3">
				<td>Scanned</td>
				<td><span data-bind="text: scan_tables"></span></td>
				<td><span data-bind="text: scan_rows"></span></td>
				<td><span data-bind="text: scan_cells"></span></td>
			</tr>
			<tr data-bind="with: status.step3">
				<td>Updated</td>
				<td><span data-bind="text: updt_tables"></span></td>
				<td><span data-bind="text: updt_rows"></span></td>
				<td><span data-bind="text: updt_cells"></span></td>
			</tr>
		</table>

		<table class='s4-report-errs' style="width:100%; border-top:none">
			<tr><th colspan="4">Report Details <br/> <i style="font-size:10px; font-weight:normal">(click links below to view details)</i></th></tr>
			<tr>
				<td data-bind="with: status.step2">
					<a href="javascript:void(0);" onclick="$('#dup-step3-errs-create').toggle(400)">Step 2: Install Results (<span data-bind="text: query_errs"></span>)</a><br/>
				</td>
				<td data-bind="with: status.step3">
					<a href="javascript:void(0);" onclick="$('#dup-step3-errs-upd').toggle(400)">Step 3: Replace Results (<span data-bind="text: err_all"></span>)</a>
				</td>
				<td data-bind="with: status.step3">
					<a href="#dup-step3-errs-warn-anchor" onclick="$('#dup-step3-warnlist').toggle(400)">General Notices (<span data-bind="text: warn_all"></span>)</a>
				</td>
			</tr>
			<tr><td colspan="4"></td></tr>
		</table>


		<div id="dup-step3-errs-create" class="s4-err-msg">
			<div class="s4-err-title">STEP 1 DEPLOY RESULTS</div>
			<b data-bind="with: status.step2">DEPLOY ERRORS (<span data-bind="text: query_errs"></span>)</b><br/>
			<div class="info-error">
				Queries that error during the deploy step are logged to the <a href="installer-log.txt" target="install_log">install-log.txt</a> file  and marked '**ERROR**'.
				<br/><br/>

				<b><u>COMMON FIXES INCLUDE:</u></b>
				<br/><br/>

				<b>Unknown collation:</b><br/>
				The MySQL Version is too old see: <a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-110-q" target="_blank">What is Compatibility mode & 'Unknown collation' errors?</a>
				<br/><br/>

				<b>Query size limits:</b><br/>
				Update your MySQL server with the <a href="https://dev.mysql.com/doc/refman/5.5/en/packet-too-large.html" target="_blank">max_allowed_packet</a>
				setting to handle larger payloads. 
				<br/><br/>
				
			</div>
		</div>


		<div id="dup-step3-errs-upd" class="s4-err-msg">
			<div class="s4-err-title">STEP 2 UPDATE RESULTS</div>
			<!-- MYSQL QUERY ERRORS -->
			<b data-bind="with: status.step3">UPDATE ERRORS (<span data-bind="text: errsql_sum"></span>) </b><br/>
			<div class="info-error">
				Update errors that show here are queries that could not be performed because the database server being used has issues running it.  Please validate the query, if
				it looks to be of concern please try to run the query manually.  In many cases if your site performs well without any issues you can ignore the error.
			</div>
			<div class="content">
				<div data-bind="foreach: status.step3.errsql"><div data-bind="text: $data"></div></div>
				<div data-bind="visible: status.step3.errsql.length == 0">No MySQL query errors found</div>
			</div>

			<!-- TABLE KEY ERRORS -->
			<b data-bind="with: status.step3">TABLE KEY NOTICES (<span data-bind="text: errkey_sum"></span>)</b><br/>
			<div class="info-notice">
				Notices should be ignored unless issues are found after you have tested an installed site. This notice indicates that a primary key is required to run the
				update engine. Below is a list of tables and the rows that were not updated.  On some databases you can remove these notices by checking the box 'Enable Full Search'
				under advanced options in step3 of the installer.
				<br/><br/>
				<small>
					<b>Advanced Searching:</b><br/>
					Use the following query to locate the table that was not updated: <br/>
					<i>SELECT @row := @row + 1 as row, t.* FROM some_table t, (SELECT @row := 0) r</i>
				</small>
			</div>
			<div class="content">
				<div data-bind="foreach: status.step3.errkey"><div data-bind="text: $data"></div></div>
				<div data-bind="visible: status.step3.errkey.length == 0">No missing primary key errors</div>
			</div>

			<!-- SERIALIZE ERRORS -->
			<b data-bind="with: status.step3">SERIALIZATION NOTICES  (<span data-bind="text: errser_sum"></span>)</b><br/>
			<div class="info-notice">
				Notices should be ignored unless issues are found after you have tested an installed site.  The SQL below will show data that may have not been
				updated during the serialization process.  Best practices for serialization notices is to just re-save the plugin/post/page in question.
			</div>
			<div class="content">
				<div data-bind="foreach: status.step3.errser"><div data-bind="text: $data"></div></div>
				<div data-bind="visible: status.step3.errser.length == 0">No serialization errors found</div>
			</div>

		</div>


		<!-- WARNINGS-->
		<div id="dup-step3-warnlist" class="s4-err-msg">
			<a href="#" id="dup-step3-errs-warn-anchor"></a>
			<b>GENERAL NOTICES</b><br/>
			<div class="info">
				The following is a list of notices that may need to be fixed in order to finalize your setup.  These values should only be investigated if your running into
				issues with your site. For more details see the <a href="https://codex.wordpress.org/Editing_wp-config.php" target="_blank">WordPress Codex</a>.
			</div>
			<div class="content">
				<div data-bind="foreach: status.step3.warnlist">
					 <div data-bind="text: $data"></div>
				</div>
				<div data-bind="visible: status.step3.warnlist.length == 0">
					No notices found
				</div>
			</div>
		</div><br/>

	</div><br/>

	<?php
		$num = rand(1,2);
		switch ($num) {
			case 1:
				$key = 'free_inst_s3btn1';
				$txt = 'Want More Power?';
				break;
			case 2:
				$key = 'free_inst_s3btn2';
				$txt = 'Go Pro Today!';
				break;
			default :
				$key = 'free_inst_s3btn2';
				$txt = 'Go Pro Today!';
		}
	?>

	<div class="s4-gopro-btn">
		<a href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_campaign=duplicator_pro&utm_content=<?php echo $key;?>" target="_blank"> <?php echo $txt;?></a>
	</div>
	<br/><br/>

	<div class='s4-connect'>
		<a href="installer.php?help=1#troubleshoot" target="_blank">Troubleshoot</a> |
		<a href='https://snapcreek.com/duplicator/docs/faqs-tech/' target='_blank'>FAQs</a>
	</div><br/>
</form>

<script>
	MyViewModel = function() {
		this.status = <?php echo urldecode($_POST['json']); ?>;
		var errorCount =  this.status.step2.query_errs || 0;
		(errorCount >= 1 )
			? $('#dup-step3-install-report-count').css('color', '#BE2323')
			: $('#dup-step3-install-report-count').css('color', '#197713')
	};
	ko.applyBindings(new MyViewModel());
</script>

