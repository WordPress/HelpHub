<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

class UpdraftPlus {

	public $version;

	public $plugin_title = 'UpdraftPlus Backup/Restore';

	// Choices will be shown in the admin menu in the order used here
	public $backup_methods = array(
		'updraftvault' => 'UpdraftPlus Vault',
		'dropbox' => 'Dropbox',
		's3' => 'Amazon S3',
		'cloudfiles' => 'Rackspace Cloud Files',
		'googledrive' => 'Google Drive',
		'onedrive' => 'Microsoft OneDrive',
		'ftp' => 'FTP',
		'azure' => 'Microsoft Azure',
		'sftp' => 'SFTP / SCP',
		'googlecloud' => 'Google Cloud',
		'webdav' => 'WebDAV',
		's3generic' => 'S3-Compatible (Generic)',
		'openstack' => 'OpenStack (Swift)',
		'dreamobjects' => 'DreamObjects',
		'email' => 'Email'
	);

	public $errors = array();
	public $nonce;
	public $logfile_name = "";
	public $logfile_handle = false;
	public $backup_time;
	public $job_time_ms;

	public $opened_log_time;
	private $backup_dir;

	private $jobdata;

	public $something_useful_happened = false;
	public $have_addons = false;

	// Used to schedule resumption attempts beyond the tenth, if needed
	public $current_resumption;
	public $newresumption_scheduled = false;

	public $cpanel_quota_readable = false;

	public $error_reporting_stop_when_logged = false;
	
	private $combine_jobs_around;

	public function __construct() {

		// Bitcasa support is deprecated
		if (is_file(UPDRAFTPLUS_DIR.'/addons/bitcasa.php')) $this->backup_methods['bitcasa'] = 'Bitcasa';
		
		// Copy.Com will be closed on 1st May 2016
		if (is_file(UPDRAFTPLUS_DIR.'/addons/copycom.php')) $this->backup_methods['copycom'] = 'Copy.Com';

		// Initialisation actions - takes place on plugin load

		if ($fp = fopen(UPDRAFTPLUS_DIR.'/updraftplus.php', 'r')) {
			$file_data = fread($fp, 1024);
			if (preg_match("/Version: ([\d\.]+)(\r|\n)/", $file_data, $matches)) {
				$this->version = $matches[1];
			}
			fclose($fp);
		}

		# Create admin page
		add_action('init', array($this, 'handle_url_actions'));
		// Run earlier than default - hence earlier than other components
		// admin_menu runs earlier, and we need it because options.php wants to use $updraftplus_admin before admin_init happens
		add_action(apply_filters('updraft_admin_menu_hook', 'admin_menu'), array($this, 'admin_menu'), 9);
		# Not a mistake: admin-ajax.php calls only admin_init and not admin_menu
		add_action('admin_init', array($this, 'admin_menu'), 9);

		# The two actions which we schedule upon
		add_action('updraft_backup', array($this, 'backup_files'));
		add_action('updraft_backup_database', array($this, 'backup_database'));

		# The three actions that can be called from "Backup Now"
		add_action('updraft_backupnow_backup', array($this, 'backupnow_files'));
		add_action('updraft_backupnow_backup_database', array($this, 'backupnow_database'));
		add_action('updraft_backupnow_backup_all', array($this, 'backup_all'));

		# backup_all as an action is legacy (Oct 2013) - there may be some people who wrote cron scripts to use it
		add_action('updraft_backup_all', array($this, 'backup_all'));

		# This is our runs-after-backup event, whose purpose is to see if it succeeded or failed, and resume/mom-up etc.
		add_action('updraft_backup_resume', array($this, 'backup_resume'), 10, 3);

		# If files + db are on different schedules but are scheduled for the same time, then combine them
		add_filter('schedule_event', array($this, 'schedule_event'));
		
		add_action('plugins_loaded', array($this, 'plugins_loaded'));

		# Prevent iThemes Security from telling people that they have no backups (and advertising them another product on that basis!)
		add_filter('itsec_has_external_backup', '__return_true', 999);
		add_filter('itsec_external_backup_link', array($this, 'itsec_external_backup_link'), 999);
		add_filter('itsec_scheduled_external_backup', array($this, 'itsec_scheduled_external_backup'), 999);

		# register_deactivation_hook(__FILE__, array($this, 'deactivation'));

		if (!empty($_POST) && !empty($_GET['udm_action']) && 'vault_disconnect' == $_GET['udm_action'] && !empty($_POST['udrpc_message']) && !empty($_POST['reset_hash'])) {
			add_action('wp_loaded', array($this, 'wp_loaded_vault_disconnect'), 1);
		}

	}

	public function itsec_scheduled_external_backup($x) { return (!wp_next_scheduled('updraft_backup')) ? false : true; }
	public function itsec_external_backup_link($x) { return UpdraftPlus_Options::admin_page_url().'?page=updraftplus'; }

	public function wp_loaded_vault_disconnect() {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_updraftvault');
		if (is_array($opts) && !empty($opts['token']) && $opts['token']) {
			$site_id = $this->siteid();
			$hash = hash('sha256', $site_id.':::'.$opts['token']);
			if ($hash == $_POST['reset_hash']) {
				$this->log('This site has been remotely disconnected from UpdraftPlus Vault');
				require_once(UPDRAFTPLUS_DIR.'/methods/updraftvault.php');
				$vault = new UpdraftPlus_BackupModule_updraftvault();
				$vault->ajax_vault_disconnect();
				// Die, as the vault method has already sent output
				die;
			} else {
					$this->log('An invalid request was received to disconnect this site from UpdraftPlus Vault');
			}
		}
		echo json_encode(array('disconnected' => 0));
		die;
	}

	// Gets an RPC object, and sets some defaults on it that we always want
	public function get_udrpc($indicator_name = 'migrator.updraftplus.com') {
		if (!class_exists('UpdraftPlus_Remote_Communications')) require_once(apply_filters('updraftplus_class_udrpc_path', UPDRAFTPLUS_DIR.'/includes/class-udrpc.php', $this->version));
		$ud_rpc = new UpdraftPlus_Remote_Communications($indicator_name);
		$ud_rpc->set_can_generate(true);
		return $ud_rpc;
	}

	public function ensure_phpseclib($classes = false, $class_paths = false) {

		if (false === strpos(get_include_path(), UPDRAFTPLUS_DIR.'/includes/phpseclib')) set_include_path(UPDRAFTPLUS_DIR.'/includes/phpseclib'.PATH_SEPARATOR.get_include_path());

		$this->no_deprecation_warnings_on_php7();

		if ($classes) {
			$any_missing = false;
			if (is_string($classes)) $classes = array($classes);
			foreach ($classes as $cl) {
				if (!class_exists($cl)) $any_missing = true;
			}
			if (!$any_missing) return;
		}

		if ($class_paths) {
			if (is_string($class_paths)) $class_paths = array($class_paths);
			foreach ($class_paths as $cp) {
				require_once(UPDRAFTPLUS_DIR.'/includes/phpseclib/'.$cp.'.php');
			}
		}
	}

	// Ugly, but necessary to prevent debug output breaking the conversation when the user has debug turned on
	private function no_deprecation_warnings_on_php7() {
		// PHP_MAJOR_VERSION is defined in PHP 5.2.7+
		// We don't test for PHP > 7 because the specific deprecated element will be removed in PHP 8 - and so no warning should come anyway (and we shouldn't suppress other stuff until we know we need to).
		if (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION == 7) {
			$old_level = error_reporting();
			$new_level = $old_level & ~E_DEPRECATED;
			if ($old_level != $new_level) error_reporting($new_level);
			$this->no_deprecation_warnings = true;
		}
	}

	public function close_browser_connection($txt = '') {
		// Close browser connection so that it can resume AJAX polling
		header('Content-Length: '.((!empty($txt)) ? 4+strlen($txt) : '0'));
		header('Connection: close');
		header('Content-Encoding: none');
		if (session_id()) session_write_close();
		echo "\r\n\r\n";
		echo $txt;
		// These two added - 19-Feb-15 - started being required on local dev machine, for unknown reason (probably some plugin that started an output buffer).
		if (ob_get_level()) ob_end_flush();
		flush();
	}

	// Returns the number of bytes free, if it can be detected; otherwise, false
	// Presently, we only detect CPanel. If you know of others, then feel free to contribute!
	public function get_hosting_disk_quota_free() {
		if (!@is_dir('/usr/local/cpanel') || $this->detect_safe_mode() || !function_exists('popen') || (!@is_executable('/usr/local/bin/perl') && !@is_executable('/usr/local/cpanel/3rdparty/bin/perl'))) return false;

		$perl = (@is_executable('/usr/local/cpanel/3rdparty/bin/perl')) ? '/usr/local/cpanel/3rdparty/bin/perl' : '/usr/local/bin/perl';

		$exec = "UPDRAFTPLUSKEY=updraftplus $perl ".UPDRAFTPLUS_DIR."/includes/get-cpanel-quota-usage.pl";

		$handle = @popen($exec, 'r');
		if (!is_resource($handle)) return false;

		$found = false;
		$lines = 0;
		while (false === $found && !feof($handle) && $lines<100) {
			$lines++;
			$w = fgets($handle);
			# Used, limit, remain
			if (preg_match('/RESULT: (\d+) (\d+) (\d+) /', $w, $matches)) { $found = true; }
		}
		$ret = pclose($handle);
		if (false === $found ||$ret != 0) return false;

		if ((int)$matches[2]<100 || ($matches[1] + $matches[3] != $matches[2])) return false;

		$this->cpanel_quota_readable = true;

		return $matches;
	}

	public function last_modified_log() {
		$updraft_dir = $this->backups_dir_location();

		$log_file = '';
		$mod_time = false;
		$nonce = '';

		if ($handle = @opendir($updraft_dir)) {
			while (false !== ($entry = readdir($handle))) {
				// The latter match is for files created internally by zipArchive::addFile
				if (preg_match('/^log\.([a-z0-9]+)\.txt$/i', $entry, $matches)) {
					$mtime = filemtime($updraft_dir.'/'.$entry);
					if ($mtime > $mod_time) {
						$mod_time = $mtime;
						$log_file = $updraft_dir.'/'.$entry;
						$nonce = $matches[1];
					}
				}
			}
			@closedir($handle);
		}

		return array($mod_time, $log_file, $nonce);
	}

	// This function may get called multiple times, so write accordingly
	public function admin_menu() {
		// We are in the admin area: now load all that code
		global $updraftplus_admin;
		if (empty($updraftplus_admin)) require_once(UPDRAFTPLUS_DIR.'/admin.php');

		if (isset($_GET['wpnonce']) && isset($_GET['page']) && isset($_GET['action']) && $_GET['page'] == 'updraftplus' && $_GET['action'] == 'downloadlatestmodlog' && wp_verify_nonce($_GET['wpnonce'], 'updraftplus_download')) {

			list ($mod_time, $log_file, $nonce) = $this->last_modified_log();

			if ($mod_time >0) {
				if (is_readable($log_file)) {
					header('Content-type: text/plain');
					readfile($log_file);
					exit;
				} else {
					add_action('all_admin_notices', array($this,'show_admin_warning_unreadablelog') );
				}
			} else {
				add_action('all_admin_notices', array($this,'show_admin_warning_nolog') );
			}
		}

	}

	public function modify_http_options($opts) {

		if (!is_array($opts)) return $opts;

		if (!UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts')) $opts['sslcertificates'] = UPDRAFTPLUS_DIR.'/includes/cacert.pem';

		$opts['sslverify'] = UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify') ? false : true;

		return $opts;

	}

	// Handle actions passed on to method plugins; e.g. Google OAuth 2.0 - ?action=updraftmethod-googledrive-auth&page=updraftplus
	// Nov 2013: Google's new cloud console, for reasons as yet unknown, only allows you to enter a redirect_uri with a single URL parameter... thus, we put page second, and re-add it if necessary. Apr 2014: Bitcasa already do this, so perhaps it is part of the OAuth2 standard or best practice somewhere.
	// Also handle action=downloadlog
	public function handle_url_actions() {

		// First, basic security check: must be an admin page, with ability to manage options, with the right parameters
		// Also, only on GET because WordPress on the options page repeats parameters sometimes when POST-ing via the _wp_referer field
		if (isset($_SERVER['REQUEST_METHOD']) && 'GET' == $_SERVER['REQUEST_METHOD'] && isset($_GET['action'])) {
			if (preg_match("/^updraftmethod-([a-z]+)-([a-z]+)$/", $_GET['action'], $matches) && file_exists(UPDRAFTPLUS_DIR.'/methods/'.$matches[1].'.php') && UpdraftPlus_Options::user_can_manage()) {
				$_GET['page'] = 'updraftplus';
				$_REQUEST['page'] = 'updraftplus';
				$method = $matches[1];
				require_once(UPDRAFTPLUS_DIR.'/methods/'.$method.'.php');
				$call_class = "UpdraftPlus_BackupModule_".$method;
				$call_method = "action_".$matches[2];
				$backup_obj = new $call_class;
				add_action('http_request_args', array($this, 'modify_http_options'));
				try {
					if (method_exists($backup_obj, $call_method)) {
						call_user_func(array($backup_obj, $call_method));
					} elseif (method_exists($backup_obj, 'action_handler')) {
						call_user_func(array($backup_obj, 'action_handler'), $matches[2]);
					}
				} catch (Exception $e) {
					$this->log(sprintf(__("%s error: %s", 'updraftplus'), $method, $e->getMessage().' ('.$e->getCode().')', 'error'));
				}
				remove_action('http_request_args', array($this, 'modify_http_options'));
			} elseif (isset( $_GET['page'] ) && $_GET['page'] == 'updraftplus' && $_GET['action'] == 'downloadlog' && isset($_GET['updraftplus_backup_nonce']) && preg_match("/^[0-9a-f]{12}$/",$_GET['updraftplus_backup_nonce']) && UpdraftPlus_Options::user_can_manage()) {
				// No WordPress nonce is needed here or for the next, since the backup is already nonce-based
				$updraft_dir = $this->backups_dir_location();
				$log_file = $updraft_dir.'/log.'.$_GET['updraftplus_backup_nonce'].'.txt';
				if (is_readable($log_file)) {
					header('Content-type: text/plain');
					if (!empty($_GET['force_download'])) header('Content-Disposition: attachment; filename="'.basename($log_file).'"');
					readfile($log_file);
					exit;
				} else {
					add_action('all_admin_notices', array($this,'show_admin_warning_unreadablelog') );
				}
			} elseif (isset( $_GET['page'] ) && $_GET['page'] == 'updraftplus' && $_GET['action'] == 'downloadfile' && isset($_GET['updraftplus_file']) && preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-db([0-9]+)?+\.(gz\.crypt)$/i', $_GET['updraftplus_file']) && UpdraftPlus_Options::user_can_manage()) {
				// Though this (venerable) code uses the action 'downloadfile', in fact, it's not that general: it's just for downloading a decrypted copy of encrypted databases, and nothing else
				$updraft_dir = $this->backups_dir_location();
				$file = $_GET['updraftplus_file'];
				$spool_file = $updraft_dir.'/'.basename($file);
				if (is_readable($spool_file)) {
					$dkey = isset($_GET['decrypt_key']) ? stripslashes($_GET['decrypt_key']) : '';
					$this->spool_file($spool_file, $dkey);
					exit;
				} else {
					add_action('all_admin_notices', array($this,'show_admin_warning_unreadablefile') );
				}
			} elseif ($_GET['action'] == 'updraftplus_spool_file' && !empty($_GET['what']) && !empty($_GET['backup_timestamp']) && is_numeric($_GET['backup_timestamp']) && UpdraftPlus_Options::user_can_manage()) {
				// At some point, it may be worth merging this with the previous section
				$updraft_dir = $this->backups_dir_location();
				
				$findex = isset($_GET['findex']) ? (int)$_GET['findex'] : 0;
				$backup_timestamp = $_GET['backup_timestamp'];
				$what = $_GET['what'];
				
				$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');

				$filename = null;
				if (isset($backup_history[$backup_timestamp])) {
					if ('db' != substr($what, 0, 2)) {
						$backupable_entities = $this->get_backupable_file_entities();
						if (!isset($backupable_entities[$what])) $filename = false;
					}
					if (false !== $filename && isset($backup_history[$backup_timestamp][$what])) {
						if (is_string($backup_history[$backup_timestamp][$what]) && 0 == $findex) {
							$filename = $backup_history[$backup_timestamp][$what];
						} elseif (isset($backup_history[$backup_timestamp][$what][$findex])) {
							$filename = $backup_history[$backup_timestamp][$what][$findex];
						}
					}
				}
				if (empty($filename) || !is_readable($updraft_dir.'/'.basename($filename))) {
					echo json_encode(array('result' => __('UpdraftPlus notice:','updraftplus').' '.__('The given file was not found, or could not be read.','updraftplus')));
					exit;
				}
				
				$dkey = isset($_GET['decrypt_key']) ? stripslashes($_GET['decrypt_key']) : "";
				
				$this->spool_file($updraft_dir.'/'.basename($filename), $dkey);
				exit;
				
			}
		}
	}

	public function get_table_prefix($allow_override = false) {
		global $wpdb;
		if (is_multisite() && !defined('MULTISITE')) {
			# In this case (which should only be possible on installs upgraded from pre WP 3.0 WPMU), $wpdb->get_blog_prefix() cannot be made to return the right thing. $wpdb->base_prefix is not explicitly marked as public, so we prefer to use get_blog_prefix if we can, for future compatibility.
			$prefix = $wpdb->base_prefix;
		} else {
			$prefix = $wpdb->get_blog_prefix(0);
		}
		return ($allow_override) ? apply_filters('updraftplus_get_table_prefix', $prefix) : $prefix;
	}

	public function siteid() {
		$sid = get_site_option('updraftplus-addons_siteid');
		if (!is_string($sid) || empty($sid)) {
			$sid = md5(rand().microtime(true).home_url());
			update_site_option('updraftplus-addons_siteid', $sid);
		}
		return $sid;
	}

	public function show_admin_warning_unreadablelog() {
		global $updraftplus_admin;
		$updraftplus_admin->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> '.__('The log file could not be read.','updraftplus'));
	}

	public function show_admin_warning_nolog() {
		global $updraftplus_admin;
		$updraftplus_admin->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> '.__('No log files were found.','updraftplus'));
	}

	public function show_admin_warning_unreadablefile() {
		global $updraftplus_admin;
		$updraftplus_admin->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> '.__('The given file was not found, or could not be read.','updraftplus'));
	}

	public function plugins_loaded() {

		// Tell WordPress where to find the translations
		load_plugin_textdomain('updraftplus', false, basename(dirname(__FILE__)).'/languages/');
		
		// The Google Analyticator plugin does something horrible: loads an old version of the Google SDK on init, always - which breaks us
		if ((defined('DOING_CRON') && DOING_CRON) || (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['subaction']) && 'backupnow' == $_REQUEST['subaction']) || (isset($_GET['page']) && $_GET['page'] == 'updraftplus')) {
			remove_action('init', 'ganalyticator_stats_init');
			// Appointments+ does the same; but provides a cleaner way to disable it
			@define('APP_GCAL_DISABLE', true);
		}
		
		if (file_exists(UPDRAFTPLUS_DIR.'/central/bootstrap.php')) require_once(UPDRAFTPLUS_DIR.'/central/bootstrap.php');
		
	}
	
	// Cleans up temporary files found in the updraft directory (and some in the site root - pclzip)
	// Always cleans up temporary files over 12 hours old.
	// With parameters, also cleans up those.
	// Also cleans out old job data older than 12 hours old (immutable value)
	// include_cachelist also looks to match any files of cached file analysis data
	public function clean_temporary_files($match = '', $older_than = 43200, $include_cachelist = false) {
		# Clean out old job data
		if ($older_than > 10000) {
			global $wpdb;

			$all_jobs = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'updraft_jobdata_%'", ARRAY_A);
			foreach ($all_jobs as $job) {
				$val = maybe_unserialize($job['option_value']);
				# TODO: Can simplify this after a while (now all jobs use job_time_ms) - 1 Jan 2014
				$delete = false;
				if (!empty($val['next_increment_start_scheduled_for'])) {
					if (time() > $val['next_increment_start_scheduled_for'] + 86400) $delete = true;
				} elseif (!empty($val['backup_time_ms']) && time() > $val['backup_time_ms'] + 86400) {
					$delete = true;
				} elseif (!empty($val['job_time_ms']) && time() > $val['job_time_ms'] + 86400) {
					$delete = true;
				} elseif (!empty($val['job_type']) && 'backup' != $val['job_type'] && empty($val['backup_time_ms']) && empty($val['job_time_ms'])) {
					$delete = true;
				}
				if ($delete) delete_option($job['option_name']);
			}
		}
		$updraft_dir = $this->backups_dir_location();
		$now_time=time();
		if ($handle = opendir($updraft_dir)) {
			while (false !== ($entry = readdir($handle))) {
				$manifest_match = preg_match("/^udmanifest$match\.json$/i", $entry);
				// This match is for files created internally by zipArchive::addFile
				$ziparchive_match = preg_match("/$match([0-9]+)?\.zip\.tmp\.([A-Za-z0-9]){6}?$/i", $entry);
				// zi followed by 6 characters is the pattern used by /usr/bin/zip on Linux systems. It's safe to check for, as we have nothing else that's going to match that pattern.
				$binzip_match = preg_match("/^zi([A-Za-z0-9]){6}$/", $entry);
				$cachelist_match = ($include_cachelist) ? preg_match("/$match-cachelist-.*.tmp$/i", $entry) : false;
				# Temporary files from the database dump process - not needed, as is caught by the catch-all
				# $table_match = preg_match("/${match}-table-(.*)\.table(\.tmp)?\.gz$/i", $entry);
				# The gz goes in with the txt, because we *don't* want to reap the raw .txt files
				if ((preg_match("/$match\.(tmp|table|txt\.gz)(\.gz)?$/i", $entry) || $cachelist_match || $ziparchive_match || $binzip_match || $manifest_match) && is_file($updraft_dir.'/'.$entry)) {
					// We delete if a parameter was specified (and either it is a ZipArchive match or an order to delete of whatever age), or if over 12 hours old
					if (($match && ($ziparchive_match || $binzip_match || $cachelist_match || $manifest_match || 0 == $older_than) && $now_time-filemtime($updraft_dir.'/'.$entry) >= $older_than) || $now_time-filemtime($updraft_dir.'/'.$entry)>43200) {
						$this->log("Deleting old temporary file: $entry");
						@unlink($updraft_dir.'/'.$entry);
					}
				}
			}
			@closedir($handle);
		}
		# Depending on the PHP setup, the current working directory could be ABSPATH or wp-admin - scan both
		# Since 1.9.32, we set them to go into $updraft_dir, so now we must check there too. Checking the old ones doesn't hurt, as other backup plugins might leave their temporary files around can cause issues with huge files.
		foreach (array(ABSPATH, ABSPATH.'wp-admin/', $updraft_dir.'/') as $path) {
			if ($handle = opendir($path)) {
				while (false !== ($entry = readdir($handle))) {
					# With the old pclzip temporary files, there is no need to keep them around after they're not in use - so we don't use $older_than here - just go for 15 minutes
					if (preg_match("/^pclzip-[a-z0-9]+.tmp$/", $entry) && $now_time-filemtime($path.$entry) >= 900) {
						$this->log("Deleting old PclZip temporary file: $entry");
						@unlink($path.$entry);
					}
				}
				@closedir($handle);
			}
		}
	}

