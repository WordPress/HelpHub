<?php
//VIEW: STEP 1- INPUT

//ARCHIVE FILE
$arcStatus	= (file_exists($GLOBALS['ARCHIVE_PATH']))	? 'Pass' : 'Fail';
$arcFormat  = ($arcStatus == 'Pass') ? 'Pass' : 'StatusFailed';
$arcSize    = @filesize($GLOBALS['ARCHIVE_PATH']);
$arcSize    = is_numeric($arcSize) ? $arcSize : 0;
$zip_archive_enabled = class_exists('ZipArchive') ? 'Enabled' : 'Not Enabled';

//ARCHIVE FORMAT
if ($arcStatus) {
	if (class_exists('ZipArchive')){
		$zip = new ZipArchive();
		if($zip->open($GLOBALS['ARCHIVE_PATH']) === TRUE ) {

			$arcFilePath = basename($GLOBALS['ARCHIVE_PATH']);
			$arcFilePath = substr($arcFilePath, 0, strrpos($arcFilePath, "."));
			$badFiles  = array('__MACOSX', $arcFilePath);
			$goodFiles = array('database.sql', 'installer-backup.php');
			$goodFilesFound = true;
			$badFilesFound  = false;

			foreach ($badFiles as $val) {
				if (is_numeric($zip->locateName("{$val}/"))) {
					$badFilesFound = true;
					break;
				}
			}

			foreach ($goodFiles as $val) {
				if ($zip->locateName($val) !== true) {
					$goodFilesFound = false;
				}
			}

			$arcFormat = ($goodFilesFound == false && $badFilesFound == true) ? 'Fail' : 'Pass';
		}
	} else {
		$arcFormat = 'NoZipArchive';
	}
}

$all_arc = ($arcStatus == 'Pass' && $arcFormat != 'Fail') ? 'Pass' : 'Fail';

//REQUIRMENTS
$req      	= array();
$req['01']	= DUPX_Server::isDirWritable($GLOBALS["CURRENT_ROOT_PATH"]) ? 'Pass' : 'Fail';
$req['02']	= 'Pass'; //Place-holder for future check
$req['03']	= (! DUPX_Server::$php_safe_mode_on) ? 'Pass' : 'Fail';
$req['04']	= function_exists('mysqli_connect')	 ? 'Pass' : 'Fail';
$req['05']	= DUPX_Server::$php_version_safe	 ? 'Pass' : 'Fail';
$all_req  	= in_array('Fail', $req) 			 ? 'Fail' : 'Pass';

//NOTICES
$openbase		= ini_get("open_basedir");
$scanfiles		= @scandir($GLOBALS["CURRENT_ROOT_PATH"]);
$scancount		= is_array($scanfiles) ? (count($scanfiles)) : -1;
$datetime1		= $GLOBALS['FW_CREATED'];
$datetime2		= date("Y-m-d H:i:s");
$fulldays		= round(abs(strtotime($datetime1) - strtotime($datetime2))/86400);
$root_path		= DUPX_U::setSafePath($GLOBALS['CURRENT_ROOT_PATH']);
$wpconf_path	= "{$root_path}/wp-config.php";
$max_time_zero  = @set_time_limit(0);
$max_time_size  = 314572800;  //300MB
$max_time_ini   = ini_get('max_execution_time');
$max_time_warn  = (is_numeric($max_time_ini) && $max_time_ini < 31  && $max_time_ini > 0) && $arcSize > $max_time_size;


$notice		    = array();
if (!$GLOBALS['FW_ARCHIVE_ONLYDB']) {
	$notice['01']   = ! file_exists($wpconf_path)	? 'Good' : 'Warn';
	$notice['02']   = $scancount <= 35 ? 'Good' : 'Warn';
}
$notice['03']	= $fulldays <= 180 ? 'Good' : 'Warn';
$notice['04']	= 'Good'; //Place-holder for future check
$notice['05']	= 'Good'; //Place-holder for future check 
$notice['06']	= empty($openbase)	 ? 'Good' : 'Warn';
$notice['07']	= ! $max_time_warn	 ? 'Good' : 'Warn';
$all_notice  	= in_array('Warn', $notice) ? 'Warn' : 'Good';

