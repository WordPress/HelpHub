<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

# SDK uses namespacing - requires PHP 5.3 (actually the SDK states its requirements as 5.3.3)
use OpenCloud\OpenStack;

require_once(UPDRAFTPLUS_DIR.'/methods/openstack-base.php');

class UpdraftPlus_BackupModule_openstack extends UpdraftPlus_BackupModule_openstack_base {

	public function __construct() {
		# 4th parameter is a relative (to UPDRAFTPLUS_DIR) logo URL, which should begin with /, should we get approved for use of the OpenStack logo in future (have requested info)
		parent::__construct('openstack', 'OpenStack', 'OpenStack (Swift)', '');
	}

	# $opts: 'tenant', 'user', 'password', 'authurl', (optional) 'region'
	public function get_service($opts, $useservercerts = false, $disablesslverify = null) {

		# 'tenant', 'user', 'password', 'authurl', 'path', (optional) 'region'
		extract($opts);

		if (null === $disablesslverify) $disablesslverify = UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify');

		if (empty($user) || empty($password) || empty($authurl)) throw new Exception(__('Authorisation failed (check your credentials)', 'updraftplus'));

		require_once(UPDRAFTPLUS_DIR.'/vendor/autoload.php');
		global $updraftplus;
		$updraftplus->log("OpenStack authentication URL: ".$authurl);

		$client = new OpenStack($authurl, array(
			'username' => $user,
			'password' => $password,
			'tenantName' => $tenant
		));
		$this->client = $client;

		if ($disablesslverify) {
			$client->setSslVerification(false);
		} else {
			if ($useservercerts) {
				$client->setConfig(array($client::SSL_CERT_AUTHORITY => false));
			} else {
				$client->setSslVerification(UPDRAFTPLUS_DIR.'/includes/cacert.pem', true, 2);
			}
		}

		$client->authenticate();

		if (empty($region)) {
			$catalog = $client->getCatalog();
			if (!empty($catalog)) {
				$items = $catalog->getItems();
				if (is_array($items)) {
					foreach ($items as $item) {
						$name = $item->getName();
						$type = $item->getType();
						if ('swift' != $name || 'object-store' != $type) continue;
						$eps = $item->getEndpoints();
						if (!is_array($eps)) continue;
						foreach ($eps as $ep) {
							if (is_object($ep) && !empty($ep->region)) {
								$region = $ep->region;
							}
						}
					}
				}
			}
		}

		$this->region = $region;

		return $client->objectStoreService('swift', $region);

	}

	public function get_credentials() {
		return array('updraft_openstack');
	}

	public function get_opts() {
		global $updraftplus;
		$opts = $updraftplus->get_job_option('updraft_openstack');
		if (!is_array($opts)) $opts = array('user' => '', 'authurl' => '', 'password' => '', 'tenant' => '', 'path' => '', 'region' => '');
		return $opts;
	}

	public function config_print_middlesection() {
		$opts = $this->get_opts();
		?>
		<tr class="updraftplusmethod <?php echo $this->method;?>">
		<th></th>
			<td>
				<p><?php _e('Get your access credentials from your OpenStack Swift provider, and then pick a container name to use for storage. This container will be created for you if it does not already exist.','updraftplus');?> <a href="https://updraftplus.com/faqs/there-appear-to-be-lots-of-extra-files-in-my-rackspace-cloud-files-container/"><?php _e('Also, you should read this important FAQ.', 'updraftplus'); ?></a></p>
			</td>
		</tr>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th><?php echo ucfirst(__('authentication URI', 'updraftplus'));?>:</th>
			<td><input data-updraft_settings_test="authurl" type="text" autocomplete="off" style="width: 364px" id="updraft_openstack_authurl" name="updraft_openstack[authurl]" value="<?php echo htmlspecialchars($opts['authurl']) ?>" />
			<br>
			<em><?php echo _x('This needs to be a v2 (Keystone) authentication URI; v1 (Swauth) is not supported.', 'Keystone and swauth are technical terms which cannot be translated', 'updraftplus');?></em>
			</td>
		</tr>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th><a href="http://docs.openstack.org/openstack-ops/content/projects_users.html" title="<?php _e('Follow this link for more information', 'updraftplus');?>"><?php _e('Tenant', 'updraftplus');?></a>:</th>
			<td><input data-updraft_settings_test="tenant" type="text" autocomplete="off" style="width: 364px" id="updraft_openstack_tenant" name="updraft_openstack[tenant]" value="<?php echo htmlspecialchars($opts['tenant']) ?>" />
			</td>
		</tr>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th><?php _e('Region', 'updraftplus');?>:</th>
			<td><input data-updraft_settings_test="region" type="text" autocomplete="off" style="width: 364px" id="updraft_openstack_region" name="updraft_openstack[region]" value="<?php echo htmlspecialchars($opts['region']) ?>" />
			<br>
			<em><?php _e('Leave this blank, and a default will be chosen.', 'updraftplus');?></em>
			</td>
		</tr>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th><?php _e('Username', 'updraftplus');?>:</th>
			<td><input data-updraft_settings_test="user" type="text" autocomplete="off" style="width: 364px" id="updraft_openstack_user" name="updraft_openstack[user]" value="<?php echo htmlspecialchars($opts['user']) ?>" />
			</td>
		</tr>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th><?php _e('Password', 'updraftplus');?>:</th>
			<td><input data-updraft_settings_test="password" type="<?php echo apply_filters('updraftplus_admin_secret_field_type', 'password'); ?>" autocomplete="off" style="width: 364px" id="updraft_openstack_password" name="updraft_openstack[password]" value="<?php echo htmlspecialchars($opts['password']); ?>" />
			</td>
		</tr>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th><?php echo __('Container', 'updraftplus');?>:</th>
			<td><input data-updraft_settings_test="path" type="text" style="width: 364px" name="updraft_openstack[path]" id="updraft_openstack_path" value="<?php echo htmlspecialchars($opts['path']); ?>" /></td>
		</tr>
		<?php
	}

	public function credentials_test($posted_settings) {

		if (empty($posted_settings['user'])) {
			printf(__("Failure: No %s was given.",'updraftplus'), __('username','updraftplus'));
			return;
		}

		if (empty($posted_settings['password'])) {
			printf(__("Failure: No %s was given.",'updraftplus'), __('password','updraftplus'));
			return;
		}

		if (empty($posted_settings['tenant'])) {
			printf(__("Failure: No %s was given.",'updraftplus'), _x('tenant','"tenant" is a term used with OpenStack storage - Google for "OpenStack tenant" to get more help on its meaning', 'updraftplus'));
			return;
		}

		if (empty($posted_settings['authurl'])) {
			printf(__("Failure: No %s was given.",'updraftplus'), __('authentication URI', 'updraftplus'));
			return;
		}

		$opts = array(
			'user' => stripslashes($posted_settings['user']),
			'password' => stripslashes($posted_settings['password']),
			'authurl' => stripslashes($posted_settings['authurl']),
			'tenant' => stripslashes($posted_settings['tenant']),
			'region' => (!empty($posted_settings['region'])) ? $posted_settings['region'] : '',
		);

		$this->credentials_test_go($opts, stripslashes($posted_settings['path']), $posted_settings['useservercerts'], $posted_settings['disableverify']);
	}

}