	public function backup_time_nonce($nonce = false) {
		$this->job_time_ms = microtime(true);
		$this->backup_time = time();
		if (false === $nonce) $nonce = substr(md5(time().rand()), 20);
		$this->nonce = $nonce;
		return $nonce;
	}
	
	public function get_wordpress_version() {
		static $got_wp_version = false;
		if (!$got_wp_version) {
			global $wp_version;
			@include(ABSPATH.WPINC.'/version.php');
			$got_wp_version = $wp_version;
		}
		return $got_wp_version;
	}

	public function logfile_open($nonce) {

		//set log file name and open log file
		$updraft_dir = $this->backups_dir_location();
		$this->logfile_name =  $updraft_dir."/log.$nonce.txt";

		if (file_exists($this->logfile_name)) {
			$seek_to = max((filesize($this->logfile_name) - 340), 1);
			$handle = fopen($this->logfile_name, 'r');
			if (is_resource($handle)) {
				# Returns 0 on success
				if (0 === @fseek($handle, $seek_to)) {
					$bytes_back = filesize($this->logfile_name) - $seek_to;
					# Return to the end of the file
					$read_recent = fread($handle, $bytes_back);
					# Move to end of file - ought to be redundant
					if (false !== strpos($read_recent, 'The backup apparently succeeded') && false !== strpos($read_recent, 'and is now complete')) {
						$this->backup_is_already_complete = true;
					}
				}
				fclose($handle);
			}
		}

		$this->logfile_handle = fopen($this->logfile_name, 'a');

		$this->opened_log_time = microtime(true);
		$this->log('Opened log file at time: '.date('r').' on '.network_site_url());
		global $wpdb;
		$wp_version = $this->get_wordpress_version();

		$mysql_version = $wpdb->db_version();

		$safe_mode = $this->detect_safe_mode();

		$memory_limit = ini_get('memory_limit');
		$memory_usage = round(@memory_get_usage(false)/1048576, 1);
		$memory_usage2 = round(@memory_get_usage(true)/1048576, 1);

		// Attempt to raise limit to avoid false positives
		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);
		$max_execution_time = (int)@ini_get("max_execution_time");

		$logline = "UpdraftPlus WordPress backup plugin (https://updraftplus.com): ".$this->version." WP: ".$wp_version." PHP: ".phpversion()." (".@php_uname().") MySQL: $mysql_version WPLANG: ".get_locale()." Server: ".$_SERVER["SERVER_SOFTWARE"]." safe_mode: $safe_mode max_execution_time: $max_execution_time memory_limit: $memory_limit (used: ${memory_usage}M | ${memory_usage2}M) multisite: ".(is_multisite() ? 'Y' : 'N')." openssl: ".(defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'N')." mcrypt: ".(function_exists('mcrypt_encrypt') ? 'Y' : 'N')." LANG: ".getenv('LANG')." ZipArchive::addFile: ";

		// method_exists causes some faulty PHP installations to segfault, leading to support requests
		if (version_compare(phpversion(), '5.2.0', '>=') && extension_loaded('zip')) {
			$logline .= 'Y';
		} else {
			$logline .= (class_exists('ZipArchive') && method_exists('ZipArchive', 'addFile')) ? "Y" : "N";
		}

// 		$w3oc = 'N';
		if (0 === $this->current_resumption) {
			$memlim = $this->memory_check_current();
			if ($memlim<65 && $memlim>0) {
				$this->log(sprintf(__('The amount of memory (RAM) allowed for PHP is very low (%s Mb) - you should increase it to avoid failures due to insufficient memory (consult your web hosting company for more help)', 'updraftplus'), round($memlim, 1)), 'warning', 'lowram');
			}
			if ($max_execution_time>0 && $max_execution_time<20) {
				$this->log(sprintf(__('The amount of time allowed for WordPress plugins to run is very low (%s seconds) - you should increase it to avoid backup failures due to time-outs (consult your web hosting company for more help - it is the max_execution_time PHP setting; the recommended value is %s seconds or more)', 'updraftplus'), $max_execution_time, 90), 'warning', 'lowmaxexecutiontime');
			}
// 			if (defined('W3TC') && W3TC == true && function_exists('w3_instance')) {
// 				$modules = w3_instance('W3_ModuleStatus');
// 				if ($modules->is_enabled('objectcache')) {
// 					$w3oc = 'Y';
// 				}
// 			}
// 			$logline .= " W3TC/ObjectCache: $w3oc";

		}

		$this->log($logline);

		$hosting_bytes_free = $this->get_hosting_disk_quota_free();
		if (is_array($hosting_bytes_free)) {
			$perc = round(100*$hosting_bytes_free[1]/(max($hosting_bytes_free[2], 1)), 1);
			$quota_free = ' / '.sprintf('Free disk space in account: %s (%s used)', round($hosting_bytes_free[3]/1048576, 1)." MB", "$perc %");
			if ($hosting_bytes_free[3] < 1048576*50) {
				$quota_free_mb = round($hosting_bytes_free[3]/1048576, 1);
				$this->log(sprintf(__('Your free space in your hosting account is very low - only %s Mb remain', 'updraftplus'), $quota_free_mb), 'warning', 'lowaccountspace'.$quota_free_mb);
			}
		} else {
			$quota_free = '';
		}