//SUMMATION
$req_success  = ($all_req == 'Pass');
$req_notice   = ($all_notice == 'Good');
$all_success  = ($req_success && $req_notice);
$agree_msg    = "To enable this button the checkbox above under the 'Terms & Notices' must be checked.";
?>


<form id='s1-input-form' method="post" class="content-form" >
<input type="hidden" name="action_ajax" value="1" />
<input type="hidden" name="action_step" value="1" />
<input type="hidden" name="archive_name"  value="<?php echo $GLOBALS['FW_PACKAGE_NAME'] ?>" />

<div class="hdr-main">
    Step <span class="step">1</span> of 4: Deployment
</div>
<br/>
	

<!-- ====================================
ARCHIVE
==================================== -->
<div class="hdr-sub1" id="s1-area-archive-file-link" data-type="toggle" data-target="#s1-area-archive-file">
    <a href="javascript:void(0)"><i class="dupx-plus-square"></i> Archive</a>
	<div class="<?php echo ($all_arc == 'Pass') ? 'status-badge-pass' : 'status-badge-fail'; ?>" style="float:right">
		<?php echo ($all_arc == 'Pass') ? 'Pass' : 'Fail'; ?>
	</div>
</div>
<div id="s1-area-archive-file" style="display:none">

    <table class="s1-archive-local">
		<tr>
			<td colspan="2"><div class="hdr-sub3">Site Details</div></td>
		</tr>
		 <tr>
            <td>Site:</td>
            <td><?php echo $GLOBALS['FW_BLOGNAME'];?> </td>
        </tr>
        <tr>
            <td>Notes:</td>
            <td><?php echo strlen($GLOBALS['FW_PACKAGE_NOTES']) ? "{$GLOBALS['FW_PACKAGE_NOTES']}" : " - no notes - ";?></td>
        </tr>
		<?php if ($GLOBALS['FW_ARCHIVE_ONLYDB']) :?>
		<tr>
			<td>Mode:</td>
			<td>Archive only database was enabled during package package creation.</td>
		</tr>
		<?php endif; ?>
	</table>

	<table class="s1-archive-local">
		<tr>
			<td colspan="2"><div class="hdr-sub3">File Details</div></td>
		</tr>
        <tr>
            <td>Size:</td>
            <td><?php echo DUPX_U::readableByteSize($arcSize); ?> </td>
        </tr>
        <tr>
            <td>Name:</td>
            <td><?php echo "{$GLOBALS['FW_PACKAGE_NAME']}";?> </td>
        </tr>
        <tr>
            <td>Path:</td>
            <td><?php echo "{$GLOBALS['CURRENT_ROOT_PATH']}";?> </td>
        </tr>
		<tr>
			<td>Status:</td>
			<td>
				<?php if ($arcStatus != 'Fail') : ?>
					<span class="dupx-pass">File Found</span>
				<?php else : ?>
					<div class="s1-archive-failed-msg">
						<b class="dupx-fail">Archive File Not Found!</b><br/>
						The archive file name below must be the <u>exact</u> name of the archive file placed in the deployment path (character for character).
						If the file does not have the same name then rename it to the name above.
						<br/><br/>

						When downloading the package files make sure both files are from the same package line in the packages view.  The archive file also
						must be completely downloaded to the server before starting the install.  The following zip files were found at the deployment path:
						<?php
							//DETECT ARCHIVE FILES
							$zip_files = DUPX_Server::getZipFiles();
							$zip_count = count($zip_files);

							if ($zip_count >= 1) {
								echo "<ol style='padding:10px 20px 0 20px; font-style:italic'>";
								foreach($zip_files as $file) {
									echo "<li> '{$file}'</li>";
								}
								echo "</ol>";
							} else {
								echo  "<br/><br/> <i>- No zip files found -</i>";
							}
						?>
					</div>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td>Format:</td>
			<td>
				<?php if ($arcFormat == 'Pass') : ?>
					<span class="dupx-pass">Good structure</span>
				<?php elseif ($arcFormat == 'StatusFailed') : ?>
					<span class="dupx-fail">Unable to validate format</span><br/>
				<?php elseif ($arcFormat == 'NoZipArchive') : ?>
					<div class="s1-archive-failed-msg">
						The PHP extraction library <a href="" target="_help">ZipArchive</a> was not found on this server.  There are a few options:
						<ol>
							<li>Contact your host to enable the this PHP library. <a href="" target="_help">[more info]</a></li>
							<li>Enable 'Manual package extraction' in the options menu and <a href="" target="_help">Manually extract the archive</a></li>
						</ol>
					</div>
				<?php else : ?>
					<div class="s1-archive-failed-msg">
						<b class="dupx-fail">Invalid Archive Format Detected!</b><br/>
						The archive files contents must be laid out in a specific format.  If the format has been changed the install process will error out.
						<br/><br/>

						This scenario is rare but can happen on some systems during the download and upload process of the zip without a user being aware of
						the issue. Please check the contents of the zip archive and be sure its contents match the layout of your site.
						<br/><br/>

						Files such as database.sql and wp-config.php should be at the root of the archive.  For more details see the FAQ article
						<a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-020-q" target="_help">The archive format is changing on my Mac what might be the problem?</a>
					</div>
				<?php endif; ?>
			</td>
		</tr>
    </table>

