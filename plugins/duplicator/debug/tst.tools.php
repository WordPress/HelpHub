
<div class="section-tests">
	<div class="section-hdr">TOOLS CTRLS</div>

	<!-- METHOD TEST -->
	<form>
		<?php 
			$CTRL['Title']  = 'DUP_CTRL_Tools_runScanValidator';
			$CTRL['Action'] = 'DUP_CTRL_Tools_runScanValidator';
			$CTRL['Test']	= true;
			DUP_DEBUG_TestSetup($CTRL); 
		?>
		<div class="params">
			<label>Allow Recursion:</label>
			<input type="checkbox" name="scan-recursive" /><br/>
			<label>Search Path:</label> 
			<input type="text" name="scan-path" value="<?php echo DUPLICATOR_WPROOTPATH ?>" /> <br/>
		</div>
	</form>
	
	<!-- METHOD TEST -->
	<form>
		<?php 
			$CTRL['Title']  = 'DUP_CTRL_Tools_runScanValidatorFull';
			$CTRL['Action'] = 'DUP_CTRL_Tools_runScanValidator';
			$CTRL['Test']	= true;
			DUP_DEBUG_TestSetup($CTRL);
		?>
		<div class="params">
			<label>Recursion:</label> True
			<input type="hidden" name="scan-recursive" value="true" /><br/>
			<label>Search Path:</label> 
			<input type="text" name="scan-path" value="<?php echo DUPLICATOR_WPROOTPATH ?>" /> <br/>
		</div>
	</form>

	<!-- METHOD TEST -->
	<form>
		<?php
			$CTRL['Title']  = 'DUP_CTRL_Tools_deleteInstallerFiles';
			$CTRL['Action'] = 'DUP_CTRL_Tools_deleteInstallerFiles';
			$CTRL['Test']	= true;
			DUP_DEBUG_TestSetup($CTRL);
		?>
		<div class="params">No Params</div>
	</form>
	

</div>