		$disk_free_space = @disk_free_space($updraft_dir);
		# == rather than === here is deliberate; support experience shows that a result of (int)0 is not reliable. i.e. 0 can be returned when the real result should be false.
		if ($disk_free_space == false) {
			$this->log("Free space on disk containing Updraft's temporary directory: Unknown".$quota_free);
		} else {
			$this->log("Free space on disk containing Updraft's temporary directory: ".round($disk_free_space/1048576,1)." MB".$quota_free);
			$disk_free_mb = round($disk_free_space/1048576, 1);
			if ($disk_free_space < 50*1048576) $this->log(sprintf(__('Your free disk space is very low - only %s Mb remain', 'updraftplus'), round($disk_free_space/1048576, 1)), 'warning', 'lowdiskspace'.$disk_free_mb);
		}

	}

	/* Logs the given line, adding (relative) time stamp and newline
	Note these subtleties of log handling:
	- Messages at level 'error' are not logged to file - it is assumed that a separate call to log() at another level will take place. This is because at level 'error', messages are translated; whereas the log file is for developers who may not know the translated language. Messages at level 'error' are for the user.
	- Messages at level 'error' do not persist through the job (they are only saved with save_backup_history(), and never restored from there - so only the final save_backup_history() errors persist); we presume that either a) they will be cleared on the next attempt, or b) they will occur again on the final attempt (at which point they will go to the user). But...
	- ... messages at level 'warning' persist. These are conditions that are unlikely to be cleared, not-fatal, but the user should be informed about. The $uniq_id field (which should not be numeric) can then be used for warnings that should only be logged once
	$skip_dblog = true is suitable when there's a risk of excessive logging, and the information is not important for the user to see in the browser on the settings page
	
	The uniq_id field is also used with PHP event detection - it is set then to 'php_event' - which is useful for anything hooking the action to detect
	*/

	public function verify_free_memory($how_many_bytes_needed) {
		// This returns in MB
		$memory_limit = $this->memory_check_current();
		if (!is_numeric($memory_limit)) return false;
		$memory_limit = $memory_limit * 1048576;
		$memory_usage = round(@memory_get_usage(false)/1048576, 1);
		$memory_usage2 = round(@memory_get_usage(true)/1048576, 1);
		if ($memory_limit - $memory_usage > $how_many_bytes_needed && $memory_limit - $memory_usage2 > $how_many_bytes_needed) return true;
		return false;
	}

	public function log($line, $level = 'notice', $uniq_id = false, $skip_dblog = false) {

		if ('error' == $level || 'warning' == $level) {
			if ('error' == $level && 0 == $this->error_count()) $this->log('An error condition has occurred for the first time during this job');
			if ($uniq_id) {
				$this->errors[$uniq_id] = array('level' => $level, 'message' => $line);
			} else {
				$this->errors[] = array('level' => $level, 'message' => $line);
			}
			# Errors are logged separately
			if ('error' == $level) return;
			# It's a warning
			$warnings = $this->jobdata_get('warnings');
			if (!is_array($warnings)) $warnings=array();
			if ($uniq_id) {
				$warnings[$uniq_id] = $line;
			} else {
				$warnings[] = $line;
			}
			$this->jobdata_set('warnings', $warnings);
		}

		if (false === ($line = apply_filters('updraftplus_logline', $line, $this->nonce, $level, $uniq_id))) return;

		if ($this->logfile_handle) {
			# Record log file times relative to the backup start, if possible
			$rtime = (!empty($this->job_time_ms)) ? microtime(true)-$this->job_time_ms : microtime(true)-$this->opened_log_time;
			fwrite($this->logfile_handle, sprintf("%08.03f", round($rtime, 3))." (".$this->current_resumption.") ".(('notice' != $level) ? '['.ucfirst($level).'] ' : '').$line."\n");
		}

		switch ($this->jobdata_get('job_type')) {
			case 'download':
				// Download messages are keyed on the job (since they could be running several), and type
				// The values of the POST array were checked before
				$findex = empty($_POST['findex']) ? 0 : $_POST['findex'];

				if (!empty($_POST['timestamp']) && !empty($_POST['type'])) $this->jobdata_set('dlmessage_'.$_POST['timestamp'].'_'.$_POST['type'].'_'.$findex, $line);

				break;
			case 'restore':
				#if ('debug' != $level) echo $line."\n";
				break;
			default:
				if (!$skip_dblog && 'debug' != $level) UpdraftPlus_Options::update_updraft_option('updraft_lastmessage', $line." (".date_i18n('M d H:i:s').")", false);
				break;
		}

		if (defined('UPDRAFTPLUS_CONSOLELOG')) print $line."\n";
		if (defined('UPDRAFTPLUS_BROWSERLOG')) print htmlentities($line)."<br>\n";
	}

	public function log_removewarning($uniq_id) {
		$warnings = $this->jobdata_get('warnings');
		if (!is_array($warnings)) $warnings=array();
		unset($warnings[$uniq_id]);
		$this->jobdata_set('warnings', $warnings);
		unset($this->errors[$uniq_id]);
	}

	# For efficiency, you can also feed false or a string into this function
	public function log_wp_error($err, $echo = false, $logerror = false) {
		if (false === $err) return false;
		if (is_string($err)) {
			$this->log("Error message: $err");
			if ($echo) echo sprintf(__('Error: %s', 'updraftplus'), htmlspecialchars($err))."<br>";
			if ($logerror) $this->log($err, 'error');
			return false;
		}
		foreach ($err->get_error_messages() as $msg) {
			$this->log("Error message: $msg");
			if ($echo) echo sprintf(__('Error: %s', 'updraftplus'), htmlspecialchars($msg))."<br>";
			if ($logerror) $this->log($msg, 'error');
		}
		$codes = $err->get_error_codes();
		if (is_array($codes)) {
			foreach ($codes as $code) {
				$data = $err->get_error_data($code);
				if (!empty($data)) {
					$ll = (is_string($data)) ? $data : serialize($data);
					$this->log("Error data (".$code."): ".$ll);
				}
			}
		}
		# Returns false so that callers can return with false more efficiently if they wish
		return false;
	}

	public function get_max_packet_size() {
		global $wpdb;
		$mp = (int)$wpdb->get_var("SELECT @@session.max_allowed_packet");
		# Default to 1MB
		$mp = (is_numeric($mp) && $mp > 0) ? $mp : 1048576;
		# 32MB
		if ($mp < 33554432) {
			$save = $wpdb->show_errors(false);
			$req = @$wpdb->query("SET GLOBAL max_allowed_packet=33554432");
			$wpdb->show_errors($save);
			if (!$req) $this->log("Tried to raise max_allowed_packet from ".round($mp/1048576,1)." MB to 32 MB, but failed (".$wpdb->last_error.", ".serialize($req).")");
			$mp = (int)$wpdb->get_var("SELECT @@session.max_allowed_packet");
			# Default to 1MB
			$mp = (is_numeric($mp) && $mp > 0) ? $mp : 1048576;
		}
		$this->log("Max packet size: ".round($mp/1048576, 1)." MB");
		return $mp;
	}

	# Q. Why is this abstracted into a separate function? A. To allow poedit and other parsers to pick up the need to translate strings passed to it (and not pick up all of those passed to log()).
	# 1st argument = the line to be logged (obligatory)
	# Further arguments = parameters for sprintf()
	public function log_e() {
		$args = func_get_args();
		# Get first argument
		$pre_line = array_shift($args);
		# Log it whilst still in English
		if (is_wp_error($pre_line)) {
			$this->log_wp_error($pre_line);
		} else {
			# Now run (v)sprintf on it, using any remaining arguments. vsprintf = sprintf but takes an array instead of individual arguments
			$this->log(vsprintf($pre_line, $args));
			echo vsprintf(__($pre_line, 'updraftplus'), $args).'<br>';
		}
	}

	// This function is used by cloud methods to provide standardised logging, but more importantly to help us detect that meaningful activity took place during a resumption run, so that we can schedule further resumptions if it is worthwhile
	public function record_uploaded_chunk($percent, $extra = '', $file_path = false, $log_it = true) {

		// Touch the original file, which helps prevent overlapping runs
		if ($file_path) touch($file_path);

		// What this means in effect is that at least one of the files touched during the run must reach this percentage (so lapping round from 100 is OK)
		if ($percent > 0.7 * ($this->current_resumption - max($this->jobdata_get('uploaded_lastreset'), 9))) $this->something_useful_happened();

		// Log it
		global $updraftplus_backup;
		$log = (!empty($updraftplus_backup->current_service)) ? ucfirst($updraftplus_backup->current_service)." chunked upload: $percent % uploaded" : '';
		if ($log && $log_it) $this->log($log.(($extra) ? " ($extra)" : ''));
		// If we are on an 'overtime' resumption run, and we are still meaningfully uploading, then schedule a new resumption
		// Our definition of meaningful is that we must maintain an overall average of at least 0.7% per run, after allowing 9 runs for everything else to get going
		// i.e. Max 100/.7 + 9 = 150 runs = 760 minutes = 12 hrs 40, if spaced at 5 minute intervals. However, our algorithm now decreases the intervals if it can, so this should not really come into play
		// If they get 2 minutes on each run, and the file is 1GB, then that equals 10.2MB/120s = minimum 59KB/s upload speed required

		$upload_status = $this->jobdata_get('uploading_substatus');
		if (is_array($upload_status)) {
			$upload_status['p'] = $percent/100;
			$this->jobdata_set('uploading_substatus', $upload_status);
		}

	}

	// $singletons : whether to upload a file that only has one chunk, or whether instead to return 1 in that case
	public function chunked_upload($caller, $file, $cloudpath, $logname, $chunk_size, $uploaded_size, $singletons=false) {

		$fullpath = $this->backups_dir_location().'/'.$file;
		$orig_file_size = filesize($fullpath);
		if ($uploaded_size >= $orig_file_size) return true;

		$chunks = floor($orig_file_size / $chunk_size);
		// There will be a remnant unless the file size was exactly on a 5MB boundary
		if ($orig_file_size % $chunk_size > 0) $chunks++;

		$this->log("$logname upload: $file (chunks: $chunks, size: $chunk_size) -> $cloudpath ($uploaded_size)");

		if ($chunks == 0) {
			return 1;
		} elseif ($chunks < 2 && !$singletons) {
			return 1;
		} else {

			if (false == ($fp = @fopen($fullpath, 'rb'))) {
				$this->log("$logname: failed to open file: $fullpath");
				$this->log("$file: ".sprintf(__('%s Error: Failed to open local file','updraftplus'), $logname), 'error');
				return false;
			}

			$errors_so_far = 0;
			for ($i = 1 ; $i <= $chunks; $i++) {

				$upload_start = ($i-1)*$chunk_size;
				// The file size minus one equals the byte offset of the final byte
				$upload_end = min($i*$chunk_size-1, $orig_file_size-1);
				// Don't forget the +1; otherwise the last byte is omitted
				$upload_size = $upload_end - $upload_start + 1;

				fseek($fp, $upload_start);

				$uploaded = $caller->chunked_upload($file, $fp, $i, $upload_size, $upload_start, $upload_end);

				// Try again? (Just once - added in 1.12.6 (can make more sophisticated if there is a need))
				if (is_wp_error($uploaded) && 'try_again' == $uploaded->get_error_code()) {
					// Arbitrary wait
					sleep(3);
					$this->log("Re-trying after wait (to allow apparent inconsistency to clear)");
					$uploaded = $caller->chunked_upload($file, $fp, $i, $upload_size, $upload_start, $upload_end);
				}
				
				// This is the only other supported case of a WP_Error - otherwise, a boolean must be returned
				// Note that this is only allowed on the first chunk. The caller is responsible to remember its chunk size if it uses this facility.
				if (1 == $i && is_wp_error($uploaded) && 'reduce_chunk_size' == $uploaded->get_error_code() && false != ($new_chunk_size = $uploaded->get_error_data()) && is_numeric($new_chunk_size)) {
					$this->log("Re-trying with new chunk size: ".$new_chunk_size);
					return $this->chunked_upload($caller, $file, $cloudpath, $logname, $new_chunk_size, $uploaded_size, $singletons=false);
				}
				
				if ($uploaded) {
					$perc = round(100*((($i-1) * $chunk_size) + $upload_size)/max($orig_file_size, 1), 1);
					# $perc = round(100*$i/$chunks,1); # Takes no notice of last chunk likely being smaller
					// Implementations used a return value of (int)1 (rather than (bool)true) to suppress logging
					$log_it = ($uploaded === 1) ? false : true;
					$this->record_uploaded_chunk($perc, $i, $fullpath, $log_it);
				} else {
					$errors_so_far++;
					if ($errors_so_far>=3) { @fclose($fp); return false; }
				}
			}

			@fclose($fp);

			if ($errors_so_far) return false;

			// All chunks are uploaded - now combine the chunks
			$ret = true;
			if (method_exists($caller, 'chunked_upload_finish')) {
				$ret = $caller->chunked_upload_finish($file);
				if (!$ret) {
					$this->log("$logname - failed to re-assemble chunks (".$e->getMessage().')');
					$this->log(sprintf(__('%s error - failed to re-assemble chunks', 'updraftplus'), $logname), 'error');
				}
			}
			if ($ret) {
				$this->log("$logname upload: success");
				# UpdraftPlus_RemoteStorage_Addons_Base calls this itself
				if (!is_a($caller, 'UpdraftPlus_RemoteStorage_Addons_Base')) $this->uploaded_file($file);
			}

			return $ret;

		}
	}

	public function chunked_download($file, $method, $remote_size, $manually_break_up = false, $passback = null, $chunk_size = 1048576) {

		try {

			$fullpath = $this->backups_dir_location().'/'.$file;
			$start_offset = (file_exists($fullpath)) ? filesize($fullpath): 0;

			if ($start_offset >= $remote_size) {
				$this->log("File is already completely downloaded ($start_offset/$remote_size)");
				return true;
			}

			// Some more remains to download - so let's do it
			if (!$fh = fopen($fullpath, 'a')) {
				$this->log("Error opening local file: $fullpath");
				$this->log($file.": ".__("Error",'updraftplus').": ".__('Error opening local file: Failed to download','updraftplus'), 'error');
				return false;
			}

			$last_byte = ($manually_break_up) ? min($remote_size, $start_offset + $chunk_size) : $remote_size;

			# This only affects logging
			$expected_bytes_delivered_so_far = true;

			while ($start_offset < $remote_size) {
				$headers = array();
				// If resuming, then move to the end of the file

				$requested_bytes = $last_byte-$start_offset;

				if ($expected_bytes_delivered_so_far) {
					$this->log("$file: local file is status: $start_offset/$remote_size bytes; requesting next $requested_bytes bytes");
				} else {
					$this->log("$file: local file is status: $start_offset/$remote_size bytes; requesting next chunk (${start_offset}-)");
				}

				if ($start_offset >0 || $last_byte<$remote_size) {
					fseek($fh, $start_offset);
					// N.B. Don't alter this format without checking what relies upon it
					$headers['Range'] = "bytes=$start_offset-$last_byte";
				}

				# The method is free to return as much data as it pleases
				$ret = $method->chunked_download($file, $headers, $passback);
				if (false === $ret) return false;

				if (strlen($ret) > $requested_bytes || strlen($ret) < $requested_bytes - 1) $expected_bytes_delivered_so_far = false;

				if (!fwrite($fh, $ret)) throw new Exception('Write failure (start offset: '.$start_offset.', bytes: '.strlen($ret).'; requested: '.$requested_bytes.')');

				clearstatcache();
				$start_offset = ftell($fh);
				$last_byte = ($manually_break_up) ? min($remote_size, $start_offset + $chunk_size) : $remote_size;

			}

		} catch(Exception $e) {
			$this->log('Error ('.get_class($e).') - failed to download the file ('.$e->getCode().', '.$e->getMessage().')');
			$this->log("$file: ".__('Error - failed to download the file','updraftplus').' ('.$e->getCode().', '.$e->getMessage().')' ,'error');
			return false;
		}

		fclose($fh);

		return true;
	}

	public function decrypt($fullpath, $key, $ciphertext = false) {
		$this->ensure_phpseclib('Crypt_Rijndael', 'Crypt/Rijndael');
		$rijndael = new Crypt_Rijndael();
		if (defined('UPDRAFTPLUS_DECRYPTION_ENGINE')) {
			if ('openssl' == UPDRAFTPLUS_DECRYPTION_ENGINE) {
				$rijndael->setPreferredEngine(CRYPT_ENGINE_OPENSSL);
			} elseif ('mcrypt' == UPDRAFTPLUS_DECRYPTION_ENGINE) {
				$rijndael->setPreferredEngine(CRYPT_ENGINE_MCRYPT);
			} elseif ('internal' == UPDRAFTPLUS_DECRYPTION_ENGINE) {
				$rijndael->setPreferredEngine(CRYPT_ENGINE_INTERNAL);
			}
		}
		$rijndael->setKey($key);
		return (false == $ciphertext) ? $rijndael->decrypt(file_get_contents($fullpath)) : $rijndael->decrypt($ciphertext);
	}

	public function detect_safe_mode() {
		return (@ini_get('safe_mode') && strtolower(@ini_get('safe_mode')) != "off") ? 1 : 0;
	}

	public function find_working_sqldump($logit = true, $cacheit = true) {

		// The hosting provider may have explicitly disabled the popen or proc_open functions
		if ($this->detect_safe_mode() || !function_exists('popen') || !function_exists('escapeshellarg')) {
			if ($cacheit) $this->jobdata_set('binsqldump', false);
			return false;
		}
		$existing = $this->jobdata_get('binsqldump', null);
		# Theoretically, we could have moved machines, due to a migration
		if (null !== $existing && (!is_string($existing) || @is_executable($existing))) return $existing;

		$updraft_dir = $this->backups_dir_location();
		global $wpdb;
		$table_name = $wpdb->get_blog_prefix().'options';
		$tmp_file = md5(time().rand()).".sqltest.tmp";
		$pfile = md5(time().rand()).'.tmp';
		file_put_contents($updraft_dir.'/'.$pfile, "[mysqldump]\npassword=".DB_PASSWORD."\n");

		$result = false;
		foreach (explode(',', UPDRAFTPLUS_MYSQLDUMP_EXECUTABLE) as $potsql) {
			if (!@is_executable($potsql)) continue;
			if ($logit) $this->log("Testing: $potsql");

			$exec = "cd ".escapeshellarg($updraft_dir)."; $potsql  --defaults-file=$pfile --max_allowed_packet=1M --quote-names --add-drop-table --skip-comments --skip-set-charset --allow-keywords --dump-date --extended-insert --where=option_name=\\'siteurl\\' --user=".escapeshellarg(DB_USER)." --host=".escapeshellarg(DB_HOST)." ".DB_NAME." ".escapeshellarg($table_name)." >$tmp_file";

			$handle = popen($exec, "r");
			if ($handle) {
				while (!feof($handle)) {
					$w = fgets($handle);
					if ($w && $logit) $this->log("Output: ".trim($w));
				}
				$ret = pclose($handle);
				if ($ret !=0) {
					if ($logit) $this->log("Binary mysqldump: error (code: $ret)");
				} else {
					$dumped = file_get_contents($updraft_dir.'/'.$tmp_file, false, null, 0, 4096);
					if (stripos($dumped, 'insert into') !== false) {
						if ($logit) $this->log("Working binary mysqldump found: $potsql");
						$result = $potsql;
						break;
					}
				}
			} else {
				if ($logit) $this->log("Error: popen failed");
			}
		}

		@unlink($updraft_dir.'/'.$pfile);
		@unlink($updraft_dir.'/'.$tmp_file);

		if ($cacheit) $this->jobdata_set('binsqldump', $result);

		return $result;
	}

	# We require -@ and -u -r to work - which is the usual Linux binzip
	public function find_working_bin_zip($logit = true, $cacheit = true) {
		if ($this->detect_safe_mode()) return false;
		// The hosting provider may have explicitly disabled the popen or proc_open functions
		if (!function_exists('popen') || !function_exists('proc_open') || !function_exists('escapeshellarg')) {
			if ($cacheit) $this->jobdata_set('binzip', false);
			return false;
		}

		$existing = $this->jobdata_get('binzip', null);
		# Theoretically, we could have moved machines, due to a migration
		if (null !== $existing && (!is_string($existing) || @is_executable($existing))) return $existing;

		$updraft_dir = $this->backups_dir_location();
		foreach (explode(',', UPDRAFTPLUS_ZIP_EXECUTABLE) as $potzip) {
			if (!@is_executable($potzip)) continue;
			if ($logit) $this->log("Testing: $potzip");

			# Test it, see if it is compatible with Info-ZIP
			# If you have another kind of zip, then feel free to tell me about it
			@mkdir($updraft_dir.'/binziptest/subdir1/subdir2', 0777, true);
			file_put_contents($updraft_dir.'/binziptest/subdir1/subdir2/test.html', '<html><body><a href="https://updraftplus.com">UpdraftPlus is a great backup and restoration plugin for WordPress.</a></body></html>');
			@unlink($updraft_dir.'/binziptest/test.zip');
			if (is_file($updraft_dir.'/binziptest/subdir1/subdir2/test.html')) {

				$exec = "cd ".escapeshellarg($updraft_dir)."; $potzip";
				if (defined('UPDRAFTPLUS_BINZIP_OPTS') && UPDRAFTPLUS_BINZIP_OPTS) $exec .= ' '.UPDRAFTPLUS_BINZIP_OPTS;
				$exec .= " -v -u -r binziptest/test.zip binziptest/subdir1";

				$all_ok=true;
				$handle = popen($exec, "r");
				if ($handle) {
					while (!feof($handle)) {
						$w = fgets($handle);
						if ($w && $logit) $this->log("Output: ".trim($w));
					}
					$ret = pclose($handle);
					if ($ret !=0) {
						if ($logit) $this->log("Binary zip: error (code: $ret)");
						$all_ok = false;
					}
				} else {
					if ($logit) $this->log("Error: popen failed");
					$all_ok = false;
				}

				# Now test -@
				if (true == $all_ok) {
					file_put_contents($updraft_dir.'/binziptest/subdir1/subdir2/test2.html', '<html><body><a href="https://updraftplus.com">UpdraftPlus is a really great backup and restoration plugin for WordPress.</a></body></html>');
					
					$exec = $potzip;
					if (defined('UPDRAFTPLUS_BINZIP_OPTS') && UPDRAFTPLUS_BINZIP_OPTS) $exec .= ' '.UPDRAFTPLUS_BINZIP_OPTS;
					$exec .= " -v -@ binziptest/test.zip";

					$all_ok=true;

					$descriptorspec = array(
						0 => array('pipe', 'r'),
						1 => array('pipe', 'w'),
						2 => array('pipe', 'w')
					);
					$handle = proc_open($exec, $descriptorspec, $pipes, $updraft_dir);
					if (is_resource($handle)) {
						if (!fwrite($pipes[0], "binziptest/subdir1/subdir2/test2.html\n")) {
							@fclose($pipes[0]);
							@fclose($pipes[1]);
							@fclose($pipes[2]);
							$all_ok = false;
						} else {
							fclose($pipes[0]);
							while (!feof($pipes[1])) {
								$w = fgets($pipes[1]);
								if ($w && $logit) $this->log("Output: ".trim($w));
							}
							fclose($pipes[1]);
							
							while (!feof($pipes[2])) {
								$last_error = fgets($pipes[2]);
								if (!empty($last_error) && $logit) $this->log("Stderr output: ".trim($w));
							}
							fclose($pipes[2]);

							$ret = proc_close($handle);
							if ($ret !=0) {
								if ($logit) $this->log("Binary zip: error (code: $ret)");
								$all_ok = false;
							}

						}

					} else {
						if ($logit) $this->log("Error: proc_open failed");
						$all_ok = false;
					}

				}

				// Do we now actually have a working zip? Need to test the created object using PclZip
				// If it passes, then remove dirs and then return $potzip;
				$found_first = false;
				$found_second = false;
				if ($all_ok && file_exists($updraft_dir.'/binziptest/test.zip')) {
					if (function_exists('gzopen')) {
						if(!class_exists('PclZip')) require_once(ABSPATH.'/wp-admin/includes/class-pclzip.php');
						$zip = new PclZip($updraft_dir.'/binziptest/test.zip');
						$foundit = 0;
						if (($list = $zip->listContent()) != 0) {
							foreach ($list as $obj) {
								if ($obj['filename'] && !empty($obj['stored_filename']) && 'binziptest/subdir1/subdir2/test.html' == $obj['stored_filename'] && $obj['size']==131) $found_first=true;
								if ($obj['filename'] && !empty($obj['stored_filename']) && 'binziptest/subdir1/subdir2/test2.html' == $obj['stored_filename'] && $obj['size']==138) $found_second=true;
							}
						}
					} else {
						// PclZip will die() if gzopen is not found
						// Obviously, this is a kludge - we assume it's working. We could, of course, just return false - but since we already know now that PclZip can't work, that only leaves ZipArchive
						$this->log("gzopen function not found; PclZip cannot be invoked; will assume that binary zip works if we have a non-zero file");
						if (filesize($updraft_dir.'/binziptest/test.zip') > 0) {
							$found_first = true;
							$found_second = true;
						}
					}
				}
				$this->remove_binzip_test_files($updraft_dir);
				if ($found_first && $found_second) {
					if ($logit) $this->log("Working binary zip found: $potzip");
					if ($cacheit) $this->jobdata_set('binzip', $potzip);
					return $potzip;
				}

			}
			$this->remove_binzip_test_files($updraft_dir);
		}
		if ($cacheit) $this->jobdata_set('binzip', false);
		return false;
	}

	private function remove_binzip_test_files($updraft_dir) {
		@unlink($updraft_dir.'/binziptest/subdir1/subdir2/test.html');
		@unlink($updraft_dir.'/binziptest/subdir1/subdir2/test2.html');
		@rmdir($updraft_dir.'/binziptest/subdir1/subdir2');
		@rmdir($updraft_dir.'/binziptest/subdir1');
		@unlink($updraft_dir.'/binziptest/test.zip');
		@rmdir($updraft_dir.'/binziptest');
	}

	// This function is purely for timing - we just want to know the maximum run-time; not whether we have achieved anything during it
	public function record_still_alive() {
		// Update the record of maximum detected runtime on each run
		$time_passed = $this->jobdata_get('run_times');
		if (!is_array($time_passed)) $time_passed = array();

		$time_this_run = microtime(true)-$this->opened_log_time;
		$time_passed[$this->current_resumption] = $time_this_run;
		$this->jobdata_set('run_times', $time_passed);

		$resume_interval = $this->jobdata_get('resume_interval');
		if ($time_this_run + 30 > $resume_interval) {
			$new_interval = ceil($time_this_run + 30);
			set_site_transient('updraft_initial_resume_interval', (int)$new_interval, 8*86400);
			$this->log("The time we have been running (".round($time_this_run,1).") is approaching the resumption interval ($resume_interval) - increasing resumption interval to $new_interval");
			$this->jobdata_set('resume_interval', $new_interval);
		}

	}

	public function something_useful_happened() {

		$this->record_still_alive();

		if (!$this->something_useful_happened) {
			$useful_checkin = $this->jobdata_get('useful_checkin');
			if (empty($useful_checkin) || $this->current_resumption > $useful_checkin) $this->jobdata_set('useful_checkin', $this->current_resumption);
		}

		$this->something_useful_happened = true;

		$updraft_dir = $this->backups_dir_location();
		if (file_exists($updraft_dir.'/deleteflag-'.$this->nonce.'.txt')) {
			$this->log("User request for abort: backup job will be immediately halted");
			@unlink($updraft_dir.'/deleteflag-'.$this->nonce.'.txt');
			$this->backup_finish($this->current_resumption + 1, true, true, $this->current_resumption, true);
			die;
		}
		
		if ($this->current_resumption >= 9 && false == $this->newresumption_scheduled) {
			$this->log("This is resumption ".$this->current_resumption.", but meaningful activity is still taking place; so a new one will be scheduled");
			// We just use max here to make sure we get a number at all
			$resume_interval = max($this->jobdata_get('resume_interval'), 75);
			// Don't consult the minimum here
			// if (!is_numeric($resume_interval) || $resume_interval<300) { $resume_interval = 300; }
			$schedule_for = time()+$resume_interval;
			$this->newresumption_scheduled = $schedule_for;
			wp_schedule_single_event($schedule_for, 'updraft_backup_resume', array($this->current_resumption + 1, $this->nonce));
		} else {
			$this->reschedule_if_needed();
		}
	}

	public function option_filter_get($which) {
		global $wpdb;
		$row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $which));
		// Has to be get_row instead of get_var because of funkiness with 0, false, null values
		return (is_object($row)) ? $row->option_value : false;
	}

	public function parse_filename($filename) {
		if (preg_match('/^backup_([\-0-9]{10})-([0-9]{4})_.*_([0-9a-f]{12})-([\-a-z]+)([0-9]+)?+\.(zip|gz|gz\.crypt)$/i', $filename, $matches)) {
			return array(
				'date' => strtotime($matches[1].' '.$matches[2]),
				'nonce' => $matches[3],
				'type' => $matches[4],
				'index' => (empty($matches[5]) ? 0 : $matches[5]-1),
				'extension' => $matches[6]);
		} else {
			return false;
		}
	}

	// Pretty printing
	public function printfile($description, $history, $entity, $checksums, $jobdata, $smaller=false) {

		if (empty($history[$entity])) return;

		if ($smaller) {
			$pfiles =  "<strong>".$description." (".sprintf(__('files: %s', 'updraftplus'), count($history[$entity])).")</strong><br>\n";
		} else {
			$pfiles =  "<h3>".$description." (".sprintf(__('files: %s', 'updraftplus'), count($history[$entity])).")</h3>\n\n";
		}

		$pfiles .= '<ul>';
		$files = $history[$entity];
		if (is_string($files)) $files = array($files);

		foreach ($files as $ind => $file) {

			$op = htmlspecialchars($file)."\n";
			$skey = $entity.((0 == $ind) ? '' : $ind).'-size';

			$meta = '';
			if ('db' == substr($entity, 0, 2) && 'db' != $entity) {
				$dind = substr($entity, 2);
				if (is_array($jobdata) && !empty($jobdata['backup_database']) && is_array($jobdata['backup_database']) && !empty($jobdata['backup_database'][$dind]) && is_array($jobdata['backup_database'][$dind]['dbinfo']) && !empty($jobdata['backup_database'][$dind]['dbinfo']['host'])) {
					$dbinfo = $jobdata['backup_database'][$dind]['dbinfo'];
					$meta .= sprintf(__('External database (%s)', 'updraftplus'), $dbinfo['user'].'@'.$dbinfo['host'].'/'.$dbinfo['name'])."<br>";
				}
			}
			if (isset($history[$skey])) $meta .= sprintf(__('Size: %s MB', 'updraftplus'), round($history[$skey]/1048576, 1));
			$ckey = $entity.$ind;
			foreach ($checksums as $ck) {
				$ck_plain = false;
				if (isset($history['checksums'][$ck][$ckey])) {
					$meta .= (($meta) ? ', ' : '').sprintf(__('%s checksum: %s', 'updraftplus'), strtoupper($ck), $history['checksums'][$ck][$ckey]);
					$ck_plain = true;
				}
				if (isset($history['checksums'][$ck][$ckey.'.crypt'])) {
					if ($ck_plain) $meta .= ' '.__('(when decrypted)');
					$meta .= (($meta) ? ', ' : '').sprintf(__('%s checksum: %s', 'updraftplus'), strtoupper($ck), $history['checksums'][$ck][$ckey.'.crypt']);
				}
			}

			$fileinfo = apply_filters("updraftplus_fileinfo_$entity", array(), $ind);
			if (is_array($fileinfo) && !empty($fileinfo)) {
				if (isset($fileinfo['html'])) {
					$meta .= $fileinfo['html'];
				}
			}

			#if ($meta) $meta = " ($meta)";
			if ($meta) $meta = "<br><em>$meta</em>";
			$pfiles .= '<li>'.$op.$meta."\n</li>\n";
		}

		$pfiles .= "</ul>\n";

		return $pfiles;

	}

	// This important function returns a list of file entities that can potentially be backed up (subject to users settings), and optionally further meta-data about them
	public function get_backupable_file_entities($include_others = true, $full_info = false) {

		$wp_upload_dir = $this->wp_upload_dir();

		if ($full_info) {
			$arr = array(
				'plugins' => array('path' => untrailingslashit(WP_PLUGIN_DIR), 'description' => __('Plugins','updraftplus')),
				'themes' => array('path' => WP_CONTENT_DIR.'/themes', 'description' => __('Themes','updraftplus')),
				'uploads' => array('path' => untrailingslashit($wp_upload_dir['basedir']), 'description' => __('Uploads','updraftplus'))
			);
		} else {
			$arr = array(
				'plugins' => untrailingslashit(WP_PLUGIN_DIR),
				'themes' => WP_CONTENT_DIR.'/themes',
				'uploads' => untrailingslashit($wp_upload_dir['basedir'])
			);
		}

		$arr = apply_filters('updraft_backupable_file_entities', $arr, $full_info);

		// We then add 'others' on to the end
		if ($include_others) {
			if ($full_info) {
				$arr['others'] = array('path' => WP_CONTENT_DIR, 'description' => __('Others', 'updraftplus'));
			} else {
				$arr['others'] = WP_CONTENT_DIR;
			}
		}

		// Entries that should be added after 'others'
		$arr = apply_filters('updraft_backupable_file_entities_final', $arr, $full_info);

		return $arr;

	}

	# This is just a long-winded way of forcing WP to get the value afresh from the db, instead of using the auto-loaded/cached value (which can be out of date, especially since backups are, by their nature, long-running)
	public function filter_updraft_backup_history($v) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'updraft_backup_history' ) );
		if (is_object($row )) return maybe_unserialize($row->option_value);
		return false;
	}

	public function php_error_to_logline($errno, $errstr, $errfile, $errline) {
		switch ($errno) {
			case 1:		$e_type = 'E_ERROR'; break;
			case 2:		$e_type = 'E_WARNING'; break;
			case 4:		$e_type = 'E_PARSE'; break;
			case 8:		$e_type = 'E_NOTICE'; break;
			case 16:		$e_type = 'E_CORE_ERROR'; break;
			case 32:		$e_type = 'E_CORE_WARNING'; break;
			case 64:		$e_type = 'E_COMPILE_ERROR'; break;
			case 128:		$e_type = 'E_COMPILE_WARNING'; break;
			case 256:		$e_type = 'E_USER_ERROR'; break;
			case 512:		$e_type = 'E_USER_WARNING'; break;
			case 1024:	$e_type = 'E_USER_NOTICE'; break;
			case 2048:	$e_type = 'E_STRICT'; break;
			case 4096:	$e_type = 'E_RECOVERABLE_ERROR'; break;
			case 8192:	$e_type = 'E_DEPRECATED'; break;
			case 16384:	$e_type = 'E_USER_DEPRECATED'; break;
			case 30719:	$e_type = 'E_ALL'; break;
			default:		$e_type = "E_UNKNOWN ($errno)"; break;
		}

		if (!is_string($errstr)) $errstr = serialize($errstr);

		if (0 === strpos($errfile, ABSPATH)) $errfile = substr($errfile, strlen(ABSPATH));

		if ('E_DEPRECATED' == $e_type && !empty($this->no_deprecation_warnings)) {
			return false;
		}
		
		return "PHP event: code $e_type: $errstr (line $errline, $errfile)";

	}

	public function php_error($errno, $errstr, $errfile, $errline) {
		if (0 == error_reporting()) return true;
		$logline = $this->php_error_to_logline($errno, $errstr, $errfile, $errline);
		if (false !== $logline) $this->log($logline, 'notice', 'php_event');
		// Pass it up the chain
		return $this->error_reporting_stop_when_logged;
	}

	public function backup_resume($resumption_no, $bnonce) {

		set_error_handler(array($this, 'php_error'), E_ALL & ~E_STRICT);

		$this->current_resumption = $resumption_no;

		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);
		@ignore_user_abort(true);

		$runs_started = array();
		$time_now = microtime(true);

		add_filter('pre_option_updraft_backup_history', array($this, 'filter_updraft_backup_history'));

		// Restore state
		$resumption_extralog = '';
		$prev_resumption = $resumption_no - 1;
		$last_successful_resumption = -1;
		$job_type = 'backup';

		if ($resumption_no > 0) {

			$this->nonce = $bnonce;
			$this->backup_time = $this->jobdata_get('backup_time');
			$this->job_time_ms = $this->jobdata_get('job_time_ms');
			
			# Get the warnings before opening the log file, as opening the log file may generate new ones (which then leads to $this->errors having duplicate entries when they are copied over below)
			$warnings = $this->jobdata_get('warnings');
			
			$this->logfile_open($bnonce);
			
			// Import existing warnings. The purpose of this is so that when save_backup_history() is called, it has a complete set - because job data expires quickly, whilst the warnings of the last backup run need to persist
			if (is_array($warnings)) {
				foreach ($warnings as $warning) {
					$this->errors[] = array('level' => 'warning', 'message' => $warning);
				}
			}

			$runs_started = $this->jobdata_get('runs_started');
			if (!is_array($runs_started)) $runs_started=array();
			$time_passed = $this->jobdata_get('run_times');
			if (!is_array($time_passed)) $time_passed = array();
			
			foreach ($time_passed as $run => $passed) {
				if (isset($runs_started[$run]) && $runs_started[$run] + $time_passed[$run] + 30 > $time_now) {
					// We don't want to increase the resumption if WP has started two copies of the same resumption off
					if ($run && $run == $resumption_no) {
						$increase_resumption = false;
						$this->log("It looks like WordPress's scheduler has started multiple instances of this resumption");
					} else {
						$increase_resumption = true;
					}
					$this->terminate_due_to_activity('check-in', round($time_now, 1), round($runs_started[$run] + $time_passed[$run], 1), $increase_resumption);
				}
			}

			for ($i = 0; $i<=$prev_resumption; $i++) {
				if (isset($time_passed[$i])) $last_successful_resumption = $i;
			}

			if (isset($time_passed[$prev_resumption])) {
				$resumption_extralog = ", previous check-in=".round($time_passed[$prev_resumption], 1)."s";
			} else {
				$this->no_checkin_last_time = true;
			}

			// This is just a simple test to catch restorations of old backup sets where the backup includes a resumption of the backup job
			if ($time_now - $this->backup_time > 172800 && true == apply_filters('updraftplus_check_obsolete_backup', true, $time_now, $this)) {
			
				// We have seen cases where the get_site_option() call that self::get_jobdata() relies on returns nothing, even though the data was there in the database. This appears to be sometimes reproducible for the people who get it, but stops being reproducible if they change their backup times - which suggests that they're having failures at times of extreme load. We can attempt to detect this case, and reschedule, instead of aborting.
				if (empty($this->backup_time) && empty($this->backup_is_already_complete) && !empty($this->logfile_name) && is_readable($this->logfile_name)) {
					$first_log_bit = file_get_contents($this->logfile_name, false, null, 0, 250);
					if (preg_match('/\(0\) Opened log file at time: (.*) on /', $first_log_bit, $matches)) {
						$first_opened = strtotime($matches[1]);
						// The value of 1000 seconds here is somewhat arbitrary; but allows for the problem to occur in ~ the first 15 minutes. In practice, the problem is extremely rare; if this does not catch it, we can tweak the algorithm.
						if (time() - $first_opened < 1000) {
							$this->log("This backup task (".$this->nonce.") failed to load its job data (possible database server malfunction), but appears to be only recently started: scheduling a fresh resumption in order to try again, and then ending this resumption ($time_now, ".$this->backup_time.") (existing jobdata keys: ".implode(', ', array_keys($this->jobdata)).")");
							$this->reschedule(120);
							die;
						}
					}
				}
			
				$this->log("This backup task (".$this->nonce.") is either complete or began over 2 days ago: ending ($time_now, ".$this->backup_time.") (existing jobdata keys: ".implode(', ', array_keys($this->jobdata)).")");
				die;
			}

		} else {
			$label = $this->jobdata_get('label');
			if ($label) $resumption_extralog = ", label=$label";
		}

		$this->last_successful_resumption = $last_successful_resumption;

		$runs_started[$resumption_no] = $time_now;
		if (!empty($this->backup_time)) $this->jobdata_set('runs_started', $runs_started);

		// Schedule again, to run in 5 minutes again, in case we again fail
		// The actual interval can be increased (for future resumptions) by other code, if it detects apparent overlapping
		$resume_interval = max(intval($this->jobdata_get('resume_interval')), 100);

		$btime = $this->backup_time;

		$job_type = $this->jobdata_get('job_type');

		do_action('updraftplus_resume_backup_'.$job_type);

		$updraft_dir = $this->backups_dir_location();

		$time_ago = time()-$btime;

		$this->log("Backup run: resumption=$resumption_no, nonce=$bnonce, begun at=$btime (${time_ago}s ago), job type=$job_type".$resumption_extralog);

		// This works round a bizarre bug seen in one WP install, where delete_transient and wp_clear_scheduled_hook both took no effect, and upon 'resumption' the entire backup would repeat.
		// Argh. In fact, this has limited effect, as apparently (at least on another install seen), the saving of the updated transient via jobdata_set() also took no effect. Still, it does not hurt.
		if ($resumption_no >= 1 && 'finished' == $this->jobdata_get('jobstatus')) {
			$this->log('Terminate: This backup job is already finished (1).');
			die;
		} elseif ('backup' == $job_type && !empty($this->backup_is_already_complete)) {
			$this->log('Terminate: This backup job is already finished (2).');
			die;
		}

		if ($resumption_no > 0 && isset($runs_started[$prev_resumption])) {
			$our_expected_start = $runs_started[$prev_resumption] + $resume_interval;
			# If the previous run increased the resumption time, then it is timed from the end of the previous run, not the start
			if (isset($time_passed[$prev_resumption]) && $time_passed[$prev_resumption]>0) $our_expected_start += $time_passed[$prev_resumption];
			$our_expected_start = apply_filters('updraftplus_expected_start', $our_expected_start, $job_type);
			# More than 12 minutes late?
			if ($time_now > $our_expected_start + 720) {
				$this->log('Long time past since expected resumption time: approx expected='.round($our_expected_start,1).", now=".round($time_now, 1).", diff=".round($time_now-$our_expected_start,1));
				$this->log(__('Your website is visited infrequently and UpdraftPlus is not getting the resources it hoped for; please read this page:', 'updraftplus').' https://updraftplus.com/faqs/why-am-i-getting-warnings-about-my-site-not-having-enough-visitors/', 'warning', 'infrequentvisits');
			}
		}

		$this->jobdata_set('current_resumption', $resumption_no);

		$first_run = apply_filters('updraftplus_filerun_firstrun', 0);

		// We just do this once, as we don't want to be in permanent conflict with the overlap detector
		if ($resumption_no >= $first_run + 8 && $resumption_no < $first_run + 15 && $resume_interval >= 300) {

			// $time_passed is set earlier
			list($max_time, $timings_string, $run_times_known) = $this->max_time_passed($time_passed, $resumption_no - 1, $first_run);

			# Do this on resumption 8, or the first time that we have 6 data points
			if (($first_run + 8 == $resumption_no && $run_times_known >= 6) || (6 == $run_times_known && !empty($time_passed[$prev_resumption]))) {
				$this->log("Time passed on previous resumptions: $timings_string (known: $run_times_known, max: $max_time)");
				// Remember that 30 seconds is used as the 'perhaps something is still running' detection threshold, and that 45 seconds is used as the 'the next resumption is approaching - reschedule!' interval
				if ($max_time + 52 < $resume_interval) {
					$resume_interval = round($max_time + 52);
					$this->log("Based on the available data, we are bringing the resumption interval down to: $resume_interval seconds");
					$this->jobdata_set('resume_interval', $resume_interval);
				}
			}

		}

		// A different argument than before is needed otherwise the event is ignored
		$next_resumption = $resumption_no+1;
		if ($next_resumption < $first_run + 10) {
			if (true === $this->jobdata_get('one_shot')) {
				if (true === $this->jobdata_get('reschedule_before_upload') && 1 == $next_resumption) {
					$this->log('A resumption will be scheduled for the cloud backup stage');
					$schedule_resumption = true;
				} else {
					$this->log('We are in "one shot" mode - no resumptions will be scheduled');
				}
			} else {
				$schedule_resumption = true;
			}
		} else {
			// We're in over-time - we only reschedule if something useful happened last time (used to be that we waited for it to happen this time - but that meant that temporary errors, e.g. Google 400s on uploads, scuppered it all - we'd do better to have another chance
			$useful_checkin = $this->jobdata_get('useful_checkin');
			$last_resumption = $resumption_no-1;

			if (empty($useful_checkin) || $useful_checkin < $last_resumption) {
				$this->log(sprintf('The current run is resumption number %d, and there was nothing useful done on the last run (last useful run: %s) - will not schedule a further attempt until we see something useful happening this time', $resumption_no, $useful_checkin));
			} else {
				$schedule_resumption = true;
			}
		}

		// Sanity check
		if (empty($this->backup_time)) {
			$this->log('The backup_time parameter appears to be empty (usually caused by resuming an already-complete backup).');
			return false;
		}

		if (isset($schedule_resumption)) {
			$schedule_for = time()+$resume_interval;
			$this->log("Scheduling a resumption ($next_resumption) after $resume_interval seconds ($schedule_for) in case this run gets aborted");
			wp_schedule_single_event($schedule_for, 'updraft_backup_resume', array($next_resumption, $bnonce));
			$this->newresumption_scheduled = $schedule_for;
		}

		$backup_files = $this->jobdata_get('backup_files');

		global $updraftplus_backup;
		// Bring in all the backup routines
		require_once(UPDRAFTPLUS_DIR.'/backup.php');
		$updraftplus_backup = new UpdraftPlus_Backup($backup_files, apply_filters('updraftplus_files_altered_since', -1, $job_type));

		$undone_files = array();

		if ('no' == $backup_files) {
			$this->log("This backup run is not intended for files - skipping");
			$our_files = array();
		} else {

			// This should be always called; if there were no files in this run, it returns us an empty array
			$backup_array = $updraftplus_backup->resumable_backup_of_files($resumption_no);

			// This save, if there was something, is then immediately picked up again
			if (is_array($backup_array)) {
				$this->log('Saving backup status to database (elements: '.count($backup_array).")");
				$this->save_backup_history($backup_array);
			}

			// Switch of variable name is purely vestigial
			$our_files = $backup_array;
			if (!is_array($our_files)) $our_files = array();

		}

		$backup_databases = $this->jobdata_get('backup_database');

		if (!is_array($backup_databases)) $backup_databases = array('wp' => $backup_databases);

		foreach ($backup_databases as $whichdb => $backup_database) {

			if (is_array($backup_database)) {
				$dbinfo = $backup_database['dbinfo'];
				$backup_database = $backup_database['status'];
			} else {
				$dbinfo = array();
			}

			$tindex = ('wp' == $whichdb) ? 'db' : 'db'.$whichdb;

			if ('begun' == $backup_database || 'finished' == $backup_database || 'encrypted' == $backup_database) {

				if ('wp' == $whichdb) {
					$db_descrip = 'WordPress DB';
				} else {
					if (!empty($dbinfo) && is_array($dbinfo) && !empty($dbinfo['host'])) {
						$db_descrip = "External DB $whichdb - ".$dbinfo['user'].'@'.$dbinfo['host'].'/'.$dbinfo['name'];
					} else {
						$db_descrip = "External DB $whichdb - details appear to be missing";
					}
				}

				if ('begun' == $backup_database) {
					if ($resumption_no > 0) {
						$this->log("Resuming creation of database dump ($db_descrip)");
					} else {
						$this->log("Beginning creation of database dump ($db_descrip)");
					}
				} elseif ('encrypted' == $backup_database) {
					$this->log("Database dump ($db_descrip): Creation and encryption were completed already");
				} else {
					$this->log("Database dump ($db_descrip): Creation was completed already");
				}

				if ('wp' != $whichdb && (empty($dbinfo) || !is_array($dbinfo) || empty($dbinfo['host']))) {
					unset($backup_databases[$whichdb]);
					$this->jobdata_set('backup_database', $backup_databases);
					continue;
				}

				$db_backup = $updraftplus_backup->backup_db($backup_database, $whichdb, $dbinfo);

				if(is_array($our_files) && is_string($db_backup)) $our_files[$tindex] = $db_backup;

				if ('encrypted' != $backup_database) {
					$backup_databases[$whichdb] = array('status' => 'finished', 'dbinfo' => $dbinfo);
					$this->jobdata_set('backup_database', $backup_databases);
				}
			} elseif ('no' == $backup_database) {
				$this->log("No database backup ($whichdb) - not part of this run");
			} else {
				$this->log("Unrecognised data when trying to ascertain if the database ($whichdb) was backed up (".serialize($backup_database).")");
			}

			// This is done before cloud despatch, because we want a record of what *should* be in the backup. Whether it actually makes it there or not is not yet known.
			$this->save_backup_history($our_files);

			// Potentially encrypt the database if it is not already
			if ('no' != $backup_database && isset($our_files[$tindex]) && !preg_match("/\.crypt$/", $our_files[$tindex])) {
				$our_files[$tindex] = $updraftplus_backup->encrypt_file($our_files[$tindex]);
				// No need to save backup history now, as it will happen in a few lines time
				if (preg_match("/\.crypt$/", $our_files[$tindex])) {
					$backup_databases[$whichdb] = array('status' => 'encrypted', 'dbinfo' => $dbinfo);
					$this->jobdata_set('backup_database', $backup_databases);
				}
			}

			if ('no' != $backup_database && isset($our_files[$tindex]) && file_exists($updraft_dir.'/'.$our_files[$tindex])) {
				$our_files[$tindex.'-size'] = filesize($updraft_dir.'/'.$our_files[$tindex]);
				$this->save_backup_history($our_files);
			}

		}

		$backupable_entities = $this->get_backupable_file_entities(true);

		$checksums = array('sha1' => array());

		$total_size = 0;
		
		// Queue files for upload
		foreach ($our_files as $key => $files) {
			// Only continue if the stored info was about a dump
			if (!isset($backupable_entities[$key]) && ('db' != substr($key, 0, 2) || '-size' == substr($key, -5, 5))) continue;
			if (is_string($files)) $files = array($files);
			foreach ($files as $findex => $file) {
			
				$size_key = (0 == $findex) ? $key.'-size' : $key.$findex.'-size';
				$total_size = (false === $total_size || !isset($our_files[$size_key]) || !is_numeric($our_files[$size_key])) ? false : $total_size + $our_files[$size_key];
			
				$sha = $this->jobdata_get('sha1-'.$key.$findex);
				if ($sha) $checksums['sha1'][$key.$findex] = $sha;
				$sha = $this->jobdata_get('sha1-'.$key.$findex.'.crypt');
				if ($sha) $checksums['sha1'][$key.$findex.".crypt"] = $sha;
				if ($this->is_uploaded($file)) {
					$this->log("$file: $key: This file has already been successfully uploaded");
				} elseif (is_file($updraft_dir.'/'.$file)) {
					if (!in_array($file, $undone_files)) {
						$this->log("$file: $key: This file has not yet been successfully uploaded: will queue");
						$undone_files[$key.$findex] = $file;
					} else {
						$this->log("$file: $key: This file was already queued for upload (this condition should never be seen)");
					}
				} else {
					$this->log("$file: $key: Note: This file was not marked as successfully uploaded, but does not exist on the local filesystem ($updraft_dir/$file)");
					$this->uploaded_file($file, true);
				}
			}
		}
		$our_files['checksums'] = $checksums;

		// Save again (now that we have checksums)
		$size_description = (false === $total_size) ? 'Unknown' : $this->convert_numeric_size_to_text($total_size);
		$this->log("Saving backup history. Total backup size: $size_description");
		$this->save_backup_history($our_files);
		do_action('updraft_final_backup_history', $our_files);

		// We finished; so, low memory was not a problem
		$this->log_removewarning('lowram');

		if (0 == count($undone_files)) {
			$this->log("Resume backup ($bnonce, $resumption_no): finish run");
			if (is_array($our_files)) $this->save_last_backup($our_files);
			$this->log("There were no more files that needed uploading");
			// No email, as the user probably already got one if something else completed the run
			$allow_email = false;
			if ('begun' == $this->jobdata_get('prune')) {
				// Begun, but not finished
				$this->log("Restarting backup prune operation");
				$updraftplus_backup->do_prune_standalone();
				$allow_email = true;
			}
			$this->backup_finish($next_resumption, true, $allow_email, $resumption_no);
			restore_error_handler();
			return;
		}

		$this->error_count_before_cloud_backup = $this->error_count();

		// This is intended for one-shot backups, where we do want a resumption if it's only for uploading
		if (empty($this->newresumption_scheduled) && 0 == $resumption_no && 0 == $this->error_count_before_cloud_backup && true === $this->jobdata_get('reschedule_before_upload')) {
			$this->log("Cloud backup stage reached on one-shot backup: scheduling resumption for the cloud upload");
			$this->reschedule(60);
			$this->record_still_alive();
		}

		$this->log("Requesting upload of the files that have not yet been successfully uploaded (".count($undone_files).")");
		
		$updraftplus_backup->cloud_backup($undone_files);

		$this->log("Resume backup ($bnonce, $resumption_no): finish run");
		if (is_array($our_files)) $this->save_last_backup($our_files);
		$this->backup_finish($next_resumption, true, true, $resumption_no);

		restore_error_handler();

	}

	public function convert_numeric_size_to_text($size) {
		if ($size > 1073741824) {
			return round($size / 1073741824, 1).' GB';
		} elseif ($size > 1048576) {
			return round($size / 1048576, 1).' MB';
		} elseif ($size > 1024) {
			return round($size / 1024, 1).' KB';
		} else {
			return round($size, 1).' B';
		}
	}
	
	public function max_time_passed($time_passed, $upto, $first_run) {
		$max_time = 0;
		$timings_string = "";
		$run_times_known=0;
		for ($i=$first_run; $i<=$upto; $i++) {
			$timings_string .= "$i:";
			if (isset($time_passed[$i])) {
				$timings_string .=  round($time_passed[$i], 1).' ';
				$run_times_known++;
				if ($time_passed[$i] > $max_time) $max_time = round($time_passed[$i]);
			} else {
				$timings_string .=  '? ';
			}
		}
		return array($max_time, $timings_string, $run_times_known);
	}

	public function jobdata_getarray($non) {
		return get_site_option("updraft_jobdata_".$non, array());
	}

	public function jobdata_set_from_array($array) {
		$this->jobdata = $array;
		if (!empty($this->nonce)) update_site_option("updraft_jobdata_".$this->nonce, $this->jobdata);
	}

	// This works with any amount of settings, but we provide also a jobdata_set for efficiency as normally there's only one setting
	public function jobdata_set_multi() {
		if (!is_array($this->jobdata)) $this->jobdata = array();

		$args = func_num_args();

		for ($i=1; $i<=$args/2; $i++) {
			$key = func_get_arg($i*2-2);
			$value = func_get_arg($i*2-1);
			$this->jobdata[$key] = $value;
		}
		if (!empty($this->nonce)) update_site_option("updraft_jobdata_".$this->nonce, $this->jobdata);
	}

	public function jobdata_set($key, $value) {
		if (empty($this->jobdata)) {
			$this->jobdata = empty($this->nonce) ? array() : get_site_option("updraft_jobdata_".$this->nonce);
			if (!is_array($this->jobdata)) $this->jobdata = array();
		}
		$this->jobdata[$key] = $value;
		if ($this->nonce) update_site_option("updraft_jobdata_".$this->nonce, $this->jobdata);
	}

	public function jobdata_delete($key) {
		if (!is_array($this->jobdata)) {
			$this->jobdata = empty($this->nonce) ? array() : get_site_option("updraft_jobdata_".$this->nonce);
			if (!is_array($this->jobdata)) $this->jobdata = array();
		}
		unset($this->jobdata[$key]);
		if ($this->nonce) update_site_option("updraft_jobdata_".$this->nonce, $this->jobdata);
	}

	public function get_job_option($opt) {
		// These are meant to be read-only
		if (empty($this->jobdata['option_cache']) || !is_array($this->jobdata['option_cache'])) {
			if (!is_array($this->jobdata)) $this->jobdata = get_site_option("updraft_jobdata_".$this->nonce, array());
			$this->jobdata['option_cache'] = array();
		}
		return (isset($this->jobdata['option_cache'][$opt])) ? $this->jobdata['option_cache'][$opt] : UpdraftPlus_Options::get_updraft_option($opt);
	}

	public function jobdata_get($key, $default = null) {
		if (empty($this->jobdata)) {
			$this->jobdata = empty($this->nonce) ? array() : get_site_option("updraft_jobdata_".$this->nonce, array());
			if (!is_array($this->jobdata)) return $default;
		}
		return isset($this->jobdata[$key]) ? $this->jobdata[$key] : $default;
	}

	public function jobdata_reset() {
		$this->jobdata = null;
	}

	private function ensure_semaphore_exists($semaphore) {
		// Make sure the options for semaphores exist
		global $wpdb;
		$results = $wpdb->get_results("
			SELECT option_id
				FROM $wpdb->options
				WHERE option_name IN ('updraftplus_locked_$semaphore', 'updraftplus_unlocked_$semaphore', 'updraftplus_last_lock_time_$semaphore', 'updraftplus_semaphore_$semaphore')
		");
		// Use of update_option() is correct here - since it is what is used in class-semaphore.php
		if (!is_array($results) || count($results) < 3) {
			if (is_array($results) && count($results) > 0) $this->log("Semaphore ($semaphore) in an impossible/broken state - fixing (".count($results).")");
			update_option('updraftplus_unlocked_'.$semaphore, '1');
			delete_option('updraftplus_locked_'.$semaphore);
			update_option('updraftplus_last_lock_time_'.$semaphore, current_time('mysql', 1));
			update_option('updraftplus_semaphore_'.$semaphore, '0');
		}
	}

	public function backup_files() {
		# Note that the "false" for database gets over-ridden automatically if they turn out to have the same schedules
		$this->boot_backup(true, false);
	}
	
	public function backup_database() {
		# Note that nothing will happen if the file backup had the same schedule
		$this->boot_backup(false, true);
	}

	public function backup_all($options) {
		$skip_cloud = empty($options['nocloud']) ? false : true;
		$this->boot_backup(1, 1, false, false, ($skip_cloud) ? 'none' : false, $options);
	}
	
	public function backupnow_files($options) {
		$skip_cloud = empty($options['nocloud']) ? false : true;
		$this->boot_backup(1, 0, false, false, ($skip_cloud) ? 'none' : false, $options);
	}
	
	public function backupnow_database($options) {
		$skip_cloud = empty($options['nocloud']) ? false : true;
		$this->boot_backup(0, 1, false, false, ($skip_cloud) ? 'none' : false, $options);
	}

	// This procedure initiates a backup run
	// $backup_files/$backup_database: true/false = yes/no (over-write allowed); 1/0 = yes/no (force)
	public function boot_backup($backup_files, $backup_database, $restrict_files_to_override = false, $one_shot = false, $service = false, $options = array()) {

		@ignore_user_abort(true);
		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);

		if (false === $restrict_files_to_override && isset($options['restrict_files_to_override'])) $restrict_files_to_override = $options['restrict_files_to_override'];
		// Generate backup information
		$use_nonce = (empty($options['use_nonce'])) ? false : $options['use_nonce'];
		$this->backup_time_nonce($use_nonce);
		// The current_resumption is consulted within logfile_open()
		$this->current_resumption = 0;
		$this->logfile_open($this->nonce);

		if (!is_file($this->logfile_name)) {
			$this->log('Failed to open log file ('.$this->logfile_name.') - you need to check your UpdraftPlus settings (your chosen directory for creating files in is not writable, or you ran out of disk space). Backup aborted.');
			$this->log(__('Could not create files in the backup directory. Backup aborted - check your UpdraftPlus settings.','updraftplus'), 'error');
			return false;
		}

		// Some house-cleaning
		$this->clean_temporary_files();
		
		// Log some information that may be helpful
		$this->log("Tasks: Backup files: $backup_files (schedule: ".UpdraftPlus_Options::get_updraft_option('updraft_interval', 'unset').") Backup DB: $backup_database (schedule: ".UpdraftPlus_Options::get_updraft_option('updraft_interval_database', 'unset').")");

		// The is_bool() check here is confirming that we're allowed to adjust the parameters
		if (false === $one_shot && is_bool($backup_database)) {
			# If the files and database schedules are the same, and if this the file one, then we rope in database too.
			# On the other hand, if the schedules were the same and this was the database run, then there is nothing to do.
			
			$files_schedule = UpdraftPlus_Options::get_updraft_option('updraft_interval');
			$db_schedule = UpdraftPlus_Options::get_updraft_option('updraft_interval_database');
			
			$sched_log_extra = '';
			
			if ('manual' != $files_schedule) {
				if ($files_schedule == $db_schedule || UpdraftPlus_Options::get_updraft_option('updraft_interval_database', 'xyz') == 'xyz') {
					$sched_log_extra = 'Combining jobs from identical schedules. ';
					$backup_database = ($backup_files == true) ? true : false;
				} elseif ($files_schedule && $db_schedule && $files_schedule != $db_schedule) {

					// This stored value is the earliest of the two apparently-close jobs
					$combine_around = empty($this->combine_jobs_around) ? false : $this->combine_jobs_around;

					if (preg_match('/^(cancel:)?(\d+)$/', $combine_around, $matches)) {
					
						$combine_around = $matches[2];
					
						// Re-save the option, since otherwise it will have been reset and not be accessible to the 'other' run
						UpdraftPlus_Options::update_updraft_option('updraft_combine_jobs_around', 'cancel:'.$this->combine_jobs_around);
					
						$margin = (defined('UPDRAFTPLUS_COMBINE_MARGIN') && is_numeric(UPDRAFTPLUS_COMBINE_MARGIN)) ? UPDRAFTPLUS_COMBINE_MARGIN : 600;
						
						$time_now = time();

						// The margin is doubled, to cope with the lack of predictability in WP's cron system
						if ($time_now >= $combine_around && $time_now <= $combine_around + 2*$margin) {

							$sched_log_extra = 'Combining jobs from co-inciding events. ';
							
							if ('cancel:' == $matches[1]) {
								$backup_database = false;
								$backup_files = false;
							} else {
								// We want them both to happen on whichever run is first (since, afterwards, the updraft_combine_jobs_around option will have been removed when the event is rescheduled).
								$backup_database = true;
								$backup_files = true;
							}
							
						}
						
					}
				}
			}
			$this->log("Processed schedules. ${sched_log_extra}Tasks now: Backup files: $backup_files Backup DB: $backup_database");
		}

		$semaphore = (($backup_files) ? 'f' : '') . (($backup_database) ? 'd' : '');
		$this->ensure_semaphore_exists($semaphore);

		if (false == apply_filters('updraftplus_boot_backup', true, $backup_files, $backup_database, $one_shot)) {
			$this->log("Backup aborted (via filter)");
			return false;
		}

		if (!is_string($service) && !is_array($service)) $service = UpdraftPlus_Options::get_updraft_option('updraft_service');
		$service = $this->just_one($service);
		if (is_string($service)) $service = array($service);
		if (!is_array($service)) $service = array('none');

		if (!empty($options['extradata']) && preg_match('#services=remotesend/(\d+)#', $options['extradata'])) {
			if ($service === array('none')) $service = array();
			$service[] = 'remotesend';
		}

		$option_cache = array();

		foreach ($service as $serv) {
			if ('' == $serv || 'none' == $serv) continue;
			include_once(UPDRAFTPLUS_DIR.'/methods/'.$serv.'.php');
			$cclass = 'UpdraftPlus_BackupModule_'.$serv;
			$obj = new $cclass;

			if (method_exists($cclass, 'get_credentials')) {
				$opts = $obj->get_credentials();
				if (is_array($opts)) {
					foreach ($opts as $opt) $option_cache[$opt] = UpdraftPlus_Options::get_updraft_option($opt);
				}
			}
		}
		$option_cache = apply_filters('updraftplus_job_option_cache', $option_cache);

		// If nothing to be done, then just finish
		if (!$backup_files && !$backup_database) {
			$ret = $this->backup_finish(1, false, false, 0);
			// Don't keep useless log files
			if (!UpdraftPlus_Options::get_updraft_option('updraft_debug_mode') && !empty($this->logfile_name) && file_exists($this->logfile_name)) {
				unlink($this->logfile_name);
			}
			return $ret;
		}

		// Are we doing an action called by the WP scheduler? If so, we want to check when that last happened; the point being that the dodgy WP scheduler, when overloaded, can call the event multiple times - and sometimes, it evades the semaphore because it calls a second run after the first has finished, or > 3 minutes (our semaphore lock time) later
		// doing_action() was added in WP 3.9
		// wp_cron() can be called from the 'init' action
		
		if (function_exists('doing_action') && (doing_action('init') || @constant('DOING_CRON')) && (doing_action('updraft_backup_database') || doing_action('updraft_backup'))) {
			$last_scheduled_action_called_at = get_option("updraft_last_scheduled_$semaphore");
			// 11 minutes - so, we're assuming that they haven't custom-modified their schedules to run scheduled backups more often than that. If they have, they need also to use the filter to over-ride this check.
			$seconds_ago = time() - $last_scheduled_action_called_at;
			if ($last_scheduled_action_called_at && $seconds_ago < 660 && apply_filters('updraft_check_repeated_scheduled_backups', true)) {
				$this->log(sprintf('Scheduled backup aborted - another backup of this type was apparently invoked by the WordPress scheduler only %d seconds ago - the WordPress scheduler invoking events multiple times usually indicates a very overloaded server (or other plugins that mis-use the scheduler)', $seconds_ago));
				return;
			}
		}
		update_option("updraft_last_scheduled_$semaphore", time());
		
		require_once(UPDRAFTPLUS_DIR.'/includes/class-semaphore.php');
		$this->semaphore = UpdraftPlus_Semaphore::factory();
		$this->semaphore->lock_name = $semaphore;
		
		$semaphore_log_message = 'Requesting semaphore lock ('.$semaphore.')';
		if (!empty($last_scheduled_action_called_at)) {
			$semaphore_log_message .= " (apparently via scheduler: last_scheduled_action_called_at=$last_scheduled_action_called_at, seconds_ago=$seconds_ago)";
		} else {
			$semaphore_log_message .= " (apparently not via scheduler)";
		}
		
		$this->log($semaphore_log_message);
		if (!$this->semaphore->lock()) {
			$this->log('Failed to gain semaphore lock ('.$semaphore.') - another backup of this type is apparently already active - aborting (if this is wrong - i.e. if the other backup crashed without removing the lock, then another can be started after 3 minutes)');
			return;
		}
		
		// Allow the resume interval to be more than 300 if last time we know we went beyond that - but never more than 600
		if (defined('UPDRAFTPLUS_INITIAL_RESUME_INTERVAL') && is_numeric(UPDRAFTPLUS_INITIAL_RESUME_INTERVAL)) {
			$resume_interval = UPDRAFTPLUS_INITIAL_RESUME_INTERVAL;
		} else {
			$resume_interval = (int)min(max(300, get_site_transient('updraft_initial_resume_interval')), 600);
		}
		# We delete it because we only want to know about behaviour found during the very last backup run (so, if you move servers then old data is not retained)
		delete_site_transient('updraft_initial_resume_interval');

		$job_file_entities = array();
		if ($backup_files) {
			$possible_backups = $this->get_backupable_file_entities(true);
			foreach ($possible_backups as $youwhat => $whichdir) {
				if ((false === $restrict_files_to_override && UpdraftPlus_Options::get_updraft_option("updraft_include_$youwhat", apply_filters("updraftplus_defaultoption_include_$youwhat", true))) || (is_array($restrict_files_to_override) && in_array($youwhat, $restrict_files_to_override))) {
					// The 0 indicates the zip file index
					$job_file_entities[$youwhat] = array(
						'index' => 0
					);
				}
			}
		}

		$followups_allowed = (((!$one_shot && defined('DOING_CRON') && DOING_CRON)) || (defined('UPDRAFTPLUS_FOLLOWUPS_ALLOWED') && UPDRAFTPLUS_FOLLOWUPS_ALLOWED));

		$split_every = max(intval(UpdraftPlus_Options::get_updraft_option('updraft_split_every', 400)), UPDRAFTPLUS_SPLIT_MIN);

		$initial_jobdata = array(
			'resume_interval', $resume_interval,
			'job_type', 'backup',
			'jobstatus', 'begun',
			'backup_time', $this->backup_time,
			'job_time_ms', $this->job_time_ms,
			'service', $service,
			'split_every', $split_every,
			'maxzipbatch', 26214400, #25MB
			'job_file_entities', $job_file_entities,
			'option_cache', $option_cache,
			'uploaded_lastreset', 9,
			'one_shot', $one_shot,
			'followsups_allowed', $followups_allowed
		);

		if ($one_shot) update_site_option('updraft_oneshotnonce', $this->nonce);

		if (!empty($options['extradata']) && 'autobackup' == $options['extradata']) array_push($initial_jobdata, 'is_autobackup', true);

		// Save what *should* be done, to make it resumable from this point on
		if ($backup_database) {
			$dbs = apply_filters('updraft_backup_databases', array('wp' => 'begun'));
			if (is_array($dbs)) {
				foreach ($dbs as $key => $db) {
					if ('wp' != $key && (!is_array($db) || empty($db['dbinfo']) || !is_array($db['dbinfo']) || empty($db['dbinfo']['host']))) unset($dbs[$key]);
				}
			}
		} else {
			$dbs = "no";
		}

		array_push($initial_jobdata, 'backup_database', $dbs);
		array_push($initial_jobdata, 'backup_files', (($backup_files) ? 'begun' : 'no'));

		if (is_array($options) && !empty($options['label'])) array_push($initial_jobdata, 'label', $options['label']);

		try {
			// Use of jobdata_set_multi saves around 200ms
			call_user_func_array(array($this, 'jobdata_set_multi'), apply_filters('updraftplus_initial_jobdata', $initial_jobdata, $options, $split_every));
		} catch (Exception $e) {
			$this->log($e->getMessage());
			return false;
		}

		// Everything is set up; now go
		$this->backup_resume(0, $this->nonce);

		if ($one_shot) delete_site_option('updraft_oneshotnonce');

	}

	// This function examines inside the updraft directory to see if any new archives have been uploaded. If so, it adds them to the backup set. (Non-present items are also removed, only if the service is 'none').
	// If $remotescan is set, then remote storage is also scanned
	// $only_add_this_file : an array with keys 'name' and (optionally) 'label' 
	public function rebuild_backup_history($remotescan = false, $only_add_this_file = false) {

		# TODO: Make compatible with incremental naming scheme

		$messages = array();
		$gmt_offset = get_option('gmt_offset');

		// Array of nonces keyed by filename
		$known_files = array();
		// Array of backup times keyed by nonce
		$known_nonces = array();
		$changes = false;

		$backupable_entities = $this->get_backupable_file_entities(true, false);

		$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
		if (!is_array($backup_history)) $backup_history = array();
		$updraft_dir = $this->backups_dir_location();
		if (!is_dir($updraft_dir)) return;

		$accept = apply_filters('updraftplus_accept_archivename', array());
		if (!is_array($accept)) $accept = array();
		// Process what is known from the database backup history; this means populating $known_files and $known_nonces
		foreach ($backup_history as $btime => $bdata) {
			$found_file = false;
			foreach ($bdata as $key => $values) {
				if ('db' != $key && !isset($backupable_entities[$key])) continue;
				// Record which set this file is found in
				if (!is_array($values)) $values=array($values);
				foreach ($values as $val) {
					if (!is_string($val)) continue;
					if (preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-[\-a-z]+([0-9]+)?+(\.(zip|gz|gz\.crypt))?$/i', $val, $matches)) {
						$nonce = $matches[2];
						if (isset($bdata['service']) && ($bdata['service'] === 'none' || (is_array($bdata['service']) && (array('none') === $bdata['service'] || (1 == count($bdata['service']) && isset($bdata['service'][0]) && empty($bdata['service'][0]))))) && !is_file($updraft_dir.'/'.$val)) {
							# File without remote storage is no longer present
						} else {
							$found_file = true;
							$known_files[$val] = $nonce;
							$known_nonces[$nonce] = (empty($known_nonces[$nonce]) || $known_nonces[$nonce]<100) ? $btime : min($btime, $known_nonces[$nonce]);
						}
					} else {
						$accepted = false;
						foreach ($accept as $fkey => $acc) {
							if (preg_match('/'.$acc['pattern'].'/i', $val)) $accepted = $fkey;
						}
						if (!empty($accepted) && (false != ($btime = apply_filters('updraftplus_foreign_gettime', false, $accepted, $val))) && $btime > 0) {
							$found_file = true;
							# Generate a nonce; this needs to be deterministic and based on the filename only
							$nonce = substr(md5($val), 0, 12);
							$known_files[$val] = $nonce;
							$known_nonces[$nonce] = (empty($known_nonces[$nonce]) || $known_nonces[$nonce]<100) ? $btime : min($btime, $known_nonces[$nonce]);
						}
					}
				}
			}
			if (!$found_file) {
				# File recorded as being without remote storage is no longer present - though it may in fact exist in remote storage, and this will be picked up later
				unset($backup_history[$btime]);
				$changes = true;
			}
		}

		$remotefiles = array();
		$remotesizes = array();
		# Scan remote storage and get back lists of files and their sizes
		# TODO: Make compatible with incremental naming
		if ($remotescan) {
			add_action('http_request_args', array($this, 'modify_http_options'));
			foreach ($this->backup_methods as $method => $desc) {
				require_once(UPDRAFTPLUS_DIR.'/methods/'.$method.'.php');
				$objname = 'UpdraftPlus_BackupModule_'.$method;
				$obj = new $objname;
				if (!method_exists($obj, 'listfiles')) continue;
				$files = $obj->listfiles('backup_');
				if (is_array($files)) {
					foreach ($files as $entry) {
						$n = $entry['name'];
						if (!preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-([\-a-z]+)([0-9]+)?(\.(zip|gz|gz\.crypt))?$/i', $n, $matches)) continue;
						if (isset($remotefiles[$n])) {
							$remotefiles[$n][] = $method;
						} else {
							$remotefiles[$n] = array($method);
						}
						if (!empty($entry['size'])) {
							if (empty($remotesizes[$n]) || $remotesizes[$n] < $entry['size']) $remotesizes[$n] = $entry['size'];
						}
					}
				} elseif (is_wp_error($files)) {
					foreach ($files->get_error_codes() as $code) {
						if ('no_settings' == $code || 'no_addon' == $code || 'insufficient_php' == $code || 'no_listing' == $code) continue;
						$messages[] = array(
							'method' => $method,
							'desc' => $desc,
							'code' => $code,
							'message' => $files->get_error_message($code)
						);
					}
				}
			}
			remove_action('http_request_args', array($this, 'modify_http_options'));
		}

		if (!$handle = opendir($updraft_dir)) return;

		// See if there are any more files in the local directory than the ones already known about
		while (false !== ($entry = readdir($handle))) {
			$accepted_foreign = false;
			$potmessage = false;

			if ($only_add_this_file !== false && $entry != $only_add_this_file['file']) continue;

			if ('.' == $entry || '..' == $entry) continue;

			# TODO: Make compatible with Incremental naming
			if (preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-([\-a-z]+)([0-9]+)?(\.(zip|gz|gz\.crypt))?$/i', $entry, $matches)) {
				// Interpret the time as one from the blog's local timezone, rather than as UTC
				# $matches[1] is YYYY-MM-DD-HHmm, to be interpreted as being the local timezone
				$btime2 = strtotime($matches[1]);
				$btime = (!empty($gmt_offset)) ? $btime2 - $gmt_offset*3600 : $btime2;
				$nonce = $matches[2];
				$type = $matches[3];
				if ('db' == $type) {
					$type .= (!empty($matches[4])) ? $matches[4] : '';
					$index = 0;
				} else {
					$index = (empty($matches[4])) ? '0' : (max((int)$matches[4]-1,0));
				}
				$itext = ($index == 0) ? '' : $index;
			} elseif (false != ($accepted_foreign = apply_filters('updraftplus_accept_foreign', false, $entry)) && false !== ($btime = apply_filters('updraftplus_foreign_gettime', false, $accepted_foreign, $entry))) {
				$nonce = substr(md5($entry), 0, 12);
				$type = (preg_match('/\.sql(\.(bz2|gz))?$/i', $entry) || preg_match('/-database-([-0-9]+)\.zip$/i', $entry) || preg_match('/backup_db_/', $entry)) ? 'db' : 'wpcore';
				$index = apply_filters('updraftplus_accepted_foreign_index', 0, $entry, $accepted_foreign);
				$itext = $index ? $index : '';
				$potmessage = array(
					'code' => 'foundforeign_'.md5($entry),
					'desc' => $entry,
					'method' => '',
					'message' => sprintf(__('Backup created by: %s.', 'updraftplus'), $accept[$accepted_foreign]['desc'])
				);
			} elseif ('.zip' == strtolower(substr($entry, -4, 4)) || preg_match('/\.sql(\.(bz2|gz))?$/i', $entry)) {
				$potmessage = array(
					'code' => 'possibleforeign_'.md5($entry),
					'desc' => $entry,
					'method' => '',
					'message' => __('This file does not appear to be an UpdraftPlus backup archive (such files are .zip or .gz files which have a name like: backup_(time)_(site name)_(code)_(type).(zip|gz)).', 'updraftplus').' <a href="https://updraftplus.com/shop/updraftplus-premium/">'.__('If this is a backup created by a different backup plugin, then UpdraftPlus Premium may be able to help you.', 'updraftplus').'</a>'
				);
				$messages[$potmessage['code']] = $potmessage;
				continue;
			} else {
				continue;
			}
			// The time from the filename does not include seconds. Need to identify the seconds to get the right time
			if (isset($known_nonces[$nonce])) {
				$btime_exact = $known_nonces[$nonce];
				# TODO: If the btime we had was more than 60 seconds earlier, then this must be an increment - we then need to change the $backup_history array accordingly. We can pad the '60 second' test, as there's no option to run an increment more frequently than every 4 hours (though someone could run one manually from the CLI)
				if ($btime > 100 && $btime_exact - $btime > 60 && !empty($backup_history[$btime_exact])) {
					# TODO: This needs testing
					# The code below assumes that $backup_history[$btime] is presently empty
					# Re-key array, indicating the newly-found time to be the start of the backup set
					$backup_history[$btime] = $backup_history[$btime_exact];
					unset($backup_history[$btime_exact]);
					$btime_exact = $btime;
				}
				$btime = $btime_exact;
			}
			if ($btime <= 100) continue;
			$fs = @filesize($updraft_dir.'/'.$entry);

			if (!isset($known_files[$entry])) {
				$changes = true;
				if (is_array($potmessage)) $messages[$potmessage['code']] = $potmessage;
				if (is_array($only_add_this_file)) {
					if (isset($only_add_this_file['label'])) $backup_history[$btime]['label'] = $only_add_this_file['label'];
					$backup_history[$btime]['native'] = false;
				} elseif ('db' == $type && !$accepted_foreign) {
					list ($mess, $warn, $err, $info) = $this->analyse_db_file(false, array(), $updraft_dir.'/'.$entry, true);
					if (!empty($info['label'])) {
						$backup_history[$btime]['label'] = $info['label'];
					}
					if (!empty($info['created_by_version'])) {
						$backup_history[$btime]['created_by_version'] = $info['created_by_version'];
					}
				}
			}

			# TODO: Code below here has not been reviewed or adjusted for compatibility with incremental backups
			# Make sure we have the right list of services
			$current_services = (!empty($backup_history[$btime]) && !empty($backup_history[$btime]['service'])) ? $backup_history[$btime]['service'] : array();
			if (is_string($current_services)) $current_services = array($current_services);
			if (!is_array($current_services)) $current_services = array();
			if (!empty($remotefiles[$entry])) {
				if (0 == count(array_diff($current_services, $remotefiles[$entry]))) {
					$backup_history[$btime]['service'] = $remotefiles[$entry];
					$changes = true;
				}
				# Get the right size (our local copy may be too small)
				foreach ($remotefiles[$entry] as $rem) {
					if (!empty($rem['size']) && $rem['size'] > $fs) {
						$fs = $rem['size'];
						$changes = true;
					}
				}
				# Remove from $remotefiles, so that we can later see what was left over
				unset($remotefiles[$entry]);
			} else {
				# Not known remotely
				if (!empty($backup_history[$btime])) {
					if (empty($backup_history[$btime]['service']) || ('none' !== $backup_history[$btime]['service'] && ''  !== $backup_history[$btime]['service'] && array('none') !== $backup_history[$btime]['service'])) {
						$backup_history[$btime]['service'] = 'none';
						$changes = true;
					}
				} else {
					$backup_history[$btime]['service'] = 'none';
					$changes = true;
				}
			}

			$backup_history[$btime][$type][$index] = $entry;
			if ($fs > 0) $backup_history[$btime][$type.$itext.'-size'] = $fs;
			$backup_history[$btime]['nonce'] = $nonce;
			if (!empty($accepted_foreign)) $backup_history[$btime]['meta_foreign'] = $accepted_foreign;
		}

		# Any found in remote storage that we did not previously know about?
		# Compare $remotefiles with $known_files / $known_nonces, and adjust $backup_history
		if (count($remotefiles) > 0) {

			# $backup_history[$btime]['nonce'] = $nonce
			foreach ($remotefiles as $file => $services) {
				if (!preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-([\-a-z]+)([0-9]+)?(\.(zip|gz|gz\.crypt))?$/i', $file, $matches)) continue;
				$nonce = $matches[2];
				$type = $matches[3];
				if ('db' == $type) {
					$index = 0;
					$type .= empty($matches[4]) ? $matches[4] : '';
				} else {
					$index = (empty($matches[4])) ? '0' : (max((int)$matches[4]-1,0));
				}
				$itext = ($index == 0) ? '' : $index;
				$btime2 = strtotime($matches[1]);
				$btime = (!empty($gmt_offset)) ? $btime2 - $gmt_offset*3600 : $btime2;

				if (isset($known_nonces[$nonce])) $btime = $known_nonces[$nonce];
				if ($btime <= 100) continue;
				# Remember that at this point, we already know that the file is not known about locally
				if (isset($backup_history[$btime])) {
					if (!isset($backup_history[$btime]['service']) || ((is_array($backup_history[$btime]['service']) && $backup_history[$btime]['service'] !== $services) || is_string($backup_history[$btime]['service']) && (1 != count($services) || $services[0] !== $backup_history[$btime]['service']))) {
						$changes = true;
						$backup_history[$btime]['service'] = $services;
						$backup_history[$btime]['nonce'] = $nonce;
					}
					if (!isset($backup_history[$btime][$type][$index])) {
						$changes = true;
						$backup_history[$btime][$type][$index] = $file;
						$backup_history[$btime]['nonce'] = $nonce;
						if (!empty($remotesizes[$file])) $backup_history[$btime][$type.$itext.'-size'] = $remotesizes[$file];
					}
				} else {
					$changes = true;
					$backup_history[$btime]['service'] = $services;
					$backup_history[$btime][$type][$index] = $file;
					$backup_history[$btime]['nonce'] = $nonce;
					if (!empty($remotesizes[$file])) $backup_history[$btime][$type.$itext.'-size'] = $remotesizes[$file];
					$backup_history[$btime]['native'] = false;
					$messages['nonnative'] = array(
						'message' => __('One or more backups has been added from scanning remote storage; note that these backups will not be automatically deleted through the "retain" settings; if/when you wish to delete them then you must do so manually.', 'updraftplus'),
						'code' => 'nonnative',
						'desc' => '',
						'method' => ''
					);
				}

			}
		}

		if ($changes) UpdraftPlus_Options::update_updraft_option('updraft_backup_history', $backup_history);

		return $messages;

	}

	private function backup_finish($cancel_event, $do_cleanup, $allow_email, $resumption_no, $force_abort = false) {

		if (!empty($this->semaphore)) $this->semaphore->unlock();

		$delete_jobdata = false;

		// The valid use of $do_cleanup is to indicate if in fact anything exists to clean up (if no job really started, then there may be nothing)

		// In fact, leaving the hook to run (if debug is set) is harmless, as the resume job should only do tasks that were left unfinished, which at this stage is none.
		if (0 == $this->error_count() || $force_abort) {
			if ($do_cleanup) {
				$this->log("There were no errors in the uploads, so the 'resume' event ($cancel_event) is being unscheduled");
				# This apparently-worthless setting of metadata before deleting it is for the benefit of a WP install seen where wp_clear_scheduled_hook() and delete_transient() apparently did nothing (probably a faulty cache)
				$this->jobdata_set('jobstatus', 'finished');
				wp_clear_scheduled_hook('updraft_backup_resume', array($cancel_event, $this->nonce));
				# This should be unnecessary - even if it does resume, all should be detected as finished; but I saw one very strange case where it restarted, and repeated everything; so, this will help
				wp_clear_scheduled_hook('updraft_backup_resume', array($cancel_event+1, $this->nonce));
				wp_clear_scheduled_hook('updraft_backup_resume', array($cancel_event+2, $this->nonce));
				wp_clear_scheduled_hook('updraft_backup_resume', array($cancel_event+3, $this->nonce));
				wp_clear_scheduled_hook('updraft_backup_resume', array($cancel_event+4, $this->nonce));
				$delete_jobdata = true;
			}
		} else {
			$this->log("There were errors in the uploads, so the 'resume' event is remaining scheduled");
			$this->jobdata_set('jobstatus', 'resumingforerrors');
			# If there were no errors before moving to the upload stage, on the first run, then bring the resumption back very close. Since this is only attempted on the first run, it is really only an efficiency thing for a quicker finish if there was an unexpected networking event. We don't want to do it straight away every time, as it may be that the cloud service is down - and might be up in 5 minutes time. This was added after seeing a case where resumption 0 got to run for 10 hours... and the resumption 7 that should have picked up the uploading of 1 archive that failed never occurred.
			if (isset($this->error_count_before_cloud_backup) && 0 === $this->error_count_before_cloud_backup) {
				if (0 == $resumption_no) {
					$this->reschedule(60);
				} else {
					// Added 27/Feb/2016 - though the cloud service seems to be down, we still don't want to wait too long
					$resume_interval = $this->jobdata_get('resume_interval');
					
					// 15 minutes + 2 for each resumption (a modest back-off)
					$max_interval = 900 + $resumption_no * 120;
					if ($resume_interval > $max_interval) {
						$this->reschedule($max_interval);
					}
				}
			}
		}

		// Send the results email if appropriate, which means:
		// - The caller allowed it (which is not the case in an 'empty' run)
		// - And: An email address was set (which must be so in email mode)
		// And one of:
		// - Debug mode
		// - There were no errors (which means we completed and so this is the final run - time for the final report)
		// - It was the tenth resumption; everything failed

		$send_an_email = false;
		# Save the jobdata's state for the reporting - because it might get changed (e.g. incremental backup is scheduled)
		$jobdata_as_was = $this->jobdata;

		// Make sure that the final status is shown
		if ($force_abort) {
			$send_an_email = true;
			$final_message = __('The backup was aborted by the user', 'updraftplus');
		} elseif (0 == $this->error_count()) {
			$send_an_email = true;
			$service = $this->jobdata_get('service');
			$remote_sent = (!empty($service) && ((is_array($service) && in_array('remotesend', $service)) || 'remotesend' === $service)) ? true : false;
			if (0 == $this->error_count('warning')) {
				$final_message = __('The backup apparently succeeded and is now complete', 'updraftplus');
				# Ensure it is logged in English. Not hugely important; but helps with a tiny number of really broken setups in which the options cacheing is broken
				if ('The backup apparently succeeded and is now complete' != $final_message) {
					$this->log('The backup apparently succeeded and is now complete');
				}
			} else {
				$final_message = __('The backup apparently succeeded (with warnings) and is now complete','updraftplus');
				if ('The backup apparently succeeded (with warnings) and is now complete' != $final_message) {
					$this->log('The backup apparently succeeded (with warnings) and is now complete');
				}
			}
			if ($remote_sent && !$force_abort) $final_message .= '. '.__('To complete your migration/clone, you should now log in to the remote site and restore the backup set.', 'updraftplus');
			if ($do_cleanup) $delete_jobdata = apply_filters('updraftplus_backup_complete', $delete_jobdata);
		} elseif (false == $this->newresumption_scheduled) {
			$send_an_email = true;
			$final_message = __('The backup attempt has finished, apparently unsuccessfully', 'updraftplus');
		} else {
			// There are errors, but a resumption will be attempted
			$final_message = __('The backup has not finished; a resumption is scheduled', 'updraftplus');
		}

		// Now over-ride the decision to send an email, if needed
		if (UpdraftPlus_Options::get_updraft_option('updraft_debug_mode')) {
			$send_an_email = true;
			$this->log("An email has been scheduled for this job, because we are in debug mode");
		}

		$email = UpdraftPlus_Options::get_updraft_option('updraft_email');

		// If there's no email address, or the set was empty, that is the final over-ride: don't send
		if (!$allow_email) {
			$send_an_email = false;
			$this->log("No email will be sent - this backup set was empty.");
		} elseif (empty($email)) {
			$send_an_email = false;
			$this->log("No email will/can be sent - the user has not configured an email address.");
		}

		global $updraftplus_backup;

		if ($force_abort) $jobdata_as_was['aborted'] = true;
		if ($send_an_email) $updraftplus_backup->send_results_email($final_message, $jobdata_as_was);

		# Make sure this is the final message logged (so it remains on the dashboard)
		$this->log($final_message);

		@fclose($this->logfile_handle);
		$this->logfile_handle = null;

		// This is left until last for the benefit of the front-end UI, which then gets maximum chance to display the 'finished' status
		if ($delete_jobdata) delete_site_option('updraft_jobdata_'.$this->nonce);

	}

	// This function returns 'true' if mod_rewrite could be detected as unavailable; a 'false' result may mean it just couldn't find out the answer
	public function mod_rewrite_unavailable($check_if_in_use_first = true) {
		if (function_exists('apache_get_modules')) {
			global $wp_rewrite;
			$mods = apache_get_modules();
			if ((!$check_if_in_use_first || $wp_rewrite->using_mod_rewrite_permalinks()) && ((in_array('core', $mods) || in_array('http_core', $mods)) && !in_array('mod_rewrite', $mods))) {
				return true;
			}
		}
		return false;
	}
	
	public function error_count($level = 'error') {
		$count = 0;
		foreach ($this->errors as $err) {
			if (('error' == $level && (is_string($err) || is_wp_error($err))) || (is_array($err) && $level == $err['level']) ) { $count++; }
		}
		return $count;
	}

	public function list_errors() {
		echo '<ul style="list-style: disc inside;">';
		foreach ($this->errors as $err) {
			if (is_wp_error($err)) {
				foreach ($err->get_error_messages() as $msg) {
					echo '<li>'.htmlspecialchars($msg).'<li>';
				}
			} elseif (is_array($err) && 'error' == $err['level']) {
				echo  "<li>".htmlspecialchars($err['message'])."</li>";
			} elseif (is_string($err)) {
				echo  "<li>".htmlspecialchars($err)."</li>";
			} else {
				print "<li>".print_r($err,true)."</li>";
			}
		}
		echo '</ul>';
	}

	private function save_last_backup($backup_array) {
		$success = ($this->error_count() == 0) ? 1 : 0;
		$last_backup = apply_filters('updraftplus_save_last_backup', array(
			'backup_time' => $this->backup_time,
			'backup_array' => $backup_array,
			'success' => $success,
			'errors' => $this->errors,
			'backup_nonce' => $this->nonce
		));
		UpdraftPlus_Options::update_updraft_option('updraft_last_backup', $last_backup, false);
	}

	# $handle must be either false or a WPDB class (or extension thereof). Other options are not yet fully supported.
	public function check_db_connection($handle = false, $logit = false, $reschedule = false) {

		$type = false;
		if (false === $handle || is_a($handle, 'wpdb')) {
			$type='wpdb';
		} elseif (is_resource($handle)) {
			# Expected: string(10) "mysql link"
			$type=get_resource_type($handle);
		} elseif (is_object($handle) && is_a($handle, 'mysqli')) {
			$type='mysqli';
		}
 
		if (false === $type) return -1;

		$db_connected = -1;

		if ('mysql link' == $type || 'mysqli' == $type) {
			if ('mysql link' == $type && @mysql_ping($handle)) return true;
			if ('mysqli' == $type && @mysqli_ping($handle)) return true;

			for ( $tries = 1; $tries <= 5; $tries++ ) {
				# to do, if ever needed
// 				if ( $this->db_connect( false ) ) return true;
// 				sleep( 1 );
			}

		} elseif ('wpdb' == $type) {
			if (false === $handle || (is_object($handle) && 'wpdb' == get_class($handle))) {
				global $wpdb;
				$handle = $wpdb;
			}
			if (method_exists($handle, 'check_connection')) {
				if (!$handle->check_connection(false)) {
					if ($logit) $this->log("The database went away, and could not be reconnected to");
					# Almost certainly a no-op
					if ($reschedule) $this->reschedule(60);
					$db_connected = false;
				} else {
					$db_connected = true;
				}
			}
		}

		return $db_connected;

	}

	// This should be called whenever a file is successfully uploaded
	public function uploaded_file($file, $force = false) {
	
		global $updraftplus_backup;

		$db_connected = $this->check_db_connection(false, true, true);

		$service = (empty($updraftplus_backup->current_service)) ? '' : $updraftplus_backup->current_service;
		$shash = $service.'-'.md5($file);

		$this->jobdata_set("uploaded_".$shash, 'yes');
	
		if ($force || !empty($updraftplus_backup->last_service)) {
			$hash = md5($file);
			$this->log("Recording as successfully uploaded: $file ($hash)");
			$this->jobdata_set('uploaded_lastreset', $this->current_resumption);
			$this->jobdata_set("uploaded_".$hash, 'yes');
		} else {
			$this->log("Recording as successfully uploaded: $file (".$updraftplus_backup->current_service.", more services to follow)");
		}

		$upload_status = $this->jobdata_get('uploading_substatus');
		if (is_array($upload_status) && isset($upload_status['i'])) {
			$upload_status['i']++;
			$upload_status['p']=0;
			$this->jobdata_set('uploading_substatus', $upload_status);
		}

		# Really, we could do this immediately when we realise the DB has gone away. This is just for the probably-impossible case that a DB write really can still succeed. But, we must abort before calling delete_local(), as the removal of the local file can cause it to be recreated if the DB is out of sync with the fact that it really is already uploaded
		if (false === $db_connected) {
			$this->record_still_alive();
			die;
		}

		// Delete local files immediately if the option is set
		// Where we are only backing up locally, only the "prune" function should do deleting
		$service = $this->jobdata_get('service');
		if (!empty($updraftplus_backup->last_service) && ($service !== '' && ((is_array($service) && count($service)>0 && (count($service) > 1 || ($service[0] != '' && $service[0] != 'none'))) || (is_string($service) && $service !== 'none')))) {
			$this->delete_local($file);
		}
	}

	public function is_uploaded($file, $service = '') {
		$hash = $service.(('' == $service) ? '' : '-').md5($file);
		return ($this->jobdata_get("uploaded_$hash") === "yes") ? true : false;
	}

	private function delete_local($file) {
		$log = "Deleting local file: $file: ";
		if (UpdraftPlus_Options::get_updraft_option('updraft_delete_local')) {
			$fullpath = $this->backups_dir_location().'/'.$file;
			$deleted = unlink($fullpath);
			$this->log($log.(($deleted) ? 'OK' : 'failed'));
			return $deleted;
		} else {
			$this->log($log."skipped: user has unchecked updraft_delete_local option");
		}
		return true;
	}

	// This function is not needed for backup success, according to the design, but it helps with efficient scheduling
	private function reschedule_if_needed() {
		// If nothing is scheduled, then return
		if (empty($this->newresumption_scheduled)) return;
		$time_now = time();
		$time_away = $this->newresumption_scheduled - $time_now;
		// 45 is chosen because it is 15 seconds more than what is used to detect recent activity on files (file mod times). (If we use exactly the same, then it's more possible to slightly miss each other)
		if ($time_away >1 && $time_away <= 45) {
			$this->log('The scheduled resumption is within 45 seconds - will reschedule');
			// Push 45 seconds into the future
 			// $this->reschedule(60);
			// Increase interval generally by 45 seconds, on the assumption that our prior estimates were innaccurate (i.e. not just 45 seconds *this* time)
			$this->increase_resume_and_reschedule(45);
		}
	}

	public function reschedule($how_far_ahead) {
		// Reschedule - remove presently scheduled event
		$next_resumption = $this->current_resumption + 1;
		wp_clear_scheduled_hook('updraft_backup_resume', array($next_resumption, $this->nonce));
		// Add new event
		# This next line may be too cautious; but until 14-Aug-2014, it was 300.
		# Update 20-Mar-2015 - lowered from 180
		if ($how_far_ahead < 120) $how_far_ahead=120;
		$schedule_for = time() + $how_far_ahead;
		$this->log("Rescheduling resumption $next_resumption: moving to $how_far_ahead seconds from now ($schedule_for)");
		wp_schedule_single_event($schedule_for, 'updraft_backup_resume', array($next_resumption, $this->nonce));
		$this->newresumption_scheduled = $schedule_for;
	}

	private function increase_resume_and_reschedule($howmuch = 120, $force_schedule = false) {

		$resume_interval = max(intval($this->jobdata_get('resume_interval')), ($howmuch === 0) ? 120 : 300);

		if (empty($this->newresumption_scheduled) && $force_schedule) {
			$this->log("A new resumption will be scheduled to prevent the job ending");
		}

		$new_resume = $resume_interval + $howmuch;
		# It may be that we're increasing for the second (or more) time during a run, and that we already know that the new value will be insufficient, and can be increased
		if ($this->opened_log_time > 100 && microtime(true)-$this->opened_log_time > $new_resume) {
			$new_resume = ceil(microtime(true)-$this->opened_log_time)+45;
			$howmuch = $new_resume-$resume_interval;
		}

		# This used to be always $new_resume, until 14-Aug-2014. However, people who have very long-running processes can end up with very long times between resumptions as a result.
		# Actually, let's not try this yet. I think it is safe, but think there is a more conservative solution available.
		#$how_far_ahead = min($new_resume, 600);
		$how_far_ahead = $new_resume;
		# If it is very long-running, then that would normally be known soon.
		# If the interval is already 12 minutes or more, then try the next resumption 10 minutes from now (i.e. sooner than it would have been). Thus, we are guaranteed to get at least 24 minutes of processing in the first 34.
		if ($this->current_resumption <= 1 && $new_resume > 720) $how_far_ahead = 600;

		if (!empty($this->newresumption_scheduled) || $force_schedule) $this->reschedule($how_far_ahead);
		$this->jobdata_set('resume_interval', $new_resume);

		$this->log("To decrease the likelihood of overlaps, increasing resumption interval to: $resume_interval + $howmuch = $new_resume");
	}

	// For detecting another run, and aborting if one was found
	public function check_recent_modification($file) {
		if (file_exists($file)) {
			$time_mod = (int)@filemtime($file);
			$time_now = time();
			if ($time_mod>100 && ($time_now-$time_mod)<30) {
				$this->terminate_due_to_activity($file, $time_now, $time_mod);
			}
		}
	}

	public function get_exclude($whichone) {
		if ('uploads' == $whichone) {
			$exclude = explode(',', UpdraftPlus_Options::get_updraft_option('updraft_include_uploads_exclude', UPDRAFT_DEFAULT_UPLOADS_EXCLUDE));
		} elseif ('others' == $whichone) {
			$exclude = explode(',', UpdraftPlus_Options::get_updraft_option('updraft_include_others_exclude', UPDRAFT_DEFAULT_OTHERS_EXCLUDE));
		} else {
			$exclude = apply_filters('updraftplus_include_'.$whichone.'_exclude', array());
		}
		return (empty($exclude) || !is_array($exclude)) ? array() : $exclude;
	}

	public function really_is_writable($dir) {
		// Suppress warnings, since if the user is dumping warnings to screen, then invalid JavaScript results and the screen breaks.
		if (!@is_writable($dir)) return false;
		// Found a case - GoDaddy server, Windows, PHP 5.2.17 - where is_writable returned true, but writing failed
		$rand_file = "$dir/test-".md5(rand().time()).".txt";
		while (file_exists($rand_file)) {
			$rand_file = "$dir/test-".md5(rand().time()).".txt";
		}
		$ret = @file_put_contents($rand_file, 'testing...');
		@unlink($rand_file);
		return ($ret > 0);
	}

	public function wp_upload_dir() {
		if (is_multisite()) {
			global $current_site;
			switch_to_blog($current_site->blog_id);
		}
		
		$wp_upload_dir = wp_upload_dir();
		
		if (is_multisite()) restore_current_blog();

		return $wp_upload_dir;
	}

	public function backup_uploads_dirlist($logit = false) {
		# Create an array of directories to be skipped
		# Make the values into the keys
		$exclude = UpdraftPlus_Options::get_updraft_option('updraft_include_uploads_exclude', UPDRAFT_DEFAULT_UPLOADS_EXCLUDE);
		if ($logit) $this->log("Exclusion option setting (uploads): ".$exclude);
		$skip = array_flip(preg_split("/,/", $exclude));
		$wp_upload_dir = $this->wp_upload_dir();
		$uploads_dir = $wp_upload_dir['basedir'];
		return $this->compile_folder_list_for_backup($uploads_dir, array(), $skip);
	}

	public function backup_others_dirlist($logit = false) {
		# Create an array of directories to be skipped
		# Make the values into the keys
		$exclude = UpdraftPlus_Options::get_updraft_option('updraft_include_others_exclude', UPDRAFT_DEFAULT_OTHERS_EXCLUDE);
		if ($logit) $this->log("Exclusion option setting (others): ".$exclude);
		$skip = array_flip(preg_split("/,/", $exclude));
		$file_entities = $this->get_backupable_file_entities(false);

		# Keys = directory names to avoid; values = the label for that directory (used only in log files)
		#$avoid_these_dirs = array_flip($file_entities);
		$avoid_these_dirs = array();
		foreach ($file_entities as $type => $dirs) {
			if (is_string($dirs)) {
				$avoid_these_dirs[$dirs] = $type;
			} elseif (is_array($dirs)) {
				foreach ($dirs as $dir) {
					$avoid_these_dirs[$dir] = $type;
				}
			}
		}
		return $this->compile_folder_list_for_backup(WP_CONTENT_DIR, $avoid_these_dirs, $skip);
	}

	// Add backquotes to tables and db-names in SQL queries. Taken from phpMyAdmin.
	public function backquote($a_name) {
		if (!empty($a_name) && $a_name != '*') {
			if (is_array($a_name)) {
				$result = array();
				reset($a_name);
				while(list($key, $val) = each($a_name)) 
					$result[$key] = '`'.$val.'`';
				return $result;
			} else {
				return '`'.$a_name.'`';
			}
		} else {
			return $a_name;
		}
	}

	public function strip_dirslash($string) {
		return preg_replace('#/+(,|$)#', '$1', $string);
	}

	public function remove_empties($list) {
		if (!is_array($list)) return $list;
		foreach ($list as $ind => $entry) {
			if (empty($entry)) unset($list[$ind]);
		}
		return $list;
	}

	// avoid_these_dirs and skip_these_dirs ultimately do the same thing; but avoid_these_dirs takes full paths whereas skip_these_dirs takes basenames; and they are logged differently (dirs in avoid are potentially dangerous to include; skip is just a user-level preference). They are allowed to overlap.
	public function compile_folder_list_for_backup($backup_from_inside_dir, $avoid_these_dirs, $skip_these_dirs) {

		// Entries in $skip_these_dirs are allowed to end in *, which means "and anything else as a suffix". It's not a full shell glob, but it covers what is needed to-date.

		$dirlist = array();
		$added = 0;

		$this->log('Looking for candidates to back up in: '.$backup_from_inside_dir);
		$updraft_dir = $this->backups_dir_location();

		if (is_file($backup_from_inside_dir)) {
			array_push($dirlist, $backup_from_inside_dir);
			$added++;
			$this->log("finding files: $backup_from_inside_dir: adding to list ($added)");
		} elseif ($handle = opendir($backup_from_inside_dir)) {
			
			while (false !== ($entry = readdir($handle))) {
				// $candidate: full path; $entry = one-level
				$candidate = $backup_from_inside_dir.'/'.$entry;
				if ($entry != "." && $entry != "..") {
					if (isset($avoid_these_dirs[$candidate])) {
						$this->log("finding files: $entry: skipping: this is the ".$avoid_these_dirs[$candidate]." directory");
					} elseif ($candidate == $updraft_dir) {
						$this->log("finding files: $entry: skipping: this is the updraft directory");
					} elseif (isset($skip_these_dirs[$entry])) {
						$this->log("finding files: $entry: skipping: excluded by options");
					} else {
						$add_to_list = true;
						// Now deal with entries in $skip_these_dirs ending in * or starting with *
						foreach ($skip_these_dirs as $skip => $sind) {
							if ('*' == substr($skip, -1, 1) && '*' == substr($skip, 0, 1) && strlen($skip) > 2) {
								if (strpos($entry, substr($skip, 1, strlen($skip-2))) !== false) {
									$this->log("finding files: $entry: skipping: excluded by options (glob)");
									$add_to_list = false;
								}
							} elseif ('*' == substr($skip, -1, 1) && strlen($skip) > 1) {
								if (substr($entry, 0, strlen($skip)-1) == substr($skip, 0, strlen($skip)-1)) {
									$this->log("finding files: $entry: skipping: excluded by options (glob)");
									$add_to_list = false;
								}
							} elseif ('*' == substr($skip, 0, 1) && strlen($skip) > 1) {
								if (strlen($entry) >= strlen($skip)-1 && substr($entry, (strlen($skip)-1)*-1) == substr($skip, 1)) {
									$this->log("finding files: $entry: skipping: excluded by options (glob)");
									$add_to_list = false;
								}
							}
						}
						if ($add_to_list) {
							array_push($dirlist, $candidate);
							$added++;
							$skip_dblog = (($added > 50 && 0 != $added % 100) || ($added > 2000 && 0 != $added % 500));
							$this->log("finding files: $entry: adding to list ($added)", 'notice', false, $skip_dblog);
						}
					}
				}
			}
			@closedir($handle);
		} else {
			$this->log('ERROR: Could not read the directory: '.$backup_from_inside_dir);
			$this->log(__('Could not read the directory', 'updraftplus').': '.$backup_from_inside_dir, 'error');
		}

		return $dirlist;

	}

	private function save_backup_history($backup_array) {
		if(is_array($backup_array)) {
			$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
			$backup_history = (is_array($backup_history)) ? $backup_history : array();
			$backup_array['nonce'] = $this->nonce;
			$backup_array['service'] = $this->jobdata_get('service');
			if ('' != ($label = $this->jobdata_get('label', ''))) $backup_array['label'] = $label;
			$backup_array['created_by_version'] = $this->version;
			$backup_array['is_multisite'] = is_multisite() ? true : false;
			$remotesend_info = $this->jobdata_get('remotesend_info');
			if (is_array($remotesend_info) && !empty($remotesend_info['url'])) $backup_array['remotesend_url'] = $remotesend_info['url'];
			if (false != ($autobackup = $this->jobdata_get('is_autobackup', false))) $backup_array['autobackup'] = true;
			$backup_history[$this->backup_time] = $backup_array;
			UpdraftPlus_Options::update_updraft_option('updraft_backup_history', $backup_history, false);
		} else {
			$this->log('Could not save backup history because we have no backup array. Backup probably failed.');
			$this->log(__('Could not save backup history because we have no backup array. Backup probably failed.','updraftplus'), 'error');
		}
	}
	
	public function is_db_encrypted($file) {
		return preg_match('/\.crypt$/i', $file);
	}

	public function get_backup_history($timestamp = false) {
		$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
		// The line below actually *introduces* a race condition
 		// global $wpdb;
 		// $backup_history = @unserialize($wpdb->get_var($wpdb->prepare("SELECT option_value from $wpdb->options WHERE option_name='updraft_backup_history'")));
		if (is_array($backup_history)) {
			krsort($backup_history); //reverse sort so earliest backup is last on the array. Then we can array_pop.
		} else {
			$backup_history = array();
		}
		if (!$timestamp) return $backup_history;
		return (isset($backup_history[$timestamp])) ? $backup_history[$timestamp] : array();
	}

	public function terminate_due_to_activity($file, $time_now, $time_mod, $increase_resumption = true) {
		# We check-in, to avoid 'no check in last time!' detectors firing
		$this->record_still_alive();
		$file_size = file_exists($file) ? round(filesize($file)/1024,1). 'KB' : 'n/a';
		$this->log("Terminate: ".basename($file)." exists with activity within the last 30 seconds (time_mod=$time_mod, time_now=$time_now, diff=".(floor($time_now-$time_mod)).", size=$file_size). This likely means that another UpdraftPlus run is at work; so we will exit.");
		$increase_by = ($increase_resumption) ? 120 : 0;
		$this->increase_resume_and_reschedule($increase_by, true);
		if (!defined('UPDRAFTPLUS_ALLOW_RECENT_ACTIVITY') || true != UPDRAFTPLUS_ALLOW_RECENT_ACTIVITY) die;
	}

	# Replace last occurence
	public function str_lreplace($search, $replace, $subject) {
		$pos = strrpos($subject, $search);
		if($pos !== false) $subject = substr_replace($subject, $replace, $pos, strlen($search));
		return $subject;
	}

	public function str_replace_once($needle, $replace, $haystack) {
		$pos = strpos($haystack, $needle);
		return ($pos !== false) ? substr_replace($haystack,$replace,$pos,strlen($needle)) : $haystack;
	}

	/*
		If files + db are on different schedules but are scheduled for the same time, then combine them
		$event = (object) array( 'hook' => $hook, 'timestamp' => $timestamp, 'schedule' => $recurrence, 'args' => $args, 'interval' => $schedules[$recurrence]['interval'] );
		See wp_schedule_single_event() and wp_schedule_event() in wp-includes/cron.php
	*/
	public function schedule_event($event) {
	
		static $scheduled = array();
	
		
		if ('updraft_backup' == $event->hook || 'updraft_backup_database' == $event->hook) {
		
			// Reset the option - but make sure it is saved first so that we can used it (since this hook may be called just before our actual cron task)
			$this->combine_jobs_around = UpdraftPlus_Options::get_updraft_option('updraft_combine_jobs_around');
			
			UpdraftPlus_Options::delete_updraft_option('updraft_combine_jobs_around');
		
			$scheduled[$event->hook] = true;
			
			// This next fragment is wrong: there's only a 'second call' when saving all settings; otherwise, the WP scheduler might just be updating one event. So, there's some inefficieny as the option is wiped and set uselessly at least once when saving settings.
			// We only want to take action on the second call (otherwise, our information is out-of-date already)
			// If there is no second call, then that's fine - nothing to do
			//if (count($scheduled) < 2) {
			//	return $event;
			//}
		
			$backup_scheduled_for =  ('updraft_backup' == $event->hook) ? $event->timestamp : wp_next_scheduled('updraft_backup');
			$db_scheduled_for = ('updraft_backup_database' == $event->hook) ? $event->timestamp : wp_next_scheduled('updraft_backup_database');
		
			$diff = absint($backup_scheduled_for - $db_scheduled_for);
			
			$margin = (defined('UPDRAFTPLUS_COMBINE_MARGIN') && is_numeric(UPDRAFTPLUS_COMBINE_MARGIN)) ? UPDRAFTPLUS_COMBINE_MARGIN : 600;
			
			if ($backup_scheduled_for && $db_scheduled_for && $diff < $margin) {
				// We could change the event parameters; however, this would complicate other code paths (because the WP cron system uses a hash of the parameters as a key, and you must supply the exact parameters to look up events). So, we just set a marker that boot_backup() can pick up on.
				UpdraftPlus_Options::update_updraft_option('updraft_combine_jobs_around', min($backup_scheduled_for, $db_scheduled_for));
			}
			
		}
	
		return $event;
	
	}
	
	/*
		This function is both the backup scheduler and a filter callback for saving the option.
		It is called in the register_setting for the updraft_interval, which means when the
		admin settings are saved it is called.
	*/
	public function schedule_backup($interval) {
		$previous_time = wp_next_scheduled('updraft_backup');

		// Clear schedule so that we don't stack up scheduled backups
		wp_clear_scheduled_hook('updraft_backup');
		if ('manual' == $interval) return 'manual';
		$previous_interval = UpdraftPlus_Options::get_updraft_option('updraft_interval');

		$valid_schedules = wp_get_schedules();
		if (empty($valid_schedules[$interval])) $interval = 'daily';

		// Try to avoid changing the time is one was already scheduled. This is fairly conservative - we could do more, e.g. check if a backup already happened today.
		$default_time = ($interval == $previous_interval && $previous_time>0) ? $previous_time : time()+120;
		$first_time = apply_filters('updraftplus_schedule_firsttime_files', $default_time);

		wp_schedule_event($first_time, $interval, 'updraft_backup');

		return $interval;
	}

	public function schedule_backup_database($interval) {
		$previous_time = wp_next_scheduled('updraft_backup_database');

		// Clear schedule so that we don't stack up scheduled backups
		wp_clear_scheduled_hook('updraft_backup_database');
		if ('manual' == $interval) return 'manual';

		$previous_interval = UpdraftPlus_Options::get_updraft_option('updraft_interval_database');

		$valid_schedules = wp_get_schedules();
		if (empty($valid_schedules[$interval])) $interval = 'daily';

		// Try to avoid changing the time is one was already scheduled. This is fairly conservative - we could do more, e.g. check if a backup already happened today.
		$default_time = ($interval == $previous_interval && $previous_time>0) ? $previous_time : time()+120;

		$first_time = apply_filters('updraftplus_schedule_firsttime_db', $default_time);
		wp_schedule_event($first_time, $interval, 'updraft_backup_database');

		return $interval;
	}

	// Acts as a WordPress options filter
	public function onedrive_checkchange($onedrive) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_onedrive');
		if (!is_array($opts)) $opts = array();
		if (!is_array($onedrive)) return $opts;
		$old_client_id = (empty($opts['clientid'])) ? '' : $opts['clientid'];
		if (!empty($opts['token']) && $old_client_id != $onedrive['clientid']) {
			unset($opts['token']);
			unset($opts['tokensecret']);
			unset($opts['ownername']);
		}
		foreach ($onedrive as $key => $value) {
			if ('folder' == $key) $value = trim(str_replace('\\', '/', $value), '/');
			$opts[$key] = ('clientid' == $key || 'secret' == $key) ? trim($value) : $value;
		}
		return $opts;
	}

	// This is a WordPress options filter
	public function azure_checkchange($azure) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_azure');
		if (!is_array($opts)) $opts = array();
		if (!is_array($azure)) return $opts;
		foreach ($azure as $key => $value) {
			if ('folder' == $key) $value = trim(str_replace('\\', '/', $value), '/');
			// Only lower-case containers are permitted - enforce this
			if ('container' == $key) $value = strtolower($value);
			$opts[$key] = ('key' == $key || 'account_name' == $key) ? trim($value) : $value;
			// Convert one likely misunderstanding of the format to enter the account name in
			if ('account_name' == $key && preg_match('#^https?://(.*)\.blob\.core\.windows#i', $opts['account_name'], $matches)) {
				$opts['account_name'] = $matches[1];
			}
		}
		return $opts;
	}


	// Acts as a WordPress options filter
	public function googledrive_checkchange($google) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_googledrive');
		if (!is_array($google)) return $opts;
		$old_client_id = (empty($opts['clientid'])) ? '' : $opts['clientid'];
		if (!empty($opts['token']) && $old_client_id != $google['clientid']) {
			require_once(UPDRAFTPLUS_DIR.'/methods/googledrive.php');
			add_action('http_request_args', array($this, 'modify_http_options'));
			UpdraftPlus_BackupModule_googledrive::gdrive_auth_revoke(false);
			remove_action('http_request_args', array($this, 'modify_http_options'));
			$google['token'] = '';
			unset($opts['ownername']);
		}
		foreach ($google as $key => $value) {
			// Trim spaces - I got support requests from users who didn't spot the spaces they introduced when copy/pasting
			$opts[$key] = ('clientid' == $key || 'secret' == $key) ? trim($value) : $value;
		}
		if (isset($opts['folder'])) {
			$opts['folder'] = apply_filters('updraftplus_options_googledrive_foldername', 'UpdraftPlus', $opts['folder']);
			unset($opts['parentid']);
		}
		return $opts;
	}

	// Acts as a WordPress options filter
	public function googlecloud_checkchange($google) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_googlecloud');
		if (!is_array($google)) return $opts;
		
		$old_token = (empty($opts['token'])) ? '' : $opts['token'];
		$old_client_id = (empty($opts['clientid'])) ? '' : $opts['clientid'];
		$old_client_secret = (empty($opts['secret'])) ? '' : $opts['secret'];
		
		if($old_client_id == $google['clientid'] && $old_client_secret == $google['secret']){
			$google['token'] = $old_token;
		}
		if (!empty($opts['token']) && $old_client_id != $google['clientid']) {
			add_action('http_request_args', array($this, 'modify_http_options'));
			UpdraftPlus_Addons_RemoteStorage_googlecloud::gcloud_auth_revoke(false);
			remove_action('http_request_args', array($this, 'modify_http_options'));
			$google['token'] = '';
			unset($opts['ownername']);
		}
		foreach ($google as $key => $value) {
			// Trim spaces - I got support requests from users who didn't spot the spaces they introduced when copy/pasting
			$opts[$key] = ('clientid' == $key || 'secret' == $key) ? trim($value) : $value;
			if ($key == 'bucket_location') $opts[$key] = trim(strtolower($value));
		}
		
		return $google;
	}

	public function ftp_sanitise($ftp) {
		if (is_array($ftp) && !empty($ftp['host']) && preg_match('#ftp(es|s)?://(.*)#i', $ftp['host'], $matches)) {
			$ftp['host'] = untrailingslashit($matches[2]);
		}
		return $ftp;
	}

	public function s3_sanitise($s3) {
		if (is_array($s3) && !empty($s3['path']) && '/' == substr($s3['path'], 0, 1)) {
			$s3['path'] = substr($s3['path'], 1);
		}
		return $s3;
	}

	// Acts as a WordPress options filter
	public function bitcasa_checkchange($bitcasa) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_bitcasa');
		if (!is_array($opts)) $opts = array();
		if (!is_array($bitcasa)) return $opts;
		$old_client_id = (empty($opts['clientid'])) ? '' : $opts['clientid'];
		if (!empty($opts['token']) && $old_client_id != $bitcasa['clientid']) {
			unset($opts['token']);
			unset($opts['ownername']);
		}
		foreach ($bitcasa as $key => $value) { $opts[$key] = $value; }
		return $opts;
	}

	// Acts as a WordPress options filter
	public function copycom_checkchange($copycom) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_copycom');
		if (!is_array($opts)) $opts = array();
		if (!is_array($copycom)) return $opts;
		$old_client_id = (empty($opts['clientid'])) ? '' : $opts['clientid'];
		if (!empty($opts['token']) && $old_client_id != $copycom['clientid']) {
			unset($opts['token']);
			unset($opts['tokensecret']);
			unset($opts['ownername']);
		}
		foreach ($copycom as $key => $value) {
			if ('clientid' == $key || 'secret' == $key) {
				$opts[$key] = trim($value);
			} else {
				$opts[$key] = $value;
			}
		}
		return $opts;
	}

	// Acts as a WordPress options filter
	public function dropbox_checkchange($dropbox) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_dropbox');
		if (!is_array($opts)) $opts = array();
		if (!is_array($dropbox)) return $opts;
		foreach ($dropbox as $key => $value) { $opts[$key] = $value; }
		if (!empty($opts['folder']) && preg_match('#^https?://(www.)dropbox\.com/home/Apps/UpdraftPlus([^/]*)/(.*)$#i', $opts['folder'], $matches)) $opts['folder'] = $matches[3];
		return $opts;
	}

	public function remove_local_directory($dir, $contents_only = false) {
		// PHP 5.3+ only
		//foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
		//	$path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
		//}
		//return rmdir($dir);

		if ($handle = @opendir($dir)) {
			while (false !== ($entry = readdir($handle))) {
				if ('.' !== $entry && '..' !== $entry) {
					if (is_dir($dir.'/'.$entry)) {
						$this->remove_local_directory($dir.'/'.$entry, false);
					} else {
						@unlink($dir.'/'.$entry);
					}
				}
			}
			@closedir($handle);
		}

		return ($contents_only) ? true : rmdir($dir);
	}

	// Returns without any trailing slash
	public function backups_dir_location($allow_cache = true) {

		if ($allow_cache && !empty($this->backup_dir)) return $this->backup_dir;

		$updraft_dir = untrailingslashit(UpdraftPlus_Options::get_updraft_option('updraft_dir'));
		# When newly installing, if someone had (e.g.) wp-content/updraft in their database from a previous, deleted pre-1.7.18 install but had removed the updraft directory before re-installing, without this fix they'd end up with wp-content/wp-content/updraft.
		if (preg_match('/^wp-content\/(.*)$/', $updraft_dir, $matches) && ABSPATH.'wp-content' === WP_CONTENT_DIR) {
			UpdraftPlus_Options::update_updraft_option('updraft_dir', $matches[1]);
			$updraft_dir = WP_CONTENT_DIR.'/'.$matches[1];
		}
		$default_backup_dir = WP_CONTENT_DIR.'/updraft';
		$updraft_dir = ($updraft_dir) ? $updraft_dir : $default_backup_dir;

		// Do a test for a relative path
		if ('/' != substr($updraft_dir, 0, 1) && "\\" != substr($updraft_dir, 0, 1) && !preg_match('/^[a-zA-Z]:/', $updraft_dir)) {
			# Legacy - file paths stored related to ABSPATH
			if (is_dir(ABSPATH.$updraft_dir) && is_file(ABSPATH.$updraft_dir.'/index.html') && is_file(ABSPATH.$updraft_dir.'/.htaccess') && !is_file(ABSPATH.$updraft_dir.'/index.php') && false !== strpos(file_get_contents(ABSPATH.$updraft_dir.'/.htaccess', false, null, 0, 20), 'deny from all')) {
				$updraft_dir = ABSPATH.$updraft_dir;
			} else {
				# File paths stored relative to WP_CONTENT_DIR
				$updraft_dir = trailingslashit(WP_CONTENT_DIR).$updraft_dir;
			}
		}

		// Check for the existence of the dir and prevent enumeration
		// index.php is for a sanity check - make sure that we're not somewhere unexpected
		if((!is_dir($updraft_dir) || !is_file($updraft_dir.'/index.html') || !is_file($updraft_dir.'/.htaccess')) && !is_file($updraft_dir.'/index.php') || !is_file($updraft_dir.'/web.config')) {
			@mkdir($updraft_dir, 0775, true);
			@file_put_contents($updraft_dir.'/index.html',"<html><body><a href=\"https://updraftplus.com\">WordPress backups by UpdraftPlus</a></body></html>");
			if (!is_file($updraft_dir.'/.htaccess')) @file_put_contents($updraft_dir.'/.htaccess','deny from all');
			if (!is_file($updraft_dir.'/web.config')) @file_put_contents($updraft_dir.'/web.config', "<configuration>\n<system.webServer>\n<authorization>\n<deny users=\"*\" />\n</authorization>\n</system.webServer>\n</configuration>\n");
		}

		$this->backup_dir = $updraft_dir;

		return $updraft_dir;
	}

	private function spool_crypted_file($fullpath, $encryption) {
		if ('' == $encryption) $encryption = UpdraftPlus_Options::get_updraft_option('updraft_encryptionphrase');
		if ('' == $encryption) {
			header('Content-type: text/plain');
			_e("Decryption failed. The database file is encrypted, but you have no encryption key entered.", 'updraftplus');
			$this->log('Decryption of database failed: the database file is encrypted, but you have no encryption key entered.', 'error');
		} else {
			$ciphertext = $this->decrypt($fullpath, $encryption);
			if ($ciphertext) {
				header('Content-type: application/x-gzip');
				header("Content-Disposition: attachment; filename=\"".substr(basename($fullpath), 0, -6)."\";");
				header("Content-Length: ".strlen($ciphertext));
				print $ciphertext;
			} else {
				header('Content-type: text/plain');
				echo __("Decryption failed. The most likely cause is that you used the wrong key.", 'updraftplus')." ".__('The decryption key used:', 'updraftplus').' '.$encryption;
				
			}
		}
	}

	public function get_mime_type_from_filename($filename, $allow_gzip = true) {
		if ('.zip' == substr($filename, -4, 4)) {
			return 'application/zip';
		} elseif ('.tar' == substr($filename, -4, 4)) {
			return 'application/x-tar';
		} elseif ('.tar.gz' == substr($filename, -7, 7)) {
			return 'application/x-tgz';
		} elseif ('.tar.bz2' == substr($filename, -8, 8)) {
			return 'application/x-bzip-compressed-tar';
		} elseif ($allow_gzip && '.gz' == substr($filename, -3, 3)) {
			// When we sent application/x-gzip as a content-type header to the browser, we found a case where the server compressed it a second time (since observed several times)
			return 'application/x-gzip';
		} else {
			return 'application/octet-stream';
		}
	}

	public function spool_file($fullpath, $encryption = '') {
		@set_time_limit(900);

		if (file_exists($fullpath)) {

			// Prevent any debug output
			// Don't enable this line - it causes 500 HTTP errors in some cases/hosts on some large files, for unknown reason
			//@ini_set('display_errors', '0');
		
			$spooled = false;
			if ('.crypt' == substr($fullpath, -6, 6)) {
				if (ob_get_level()) {
					$flush_max = min(5, (int)ob_get_level());
					for ($i=1; $i<=$flush_max; $i++) { @ob_end_clean(); }
				}
				header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
				header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
				$this->spool_crypted_file($fullpath, (string)$encryption);
				return;
			}

			$content_type = $this->get_mime_type_from_filename($fullpath, false);
			
			require_once(UPDRAFTPLUS_DIR.'/includes/class-partialfileservlet.php');

			//Prevent the file being read into memory
			if (ob_get_level()) {
				$flush_max = min(5, (int)ob_get_level());
				for ($i=1; $i<=$flush_max; $i++) { @ob_end_clean(); }
			}
			if (ob_get_level()) @ob_end_clean(); // Twice - see HS#6673 - someone at least needed it
			
			if (isset($_SERVER['HTTP_RANGE'])) {
				$range_header = trim($_SERVER['HTTP_RANGE']);
			} elseif (function_exists('apache_request_headers')) {
				foreach (apache_request_headers() as $name => $value) {
					if (strtoupper($name) === 'RANGE') {
						$range_header = trim($value);
					}
				}
			}
			
			if (empty($range_header)) {
				header("Content-Length: ".filesize($fullpath));
				header("Content-type: $content_type");
				header("Content-Disposition: attachment; filename=\"".basename($fullpath)."\";");
				readfile($fullpath);
				return;
			}

			try {
				$range_header = UpdraftPlus_RangeHeader::createFromHeaderString($range_header);
				$servlet = new UpdraftPlus_PartialFileServlet($range_header);
				$servlet->sendFile($fullpath, $content_type);
			} catch (UpdraftPlus_InvalidRangeHeaderException $e) {
				header("HTTP/1.1 400 Bad Request");
				error_log("UpdraftPlus: UpdraftPlus_InvalidRangeHeaderException: ".$e->getMessage());
			} catch (UpdraftPlus_UnsatisfiableRangeException $e) {
				header("HTTP/1.1 416 Range Not Satisfiable");
			} catch (UpdraftPlus_NonExistentFileException $e) {
				header("HTTP/1.1 404 Not Found");
			} catch (UpdraftPlus_UnreadableFileException $e) {
				header("HTTP/1.1 500 Internal Server Error");
			}
			
		} else {
			echo __('File not found', 'updraftplus');
		}
	}

	public function retain_range($input) {
		$input = (int)$input;
		return  ($input > 0) ? min($input, 9999) : 1;
	}

	public function replace_http_with_webdav($input) {
		if (!empty($input['url']) && 'http' == strtolower(substr($input['url'], 0, 4))) $input['url'] = 'webdav'.substr($input['url'], 4);
		return $input;
	}

	public function just_one_email($input, $required = false) {
		$x = $this->just_one($input, 'saveemails', (empty($input) && false === $required) ? '' : get_bloginfo('admin_email'));
		if (is_array($x)) {
			foreach ($x as $ind => $val) {
				if (empty($val)) unset($x[$ind]);
			}
			if (empty($x)) $x = '';
		}
		return $x;
	}

	public function just_one($input, $filter = 'savestorage', $rinput = false) {
		$oinput = $input;
		if (false === $rinput) $rinput = (is_array($input)) ? array_pop($input) : $input;
		if (is_string($rinput) && false !== strpos($rinput, ',')) $rinput = substr($rinput, 0, strpos($rinput, ','));
		return apply_filters('updraftplus_'.$filter, $rinput, $oinput);
	}

	public function memory_check_current($memory_limit = false) {
		# Returns in megabytes
		if ($memory_limit == false) $memory_limit = ini_get('memory_limit');
		$memory_limit = rtrim($memory_limit);
		$memory_unit = $memory_limit[strlen($memory_limit)-1];
		if ((int)$memory_unit == 0 && $memory_unit !== '0') {
			$memory_limit = substr($memory_limit,0,strlen($memory_limit)-1);
		} else {
			$memory_unit = '';
		}
		switch($memory_unit) {
			case '':
				$memory_limit = floor($memory_limit/1048576);
			break;
			case 'K':
			case 'k':
				$memory_limit = floor($memory_limit/1024);
			break;
			case 'G':
				$memory_limit = $memory_limit*1024;
			break;
			case 'M':
				//assumed size, no change needed
			break;
		}
		return $memory_limit;
	}

	public function memory_check($memory, $check_using = false) {
		$memory_limit = $this->memory_check_current($check_using);
		return ($memory_limit >= $memory)?true:false;
	}

	private function url_start($urls, $url, $https = false) {
		$proto = ($https) ? 'https' : 'http';
		return ($urls) ? "<a href=\"$proto://$url\">" : "";
	}

	private function url_end($urls, $url, $https = false) {
		$proto = ($https) ? 'https' : 'http';
		return ($urls) ? '</a>' : " ($proto://$url)";
	}

	public function get_updraftplus_rssfeed() {
		if (!function_exists('fetch_feed')) require(ABSPATH . WPINC . '/feed.php');
		return fetch_feed('http://feeds.feedburner.com/updraftplus/');
	}

	public function wordshell_random_advert($urls) {
		if (defined('UPDRAFTPLUS_NOADS_B')) return "";
		$rad = rand(0, 8);
		switch ($rad) {
		case 0:
			return $this->url_start($urls,'updraftplus.com').__("Want more features or paid, guaranteed support? Check out UpdraftPlus.Com", 'updraftplus').$this->url_end($urls,'updraftplus.com');
			break;
		case 1:
			$wplang = get_locale();
			if (strlen($wplang)>0 && !is_file(UPDRAFTPLUS_DIR.'/languages/updraftplus-'.$wplang.
'.mo')) return __('Can you translate? Want to improve UpdraftPlus for speakers of your language?','updraftplus').' '.$this->url_start($urls,'updraftplus.com/translate/')."Please go here for instructions - it is easy.".$this->url_end($urls,'updraftplus.com/translate/');

			return __('UpdraftPlus is on social media - check us out here:','updraftplus').' '.$this->url_start($urls,'twitter.com/updraftplus', true).__('Twitter', 'updraftplus').$this->url_end($urls,'twitter.com/updraftplus', true).' - '.$this->url_start($urls,'facebook.com/updraftplus', true).__('Facebook', 'updraftplus').$this->url_end($urls,'facebook.com/updraftplus', true).' - '.$this->url_start($urls,'plus.google.com/u/0/b/112313994681166369508/112313994681166369508/about', true).__('Google+', 'updraftplus').$this->url_end($urls,'plus.google.com/u/0/b/112313994681166369508/112313994681166369508/about', true).' - '.$this->url_start($urls,'www.linkedin.com/company/updraftplus', true).__('LinkedIn', 'updraftplus').$this->url_end($urls,'www.linkedin.com/company/updraftplus', true);
			break;
		case 2:
			return $this->url_start($urls,'wordshell.net').__("Check out WordShell", 'updraftplus').$this->url_end($urls,'www.wordshell.net')." - ".__('manage WordPress from the command line - huge time-saver', 'updraftplus');
			break;
		case 3:
			return __('Like UpdraftPlus and can spare one minute?','updraftplus').$this->url_start($urls,'wordpress.org/support/view/plugin-reviews/updraftplus#postform').' '.__('Please help UpdraftPlus by giving a positive review at wordpress.org','updraftplus').$this->url_end($urls,'wordpress.org/support/view/plugin-reviews/updraftplus#postform');
			break;
		case 4:
			return $this->url_start($urls,'updraftplus.com/newsletter-signup', true).__("Follow this link to sign up for the UpdraftPlus newsletter.", 'updraftplus').$this->url_end($urls,'updraftplus.com/newsletter-signup', true);
			break;
		case 5:
			if (!defined('UPDRAFTPLUS_NOADS_B')) {
				return $this->url_start($urls,'updraftplus.com').__("Need even more features and support? Check out UpdraftPlus Premium",'updraftplus').$this->url_end($urls,'updraftplus.com');
			} else {
				return "Thanks for being an UpdraftPlus premium user. Keep visiting ".$this->url_start($urls,'updraftplus.com')."updraftplus.com".$this->url_end($urls,'updraftplus.com')." to see what's going on.";
			}
			break;
		case 6:
// 			return "Need custom WordPress services from experts (including bespoke development)?".$this->url_start($urls,'www.simbahosting.co.uk/s3/products-and-services/wordpress-experts/')." Get them from the creators of UpdraftPlus.".$this->url_end($urls,'www.simbahosting.co.uk/s3/products-and-services/wordpress-experts/');
			return __("Subscribe to the UpdraftPlus blog to get up-to-date news and offers",'updraftplus')." - ".$this->url_start($urls,'updraftplus.com/news/').__("Blog link",'updraftplus').$this->url_end($urls,'updraftplus.com/news/').' - '.$this->url_start($urls,'feeds.feedburner.com/UpdraftPlus').__("RSS link",'updraftplus').$this->url_end($urls,'feeds.feedburner.com/UpdraftPlus');
			break;
		case 7:
			return $this->url_start($urls,'updraftplus.com').__("Check out UpdraftPlus.Com for help, add-ons and support",'updraftplus').$this->url_end($urls,'updraftplus.com');
			break;
// 		case 8:
// 			return __("Want to say thank-you for UpdraftPlus?",'updraftplus').$this->url_start($urls,'updraftplus.com/shop/', true)." ".__("Please buy our very cheap 'no adverts' add-on.",'updraftplus').$this->url_end($urls,'updraftplus.com/shop/', true);
// 			break;
		case 8:
			return __('UpdraftPlus is on social media - check us out here:','updraftplus').' '.$this->url_start($urls,'twitter.com/updraftplus', true).__('Twitter', 'updraftplus').$this->url_end($urls,'twitter.com/updraftplus', true).' - '.$this->url_start($urls,'facebook.com/updraftplus', true).__('Facebook', 'updraftplus').$this->url_end($urls,'facebook.com/updraftplus', true).' - '.$this->url_start($urls,'plus.google.com/u/0/b/112313994681166369508/112313994681166369508/about', true).__('Google+', 'updraftplus').$this->url_end($urls,'plus.google.com/u/0/b/112313994681166369508/112313994681166369508/about', true).' - '.$this->url_start($urls,'www.linkedin.com/company/updraftplus', true).__('LinkedIn', 'updraftplus').$this->url_end($urls,'www.linkedin.com/company/updraftplus', true);
			break;
		}
	}

	public function analyse_db_file($timestamp, $res, $db_file = false, $header_only = false) {

		$mess = array(); $warn = array(); $err = array(); $info = array();

		$wp_version = $this->get_wordpress_version();
		global $wpdb;

		$updraft_dir = $this->backups_dir_location();

		if (false === $db_file) {
			# This attempts to raise the maximum packet size. This can't be done within the session, only globally. Therefore, it has to be done before the session starts; in our case, during the pre-analysis.
			$this->get_max_packet_size();

			$backup = $this->get_backup_history($timestamp);
			if (!isset($backup['nonce']) || !isset($backup['db'])) return array($mess, $warn, $err, $info);

			$db_file = (is_string($backup['db'])) ? $updraft_dir.'/'.$backup['db'] : $updraft_dir.'/'.$backup['db'][0];
		}

		if (!is_readable($db_file)) return array($mess, $warn, $err, $info);

		// Encrypted - decrypt it
		if ($this->is_db_encrypted($db_file)) {

			$encryption = empty($res['updraft_encryptionphrase']) ? UpdraftPlus_Options::get_updraft_option('updraft_encryptionphrase') : $res['updraft_encryptionphrase'];

			if (!$encryption) {
				if (class_exists('UpdraftPlus_Addon_MoreDatabase')) {
					$err[] = sprintf(__('Error: %s', 'updraftplus'), __('Decryption failed. The database file is encrypted, but you have no encryption key entered.', 'updraftplus'));
				} else {
					$err[] = sprintf(__('Error: %s', 'updraftplus'), __('Decryption failed. The database file is encrypted.', 'updraftplus'));
				}
				return array($mess, $warn, $err, $info);
			}

			$ciphertext = $this->decrypt($db_file, $encryption);

			if ($ciphertext) {
				$new_db_file = $updraft_dir.'/'.basename($db_file, '.crypt');
				if (!file_put_contents($new_db_file, $ciphertext)) {
					$err[] = __('Failed to write out the decrypted database to the filesystem.','updraftplus');
					return array($mess, $warn, $err, $info);
				}
				$db_file = $new_db_file;
			} else {
				$err[] = __('Decryption failed. The most likely cause is that you used the wrong key.','updraftplus');
				return array($mess, $warn, $err, $info);
			}
		}

		# Even the empty schema when gzipped comes to 1565 bytes; a blank WP 3.6 install at 5158. But we go low, in case someone wants to share single tables.
		if (filesize($db_file) < 1000) {
			$err[] = sprintf(__('The database is too small to be a valid WordPress database (size: %s Kb).','updraftplus'), round(filesize($db_file)/1024, 1));
			return array($mess, $warn, $err, $info);
		}

		$is_plain = ('.gz' == substr($db_file, -3, 3)) ? false : true;

		$dbhandle = ($is_plain) ? fopen($db_file, 'r') : $this->gzopen_for_read($db_file, $warn, $err);
		if (!is_resource($dbhandle)) {
			$err[] =  __('Failed to open database file.', 'updraftplus');
			return array($mess, $warn, $err, $info);
		}

		$info['timestamp'] = $timestamp;

		# Analyse the file, print the results.

		$line = 0;
		$old_siteurl = '';
		$old_home = '';
		$old_table_prefix = '';
		$old_siteinfo = array();
		$gathering_siteinfo = true;
		$old_wp_version = '';
		$old_php_version = '';

		$tables_found = array();

		// TODO: If the backup is the right size/checksum, then we could restore the $line <= 100 in the 'while' condition and not bother scanning the whole thing? Or better: sort the core tables to be first so that this usually terminates early

		$wanted_tables = array('terms', 'term_taxonomy', 'term_relationships', 'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts', 'users', 'usermeta');

		$migration_warning = false;
		$processing_create = false;
		$db_version = $wpdb->db_version();

		// Don't set too high - we want a timely response returned to the browser
		// Until April 2015, this was always 90. But we've seen a few people with ~1GB databases (uncompressed), and 90s is not enough. Note that we don't bother checking here if it's compressed - having a too-large timeout when unexpected is harmless, as it won't be hit. On very large dbs, they're expecting it to take a while.
		// "120 or 240" is a first attempt at something more useful than just fixed at 90 - but should be sufficient (as 90 was for everyone without ~1GB databases)
		$default_dbscan_timeout = (filesize($db_file) < 31457280) ? 120 : 240;
		$dbscan_timeout = (defined('UPDRAFTPLUS_DBSCAN_TIMEOUT') && is_numeric(UPDRAFTPLUS_DBSCAN_TIMEOUT)) ? UPDRAFTPLUS_DBSCAN_TIMEOUT : $default_dbscan_timeout;
		@set_time_limit($dbscan_timeout);

		while ((($is_plain && !feof($dbhandle)) || (!$is_plain && !gzeof($dbhandle))) && ($line<100 || (!$header_only && count($wanted_tables)>0))) {
			$line++;
			// Up to 1MB
			$buffer = ($is_plain) ? rtrim(fgets($dbhandle, 1048576)) : rtrim(gzgets($dbhandle, 1048576));
			// Comments are what we are interested in
			if (substr($buffer, 0, 1) == '#') {
				$processing_create = false;
				if ('' == $old_siteurl && preg_match('/^\# Backup of: (http(.*))$/', $buffer, $matches)) {
					$old_siteurl = untrailingslashit($matches[1]);
					$mess[] = __('Backup of:', 'updraftplus').' '.htmlspecialchars($old_siteurl).((!empty($old_wp_version)) ? ' '.sprintf(__('(version: %s)', 'updraftplus'), $old_wp_version) : '');
					// Check for should-be migration
					if ($old_siteurl != untrailingslashit(site_url())) {
						if (!$migration_warning) {
							$migration_warning = true;
							$powarn = apply_filters('updraftplus_dbscan_urlchange', sprintf(__('Warning: %s', 'updraftplus'), '<a href="https://updraftplus.com/shop/migrator/">'.__('This backup set is from a different site - this is not a restoration, but a migration. You need the Migrator add-on in order to make this work.', 'updraftplus').'</a>'), $old_siteurl, $res);
							if (!empty($powarn)) $warn[] = $powarn;
						}
						// Explicitly set it, allowing the consumer to detect when the result was unknown
						$info['same_url'] = false;
						
						if ($this->mod_rewrite_unavailable(false)) {
							$warn[] = sprintf(__('You are using the %s webserver, but do not seem to have the %s module loaded.', 'updraftplus'), 'Apache', 'mod_rewrite').' '.sprintf(__('You should enable %s to make any pretty permalinks (e.g. %s) work', 'updraftplus'), 'mod_rewrite', 'http://example.com/my-page/');
						}
						
					} else {
						$info['same_url'] = true;
					}
				} elseif ('' == $old_home && preg_match('/^\# Home URL: (http(.*))$/', $buffer, $matches)) {
					$old_home = untrailingslashit($matches[1]);
					// Check for should-be migration
					if (!$migration_warning && $old_home != home_url()) {
						$migration_warning = true;
						$powarn = apply_filters('updraftplus_dbscan_urlchange', sprintf(__('Warning: %s', 'updraftplus'), '<a href="https://updraftplus.com/shop/migrator/">'.__('This backup set is from a different site - this is not a restoration, but a migration. You need the Migrator add-on in order to make this work.', 'updraftplus').'</a>'), $old_home, $res);
						if (!empty($powarn)) $warn[] = $powarn;
					}
				} elseif (!isset($info['created_by_version']) && preg_match('/^\# Created by UpdraftPlus version ([\d\.]+)/', $buffer, $matches)) {
					$info['created_by_version'] = trim($matches[1]);
				} elseif ('' == $old_wp_version && preg_match('/^\# WordPress Version: ([0-9]+(\.[0-9]+)+)(-[-a-z0-9]+,)?(.*)$/', $buffer, $matches)) {
					$old_wp_version = $matches[1];
					if (!empty($matches[3])) $old_wp_version .= substr($matches[3], 0, strlen($matches[3])-1);
					if (version_compare($old_wp_version, $wp_version, '>')) {
						//$mess[] = sprintf(__('%s version: %s', 'updraftplus'), 'WordPress', $old_wp_version);
						$warn[] = sprintf(__('You are importing from a newer version of WordPress (%s) into an older one (%s). There are no guarantees that WordPress can handle this.', 'updraftplus'), $old_wp_version, $wp_version);
					}
					if (preg_match('/running on PHP ([0-9]+\.[0-9]+)(\s|\.)/', $matches[4], $nmatches) && preg_match('/^([0-9]+\.[0-9]+)(\s|\.)/', PHP_VERSION, $cmatches)) {
						$old_php_version = $nmatches[1];
						$current_php_version = $cmatches[1];
						if (version_compare($old_php_version, $current_php_version, '>')) {
							//$mess[] = sprintf(__('%s version: %s', 'updraftplus'), 'WordPress', $old_wp_version);
							$warn[] = sprintf(__('The site in this backup was running on a webserver with version %s of %s. ', 'updraftplus'), $old_php_version, 'PHP').' '.sprintf(__('This is significantly newer than the server which you are now restoring onto (version %s).', 'updraftplus'), PHP_VERSION).' '.sprintf(__('You should only proceed if you cannot update the current server and are confident (or willing to risk) that your plugins/themes/etc. are compatible with the older %s version.', 'updraftplus'), 'PHP').' '.sprintf(__('Any support requests to do with %s should be raised with your web hosting company.', 'updraftplus'), 'PHP');
						}
					}
				} elseif ('' == $old_table_prefix && (preg_match('/^\# Table prefix: (\S+)$/', $buffer, $matches) || preg_match('/^-- Table prefix: (\S+)$/i', $buffer, $matches))) {
					$old_table_prefix = $matches[1];
// 					echo '<strong>'.__('Old table prefix:', 'updraftplus').'</strong> '.htmlspecialchars($old_table_prefix).'<br>';
				} elseif (empty($info['label']) && preg_match('/^\# Label: (.*)$/', $buffer, $matches)) {
					$info['label'] = $matches[1];
					$mess[] = __('Backup label:', 'updraftplus').' '.htmlspecialchars($info['label']);
				} elseif ($gathering_siteinfo && preg_match('/^\# Site info: (\S+)$/', $buffer, $matches)) {
					if ('end' == $matches[1]) {
						$gathering_siteinfo = false;
						// Sanity checks
						if (isset($old_siteinfo['multisite']) && !$old_siteinfo['multisite'] && is_multisite()) {
							// Just need to check that you're crazy
							//if (!defined('UPDRAFTPLUS_EXPERIMENTAL_IMPORTINTOMULTISITE') || !UPDRAFTPLUS_EXPERIMENTAL_IMPORTINTOMULTISITE) {
								//$err[] =  sprintf(__('Error: %s', 'updraftplus'), __('You are running on WordPress multisite - but your backup is not of a multisite site.', 'updraftplus'));
								//return array($mess, $warn, $err, $info);
							//} else {
								$warn[] = __('You are running on WordPress multisite - but your backup is not of a multisite site.', 'updraftplus').' '.__('It will be imported as a new site.', 'updraftplus').' <a href="https://updraftplus.com/information-on-importing-a-single-site-wordpress-backup-into-a-wordpress-network-i-e-multisite/">'.__('Please read this link for important information on this process.', 'updraftplus').'</a>';
							//}
							// Got the needed code?
							if (!class_exists('UpdraftPlusAddOn_MultiSite') || !class_exists('UpdraftPlus_Addons_Migrator')) {
								 $err[] = sprintf(__('Error: %s', 'updraftplus'), sprintf(__('To import an ordinary WordPress site into a multisite installation requires %s.', 'updraftplus'), 'UpdraftPlus Premium'));
								return array($mess, $warn, $err, $info);
							}
						} elseif (isset($old_siteinfo['multisite']) && $old_siteinfo['multisite'] && !is_multisite()) {
							$warn[] = __('Warning:', 'updraftplus').' '.__('Your backup is of a WordPress multisite install; but this site is not. Only the first site of the network will be accessible.', 'updraftplus').' <a href="https://codex.wordpress.org/Create_A_Network">'.__('If you want to restore a multisite backup, you should first set up your WordPress installation as a multisite.', 'updraftplus').'</a>';
						}
					} elseif (preg_match('/^([^=]+)=(.*)$/', $matches[1], $kvmatches)) {
						$key = $kvmatches[1];
						$val = $kvmatches[2];
						if ('multisite' == $key) {
							$info['multisite'] = $val ? true : false;
							if ($val) $mess[] = '<strong>'.__('Site information:', 'updraftplus').'</strong> '.'backup is of a WordPress Network';
						}
						$old_siteinfo[$key]=$val;
					}
				}

			} elseif (preg_match('/^\s*create table \`?([^\`\(]*)\`?\s*\(/i', $buffer, $matches)) {
				$table = $matches[1];
				$tables_found[] = $table;
				if ($old_table_prefix) {
					// Remove prefix
					$table = $this->str_replace_once($old_table_prefix, '', $table);
					if (in_array($table, $wanted_tables)) {
						$wanted_tables = array_diff($wanted_tables, array($table));
					}
				}
				if (substr($buffer, -1, 1) != ';') $processing_create = true;
			} elseif ($processing_create) {
				if (substr($buffer, -1, 1) == ';') $processing_create = false;
				static $mysql_version_warned = false;
				if (!$mysql_version_warned && version_compare($db_version, '5.2.0', '<') && preg_match('/(CHARSET|COLLATE)[= ]utf8mb4/', $buffer)) {
					$mysql_version_warned = true;
					 $err[] = sprintf(__('Error: %s', 'updraftplus'), sprintf(__('The database backup uses MySQL features not available in the old MySQL version (%s) that this site is running on.', 'updraftplus'), $db_version).' '.__('You must upgrade MySQL to be able to use this database.', 'updraftplus'));
				}
			}
		}

		if ($is_plain) {
			@fclose($dbhandle);
		} else {
			@gzclose($dbhandle);
		}

/*        $blog_tables = "CREATE TABLE $wpdb->terms (
CREATE TABLE $wpdb->term_taxonomy (
CREATE TABLE $wpdb->term_relationships (
CREATE TABLE $wpdb->commentmeta (
CREATE TABLE $wpdb->comments (
CREATE TABLE $wpdb->links (
CREATE TABLE $wpdb->options (
CREATE TABLE $wpdb->postmeta (
CREATE TABLE $wpdb->posts (
        $users_single_table = "CREATE TABLE $wpdb->users (
        $users_multi_table = "CREATE TABLE $wpdb->users (
        $usermeta_table = "CREATE TABLE $wpdb->usermeta (
        $ms_global_tables = "CREATE TABLE $wpdb->blogs (
CREATE TABLE $wpdb->blog_versions (
CREATE TABLE $wpdb->registration_log (
CREATE TABLE $wpdb->site (
CREATE TABLE $wpdb->sitemeta (
CREATE TABLE $wpdb->signups (
*/

		$missing_tables = array();
		if ($old_table_prefix) {
			if (!$header_only) {
				foreach ($wanted_tables as $table) {
					if (!in_array($old_table_prefix.$table, $tables_found)) {
						$missing_tables[] = $table;
					}
				}
				if (count($missing_tables)>0) {
					$warn[] = sprintf(__('This database backup is missing core WordPress tables: %s', 'updraftplus'), implode(', ', $missing_tables));
				}
			}
		} else {
			if (empty($backup['meta_foreign'])) {
				$warn[] = __('UpdraftPlus was unable to find the table prefix when scanning the database backup.', 'updraftplus');
			}
		}

		return array($mess, $warn, $err, $info);

	}

	private function gzopen_for_read($file, &$warn, &$err) {
		if (!function_exists('gzopen') || !function_exists('gzread')) {
			$missing = '';
			if (!function_exists('gzopen')) $missing .= 'gzopen';
			if (!function_exists('gzread')) $missing .= ($missing) ? ', gzread' : 'gzread';
			$err[] = sprintf(__("Your web server's PHP installation has these functions disabled: %s.", 'updraftplus'), $missing).' '.sprintf(__('Your hosting company must enable these functions before %s can work.', 'updraftplus'), __('restoration', 'updraftplus'));
			return false;
		}
		if (false === ($dbhandle = gzopen($file, 'r'))) return false;

		if (!function_exists('gzseek')) return $dbhandle;

		if (false === ($bytes = gzread($dbhandle, 3))) return false;
		# Double-gzipped?
		if ('H4sI' != base64_encode($bytes)) {
			if (0 === gzseek($dbhandle, 0)) {
				return $dbhandle;
			} else {
				@gzclose($dbhandle);
				return gzopen($file, 'r');
			}
		}
		# Yes, it's double-gzipped

		$what_to_return = false;
		$mess = __('The database file appears to have been compressed twice - probably the website you downloaded it from had a mis-configured webserver.', 'updraftplus');
		$messkey = 'doublecompress';
		$err_msg = '';

		if (false === ($fnew = fopen($file.".tmp", 'w')) || !is_resource($fnew)) {

			@gzclose($dbhandle);
			$err_msg = __('The attempt to undo the double-compression failed.', 'updraftplus');

		} else {

			@fwrite($fnew, $bytes);
			$emptimes = 0;
			while (!gzeof($dbhandle)) {
				$bytes = @gzread($dbhandle, 262144);
				if (empty($bytes)) {
					$emptimes++;
					$this->log("Got empty gzread ($emptimes times)");
					if ($emptimes>2) break;
				} else {
					@fwrite($fnew, $bytes);
				}
			}

			gzclose($dbhandle);
			fclose($fnew);
			# On some systems (all Windows?) you can't rename a gz file whilst it's gzopened
			if (!rename($file.".tmp", $file)) {
				$err_msg = __('The attempt to undo the double-compression failed.', 'updraftplus');
			} else {
				$mess .= ' '.__('The attempt to undo the double-compression succeeded.', 'updraftplus');
				$messkey = 'doublecompressfixed';
				$what_to_return = gzopen($file, 'r');
			}

		}

		$warn[$messkey] = $mess;
		if (!empty($err_msg)) $err[] = $err_msg;
		return $what_to_return;
	}

	# TODO: Remove legacy storage setting keys from here
	// These are used in 4 places (Feb 2016 - of course, you should re-scan the code to check if relying on this): showing current settings on the debug modal, wiping all current settings, getting a settings bundle to restore when migrating, and for relevant keys in POST-ed data when saving settings over AJAX
	public function get_settings_keys() {
	// N.B. updraft_backup_history is not included here, as we don't want that wiped
		return array('updraft_autobackup_default', 'updraft_dropbox', 'updraft_googledrive', 'updraftplus_tmp_googledrive_access_token', 'updraftplus_dismissedautobackup', 'updraftplus_dismissedexpiry', 'updraftplus_dismisseddashnotice', 'updraft_interval', 'updraft_interval_increments', 'updraft_interval_database', 'updraft_retain', 'updraft_retain_db', 'updraft_encryptionphrase', 'updraft_service', 'updraft_dropbox_appkey', 'updraft_dropbox_secret', 'updraft_googledrive_clientid', 'updraft_googledrive_secret', 'updraft_googledrive_remotepath', 'updraft_ftp', 'updraft_ftp_login', 'updraft_ftp_pass', 'updraft_ftp_remote_path', 'updraft_server_address', 'updraft_dir', 'updraft_email', 'updraft_delete_local', 'updraft_debug_mode', 'updraft_include_plugins', 'updraft_include_themes', 'updraft_include_uploads', 'updraft_include_others', 'updraft_include_wpcore', 'updraft_include_wpcore_exclude', 'updraft_include_more', 'updraft_include_blogs', 'updraft_include_mu-plugins',
		'updraft_include_others_exclude', 'updraft_include_uploads_exclude', 'updraft_lastmessage', 'updraft_googledrive_token', 'updraft_dropboxtk_request_token', 'updraft_dropboxtk_access_token', 'updraft_dropbox_folder', 'updraft_adminlocking', 'updraft_updraftvault', 'updraft_remotesites', 'updraft_migrator_localkeys', 'updraft_central_localkeys', 'updraft_retain_extrarules', 'updraft_googlecloud', 'updraft_include_more_path', 'updraft_split_every', 'updraft_ssl_nossl', 'updraft_backupdb_nonwp', 'updraft_extradbs', 'updraft_combine_jobs_around',
		'updraft_last_backup', 'updraft_starttime_files', 'updraft_starttime_db', 'updraft_startday_db', 'updraft_startday_files', 'updraft_sftp_settings', 'updraft_s3', 'updraft_s3generic', 'updraft_dreamhost', 'updraft_s3generic_login', 'updraft_s3generic_pass', 'updraft_s3generic_remote_path', 'updraft_s3generic_endpoint', 'updraft_webdav_settings', 'updraft_openstack', 'updraft_bitcasa', 'updraft_copycom', 'updraft_onedrive', 'updraft_azure', 'updraft_cloudfiles', 'updraft_cloudfiles_user', 'updraft_cloudfiles_apikey', 'updraft_cloudfiles_path', 'updraft_cloudfiles_authurl', 'updraft_ssl_useservercerts', 'updraft_ssl_disableverify', 'updraft_s3_login', 'updraft_s3_pass', 'updraft_s3_remote_path', 'updraft_dreamobjects_login', 'updraft_dreamobjects_pass', 'updraft_dreamobjects_remote_path', 'updraft_dreamobjects', 'updraft_report_warningsonly', 'updraft_report_wholebackup', 'updraft_log_syslog', 'updraft_extradatabases');
	}

}