</div>
<br/><br/>


<!-- ====================================
VALIDATION
==================================== -->
<div class="hdr-sub1" id="s1-area-sys-setup-link" data-type="toggle" data-target="#s1-area-sys-setup">
	<a href="javascript:void(0)"><i class="dupx-plus-square"></i> Validation</a>
	<div class="<?php echo ($req_success) ? 'status-badge-pass' : 'status-badge-fail'; ?>" style="float:right">
		<?php echo ($req_success) ? 'Pass' : 'Fail'; ?>
	</div>
</div>
<div id="s1-area-sys-setup" style="display:none">
	<div class='info-top'>The system validation checks help to make sure the system is ready for install.</div>

    <!-- *** REQUIREMENTS ***  -->
	<div class="s1-reqs" id="s1-reqs-all">
		<div class="header">
			<table class="s1-checks-area">
				<tr>
					<td class="title">Requirements <small>(must pass)</small></td>
					<td class="toggle"><a href="javascript:void(0)" onclick="DUPX.toggleAll('#s1-reqs-all')">[toggle]</a></td>
				</tr>
			</table>
		</div>

		<!-- REQ 1 -->
		<div class="status <?php echo strtolower($req['01']); ?>"><?php echo $req['01']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-reqs01">+ Directory Writable</div>
		<div class="info" id="s1-reqs01">
			<table>
				<tr>
					<td><b>Deployment Path:</b> </td>
					<td><i><?php echo "{$GLOBALS['CURRENT_ROOT_PATH']}"; ?></i> </td>
				</tr>
				<tr>
					<td><b>Suhosin Extension:</b> </td>
					<td><?php echo extension_loaded('suhosin') ? "<i class='dupx-fail'>Enabled</i>'" : "<i class='dupx-pass'>Disabled</i>"; ?> </td>
				</tr>
			</table><br/>

			The deployment path must be writable by PHP in order to extract the archive file.  Incorrect permissions and extension such as
			<a href="https://suhosin.org/stories/index.html" target="_blank">suhosin</a> can sometimes inter-fear with PHP being able to write/extract files.
			Please see the <a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-055-q" target="_blank">FAQ permission</a> help link for complete details.
		</div>

		<!-- REQ 2
		<div class="status <?php echo strtolower($req['02']); ?>"><?php echo $req['02']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-reqs02">+ Place Holder</div>
		<div class="info" id="s1-reqs02"></div>-->

		<!-- REQ 3 -->
		<div class="status <?php echo strtolower($req['03']); ?>"><?php echo $req['03']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-reqs03">+ PHP SafeMode</div>
		<div class="info" id="s1-reqs03">
			PHP with <a href='http://php.net/manual/en/features.safe-mode.php' target='_blank'>safe mode</a> must be disabled.  If this test fails
			please contact your hosting provider or server administrator to disable PHP safe mode.
		</div>

		<!-- REQ 4 -->
		<div class="status <?php echo strtolower($req['04']); ?>"><?php echo $req['04']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-reqs04">+ PHP mysqli</div>
		<div class="info" id="s1-reqs04">
			Support for the PHP <a href='http://us2.php.net/manual/en/mysqli.installation.php' target='_blank'>mysqli extension</a> is required.
			Please contact your hosting provider or server administrator to enable the mysqli extension.  <i>The detection for this call uses
			the function_exists('mysqli_connect') call.</i>
		</div>

		<!-- REQ 5 -->
		<div class="status <?php echo strtolower($req['05']); ?>"><?php echo $req['05']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-reqs05">+ PHP Version</div>
		<div class="info" id="s1-reqs05">
			This server is running PHP: <b><?php echo DUPX_Server::$php_version ?></b>. <i>A minimum of PHP 5.2.17 is required</i>.
			Contact your hosting provider or server administrator and let them know you would like to upgrade your PHP version.
		</div>
	</div><br/>


	<!-- *** NOTICES ***  -->
	<div class="s1-reqs" id="s1-notice-all">
		<div class="header">
			<table class="s1-checks-area">
				<tr>
					<td class="title">Notices <small>(optional)</small></td>
					<td class="toggle"><a href="javascript:void(0)" onclick="DUPX.toggleAll('#s1-notice-all')">[toggle]</a></td>
				</tr>
			</table>
		</div>

		<?php if (!$GLOBALS['FW_ARCHIVE_ONLYDB']) :?>

			<!-- NOTICE 1 -->
			<div class="status <?php echo ($notice['01'] == 'Good') ? 'pass' : 'fail' ?>"><?php echo $notice['01']; ?></div>
			<div class="title" data-type="toggle" data-target="#s1-notice01">+ Configuration File</div>
			<div class="info" id="s1-notice01">
				Duplicator works best by placing the installer and archive files into an empty directory.  If a wp-config.php file is found in the extraction
				directory it might indicate that a pre-existing WordPress site exists which can lead to a bad install.
				<br/><br/>
				<b>Options:</b>
				<ul style="margin-bottom: 0">
					<li>If the archive was already manually extracted then <a href="javascript:void(0)" onclick="DUPX.getManaualArchiveOpt()">[Enable Manual Archive Extraction]</a></li>
					<li>If the wp-config file is not needed then remove it.</li>
				</ul>
			</div>

			<!-- NOTICE 2 -->
			<div class="status <?php echo ($notice['02'] == 'Good') ? 'pass' : 'fail' ?>"><?php echo $notice['02']; ?></div>
			<div class="title" data-type="toggle" data-target="#s1-notice02">+ Directory Setup</div>
			<div class="info" id="s1-notice02">
				<b>Deployment Path:</b> <i><?php echo "{$GLOBALS['CURRENT_ROOT_PATH']}"; ?></i>
				<br/><br/>
				There are currently <?php echo "<b>[{$scancount}]</b>";?>  items in the deployment path. These items will be overwritten if they also exist
				inside the archive file.  The notice is to prevent overwriting an existing site or trying to install on-top of one which
				can have un-intended results. <i>This notice shows if it detects more than 40 items.</i>

				<br/><br/>
				<b>Options:</b>
				<ul style="margin-bottom: 0">
					<li>If the archive was already manually extracted then <a href="javascript:void(0)" onclick="DUPX.getManaualArchiveOpt()">[Enable Manual Archive Extraction]</a></li>
					<li>If the files/directories are not the same as those in the archive then this notice can be ignored.</li>
					<li>Remove the files if they are not needed and refresh this page.</li>
				</ul>
			</div>

		<?php endif; ?>

		<!-- NOTICE 3 -->
		<div class="status <?php echo ($notice['03'] == 'Good') ? 'pass' : 'fail' ?>"><?php echo $notice['03']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-notice03">+ Package Age</div>
		<div class="info" id="s1-notice03">
			<?php echo "The package is {$fulldays} day(s) old. Packages older than 180 days might be considered stale"; ?>
		</div>

        <!-- NOTICE 4
		<div class="status <?php echo ($notice['04'] == 'Good') ? 'pass' : 'fail' ?>"><?php echo $notice['04']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-notice04">+ Placeholder</div>
		<div class="info" id="s1-notice04">
		</div>-->


		<!-- NOTICE 5 
		<div class="status <?php echo ($notice['05'] == 'Good') ? 'pass' : 'fail' ?>"><?php echo $notice['05']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-notice05">+ OS Compatibility</div>
		<div class="info" id="s1-notice05">
			<?php
				$currentOS = PHP_OS;
				echo "The current OS (operating system) is '{$currentOS}'.  The package was built on '{$GLOBALS['FW_VERSION_OS']}'.  Moving from one OS to another
				is typically very safe and normal, however if any issues do arise be sure that you don't have any items on your site that were OS specific";
			?>
		</div>-->

		<!-- NOTICE 6 -->
		<div class="status <?php echo ($notice['06'] == 'Good') ? 'pass' : 'fail' ?>"><?php echo $notice['06']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-notice06">+ PHP Open Base</div>
		<div class="info" id="s1-notice06">
			<b>Open BaseDir:</b> <i><?php echo $notice['06'] == 'Good' ? "<i class='dupx-pass'>Disabled</i>" : "<i class='dupx-fail'>Enabled</i>"; ?></i>
			<br/><br/>

			If <a href="http://www.php.net/manual/en/ini.core.php#ini.open-basedir" target="_blank">open_basedir</a> is enabled and your
			having issues getting your site to install properly; please work with your host and follow these steps to prevent issues:
			<ol style="margin:7px; line-height:19px">
				<li>Disable the open_basedir setting in the php.ini file</li>
				<li>If the host will not disable, then add the path below to the open_basedir setting in the php.ini<br/>
					<i style="color:maroon">"<?php echo str_replace('\\', '/', dirname( __FILE__ )); ?>"</i>
				</li>
				<li>Save the settings and restart the web server</li>
			</ol>
			Note: This warning will still show if you choose option #2 and open_basedir is enabled, but should allow the installer to run properly.  Please work with your
			hosting provider or server administrator to set this up correctly.
		</div>

		<!-- NOTICE 7 -->
		<div class="status <?php echo ($notice['07'] == 'Good') ? 'pass' : 'fail' ?>"><?php echo $notice['07']; ?></div>
		<div class="title" data-type="toggle" data-target="#s1-notice07">+ PHP Timeout</div>
		<div class="info" id="s1-notice07">
			<b>Archive Size:</b> <?php echo DUPX_U::readableByteSize($arcSize) ?>  <small>(detection limit is set at <?php echo DUPX_U::readableByteSize($max_time_size) ?>) </small><br/>
			<b>PHP max_execution_time:</b> <?php echo "{$max_time_ini}"; ?> <small>(zero means not limit)</small> <br/>
			<b>PHP set_time_limit:</b> <?php echo ($max_time_zero) ? '<i style="color:green">Success</i>' : '<i style="color:maroon">Failed</i>' ?>
			<br/><br/>

			The PHP <a href="http://php.net/manual/en/info.configuration.php#ini.max-execution-time" target="_blank">max_execution_time</a> setting is used to
			determine how long a PHP process is allowed to run.  If the setting is too small and the archive file size is too large then PHP may not have enough
			time to finish running before the process is killed causing a timeout.
			<br/><br/>

			Duplicator attempts to turn off the timeout by using the
			<a href="http://php.net/manual/en/function.set-time-limit.php" target="_blank">set_time_limit</a> setting.   If this notice shows as a warning then it is
			still safe to continue with the install.  However, if a timeout occurs then you will need to consider working with the max_execution_time setting or extracting the
			archive file using the 'Manual package extraction' method.
			Please see the	<a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-100-q" target="_blank">FAQ timeout</a> help link for more details.

		</div>
	</div>
