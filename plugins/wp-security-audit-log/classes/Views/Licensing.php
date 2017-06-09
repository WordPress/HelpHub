<?php

class WSAL_Views_Licensing extends WSAL_AbstractView {
	
	public function GetTitle() {
		return __('Licensing', 'wp-security-audit-log');
	}
	
	public function GetIcon() {
		return 'dashicons-cart';
	}
	
	public function GetName() {
		return __('Licensing', 'wp-security-audit-log');
	}
	
	public function GetWeight() {
		return 4;
	}
	
	public function IsAccessible(){
		return !!$this->_plugin->licensing->CountPlugins();
	}

	protected function Save(){
		$this->_plugin->settings->ClearLicenses();
		if (isset($_REQUEST['license'])) 
			foreach ($_REQUEST['license'] as $name => $key)
				$this->_plugin->licensing->ActivateLicense($name, $key);
	}
	
	public function Render(){
		if(!$this->_plugin->settings->CurrentUserCan('edit')){
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'wp-security-audit-log') );
		}
		if(isset($_POST['submit'])){
			try {
				$this->Save();
				?><div class="updated"><p><?php _e('Settings have been saved.', 'wp-security-audit-log'); ?></p></div><?php
			}catch(Exception $ex){
				?><div class="error"><p><?php _e('Error: ', 'wp-security-audit-log'); ?><?php echo $ex->getMessage(); ?></p></div><?php
			}
		}
		?><form id="audit-log-licensing" method="post">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			
			<table class="wp-list-table widefat fixed">
				<thead>
					<tr><th>Plugin</th><th>License</th><th></th></tr>
				</thead><tbody>
					<?php $counter = 0; ?>
					<?php foreach($this->_plugin->licensing->Plugins() as $name => $plugin){ ?>
						<?php $licenseKey = trim($this->_plugin->settings->GetLicenseKey($name)); ?>
						<?php $licenseStatus = trim($this->_plugin->settings->GetLicenseStatus($name)); ?>
						<?php $licenseErrors = trim($this->_plugin->settings->GetLicenseErrors($name)); ?>
						<tr class="<?php echo ($counter++ % 2 === 0) ? 'alternate' : ''; ?>">
							<td>
								<a href="<?php echo esc_attr($plugin['PluginData']['PluginURI']); ?>" target="_blank">
									<?php echo esc_html($plugin['PluginData']['Name']); ?> 
								</a><br/><small><b>
									<?php _e('Version', 'wp-security-audit-log'); ?>
									<?php echo esc_html($plugin['PluginData']['Version']); ?>
								</b></small>
							</td><td>
								<input type="text" style="width: 360px; margin: 6px 0;"
									   name="license[<?php echo esc_attr($name); ?>]"
									   value="<?php echo esc_attr($licenseKey); ?>"/>
							</td><td style="vertical-align: middle;">
								<?php if($licenseKey){ ?>
									<?php if($licenseStatus === 'valid'){ ?>
										<?php _e('Active', 'wp-security-audit-log'); ?>
									<?php }else{ ?>
										<?php _e('Inactive', 'wp-security-audit-log'); ?><br/>
										<small><?php echo esc_html($licenseErrors); ?></small>
									<?php } ?>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody><tfoot>
					<tr><th>Plugin</th><th>License</th><th></th></tr>
				</tfoot>
			</table>
			<?php submit_button(); ?>
		</form><?php
	}

}