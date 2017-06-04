<?php
	$scan_run = (isset($_POST['action']) && $_POST['action'] == 'duplicator_recursion') ? true :false;	
	$ajax_nonce	= wp_create_nonce('DUP_CTRL_Tools_runScanValidator');
?>

<style>
	div#hb-result {padding: 10px 5px 0 5px; line-height: 22px}
</style>

<!-- ==========================================
THICK-BOX DIALOGS: -->
<?php
	$confirm1 = new DUP_UI_Dialog();
	$confirm1->title			= __('Run Validator', 'duplicator');
	$confirm1->message			= __('This will run the scan validation check.  This may take several minutes.  Do you want to Continue?', 'duplicator');
	$confirm1->progressOn		= false;
	$confirm1->jscallback		= 'Duplicator.Tools.runScanValidator()';
	$confirm1->initConfirm();
?>

<!-- ==============================
SCAN VALIDATOR -->
<div class="dup-box">
	<div class="dup-box-title">
		<i class="fa fa-check-square-o"></i>
		<?php _e("Scan Validator", 'duplicator'); ?>
		<div class="dup-box-arrow"></div>
	</div>
	<div class="dup-box-panel" style="display: <?php echo $scan_run ? 'block' : 'none';  ?>">	
		<?php 
			_e("This utility will help to find unreadable files and sys-links in your environment  that can lead to issues during the scan process.  ", "duplicator"); 
			_e("The utility  will also show how many files and directories you have in your system.  This process may take several minutes to run.  ", "duplicator"); 
			_e("If there is a recursive loop on your system then the process has a built in check to stop after a large set of files and directories have been scanned.  ", "duplicator"); 
			_e("A message will show indicated that that a scan depth has been reached. ", "duplicator"); 
		?> 
		<br/><br/>


		<button id="scan-run-btn" type="button" class="button button-large button-primary" onclick="Duplicator.Tools.ConfirmScanValidator()">
			<?php _e("Run Scan Integrity Validation", "duplicator"); ?>
		</button>

		<script id="hb-template" type="text/x-handlebars-template">
			<b>Scan Path:</b> <?php echo DUPLICATOR_WPROOTPATH ?> <br/>
			<b>Scan Results</b><br/>
			<table>
				<tr>
					<td><b>Files:</b></td>
					<td>{{Payload.fileCount}} </td>
				</tr>
				<tr>
					<td><b>Dirs:</b></td>
					<td>{{Payload.dirCount}} </td>
				</tr>
			</table>

			<b>Unreadable Files:</b> <br/>
			{{#if Payload.unreadable}}
				{{#each Payload.unreadable}}
					&nbsp; &nbsp; {{@index}} : {{this}}<br/>
				{{/each}}
			{{else}}
				<i>No Unreadable items found</i> <br/>
			{{/if}}
			
			<b>Symbolic Links:</b> <br/>
			{{#if Payload.symLinks}}
				{{#each Payload.symLinks}}
					&nbsp; &nbsp; {{@index}} : {{this}}<br/>
				{{/each}}
			{{else}}
				<i>No Sym-links found</i> <br/>
			{{/if}}
			<br/>
		</script>
		<div id="hb-result"></div>	

	</div> 
</div> 
<br/>

<script>	
jQuery(document).ready(function($) 
{
	Duplicator.Tools.ConfirmScanValidator = function() 
	{
		<?php $confirm1->showConfirm(); ?>
	}
	
	
	//Run request to: admin-ajax.php?action=DUP_CTRL_Tools_runScanValidator
	Duplicator.Tools.runScanValidator = function()
	{
		tb_remove();
		var data = {action : 'DUP_CTRL_Tools_runScanValidator', nonce: '<?php echo $ajax_nonce; ?>', 'scan-recursive': true};
		
		$('#hb-result').html('<?php _e("Scanning Enviroment... This may take a few minutes.", "duplicator"); ?>');
		$('#scan-run-btn').html('<i class="fa fa-circle-o-notch fa-spin fa-fw"></i> Running Please Wait...');
		
		$.ajax({
			type: "POST",
			url: ajaxurl,
			dataType: "json",
			data: data,
			success: function(data) {Duplicator.Tools.IntScanValidator(data)},
			error: function(data) {console.log(data)},
			done: function(data) {console.log(data)}
		});	
	}
	
	//Process Ajax Template
	Duplicator.Tools.IntScanValidator= function(data) 
	{
		var template = $('#hb-template').html();
		var templateScript = Handlebars.compile(template);
		var html = templateScript(data);
		$('#hb-result').html(html);
		$('#scan-run-btn').html('<?php _e("Run Scan Integrity Validation", "duplicator"); ?>');
	}
});	
</script>