</div>
<br/><br/>
	

<!-- ====================================
OPTIONS
==================================== -->
<div class="hdr-sub1" data-type="toggle" data-target="#s1-area-adv-opts">
	<a href="javascript:void(0)"><i class="dupx-plus-square"></i> Options</a>
</div>
<div id="s1-area-adv-opts" style="display:none">
	<div class="help-target"><a href="?help#help-s1" target="_blank">[help]</a></div>
	<br/>
	<div class="hdr-sub3">Advanced</div>
	<table class="dupx-opts dupx-advopts">
		<tr>
			<td>Extraction:</td>
			<td>

				<select id="archive_engine" name="archive_engine" size="2">
					<option value="manual">Manual Archive Extraction</option>
					<?php
					//ZIP-ARCHIVE
					echo (! $zip_archive_enabled)
						? '<option disabled="true">PHP ZipArchive (not detected on server)</option>'
						: '<option value="ziparchive" selected="true">PHP ZipArchive</option>';
					?>
				</select>
			</td>
		</tr>
	</table>

	<table class="dupx-opts dupx-advopts">
		<tr>
			<td>File Times:</td>
			<td>
				<input type="radio" name="archive_filetime" id="archive_filetime_now" value="current" checked="checked" /> <label class="radio" for="archive_filetime_now" title='Set the files current date time to now'>Current</label>
				<input type="radio" name="archive_filetime" id="archive_filetime_orginal" value="original" /> <label class="radio" for="archive_filetime_orginal" title="Keep the files date time the same">Original</label>
			</td>
		</tr>
		<tr>
			<td>Logging:</td>
			<td>
				<input type="radio" name="logging" id="logging-light" value="1" checked="true"> <label for="logging-light">Light</label>
				<input type="radio" name="logging" id="logging-detailed" value="2"> <label for="logging-detailed">Detailed</label>
				<input type="radio" name="logging" id="logging-debug" value="3"> <label for="logging-debug">Debug</label>
			</td>
		</tr>
	</table>
     <br/><br/>

     <!-- *** SETUP HELP *** -->
     <div class="hdr-sub3">Setup Help</div>
     <div id='s1-area-setup-help'>
        <div style="padding:10px 0px 0px 10px;line-height:22px">
			<table style='width:100%'>
				<tr>
					<td style="width:200px">
						&raquo; Watch the <a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-resource-070-q" target="_blank">video tutorials</a> <br/>
						&raquo; Read helpful <a href="https://snapcreek.com/duplicator/docs/faqs-tech/" target="_blank">articles</a> <br/>
					</td>
					<td>
						 &raquo; Visit the <a href="https://snapcreek.com/duplicator/docs/quick-start/" target="_blank">quick start guides</a> <br/>
						 &raquo; Browse the <a href="https://snapcreek.com/duplicator/docs/" target="_blank">online docs</a> <br/>
					</td>
				</tr>
			</table>
        </div>
     </div><br/>

