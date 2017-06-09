<script type="text/javascript">
/* DESCRIPTION: Methods and Objects in this file are global and common in 
 * nature use this file to place all shared methods and varibles */	

//UNIQUE NAMESPACE
Duplicator			= new Object();
Duplicator.UI		= new Object();
Duplicator.Pack		= new Object();
Duplicator.Settings = new Object();
Duplicator.Tools	= new Object();
Duplicator.Tasks	= new Object();

//GLOBAL CONSTANTS
Duplicator.DEBUG_AJAX_RESPONSE = false;
Duplicator.AJAX_TIMER = null;


/* ============================================================================
*  BASE NAMESPACE: All methods at the top of the Duplicator Namespace  
*  ============================================================================	*/

/*	----------------------------------------
*	METHOD: Starts a timer for Ajax calls */ 
Duplicator.StartAjaxTimer = function() {
	Duplicator.AJAX_TIMER = new Date();
};

/*	----------------------------------------
*	METHOD: Ends a timer for Ajax calls */ 
Duplicator.EndAjaxTimer = function() {
	var endTime = new Date();
	Duplicator.AJAX_TIMER =  (endTime.getTime()  - Duplicator.AJAX_TIMER) /1000;
};

/*	----------------------------------------
*	METHOD: Reloads the current window
*	@param data		An xhr object  */ 
Duplicator.ReloadWindow = function(data) {
	if (Duplicator.DEBUG_AJAX_RESPONSE) {
		Duplicator.Pack.ShowError('debug on', data);
	} else {
		window.location.reload(true);
	}
};

//Basic Util Methods here:
Duplicator.OpenLogWindow = function(log) {
	var logFile = log || null;
	if (logFile == null) {
		window.open('?page=duplicator-tools', 'Log Window');
	} else {
		window.open('<?php echo DUPLICATOR_SSDIR_URL; ?>' + '/' + log)
	}
};


/* ============================================================================
*  UI NAMESPACE: All methods at the top of the Duplicator Namespace  
*  ============================================================================	*/

/*  ----------------------------------------
 *  METHOD:   */
Duplicator.UI.SaveViewStateByPost = function (key, value) {
	if (key != undefined && value != undefined ) {
		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			dataType: "json",
			data: {action : 'DUP_UI_SaveViewStateByPost', key: key, value: value},
			success: function(data) {},
			error: function(data) {}
		});	
	}
}

/*  ----------------------------------------
 *  METHOD:   */
Duplicator.UI.AnimateProgressBar = function(id) {
	//Create Progress Bar
	var $mainbar   = jQuery("#" + id);
	$mainbar.progressbar({ value: 100 });
	$mainbar.height(25);
	runAnimation($mainbar);

	function runAnimation($pb) {
		$pb.css({ "padding-left": "0%", "padding-right": "90%" });
		$pb.progressbar("option", "value", 100);
		$pb.animate({ paddingLeft: "90%", paddingRight: "0%" }, 3000, "linear", function () { runAnimation($pb); });
	}
}


/*	----------------------------------------
* METHOD: Toggle MetaBoxes */ 
Duplicator.UI.ToggleMetaBox = function() {
	var $title = jQuery(this);
	var $panel = $title.parent().find('.dup-box-panel');
	var $arrow = $title.parent().find('.dup-box-arrow i');
	var key   = $panel.attr('id');
	var value = $panel.is(":visible") ? 0 : 1;
	$panel.toggle();
	Duplicator.UI.SaveViewStateByPost(key, value);
	(value) 
		? $arrow.removeClass().addClass('fa fa-caret-up') 
		: $arrow.removeClass().addClass('fa fa-caret-down');
	
}


jQuery(document).ready(function($) {
	//Init: Toggle MetaBoxes
	$('div.dup-box div.dup-box-title').each(function() { 
		var $title = $(this);
		var $panel = $title.parent().find('.dup-box-panel');
		var $arrow = $title.find('.dup-box-arrow');
		$title.click(Duplicator.UI.ToggleMetaBox); 
		($panel.is(":visible")) 
			? $arrow.html('<i class="fa fa-caret-up"></i>')
			: $arrow.html('<i class="fa fa-caret-down"></i>');
	});
	
	//Look for tooltip data
	$('i[data-tooltip!=""]').qtip({ 
		content: {
			attr: 'data-tooltip',
			title: {
				text: function() { return  $(this).attr('data-tooltip-title'); }
			}
		},
		style: {
			classes: 'qtip-light qtip-rounded qtip-shadow',
			width: 500
		},
		 position: {
			my: 'top left', 
			at: 'bottom center'
		}
	});
	
	
});	

</script>