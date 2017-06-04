
<div class="section-tests">
	<div class="section-hdr">UI CTRLS</div>

	<!-- METHOD TEST -->
	<script>
		function JS_DUP_CTRL_UI_SaveViewState(form) 
		{
			//value param must be dynamic with each submit or else 
			//wp update_option method will return false
			jQuery(form).find('input[name="value"]').val(new Date(jQuery.now()));
			return ! UNIT_TEST_RUNNING;
		}
	</script>
	<form onsubmit="return JS_DUP_CTRL_UI_SaveViewState(this);">
		<?php 
			$CTRL['Title']  = 'DUP_CTRL_UI_SaveViewState';
			$CTRL['Action'] = 'DUP_CTRL_UI_SaveViewState'; 
			$CTRL['Test']	= true;
			DUP_DEBUG_TestSetup($CTRL); 
		?>
		
		<div class="params">
			<label>Key:</label>
			<input type="text" name="key" value="dup_ctrl_test" /><br/>
			<label>Value:</label> 
			<input type="text" name="value"  /> <br/>
		</div>
	</form>
	
	
	<!-- METHOD TEST -->
	<form>
		<?php 
			$CTRL['Title']  = 'DUP_CTRL_UI_GetViewStateList';
			$CTRL['Action'] = 'DUP_CTRL_UI_GetViewStateList'; 
			$CTRL['Test']	= true;
			DUP_DEBUG_TestSetup($CTRL); 
		?>
		
		<div class="params">No Params</div>
	</form>
	
	
</div>