</div>
<br/><br/>

<!-- ====================================
NOTICES
==================================== -->
<div class="hdr-sub1" data-type="toggle" data-target="#s1-area-warnings">
	<a href="javascript:void(0)"><i class="dupx-plus-square"></i> Notices</a>
</div>

<div id="s1-area-warnings" style="display:none">
	<div id='s1-warning-area'>
		<div id="s1-warning-msg">
			<b>TERMS &amp; NOTICES</b> <br/><br/>

			<b>Disclaimer:</b>
			This plugin require above average technical knowledge. Please use it at your own risk and always back up your database and files beforehand Duplicator.
            If you're not sure about how to use this tool then please enlist the guidance of a technical professional.  <u>Always</u> test
			this installer in a sandbox environment before trying to deploy into a production setting.
			<br/><br/>

			<b>Database:</b>
			Do not connect to an existing database unless you are 100% sure you want to remove all of it's data. Connecting to a database that already exists will permanently
			DELETE all data in that database. This tool is designed to populate and fill a database with NEW data from a duplicated database using the SQL script in the
			package name above.
			<br/><br/>

			<b>Setup:</b>
			Only the archive and installer file should be in the install directory, unless you have manually extracted the package and checked the
			'Manual Package Extraction' checkbox. All other files will be OVERWRITTEN during install.  Make sure you have full backups of all your databases and files
			before continuing with an installation. Manual extraction requires that all contents in the package are extracted to the same directory as the installer file.
			Manual extraction is only needed when your server does not support the ZipArchive extension.  Please see the online help for more details.
			<br/><br/>

			<b>After Install:</b> When you are done with the installation you must remove remove the these files/directories:
			<ul>
				<li>installer.php</li>
				<li>installer-data.sql</li>
				<li>installer-backup.php</li>
				<li>installer-log.txt</li>
				<li>database.sql</li>
			</ul>

			These files contain sensitive information and should not remain on a production system for system integrity and security protection.
            <br/><br/>

            <b>License Overview</b><br/>
            Duplicator is licensed under the GPL v3 https://www.gnu.org/licenses/gpl-3.0.en.html including the following disclaimers and limitation of liability.
            <br/><br/>

            <b>Disclaimer of Warranty</b><br/>
            THERE IS NO WARRANTY FOR THE PROGRAM, TO THE EXTENT PERMITTED BY APPLICABLE LAW. EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT HOLDERS AND/OR OTHER PARTIES
            PROVIDE THE PROGRAM “AS IS” WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
            FITNESS FOR A PARTICULAR PURPOSE. THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME
            THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION.
            <br/><br/>

            <b>Limitation of Liability</b><br/>
            IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN WRITING WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MODIFIES AND/OR CONVEYS THE PROGRAM AS
            PERMITTED ABOVE, BE LIABLE TO YOU FOR DAMAGES, INCLUDING ANY GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OR INABILITY TO USE THE
            PROGRAM (INCLUDING BUT NOT LIMITED TO LOSS OF DATA OR DATA BEING RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD PARTIES OR A FAILURE OF THE PROGRAM TO
            OPERATE WITH ANY OTHER PROGRAMS), EVEN IF SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
            <br/><br/>

		</div>
	</div>
