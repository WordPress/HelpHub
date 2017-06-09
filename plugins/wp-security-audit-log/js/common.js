
jQuery(document).ready(function(){
	jQuery('a.wsal-dismiss-notification').click(function(){
		var nfe = jQuery(this).parents('div:first');
		var nfn = nfe.attr('data-notice-name');
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			async: false,
			data: { action: 'AjaxDismissNotice', notice: nfn }
		});
		nfe.fadeOut();
	});
	
    jQuery('head').append('<style>.wp-submenu .dashicons-external:before{vertical-align: bottom;}</style>');
	jQuery("a[href*='page=wsal-extensions']").addClass('dashicons-before dashicons-external').css('color', '#CC4444');
	jQuery("a[href*='page=wsal-emailnotifications']").css('color', '#CC4444');
	jQuery("a[href*='page=wsal-loginusers']").css('color', '#CC4444');
	jQuery("a[href*='page=wsal-reports']").css('color', '#CC4444');
	jQuery("a[href*='page=wsal-search']").css('color', '#CC4444');
	jQuery("a[href*='page=wsal-externaldb']").css('color', '#CC4444');
});
