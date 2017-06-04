jQuery(document).ready(function(){
	var RemoveSecToken = function(){
		var $this = jQuery(this).parents('span:first');
		$this.addClass('sectoken-del').fadeOut('fast', function(){
			$this.remove();
		});
	};
	
	jQuery('#ViewerQueryBox, #EditorQueryBox, #ExRoleQueryBox, #ExUserQueryBox, #CustomQueryBox, #IpAddrQueryBox').keydown(function(event){
		if(event.keyCode === 13) {
			var type = jQuery(this).attr('id').substr(0, 6);
			jQuery('#'+type+'QueryAdd').click();
			return false;
		}
	});
	
	jQuery('#ViewerQueryAdd, #EditorQueryAdd, #ExRoleQueryAdd, #ExUserQueryAdd, #CustomQueryAdd, #IpAddrQueryAdd').click(function(){
		var type = jQuery(this).attr('id').substr(0, 6);
		var value = jQuery.trim(jQuery('#'+type+'QueryBox').val());
		var existing = jQuery('#'+type+'List input').filter(function() { return this.value === value; });
		
		if(!value || existing.length)return; // if value is empty or already used, stop here
		
		jQuery('#'+type+'QueryBox, #'+type+'QueryAdd').attr('disabled', true);
		jQuery.post(jQuery('#ajaxurl').val(), {action: 'AjaxCheckSecurityToken', token: value}, function(data){
			jQuery('#'+type+'QueryBox, #'+type+'QueryAdd').attr('disabled', false);
			if (type != 'Custom' && type != 'IpAddr') {
				if(data === 'other') {
					alert('The specified token is not a user nor a role!');
					jQuery('#'+type+'QueryBox').val('');
					return;
				}	
			}
			jQuery('#'+type+'QueryBox').val('');
			jQuery('#'+type+'List').append(jQuery('<span class="sectoken-'+data+'"/>').text(value).append(
				jQuery('<input type="hidden" name="'+type+'s[]"/>').val(value),
				jQuery('<a href="javascript:;" title="Remove">&times;</a>').click(RemoveSecToken)
			));
		});
	});
	
	jQuery('#ViewerList>span>a, #EditorList>span>a, #ExRoleList>span>a, #ExUserList>span>a, #CustomList>span>a, #IpAddrList>span>a').click(RemoveSecToken);
	
	jQuery('#RestrictAdmins').change(function(){
		var user = jQuery('#RestrictAdminsDefaultUser').val();
		var fltr = function() { return this.value === user; };
		if (this.checked) {
			if (jQuery('#EditorList input').filter(fltr).length === 1) {
				jQuery('#EditorList .sectoken-user').each(function(){
		            if (jQuery(this).find('input[type=hidden]').val() === user) {
		            	jQuery(this).remove();
		            }                
		        });
			}
			jQuery('#EditorList').append(jQuery('<span class="sectoken-user"/>').text(user).prepend(jQuery('<input type="hidden" name="Editors[]"/>').val(user)));
		} else if (!this.checked){
			jQuery('#EditorList .sectoken-user').each(function(){
	            if (jQuery(this).find('input[type=hidden]').val() === user) {
	            	jQuery(this).remove();
	            }                
	        });
		}
	});

	var usersUrl = ajaxurl + "?action=AjaxGetAllUsers";
	jQuery("#ExUserQueryBox").autocomplete({
	    source: usersUrl,
	    minLength:1
	});

	var rolesUrl = ajaxurl + "?action=AjaxGetAllRoles";
	jQuery("#ExRoleQueryBox").autocomplete({
	    source: rolesUrl,
	    minLength:1
	});	
});