</div>

<div id="s1-warning-check">
	<input id="accept-warnings" name="accpet-warnings" type="checkbox" onclick="DUPX.acceptWarning()" />
	<label for="accept-warnings">I have read and accept all terms &amp; notices <small style="font-style:italic">(required to continue)</small></label><br/>
</div>


<?php if (! $req_success  ||  $all_arc == 'Fail') :?>
	<div class="s1-err-msg">
		<i>
			This installation will not be able to proceed until the 'Archive' and 'Validation' sections pass. Please adjust your servers settings or contact your
			server administrator, hosting provider or visit the resources below for additional help.
		</i>
		<div style="padding:10px">
			&raquo; <a href="https://snapcreek.com/duplicator/docs/faqs-tech/" target="_blank">Technical FAQs</a> <br/>
			&raquo; <a href="https://snapcreek.com/support/docs/" target="_blank">Online Documentation</a> <br/>
		</div>
	</div> <br/><br/>
<?php else : ?>
    <br/><br/><br/>
    <br/><br/><br/>
    <div class="dupx-footer-buttons">
        <button id="s1-deploy-btn" type="button" class="default-btn" onclick="DUPX.runExtraction()" title="<?php echo $agree_msg; ?>"> Next </button>
    </div>
<?php endif; ?>

</form>



<!-- =========================================
VIEW: STEP 1 - AJAX RESULT
Auto Posts to view.step2.php
========================================= -->
<form id='s1-result-form' method="post" class="content-form" style="display:none">

	 <div class="dupx-logfile-link"><a href="installer-log.txt" target="install_log">installer-log.txt</a></div>
	<div class="hdr-main">
        Step <span class="step">1</span> of 4: Deployment
	</div>

	<!--  POST PARAMS -->
	<div class="dupx-debug">
		<input type="hidden" name="action_step" value="2" />
		<input type="hidden" name="archive_name" value="<?php echo $GLOBALS['FW_PACKAGE_NAME'] ?>" />
		<input type="hidden" name="logging" id="ajax-logging"  />
		<input type="hidden" name="json"    id="ajax-json" />
		<textarea id='ajax-json-debug' name='json_debug_view'></textarea>
		<input type='submit' value='manual submit'>
	</div>

	<!--  PROGRESS BAR -->
	<div id="progress-area">
	    <div style="width:500px; margin:auto">
		<h3>Running Deployment Processes Please Wait...</h3>
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
				<input type="button" class="default-btn" onclick="DUPX.hideErrorResult()" value="&laquo; Try Again" /><br/><br/>
				<i style='font-size:11px'>See online help for more details at <a href='https://snapcreek.com/ticket' target='_blank'>snapcreek.com</a></i>
			</div>
	    </div>
	</div>
</form>

<script>
    DUPX.getManaualArchiveOpt = function ()
    {
        $("html, body").animate({scrollTop: $(document).height()}, 1500);
        $("a[data-target='#s1-area-adv-opts']").find('i').removeClass('dupx-plus-square').addClass('dupx-minus-square');
        $('#s1-area-adv-opts').show(1000);
        $('select#archive_engine').val('manual').focus();
    };

	/** Performs Ajax post to extract files and create db
	 * Timeout (10000000 = 166 minutes) */
	DUPX.runExtraction = function()
	{
		var $form = $('#s1-input-form');

		//1800000 = 30 minutes
		//If the extraction takes longer than 30 minutes then user
		//will probably want to do a manual extraction or even FTP
		$.ajax({
			type: "POST",
			timeout:1800000,
			dataType: "json",
			url: window.location.href,
			data: $form.serialize(),
			beforeSend: function() {
				DUPX.showProgressBar();
				$form.hide();
				$('#s1-result-form').show();
			},			
			success: function(data) {
				var dataJSON = JSON.stringify(data);
				$("#ajax-json-debug").val(dataJSON);
                if (typeof(data) != 'undefined' && data.pass == 1) {
					$("#ajax-logging").val($("input:radio[name=logging]:checked").val());
					$("#ajax-json").val(escape(dataJSON));
					<?php if (! $GLOBALS['DUPX_DEBUG']) : ?>
						setTimeout(function() {$('#s1-result-form').submit();}, 500);
					<?php endif; ?>
					$('#progress-area').fadeOut(1000);
				} else {
					$('#ajaxerr-data').html('Error Processing Step 1');
					DUPX.hideProgressBar();
				}
			},
			error: function(xhr) {
				var status  = "<b>Server Code:</b> "	+ xhr.status		+ "<br/>";
					status += "<b>Status:</b> "			+ xhr.statusText	+ "<br/>";
					status += "<b>Response:</b> "		+ xhr.responseText  + "";
					status += "<hr/><b>Additional Troubleshooting Tips:</b><br/>";
					status += "- Check the <a href='installer-log.txt' target='install_log'>installer-log.txt</a> file for warnings or errors.<br/>";
					status += "- Check the web server and PHP error logs. <br/>";
					status += "- For timeout issues visit the <a href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-100-q' target='_blank'>Timeout FAQ Section</a><br/>";
				$('#ajaxerr-data').html(status);
				DUPX.hideProgressBar();
			}
		});	
		
	};

	/** Accetps Useage Warning */
	DUPX.acceptWarning = function()
    {
		if ($("#accept-warnings").is(':checked')) {
            $("#s1-deploy-btn").removeAttr("disabled");
			$("#s1-deploy-btn").removeAttr("title");
        } else {
            $("#s1-deploy-btn").attr("disabled", "true");
			$("#s1-deploy-btn").attr("title", "<?php echo $agree_msg; ?>");
        }
	}

	/** Go back on AJAX result view */
	DUPX.hideErrorResult = function()
    {
		$('#s1-result-form').hide();
		$('#s1-input-form').show(200);
	}
	
	//DOCUMENT LOAD
	$(document).ready(function()
    {
		DUPX.acceptWarning();
        $("*[data-type='toggle']").click(DUPX.toggleClick);
        <?php echo ($all_arc == 'Fail') 	? "$('#s1-area-archive-file-link').trigger('click');" 	: ""; ?>
		<?php echo (! $all_success)         ? "$('#s1-area-sys-setup-link').trigger('click');"      : ""; ?>
	})
</script>
