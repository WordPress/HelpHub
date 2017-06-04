<?php
if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

if (!class_exists('WP_Upgrader')) require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');

class Updraft_Restorer extends WP_Upgrader {

	private $is_multisite;

	// This is just used so far for detecting whether we're on the second run for an entity or not.
	public $been_restored = array();
	private $tables_been_dropped = array();

	// Public: it is manipulated by the caller after the caller gets the object
	public $delete = false;

	private $created_by_version = false;
	// This one can be set externally, if the information is available
	public $ud_backup_is_multisite = -1;

	private $ud_backup_info;
	public $ud_foreign;

	// The default of false means "use the global $wpdb"
	private $wpdb_obj = false;

	private $line_last_logged = 0;
	private $our_siteurl;

	private $configuration_bundle;

	private $ajax_restore_auth_code;
	
	private $ud_restore_options;
	
	private $restore_this_table = array();

	private $line = 0;
	private $statements_run = 0;
	
	// Constants for use with the move_backup_in method
	// These can't be arbitrarily changed; there is legacy code doing bitwise operations and numerical comparisons, and possibly legacy code still using the values directly.
	const MOVEIN_OVERWRITE_NO_BACKUP = 0;
	const MOVEIN_MAKE_BACKUP_OF_EXISTING = 1;
	const MOVEIN_DO_NOTHING_IF_EXISTING = 2;
	const MOVEIN_COPY_IN_CONTENTS = 3;
	
	public function __construct($skin = null, $info = null, $shortinit = false, $restore_options = array()) {

		global $wpdb;
		// Line up a wpdb-like object
		$this->use_wpdb = ((!function_exists('mysql_query') && !function_exists('mysqli_query')) || !$wpdb->is_mysql || !$wpdb->ready) ? true : false;

		$this->our_siteurl = untrailingslashit(site_url());

		if (false == $this->use_wpdb) {
			// We have our own extension which drops lots of the overhead on the query
			$wpdb_obj = new UpdraftPlus_WPDB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
			// Was that successful?
			if (!$wpdb_obj->is_mysql || !$wpdb_obj->ready) {
				$this->use_wpdb = true;
			} else {
				$this->wpdb_obj = $wpdb_obj;
				$this->mysql_dbh = $wpdb_obj->updraftplus_getdbh();
				$this->use_mysqli = $wpdb_obj->updraftplus_use_mysqli();
			}
		}

		if ($shortinit) return;
		$this->ud_backup_info = $info;
		do_action('updraftplus_restorer_restore_options', $restore_options);
		$this->ud_multisite_selective_restore = (is_array($restore_options) && !empty($restore_options['updraft_restore_ms_whichsites']) && $restore_options['updraft_restore_ms_whichsites'] > 0) ? $restore_options['updraft_restore_ms_whichsites'] : false;
		$this->ud_restore_options = $restore_options;
		
		$this->ud_foreign = empty($info['meta_foreign']) ? false : $info['meta_foreign'];
		if (isset($info['is_multisite'])) $this->ud_backup_is_multisite = $info['is_multisite'];
		if (isset($info['created_by_version'])) $this->created_by_version = $info['created_by_version'];

		parent::__construct($skin);
		$this->init();
		$this->backup_strings();
		$this->is_multisite = is_multisite();
	}

	public function ud_get_skin() {
		return $this->skin;
	}
	
	private function backup_strings() {
		$this->strings['not_possible'] = __('UpdraftPlus is not able to directly restore this kind of entity. It must be restored manually.','updraftplus');
		$this->strings['no_package'] = __('Backup file not available.','updraftplus');
		$this->strings['copy_failed'] = __('Copying this entity failed.','updraftplus');
		$this->strings['unpack_package'] = __('Unpacking backup...','updraftplus');
		$this->strings['decrypt_database'] = __('Decrypting database (can take a while)...','updraftplus');
		$this->strings['decrypted_database'] = __('Database successfully decrypted.','updraftplus');
		$this->strings['moving_old'] = __('Moving old data out of the way...','updraftplus');
		$this->strings['moving_backup'] = __('Moving unpacked backup into place...','updraftplus');
		$this->strings['restore_database'] = __('Restoring the database (on a large site this can take a long time - if it times out (which can happen if your web hosting company has configured your hosting to limit resources) then you should use a different method, such as phpMyAdmin)...','updraftplus');
		$this->strings['cleaning_up'] = __('Cleaning up rubbish...','updraftplus');
		$this->strings['old_move_failed'] = __('Could not move old files out of the way.','updraftplus').' '.__('You should check the file ownerships and permissions in your WordPress installation', 'updraftplus');
		$this->strings['old_delete_failed'] = __('Could not delete old directory.','updraftplus');
		$this->strings['new_move_failed'] = __('Could not move new files into place. Check your wp-content/upgrade folder.','updraftplus');
		$this->strings['move_failed'] = __('Could not move the files into place. Check your file permissions.','updraftplus');
		$this->strings['delete_failed'] = __('Failed to delete working directory after restoring.','updraftplus');
		$this->strings['multisite_error'] = __('You are running on WordPress multisite - but your backup is not of a multisite site.', 'updraftplus');
		$this->strings['unpack_failed'] = __('Failed to unpack the archive', 'updraftplus');
	}

	# This function is copied from class WP_Upgrader (WP 3.8 - no significant changes since 3.2 at least); we only had to fork it because it hard-codes using the basename of the zip file as its unpack directory; which can be long; and then combining that with long pathnames in the zip being unpacked can overflow a 256-character path limit (yes, they apparently still exist - amazing!)
	# Subsequently, we have also added the ability to unpack tarballs
	private function unpack_package_archive($package, $delete_package = true, $type = false) {

		if (!empty($this->ud_foreign) && !empty($this->ud_foreign_working_dir) && $package == $this->ud_foreign_package) {
			if (is_dir($this->ud_foreign_working_dir)) {
				return $this->ud_foreign_working_dir;
			} else {
				global $updraftplus;
				$updraftplus->log('Previously unpacked directory seems to have disappeared; will unpack again');
			}
		}

		global $wp_filesystem, $updraftplus;

		$packsize = round(filesize($package)/1048576, 1).' Mb';

		$this->skin->feedback($this->strings['unpack_package'].' ('.basename($package).', '.$packsize.')');

		$upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';

		// Clean up contents of upgrade directory beforehand.
		$upgrade_files = $wp_filesystem->dirlist($upgrade_folder);
		if ( !empty($upgrade_files) ) {
			foreach ( $upgrade_files as $file )
				$wp_filesystem->delete($upgrade_folder . $file['name'], true);
		}

		// We need a working directory
		#This is the only change from the WP core version - minimise path length
		#$working_dir = $upgrade_folder . basename($package, '.zip');
		$working_dir = $upgrade_folder . substr(md5($package), 0, 8);

		// Clean up working directory
		if ( $wp_filesystem->is_dir($working_dir) )
			$wp_filesystem->delete($working_dir, true);

		// Unzip package to working directory
		if ('.zip' == strtolower(substr($package, -4, 4))) {
			$result = unzip_file( $package, $working_dir );
		} elseif ('.tar' == strtolower(substr($package, -4, 4)) || '.tar.gz' == strtolower(substr($package, -7, 7)) || '.tar.bz2' == strtolower(substr($package, -8, 8))) {
			if (!class_exists('UpdraftPlus_Archive_Tar')) {
				if (false === strpos(get_include_path(), UPDRAFTPLUS_DIR.'/includes/PEAR')) set_include_path(UPDRAFTPLUS_DIR.'/includes/PEAR'.PATH_SEPARATOR.get_include_path());
				require_once(UPDRAFTPLUS_DIR.'/includes/PEAR/Archive/Tar.php');
			}

			$p_compress = null;
			if ('.tar.gz' == strtolower(substr($package, -7, 7))) {
				$p_compress = 'gz';
			} elseif ('.tar.bz2' == strtolower(substr($package, -8, 8))) {
				$p_compress = 'bz2';
			}

			# It's not pretty. But it works.
			if (is_a($wp_filesystem, 'WP_Filesystem_Direct')) {
				$extract_dir = $working_dir;
			} else {
				$updraft_dir = $updraftplus->backups_dir_location();
				if (!$updraftplus->really_is_writable($updraft_dir)) {
					$updraftplus->log_e("Backup directory (%s) is not writable, or does not exist.", $updraft_dir);
					$result = new WP_Error('unpack_failed', $this->strings['unpack_failed'], $tar->extract);
				} else {
					$extract_dir = $updraft_dir.'/'.basename($working_dir).'-old';
					if (file_exists($extract_dir)) $updraftplus->remove_local_directory($extract_dir);
					$updraftplus->log("Using a temporary folder to extract before moving over WPFS: $extract_dir");
				}
			}
			# Slightly hackish - rather than re-write Archive_Tar to use wp_filesystem, we instead unpack into the location that we already require to be directly writable for other reasons, and then move from there.
		
			if (empty($result)) {
				
				$this->ud_extract_count = 0;
				$this->ud_working_dir = trailingslashit($working_dir);
				$this->ud_extract_dir = untrailingslashit($extract_dir);
				$this->ud_made_dirs = array();
				add_filter('updraftplus_tar_wrote', array($this, 'tar_wrote'), 10, 2);
				$tar = new UpdraftPlus_Archive_Tar($package, $p_compress);
				$result = $tar->extract($extract_dir, false);
				if (!is_a($wp_filesystem, 'WP_Filesystem_Direct')) $updraftplus->remove_local_directory($extract_dir);
				if (true != $result) {
					$result = new WP_Error('unpack_failed', $this->strings['unpack_failed'], $result);
				} else {
					if (!is_a($wp_filesystem, 'WP_Filesystem_Direct')) {
						$updraftplus->log('Moved unpacked tarball contents');
					}
				}
				remove_filter('updraftplus_tar_wrote', array($this, 'tar_wrote'), 10, 2);
			}
		}

		// Once extracted, delete the package if required.
		if ( $delete_package )
			unlink($package);

		if ( is_wp_error($result) ) {
			$wp_filesystem->delete($working_dir, true);
			if ( 'incompatible_archive' == $result->get_error_code() ) {
				return new WP_Error( 'incompatible_archive', $this->strings['incompatible_archive'], $result->get_error_data() );
			}
			return $result;
		}

		if (!empty($this->ud_foreign)) {
			$this->ud_foreign_working_dir = $working_dir;
			$this->ud_foreign_package = $package;
			# Zip containing an SQL file. We try a default pattern.
			if ('db' === $type) {
				$basepack = basename($package, '.zip');
				if ($wp_filesystem->exists($working_dir.'/'.$basepack.'.sql')) {
					$wp_filesystem->move($working_dir.'/'.$basepack.'.sql', $working_dir . "/backup.db", true);
					$updraftplus->log("Moving database file $basepack.sql to backup.db");
				}
			}
		}

		return $working_dir;
	}

	public function tar_wrote($result, $file) {
		if (0 !== strpos($file, $this->ud_extract_dir)) return false;
		global $wp_filesystem, $updraftplus;
		if (!is_a($wp_filesystem, 'WP_Filesystem_Direct')) {
			$modint = 100;
			$leaf = substr($file, strlen($this->ud_extract_dir));
			$dirname = dirname($leaf);
			$need_dirs = explode('/', $dirname);
			if (empty($this->ud_made_dirs[$dirname])) {
				$cdir = '';
				foreach ($need_dirs as $ndir) {
					$cdir .= ($cdir) ? '/'.$ndir : $ndir;
					if (empty($this->ud_made_dirs[$cdir])) {
						if ( !$wp_filesystem->mkdir( $this->ud_working_dir.$cdir, FS_CHMOD_DIR) && ! $wp_filesystem->is_dir($this->ud_working_dir.$cdir) ) {
							$updraftplus->log("Failed to create WPFS directory: ".$this->ud_working_dir.$cdir);
							return false;
						} else {
							$this->ud_made_dirs[$cdir] = true;
						}
					}
				}
			}
			$put = $wp_filesystem->put_contents($this->ud_working_dir.$leaf, file_get_contents($file));
			if (is_wp_error($put)) $updraftplus->log_wp_error($put);
			@unlink($file);
		} else {
			$modint = 500;
			$put = true;
		}
		if ($put) {
			$this->ud_extract_count++;
			if ($this->ud_extract_count % $modint == 0) {
				$updraftplus->log_e("%s files have been extracted", $this->ud_extract_count);
			}
		}
		return ($put == true);
	}

	// This returns a wp_filesystem location (and we musn't change that, as we must retain compatibility with the class parent)
	public function unpack_package($package, $delete_package = true, $type = false) {

		global $wp_filesystem, $updraftplus;

		$updraft_dir = $updraftplus->backups_dir_location();

		// If not database, then it is a zip - unpack in the usual way
		#if (!preg_match('/db\.gz(\.crypt)?$/i', $package)) return parent::unpack_package($updraft_dir.'/'.$package, $delete_package);
		if (!preg_match('/-db(\.gz(\.crypt)?)?$/i', $package) && !preg_match('/\.sql(\.gz|\.bz2)?$/i', $package)) return $this->unpack_package_archive($updraft_dir.'/'.$package, $delete_package, $type);

		$backup_dir = $wp_filesystem->find_folder($updraft_dir);

		// Unpack a database. The general shape of the following is copied from class-wp-upgrader.php

		@set_time_limit(1800);

		$this->skin->feedback('unpack_package');

		$upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';
		@$wp_filesystem->mkdir($upgrade_folder, octdec($this->calculate_additive_chmod_oct(FS_CHMOD_DIR, 0775)));

		//Clean up contents of upgrade directory beforehand.
		$upgrade_files = $wp_filesystem->dirlist($upgrade_folder);
		if ( !empty($upgrade_files) ) {
			foreach ( $upgrade_files as $file )
				$wp_filesystem->delete($upgrade_folder.$file['name'], true);
		}

		//We need a working directory
		$working_dir = $upgrade_folder . basename($package, '.crypt');
		# $working_dir_localpath = WP_CONTENT_DIR.'/upgrade/'. basename($package, '.crypt');

		// Clean up working directory
		if ($wp_filesystem->is_dir($working_dir)) $wp_filesystem->delete($working_dir, true);

		if (!$wp_filesystem->mkdir($working_dir, octdec($this->calculate_additive_chmod_oct(FS_CHMOD_DIR, 0775)))) return new WP_Error('mkdir_failed', __('Failed to create a temporary directory','updraftplus').' ('.$working_dir.')');

		// Unpack package to working directory
		if ($updraftplus->is_db_encrypted($package)) {
			$this->skin->feedback('decrypt_database');

			$encryption = empty($this->ud_restore_options['updraft_encryptionphrase']) ? UpdraftPlus_Options::get_updraft_option('updraft_encryptionphrase') : $this->ud_restore_options['updraft_encryptionphrase'];

			if (!$encryption) return new WP_Error('no_encryption_key', __('Decryption failed. The database file is encrypted, but you have no encryption key entered.', 'updraftplus'));

			$plaintext = $updraftplus->decrypt(false, $encryption, $wp_filesystem->get_contents($backup_dir.$package));

			if ($plaintext) {
				$this->skin->feedback('decrypted_database');
				if (!$wp_filesystem->put_contents($working_dir.'/backup.db.gz', $plaintext)) {
					return new WP_Error('write_failed', __('Failed to write out the decrypted database to the filesystem','updraftplus'));
				}
			} else {
				return new WP_Error('decryption_failed', __('Decryption failed. The most likely cause is that you used the wrong key.','updraftplus'));
			}
		} else {

			if (preg_match('/\.sql$/i', $package)) { 
				if (!$wp_filesystem->copy($backup_dir.$package, $working_dir.'/backup.db')) {
					if ( $wp_filesystem->errors->get_error_code() ) { 
						foreach ( $wp_filesystem->errors->get_error_messages() as $message ) show_message($message); 
					}
					return new WP_Error('copy_failed', $this->strings['copy_failed']);
				}
			} elseif (preg_match('/\.bz2$/i', $package)) { 
				if (!$wp_filesystem->copy($backup_dir.$package, $working_dir.'/backup.db.bz2')) {
					if ( $wp_filesystem->errors->get_error_code() ) { 
						foreach ( $wp_filesystem->errors->get_error_messages() as $message ) show_message($message); 
					}
					return new WP_Error('copy_failed', $this->strings['copy_failed']);
				}
			} elseif (!$wp_filesystem->copy($backup_dir.$package, $working_dir.'/backup.db.gz')) {
				if ( $wp_filesystem->errors->get_error_code() ) { 
					foreach ( $wp_filesystem->errors->get_error_messages() as $message ) show_message($message); 
				}
				return new WP_Error('copy_failed', $this->strings['copy_failed']);
			}

		}

		// Once extracted, delete the package if required (non-recursive, is a file)
		if ($delete_package) $wp_filesystem->delete($backup_dir.$package, false, true);

		$updraftplus->log("Database successfully unpacked");

		return $working_dir;

	}

	// For moving files out of a directory into their new location
	// The purposes of the $type parameter are 1) to detect 'others' and apply a historical bugfix 2) to detect wpcore, and apply the setting for what to do with wp-config.php 3) to work out whether to delete the directory itself
	// Must use only wp_filesystem
	// $dest_dir must already have a trailing slash
	// $preserve_existing: this setting only applies at the top level: 0 = overwrite with no backup; 1 = make backup of existing; 2 = do nothing if there is existing, 3 = do nothing to the top level directory, but do copy-in contents (and over-write files). Thus, on a multi-archive set where you want a backup, you'd do this: first call with $preserve_existing === 1, then on subsequent zips call with 3
	public function move_backup_in($working_dir, $dest_dir, $preserve_existing = 1, $do_not_overwrite = array('plugins', 'themes', 'uploads', 'upgrade'), $type = 'not-others', $send_actions = false, $force_local = false) {

		/*
			const MOVEIN_OVERWRITE_NO_BACKUP = 0;
			const MOVEIN_MAKE_BACKUP_OF_EXISTING = 1;
			const MOVEIN_DO_NOTHING_IF_EXISTING = 2;
			const MOVEIN_COPY_IN_CONTENTS = 3;
		*/
		global $wp_filesystem, $updraftplus;
		$updraft_dir = $updraftplus->backups_dir_location();

		#  && !is_a($wp_filesystem, 'WP_Filesystem_Direct')
		if (true == $force_local) {
			$wpfs = new UpdraftPlus_WP_Filesystem_Direct(true);
		} else {
			$wpfs = $wp_filesystem;
		}

		# Get the content to be moved in. Include hidden files = true. Recursion is only required if we're likely to copy-in
		$recursive = (self::MOVEIN_COPY_IN_CONTENTS == $preserve_existing) ? true : false;
		$upgrade_files = $wpfs->dirlist($working_dir, true, $recursive);

		if (empty($upgrade_files)) return true;

		if (!$wpfs->is_dir($dest_dir)) {
			return new WP_Error('no_such_dir', __('The directory does not exist', 'updraftplus')." ($dest_dir)");
// 			$updraftplus->log_e("The directory does not exist, so will be created (%s).", $dest_dir);
// 			# Attempts to create the directory fail, as due to a core bug, $dest_dir will be the wrong value if it did not already exist (at least for themes - the value of it depends on an is_dir() check wrongly used to detect a relative path)
// 			if (!$wpfs->mkdir($dest_dir)) {
// 				return new WP_Error('create_failed', __('Failed to create directory', 'updraftplus')." ($dest_dir)");
// 			}
		}

		$wpcore_config_moved = false;

		if ('plugins' == $type || 'themes' == $type) $updraftplus->log("Top-level entities being moved: ".implode(', ', array_keys($upgrade_files)));

		foreach ( $upgrade_files as $file => $filestruc ) {

			if (empty($file)) continue;

			if ($dest_dir.$file == $updraft_dir) {
				$updraftplus->log('Skipping attempt to replace updraft_dir whilst processing '.$type);
				continue;
			}

			// Correctly restore files in 'others' in no directory that were wrongly backed up in versions 1.4.0 - 1.4.48
			if (('others' == $type || 'wpcore' == $type) && preg_match('/^([\-_A-Za-z0-9]+\.php)$/i', $file, $matches) && $wpfs->exists($working_dir . "/$file/$file")) {
				if ('others' == $type) {
					echo "Found file: $file/$file: presuming this is a backup with a known fault (backup made with versions 1.4.0 - 1.4.48, and sometimes up to 1.6.55 on some Windows servers); will rename to simply $file<br>";
				} else {
					echo "Found file: $file/$file: presuming this is a backup with a known fault (backup made with versions before 1.6.55 in certain situations on Windows servers); will rename to simply $file<br>";
				}
				$updraftplus->log("$file/$file: rename to $file");
				$file = $matches[1];
				$tmp_file = rand(0, 999999999).'.php';
				// Rename directory
				$wpfs->move($working_dir . "/$file", $working_dir . "/".$tmp_file, true);
				$wpfs->move($working_dir . "/$tmp_file/$file", $working_dir ."/".$file, true);
				$wpfs->rmdir($working_dir . "/$tmp_file", false);
			}

			if ('wp-config.php' == $file && 'wpcore' == $type) {
				if (empty($this->ud_restore_options['updraft_restorer_wpcore_includewpconfig'])) {
					$updraftplus->log_e('wp-config.php from backup: will restore as wp-config-backup.php', 'updraftplus');
					$wpfs->move($working_dir . "/$file", $working_dir . "/wp-config-backup.php", true);
					$file = "wp-config-backup.php";
					$wpcore_config_moved = true;
				} else {
					$updraftplus->log_e("wp-config.php from backup: restoring (as per user's request)", 'updraftplus');
				}
			} elseif ('wpcore' == $type && 'wp-config-backup.php' == $file && $wpcore_config_moved) {
				# The file is already gone; nothing to do
				continue;
			}

			# Sanity check (should not be possible as these were excluded at backup time)
			if (in_array($file, $do_not_overwrite)) continue;

			if (('object-cache.php' == $file || 'advanced-cache.php' == $file) && 'others' == $type) {
				if (false == apply_filters('updraftplus_restorecachefiles', true, $file)) {
					$nfile = preg_replace('/\.php$/', '-backup.php', $file);
					$wpfs->move($working_dir . "/$file", $working_dir . "/".$nfile, true);
					$file=$nfile;
				}
			} elseif (('object-cache-backup.php' == $file || 'advanced-cache-backup.php' == $file) && 'others' == $type) {
				$wpfs->delete($working_dir."/".$file);
				continue;
			} 

			# First, move the existing one, if necessary (may not be present)
			if ($wpfs->exists($dest_dir.$file)) {
				if ($preserve_existing == self::MOVEIN_MAKE_BACKUP_OF_EXISTING) {
					if ( !$wpfs->move($dest_dir.$file, $dest_dir.$file.'-old', true) ) {
						return new WP_Error('old_move_failed', $this->strings['old_move_failed']." ($dest_dir$file)");
					}
				} elseif ($preserve_existing == self::MOVEIN_OVERWRITE_NO_BACKUP) {
					if (!$wpfs->delete($dest_dir.$file, true)) {
						return new WP_Error('old_delete_failed', $this->strings['old_delete_failed']." ($file)");
					}
				}
			}


			# Secondly, move in the new one
			$is_dir = $wpfs->is_dir($working_dir."/".$file);

			if (self::MOVEIN_DO_NOTHING_IF_EXISTING == $preserve_existing && $wpfs->exists($dest_dir.$file)) {
				// Something exists - no move. Remove it from the temporary directory - so that it will be clean later
				@$wpfs->delete($working_dir.'/'.$file, true);
			// The $is_dir check was added in version 1.11.18; without this, files in the top-level that weren't in the first archive didn't get over-written
			} elseif (self::MOVEIN_COPY_IN_CONTENTS != $preserve_existing || !$wpfs->exists($dest_dir.$file) || !$is_dir) {

				if ($wpfs->move($working_dir."/".$file, $dest_dir.$file, true) ) {
					if ($send_actions) do_action('updraftplus_restored_'.$type.'_one', $file);
					# Make sure permissions are at least as great as those of the parent
					if ($is_dir) {
						# This method is broken due to https://core.trac.wordpress.org/ticket/26598
						#if (empty($chmod)) $chmod = $wpfs->getnumchmodfromh($wpfs->gethchmod($dest_dir));
						if (empty($chmod)) $chmod = octdec(sprintf("%04d", $this->get_current_chmod($dest_dir, $wpfs)));
						if (!empty($chmod)) $this->chmod_if_needed($dest_dir.$file, $chmod, false, $wpfs);
					}
				} else {
					return new WP_Error('move_failed', $this->strings['move_failed'], $working_dir."/".$file." -> ".$dest_dir.$file);
				}
			} elseif (self::MOVEIN_COPY_IN_CONTENTS == $preserve_existing && !empty($filestruc['files'])) {
				# The directory ($dest_dir) already exists, and we've been requested to copy-in. We need to perform the recursive copy-in
				# $filestruc['files'] is then a new structure like $upgrade_files
				# First pass: create directory structure
				# Get chmod value for the parent directory, and re-use it (instead of passing false)

				# This method is broken due to https://core.trac.wordpress.org/ticket/26598
				#if (empty($chmod)) $chmod = $wpfs->getnumchmodfromh($wpfs->gethchmod($dest_dir));
				if (empty($chmod)) $chmod = octdec(sprintf("%04d", $this->get_current_chmod($dest_dir, $wpfs)));
				# Copy in the files. This also needs to make sure the directories exist, in case the zip file lacks entries
				$delete_root = ('others' == $type || 'wpcore' == $type) ? false : true;

				$copy_in = $this->copy_files_in($working_dir.'/'.$file, $dest_dir.$file, $filestruc['files'], $chmod, $delete_root);
				if (!empty($chmod)) $this->chmod_if_needed($dest_dir.$file, $chmod, false, $wpfs);

				if (is_wp_error($copy_in)) return $copy_in;
				if (!$copy_in) return new WP_Error('move_failed', $this->strings['move_failed'], "(2) ".$working_dir.'/'.$file." -> ".$dest_dir.$file);

				$wpfs->rmdir($working_dir.'/'.$file);
			} else {
				$wpfs->rmdir($working_dir.'/'.$file);
			}
		}

		return true;

	}

	# $dest_dir must already exist
	private function copy_files_in($source_dir, $dest_dir, $files, $chmod = false, $deletesource = false) {
		global $wp_filesystem, $updraftplus;
		foreach ($files as $rname => $rfile) {
			if ('d' != $rfile['type']) {
				# Delete it if it already exists (or perhaps WP does it for us)
				if (!$wp_filesystem->move($source_dir.'/'.$rname, $dest_dir.'/'.$rname, true)) {
					$updraftplus->log_e('Failed to move file (check your file permissions and disk quota): %s', $source_dir.'/'.$rname." -&gt; ".$dest_dir.'/'.$rname);
					return false;
				}
			} else {
				# Directory
				if ($wp_filesystem->is_file($dest_dir.'/'.$rname)) @$wp_filesystem->delete($dest_dir.'/'.$rname, false, 'f');
				# No such directory yet: just move it
				if (!$wp_filesystem->is_dir($dest_dir.'/'.$rname)) {
					if (!$wp_filesystem->move($source_dir.'/'.$rname, $dest_dir.'/'.$rname, false)) {
						$updraftplus->log_e('Failed to move directory (check your file permissions and disk quota): %s', $source_dir.'/'.$rname." -&gt; ".$dest_dir.'/'.$rname);
						return false;
					}
				} elseif (!empty($rfile['files'])) {
					# There is a directory - and we want to to copy in
					$docopy = $this->copy_files_in($source_dir.'/'.$rname, $dest_dir.'/'.$rname, $rfile['files'], $chmod, false);
					if (is_wp_error($docopy)) return $docopy;
					if (false === $docopy) {
						return false;
					}
				} else {
					# There is a directory: but nothing to copy in to it
					@$wp_filesystem->rmdir($source_dir.'/'.$rname);
				}
			}
		}
		# We are meant to leave the working directory empty. Hence, need to rmdir() once a directory is empty. But not the root of it all in case of others/wpcore.
		if ($deletesource || strpos($source_dir, '/') !== false) {
			$wp_filesystem->rmdir($source_dir, false);
		}

		return true;

	}

	// Pre-flight check: chance to complain and abort before anything at all is done
	public function pre_restore_backup($backup_files, $type, $info, $continuation_data = null) {

		if (is_string($backup_files)) $backup_files=array($backup_files);

		if ('more' == $type) {
			$this->skin->feedback('not_possible');
			return;
		}

		// Ensure access to the indicated directory - and to WP_CONTENT_DIR (in which we use upgrade/)
		$need_these = array(WP_CONTENT_DIR);
		if (!empty($info['path'])) $need_these[] = $info['path'];

		$res = $this->fs_connect($need_these);
		if (false === $res || is_wp_error($res)) return $res;

		# Check upgrade directory is writable (instead of having non-obvious messages when we try to write)
		# In theory, this is redundant (since we already checked for access to WP_CONTENT_DIR); but in practice, this extra check has been needed

		global $wp_filesystem, $updraftplus, $updraftplus_admin, $updraftplus_addons_migrator;

		if (empty($this->pre_restore_updatedir_writable)) {
			$upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';
			@$wp_filesystem->mkdir($upgrade_folder, octdec($this->calculate_additive_chmod_oct(FS_CHMOD_DIR, 0775)));
			if (!$wp_filesystem->is_dir($upgrade_folder)) {
				return new WP_Error('no_dir', sprintf(__('UpdraftPlus needed to create a %s in your content directory, but failed - please check your file permissions and enable the access (%s)', 'updraftplus'), __('folder', 'updraftplus'), $upgrade_folder));
			}
			$rand_file = 'testfile_'.rand(0,9999999).md5(microtime(true)).'.txt';
			if ($wp_filesystem->put_contents($upgrade_folder.$rand_file, 'testing...')) {
				@$wp_filesystem->delete($upgrade_folder.$rand_file);
				$this->pre_restore_updatedir_writable = true;
			} else {
				return new WP_Error('no_file', sprintf(__('UpdraftPlus needed to create a %s in your content directory, but failed - please check your file permissions and enable the access (%s)', 'updraftplus'), __('file', 'updraftplus'), $upgrade_folder.$rand_file));
			}
		}

		# Code below here assumes that we're dealing with file-based entities
		if ('db' == $type) return true;

		$wp_filesystem_dir = $this->get_wp_filesystem_dir($info['path']);
		if ($wp_filesystem_dir === false) return false;

// 		$this->maintenance_mode(true);
// 		$updraftplus->log_e('Testing file permissions...');

		$ret_val = true;
		$updraft_dir = $updraftplus->backups_dir_location();

		if (!is_array($continuation_data) && (('plugins' == $type || 'uploads' == $type || 'themes' == $type) && (!is_multisite() || $this->ud_backup_is_multisite !== 0 || ('uploads' != $type || empty($updraftplus_addons_migrator->new_blogid ))))) {
			if (file_exists($updraft_dir.'/'.basename($wp_filesystem_dir)."-old")) {
				$ret_val = new WP_Error('already_exists', sprintf(__('Existing unremoved folders from a previous restore exist (please use the "Delete Old Directories" button to delete them before trying again): %s', 'updraftplus'), $wp_filesystem_dir.'-old'));
			}
		}

// 		$this->maintenance_mode(false);

		if (!empty($this->ud_foreign)) {
			$known_foreigners = apply_filters('updraftplus_accept_archivename', array());
			if (!is_array($known_foreigners) || empty($known_foreigners[$this->ud_foreign])) {
				return new WP_Error('uk_foreign', __('This version of UpdraftPlus does not know how to handle this type of foreign backup', 'updraftplus').' ('.$this->ud_foreign.')');
			}
		}

		return $ret_val;
	}

	private function get_wp_filesystem_dir($path) {
		global $wp_filesystem;
		// Get the wp_filesystem location for the folder on the local install
		switch ($path) {
			case ABSPATH:
			case '';
				$wp_filesystem_dir = $wp_filesystem->abspath();
				break;
			case WP_CONTENT_DIR:
				$wp_filesystem_dir = $wp_filesystem->wp_content_dir();
				break;
			case WP_PLUGIN_DIR:
				$wp_filesystem_dir = $wp_filesystem->wp_plugins_dir();
				break;
			case WP_CONTENT_DIR . '/themes':
				$wp_filesystem_dir = $wp_filesystem->wp_themes_dir();
				break;
			default:
				$wp_filesystem_dir = $wp_filesystem->find_folder($path);
				break;
		}
		if ( ! $wp_filesystem_dir ) return false;
		return untrailingslashit($wp_filesystem_dir);
	}

	private function can_version_ajax_restore($version) {
		if (!defined('UPDRAFTPLUS_EXPERIMENTAL_AJAX_RESTORE') || !UPDRAFTPLUS_EXPERIMENTAL_AJAX_RESTORE) return false;
		return ((version_compare($version, '2.0', '<') && version_compare($version, '1.11.10', '>=')) || (version_compare($version, '2.0', '>=') && version_compare($version, '2.11.10', '>='))) ? true : false;
	}

	// $backup_file is just the basename, and must be a string; we expect the caller to deal with looping over an array (multi-archive sets). We do, however, record whether we have already unpacked an entity of the same type - so that we know to add (not replace).
	public function restore_backup($backup_file, $type, $info, $last_one = false) {

		if ('more' == $type) {
			$this->skin->feedback('not_possible');
			return;
		}

		global $wp_filesystem, $updraftplus_addons_migrator, $updraftplus;

		$updraftplus->log("restore_backup(backup_file=$backup_file, type=$type, info=".serialize($info).", last_one=$last_one)");

		$get_dir = empty($info['path']) ? '' : $info['path'];

		if (false === ($wp_filesystem_dir = $this->get_wp_filesystem_dir($get_dir))) return false;

		if (empty($this->abspath)) $this->abspath = trailingslashit($wp_filesystem->abspath());

		@set_time_limit(1800);

		/*
		TODO:
		- The backup set may no longer be in the DB - a restore may have over-written it.
		- UD might be installed, but not active. Test that too. (All combinations need testing - new/old UD vers, logged-in/not, etc.).
		- logging
		- authorisation on the AJAX call, given that our login may not even be valid any more.
		- pass on the WP filesystem credentials somehow - they have been POSTed, and should be included in what's POSTed back.
		- the restore function wants to know the UD version the backup set came from
		- the restore function wants to know whether we're restoring an individual blog into a multisite
		- the restore function has some things only done the first time, which isn't directly tracked (uses internal state instead, which won't work over AJAX)
		- how to handle/set this->delete
		- how to show the final result
		- how to do the clear-up of restored stuff in the restore function
		- remember, we need to do unauthenticated AJAX, as the authentication is happening via a different means. Use a separate procedure from the usual one (and no nonce, as login status may have changed).
		*/
		if (defined('UPDRAFTPLUS_EXPERIMENTAL_AJAX_RESTORE') && UPDRAFTPLUS_EXPERIMENTAL_AJAX_RESTORE && 'uploads' == $type) {
			// Read this each time, as we don't know what might have been done in the mean-time (specifically with UD being replaced by a different UD from a backup). Of course, we know what the currently running process is capable of.
			if (file_exists(UPDRAFTPLUS_DIR.'/updraftplus.php') && $fp = fopen(UPDRAFTPLUS_DIR.'/updraftplus.php', 'r')) {
				$file_data = fread($fp, 1024);
				if (preg_match("/Version: ([\d\.]+)(\r|\n)/", $file_data, $matches)) {
					$ud_version = $matches[1];
				}
				fclose($fp);
			}
			if (!empty($ud_version) && $this->can_version_ajax_restore($ud_version) && !empty($this->ud_backup_info['timestamp'])) {
				$nonce = $updraftplus->nonce;
				if (!function_exists('crypt_random_string')) $updraftplus->ensure_phpseclib('Crypt_Random', 'Crypt/Random');
				$this->ajax_restore_auth_code = bin2hex(crypt_random_string(32));
// TODO: Delete this when done, to prevent abuse
				update_site_option('updraft_ajax_restore_'.$nonce, $this->ajax_restore_auth_code.':'.time());
				$this->add_ajax_restore_admin_footer();
				$print_last_one = ($last_one) ? "1" : "0";
// TODO: Also want the timestamp
				// We don't bother to include info, as that is backup-independent information that can be re-created when needed
				echo '<p style="margin: 0px 0px;" class="updraft-ajaxrestore" data-type="'.$type.'" data-lastone="'.$print_last_one.'" data-backupfile="'.esc_attr($backup_file).'">'."\n";
				$updraftplus->log("Deferring handling of uploads ($backup_file)");
				echo "$backup_file: ".'<span class="deferprogress">'.__('Deferring...', 'updraftplus').'</span>';
				echo '</p>';
				return true;
			}
		}

		// This returns the wp_filesystem path
		$working_dir = $this->unpack_package($backup_file, $this->delete, $type);
		if (is_wp_error($working_dir)) return $working_dir;

		$working_dir_localpath = WP_CONTENT_DIR.'/upgrade/'.basename($working_dir);
		@set_time_limit(1800);

		// We copy the variable because we may be importing with a different prefix (e.g. on multisite imports of individual blog data)
		$import_table_prefix = $updraftplus->get_table_prefix(false);

		$now_done = apply_filters('updraftplus_pre_restore_move_in', false, $type, $working_dir, $info, $this->ud_backup_info, $this, $wp_filesystem_dir);
		if (is_wp_error($now_done)) return $now_done;

		// A slightly ugly way of getting a particular result back
		if (is_string($now_done)) {
			$wp_filesystem_dir = $now_done;
			$now_done = false;
			$do_not_move_old = true;
		}
		
		if (!$now_done) {
		
			if ('db' == $type) {
				// $import_table_prefix is received as a reference
				$rdb = $this->restore_backup_db($working_dir, $working_dir_localpath, $import_table_prefix);
				if (false === $rdb || is_wp_error($rdb)) return $rdb;

			} elseif ('others' == $type) {

				$dirname = basename($info['path']);

				# For foreign 'Simple Backup', we need to keep going down until we find wp-content 
				if (empty($this->ud_foreign)) {
					$move_from = $working_dir;
				} else {
					$move_from = $this->search_for_folder('wp-content', $working_dir);
					if (!is_string($move_from)) return new WP_Error('not_found', __('The WordPress content folder (wp-content) was not found in this zip file.', 'updraftplus'));
				}

				// In this special case, the backup contents are not in a folder, so it is not simply a case of moving the folder around, but rather looping over all that we find

				// On subsequent archives of a multi-archive set, don't move anything; but do on the first
				$preserve_existing = isset($this->been_restored['others']) ? self::MOVEIN_COPY_IN_CONTENTS : self::MOVEIN_MAKE_BACKUP_OF_EXISTING;

				$preserve_existing = apply_filters('updraft_move_others_preserve_existing', $preserve_existing, $this->been_restored, $this->ud_restore_options, $this->ud_backup_info);
				
				$new_move_from = apply_filters('updraft_restore_backup_move_from', $move_from, 'others', $this->ud_restore_options, $this->ud_backup_info);
				
				if ($new_move_from != $move_from && 0 === strpos($new_move_from, $move_from)) {
					$new_suffix = substr($new_move_from, strlen($move_from));
					$wp_filesystem_dir .= $new_suffix;
					$move_from = $new_move_from;
				}

				$move_in = $this->move_backup_in($move_from, trailingslashit($wp_filesystem_dir), $preserve_existing, array('plugins', 'themes', 'uploads', 'upgrade'), 'others');
				if (is_wp_error($move_in)) return $move_in;
				if (!$move_in) return new WP_Error('new_move_failed', $this->strings['new_move_failed']);
				
				$this->been_restored['others'] = true;

			} else {

				// Default action: used for plugins, themes and uploads (and wpcore, via a filter)
				// Multi-archive sets: we record what we've already begun on, and on subsequent runs, copy in instead of replacing
				$movedin = apply_filters('updraftplus_restore_movein_'.$type, $working_dir, $this->abspath, $wp_filesystem_dir);

				// A filter, to allow add-ons to perform the install of non-standard entities, or to indicate that it's not possible
				if (false === $movedin) {
					$this->skin->feedback('not_possible');
				} elseif (is_wp_error($movedin)) {
					return $movedin;
				} elseif (true !== $movedin) {

					// We get the directory to move from early, in case there is a problem with the backup that affects the result - we want to detect that before moving existing data out of the way
					
					$short_circuit = false;
					
					// For foreign 'Simple Backup', we need to keep going down until we find wp-content 
					if (empty($this->ud_foreign)) {
						$working_dir_use = $working_dir;
					} else {
						$working_dir_use = $this->search_for_folder('wp-content', $working_dir);
						if (!is_string($working_dir_use)) {
							if (empty($this->ud_foreign) || !apply_filters('updraftplus_foreign_allow_missing_entity', false, $type, $this->ud_foreign)) {
								return new WP_Error('not_found', __('The WordPress content folder (wp-content) was not found in this zip file.', 'updraftplus'));
							} else {
								$short_circuit = true;
							}
						}
					}
					
					// The backup may not actually have /$type, since that is info from the present site
					$move_from = $this->get_first_directory($working_dir_use, array(basename($info['path']), $type));
					if (false !== $move_from) $move_from = apply_filters('updraft_restore_backup_move_from', $move_from, $type, $this->ud_restore_options, $this->ud_backup_info);
					
					if (false === $move_from) {
						if (!empty($this->ud_foreign) && !apply_filters('updraftplus_foreign_allow_missing_entity', false, $type, $this->ud_foreign)) {
							return new WP_Error('new_move_failed', $this->strings['new_move_failed']);
						}
					}

					// On the first time, create the -old directory in updraft_dir
					// (Old style was: On the first time, move the existing data to -old)
					if (!isset($this->been_restored[$type]) && empty($do_not_move_old)) {
						$this->move_existing_to_old($type, $get_dir, $wp_filesystem, $wp_filesystem_dir);
					}
					
					if (empty($short_circuit)) {
										
						if (false === $move_from) {
							if (!empty($this->ud_foreign) && !apply_filters('updraftplus_foreign_allow_missing_entity', false, $type, $this->ud_foreign)) {
								return new WP_Error('new_move_failed', $this->strings['new_move_failed']);
							}
						} else {

							$this->skin->feedback('moving_backup');

							$move_in = $this->move_backup_in($move_from, trailingslashit($wp_filesystem_dir), self::MOVEIN_COPY_IN_CONTENTS, array(), $type);

							if (is_wp_error($move_in)) return $move_in;
							if (!$move_in) return new WP_Error('new_move_failed', $this->strings['new_move_failed']);

							$wp_filesystem->rmdir($move_from);
						}
					}

				}

				$this->been_restored[$type] = true;

			}
		}

		$attempt_delete = true;
		if (!empty($this->ud_foreign) && !$last_one) $attempt_delete = false;

		// Non-recursive, so the directory needs to be empty
		if ($attempt_delete) $this->skin->feedback('cleaning_up');

		if ($attempt_delete) {

			if (!empty($do_not_move_old)) @$wp_filesystem->delete($working_dir.'/'.$type);
		
			// Foreign backups can contain extra data and thus leave stuff behind, thus causing errors
			$recurse = empty($this->ud_foreign) ? false : true;
			$recurse = apply_filters('updraftplus_restore_delete_recursive', $recurse, $this->ud_foreign, $this->ud_restore_options, $type);
		
			if (!$wp_filesystem->delete($working_dir, $recurse)) {

				# TODO: Can remove this after 1-Jan-2015; or at least, make it so that it requires the version number to be present.
				$fixed_it_now = false;
				# Deal with a corner-case in version 1.8.5
				if ('uploads' == $type && (empty($this->created_by_version) || (version_compare($this->created_by_version, '1.8.5', '>=') && version_compare($this->created_by_version, '1.8.8', '<')))) {
					$updraftplus->log("Clean-up failed with uploads: will attempt 1.8.5-1.8.7 fix (".$this->created_by_version.")");
					$move_in = @$this->move_backup_in(dirname($move_from), trailingslashit($wp_filesystem_dir), 3, array(), $type);
					$updraftplus->log("Result: ".serialize($move_in));
					if ($wp_filesystem->delete($working_dir)) $fixed_it_now = true;
				}
				
				if (!$fixed_it_now) {
					$updraftplus->log_e('Error: %s', $this->strings['delete_failed'].' ('.$working_dir.')');
					# List contents
					// No need to make this a restoration-aborting error condition - it's not
					#return new WP_Error('delete_failed', $this->strings['delete_failed'].' ('.$working_dir.')');
					$dirlist = $wp_filesystem->dirlist($working_dir, true, true);
					if (is_array($dirlist)) {
						echo __('Files found:', 'updraftplus').'<br><ul style="list-style: disc inside;">';
						foreach ($dirlist as $name => $struc) {
							echo "<li>".htmlspecialchars($name)."</li>";
						}
						echo '</ul>';
					} else {
						$updraftplus->log_e('Unable to enumerate files in that directory.');
					}
				}
			}
		}

		# Permissions changes (at the top level - i.e. this does not apply if using recursion) are now *additive* - i.e. there's no danger of permissions being removed from what's on-disk
		switch($type) {
			case 'wpcore':
				$this->chmod_if_needed($wp_filesystem_dir, FS_CHMOD_DIR, false, $wp_filesystem);
				// In case we restored a .htaccess which is incorrect for the local setup
				$this->flush_rewrite_rules();
			break;
			case 'uploads':
				$this->chmod_if_needed($wp_filesystem_dir, FS_CHMOD_DIR, false, $wp_filesystem);
			break;
			case 'themes':
				// Cherry Framework needs its cache files removing after migration
				if ((empty($this->old_siteurl) || ($this->old_siteurl != $this->our_siteurl)) && function_exists('glob')) {
					$cherry_child = glob(WP_CONTENT_DIR.'/themes/theme*');
					if (is_array($cherry_child)) {
						foreach ($cherry_child as $theme) {
							if (file_exists($theme.'/style.less.cache')) unlink($theme.'/style.less.cache');
							if (file_exists($theme.'/bootstrap/less/bootstrap.less.cache')) unlink($theme.'/bootstrap/less/bootstrap.less.cache');
						}
					}
				}
			break;
			case 'db':
				if (function_exists('wp_cache_flush')) wp_cache_flush();
				do_action('updraftplus_restored_db', array(
					'expected_oldsiteurl' => $this->old_siteurl,
					'expected_oldhome' => $this->old_home,
					'expected_oldcontent' => $this->old_content
				), $import_table_prefix);
				
				# N.B. flush_rewrite_rules() causes $wp_rewrite to become up to date again - important for the no_mod_rewrite() call
				$this->flush_rewrite_rules();

				if ($updraftplus->mod_rewrite_unavailable()) {
					$updraftplus->log("Using Apache, with permalinks (".get_option('permalink_structure').") but no mod_rewrite enabled - enable it to make your permalinks work");
					$warn_no_rewrite = sprintf(__('You are using the %s webserver, but do not seem to have the %s module loaded.', 'updraftplus'), 'Apache', 'mod_rewrite').' '.sprintf(__('You should enable %s to make any pretty permalinks (e.g. %s) work', 'updraftplus'), 'mod_rewrite', 'http://example.com/my-page/');
					echo '<p><strong>'.htmlspecialchars($warn_no_rewrite).'</strong></p>';
				}

			break;
			default:
				$this->chmod_if_needed($wp_filesystem_dir, FS_CHMOD_DIR, false, $wp_filesystem);
		}
		# db was already done
		if ('db' != $type) do_action('updraftplus_restored_'.$type);

		return true;

	}

	private function move_existing_to_old($type, $get_dir, $wp_filesystem, $wp_filesystem_dir) {

		if (apply_filters('updraft_move_existing_to_old_short_circuit', false, $type, $this->ud_restore_options)) {
			// Users of the filter should do their own logging
			return;
		}

		global $updraftplus;
		$updraft_dir = $updraftplus->backups_dir_location();
	
		// Firstly, if there's already an '-old' directory, get rid of it

		// Try filesystem-level move
		$old_dir = $updraft_dir.'/'.$type.'-old';
		if (is_dir($old_dir)) {
			$updraftplus->log_e('%s: This directory already exists, and will be replaced', $old_dir);
			$updraftplus->remove_local_directory($old_dir);
		}

		$move_old_destination = apply_filters('updraftplus_restore_move_old_mode', 0, $type, $this->ud_restore_options);

		if (0 == $move_old_destination && @mkdir($old_dir)) {
			$updraftplus->log("Moving old data: filesystem method / updraft_dir is potentially possible");
			$move_old_destination = 1;
		}

		# Try wp_filesystem instead
		if ($wp_filesystem->exists($wp_filesystem_dir."-old")) {
			// Is better to warn and delete the restore than abort mid-restore and leave inconsistent site
			$updraftplus->log_e('%s: This directory already exists, and will be replaced', $wp_filesystem_dir."-old");
			# In theory, supplying true as the 3rd parameter achieves this; in practice, not always so (leads to support requests)
			$wp_filesystem->delete($wp_filesystem_dir."-old", true);
			if ($wp_filesystem->exists($wp_filesystem_dir."-old")) {
				$updraftplus->log("Failed to remove existing directory (".$wp_filesystem_dir."-old");
				$failed_to_remove = true;
				#return new WP_Error('old_move_failed', $this->strings['old_move_failed']);
			}
		}

		if (-1 != $move_old_destination && empty($failed_to_remove) && @$wp_filesystem->mkdir($wp_filesystem_dir."-old")) {
			$updraftplus->log("Moving old data: can potentially use wp_filesystem method / -old");
			$move_old_destination += 2;
		}

		if (0 == $move_old_destination) {
			$updraftplus->log_e("File permissions do not allow the old data to be moved and retained; instead, it will be deleted.");
		}

		$this->skin->feedback('moving_old');

		# Firstly, try direct filesystem method into updraft_dir
		if ($move_old_destination > 0 && 1 == $move_old_destination % 2) {
			# The final 'true' forces direct filesystem access
			$move_old = @$this->move_backup_in($get_dir, $updraft_dir.'/'.$type.'-old/' , 3, array(), $type, false, true);
			if (is_wp_error($move_old)) $updraftplus->log_wp_error($move_old);
		}

		# Try wp_filesystem method into -old if that failed
		if (2 >= $move_old_destination && (0 == $move_old_destination % 2 || (!empty($move_old) && is_wp_error($move_old)))) {
			$move_old = @$this->move_backup_in($wp_filesystem_dir, $wp_filesystem_dir."-old/" , 3, array(), $type);
			#if (is_wp_error($move_old)) return $move_old;
			if (is_wp_error($move_old)) $updraftplus->log_wp_error($move_old);
		}

		# Finally, when all else fails, nuke it
		if (-1 == $move_old_destination || 0 == $move_old_destination || (!empty($move_old) && is_wp_error($move_old))) {
			if (-1 == $move_old_destination) {
				$updraftplus->log("$type: $wp_filesystem_dir: deleting contents");
			} else {
				$updraftplus->log("$type: $wp_filesystem_dir: deleting contents (as attempts to copy failed)");
			}
			$del_files = $wp_filesystem->dirlist($wp_filesystem_dir, true, false);
			if (empty($del_files)) $del_files = array();
			foreach ( $del_files as $file => $filestruc ) {
				if (empty($file)) continue;
				$wp_filesystem->delete($wp_filesystem_dir.'/'.$file, true);
			}
		}

	}
	
	private function add_ajax_restore_admin_footer() {
		static $already = false;
		if (!$already) {
			$already = true;
			add_action('admin_footer', array($this, 'admin_footer_ajax_restore'));
		}
	}

	public function admin_footer_ajax_restore() {
// TODO: The timestamp parameter is mandatory - we should abort (earlier) if there isn't one.

		global $updraftplus;
		$nonce = $updraftplus->nonce;
// TODO: Apparently empty
		$auth_code = esc_js($this->ajax_restore_auth_code);

		echo <<<ENDHERE
		<script>
			jQuery(document).ready(function() {

			backupinfo = {
				action: 'updraft_ajaxrestore',
				subaction: 'restore',
				restorenonce: '$nonce',
				ajaxauth: '$auth_code'
			};

ENDHERE;
		$multisite = 0;
		$timestamp = $this->ud_backup_info['timestamp'];
		if (!empty($_REQUEST['updraft_restorer_backup_info'])) {
			// wp_unslash() does not exist until after WP 3.5
			if (function_exists('wp_unslash')) {
				$backup_info = wp_unslash( $_REQUEST['updraft_restorer_backup_info'] );
			} else {
				$backup_info = stripslashes_deep( $_REQUEST['updraft_restorer_backup_info'] );
			}
			if (false != ($backup_info = json_decode($backup_info, true))) {
				if (!empty($backup_info['timestamp'])) echo "\t\tbackupinfo.timestamp = '".esc_js($backup_info['timestamp'])."';\n";
				if (!empty($backup_info['created_by_version'])) echo "\t\tbackupinfo.created_by_version = '".esc_js($backup_info['created_by_version'])."';\n";
				echo "\t\tbackupinfo.multisite = ".((!empty($backup_info['multisite'])) ? '1' : '0').";\n";
			}
			
		}

		echo <<<ENDHERE

				// This is not just a list, but a queue
				var restore_these = [];

				function do_ajax_restore(restore_this) {

					var output = jQuery(restore_this.jqobject).find('.deferprogress');
					jQuery(output).html(updraftlion.processing);

					

					alert("AJAX RESTORE: "+timestamp+" "+restore_this.type+" "+restore_this.backupfile);

					backupinfo.type = restore_this.type;
					backupinfo.backupfile = restore_this.backupfile;
					backupinfo.lastone = restore_this.lastone

					jQuery.post(ajaxurl, backupinfo, function(response) {
						console.log(response);
						try {
							var resp = jQuery.parseJSON(response);
							console.log(resp);
							// TODO: Do something
						} catch (err) {
							console.log(err);
							// TODO: Report error to user
						}
						// This recurses, but won't exhaust memory as there can only be a small number
						do_ajax_restore_queue();
					});


				}

				function do_ajax_restore_queue() {
					if (restore_these.length < 1) { return; }
 					// Shift gets the *first* element of the array
					restore_this = restore_these.shift();
					// This call is asychronous - i.e. the fact it returns doesn't indicate what has or hasn't now been done
					do_ajax_restore(restore_this);
				}

				var multisite = $multisite;
				var timestamp = $timestamp;

				// What follows is a slightly crude way of ensuring that the one marked 'lastone' actually does go last
				jQuery('.updraft-ajaxrestore').each(function(ind){
					var lastone = jQuery(this).data('lastone');
					if (!lastone) {
						var thing_to_restore = {};
						thing_to_restore.type = jQuery(this).data('type');
						thing_to_restore.backupfile = jQuery(this).data('backupfile');
						thing_to_restore.jqobject = this;
						thing_to_restore.lastone = 0;
						restore_these.push(thing_to_restore);
					}
				});
				jQuery('.updraft-ajaxrestore').each(function(ind){
					var lastone = jQuery(this).data('lastone');
					if (lastone) {
						var thing_to_restore = {};
						thing_to_restore.type = jQuery(this).data('type');
						thing_to_restore.backupfile = jQuery(this).data('backupfile');
						thing_to_restore.jqobject = this;
						restore_these.push(thing_to_restore);
						thing_to_restore.lastone = 1;
					}
				});
				
				if (restore_these.length > 0) {
					do_ajax_restore_queue();
				}

			});
		</script>
ENDHERE;
	}

	// First added in UD 1.9.47. We have only ever had reports of cached stuff from WP Super Cache being retained, so, being cautious, we will only clear that for now
	public function clear_cache() {
		// Functions called here need to not assume that the relevant plugin actually exists - they should check for any functions they intend to call, before calling them.
		$this->clear_cache_wpsupercache();
	}

	// Adapted from wp_cache_clean_cache( $file_prefix, $all = false ) in WP Super Cache (wp-cache.php)
	private function clear_cache_wpsupercache() {
		$all = true;

		global $updraftplus, $cache_path, $wp_cache_object_cache;

		if ( $wp_cache_object_cache && function_exists( "reset_oc_version" ) ) reset_oc_version();

		// Removed check: && wpsupercache_site_admin()
		if ( $all == true && function_exists( 'prune_super_cache' ) ) {
			if (!empty($cache_path)) {
				$updraftplus->log_e("Clearing cached pages (%s)...", 'WP Super Cache');
				prune_super_cache( $cache_path, true );
			}
			return true;
		}
	}

	private function search_for_folder($folder, $startat) {
		if (!is_dir($startat)) return false;
		# Exists in this folder?
		if (is_dir($startat.'/'.$folder)) return trailingslashit($startat).$folder;
		# Does not
		if ($handle = opendir($startat)) {
			while (($file = readdir($handle)) !== false) {
				if ($file != '.' && $file != '..' && is_dir($startat).'/'.$file) {
					$ss = $this->search_for_folder($folder, trailingslashit($startat).$file);
					if (is_string($ss)) return $ss;
				}
			}
			closedir($handle);
		}
		return false;
	}

	# Returns an octal string (but not an octal number)
	private function get_current_chmod($file, $wpfs = false) {
		if (false == $wpfs) {
			global $wp_filesystem;
			$wpfs = $wp_filesystem;
		}
		# getchmod() is broken at least as recently as WP3.8 - see: https://core.trac.wordpress.org/ticket/26598
		return (is_a($wpfs, 'WP_Filesystem_Direct')) ? substr(sprintf("%06d", decoct(@fileperms($file))),3) : $wpfs->getchmod($file);
	}

	# Returns a string in octal format
	# $new_chmod should be an octal, i.e. what you'd pass to chmod()
	function calculate_additive_chmod_oct($old_chmod, $new_chmod) {
		# chmod() expects octal form, which means a preceding zero - see http://php.net/chmod
		$old_chmod = sprintf("%04d", $old_chmod);
		$new_chmod = sprintf("%04d", decoct($new_chmod));

		for ($i=1; $i<=3; $i++) {
			$oldbit = substr($old_chmod, $i, 1);
			$newbit = substr($new_chmod, $i, 1);
			for ($j=0; $j<=2; $j++) {
				if (($oldbit & (1<<$j)) && !($newbit & (1<<$j))) {
					$newbit = (string)($newbit | 1<<$j);
					$new_chmod = sprintf("%04d", substr($new_chmod, 0, $i).$newbit.substr($new_chmod, $i+1));
				}
			}
		}

		return $new_chmod;
	}

	# "If needed" means, "If the permissions are not already more permissive than this". i.e. This will not tighten permissions from what the user had before (we trust them)
	# $chmod should be an octal - i.e. the same as you'd pass to chmod()
	private function chmod_if_needed($dir, $chmod, $recursive = false, $wpfs = false, $suppress = true) {

		# Do nothing on Windows
		if (strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN') return true;

		if (false == $wpfs) {
			global $wp_filesystem;
			$wpfs = $wp_filesystem;
		}

		$old_chmod = $this->get_current_chmod($dir, $wpfs);

		# Sanity fcheck
		if (strlen($old_chmod) < 3) return;

		$new_chmod = $this->calculate_additive_chmod_oct($old_chmod, $chmod);

		# Don't fix what isn't broken
		if (!$recursive && $new_chmod == $old_chmod) return true;

		$new_chmod = octdec($new_chmod);

		if ($suppress) {
			return @$wpfs->chmod($dir, $new_chmod, $recursive);
		} else {
			return $wpfs->chmod($dir, $new_chmod, $recursive);
		}
	}

	// $dirnames: an array of preferred names
	public function get_first_directory($working_dir, $dirnames) {
		global $wp_filesystem, $updraftplus;
		$fdirnames = array_flip($dirnames);
		$dirlist = $wp_filesystem->dirlist($working_dir, true, false);
		if (is_array($dirlist)) {
			$move_from = false;
			foreach ($dirlist as $name => $struc) {
				if (isset($struc['type']) && 'd' != $struc['type']) continue;
				if (false === $move_from) {
					if (isset($fdirnames[$name])) {
						$move_from = $working_dir . "/".$name;
					} elseif (preg_match('/^([^\.].*)$/', $name, $fmatch)) {
						// In the case of a third-party backup, the first entry may be the wrong entity. We could try a more sophisticated algorithm, but a third party backup requiring one has never been seen (and it is not easy to envisage what the algorithm might be).
						if (empty($this->ud_foreign)) {
							$first_entry = $working_dir."/".$fmatch[1];
						}
					}
				}
			}
			if ($move_from === false && isset($first_entry)) {
				$updraftplus->log_e('Using directory from backup: %s', basename($first_entry));
				$move_from = $first_entry;
			}
		} else {
			# That shouldn't happen. Fall back to default
			$move_from = $working_dir."/".$dirnames[0];
		}
		return $move_from;
	}

	private function pre_sql_actions($import_table_prefix) {

		$import_table_prefix = apply_filters('updraftplus_restore_set_table_prefix', $import_table_prefix, $this->ud_backup_is_multisite);

		if (!is_string($import_table_prefix)) {
			$this->maintenance_mode(false);
			if ($import_table_prefix === false) {
				echo '<p>'.__('Please supply the requested information, and then continue.', 'updraftplus').'</p>';
				return false;
			} elseif (is_wp_error($import_table_prefix)) {
				return $import_table_prefix;
			} else {
				return new WP_Error('invalid_table_prefix', __('Error:', 'updraftplus').' '.serialize($import_table_prefix));
			}
		}

		global $updraftplus;
		echo $updraftplus->log_e('New table prefix: %s', $import_table_prefix);

		return $import_table_prefix;

	}

	public function option_filter_permalink_structure($val) {
		global $updraftplus;
		return $updraftplus->option_filter_get('permalink_structure');
	}

	public function option_filter_page_on_front($val) {
		global $updraftplus;
		return $updraftplus->option_filter_get('page_on_front');
	}

	public function option_filter_rewrite_rules($val) {
		global $updraftplus;
		return $updraftplus->option_filter_get('rewrite_rules');
	}

	// The pass-by-reference on $import_table_prefix is due to historical refactoring
	private function restore_backup_db($working_dir, $working_dir_localpath, &$import_table_prefix) {

		do_action('updraftplus_restore_db_pre');

		# This is now a legacy option (at least on the front end), so we should not see it much
		$this->prior_upload_path = get_option('upload_path');

		// There is a file backup.db(.gz) inside the working directory

		# The 'off' check is for badly configured setups - http://wordpress.org/support/topic/plugin-wp-super-cache-warning-php-safe-mode-enabled-but-safe-mode-is-off
		if (@ini_get('safe_mode') && 'off' != strtolower(@ini_get('safe_mode'))) {
			echo "<p>".__('Warning: PHP safe_mode is active on your server. Timeouts are much more likely. If these happen, then you will need to manually restore the file via phpMyAdmin or another method.', 'updraftplus')."</p><br/>";
		}

		$db_basename = 'backup.db.gz';
		if (!empty($this->ud_foreign)) {
			$plugins = apply_filters('updraftplus_accept_archivename', array());

			if (empty($plugins[$this->ud_foreign])) return new WP_Error('unknown', sprintf(__('Backup created by unknown source (%s) - cannot be restored.', 'updraftplus'), $this->ud_foreign));

			if (!file_exists($working_dir_localpath.'/'.$db_basename) && file_exists($working_dir_localpath.'/backup.db')) {
				$db_basename = 'backup.db';
			} elseif (!file_exists($working_dir_localpath.'/'.$db_basename) && file_exists($working_dir_localpath.'/backup.db.bz2')) {
				$db_basename = 'backup.db.bz2';
			}

			if (!file_exists($working_dir_localpath.'/'.$db_basename)) {
				$separatedb = empty($plugins[$this->ud_foreign]['separatedb']) ? false : true;
				$filtered_db_name = apply_filters('updraftplus_foreign_dbfilename', false, $this->ud_foreign, $this->ud_backup_info, $working_dir_localpath, $separatedb);
				if (is_string($filtered_db_name)) $db_basename = $filtered_db_name;
			}
		}

		// wp_filesystem has no gzopen method, so we switch to using the local filesystem (which is harmless, since we are performing read-only operations)
		if (false === $db_basename || !is_readable($working_dir_localpath.'/'.$db_basename)) return new WP_Error('dbopen_failed',__('Failed to find database file','updraftplus')." ($working_dir/".$db_basename.")");

		global $wpdb, $updraftplus;
		
		$this->skin->feedback('restore_database');

		$is_plain = (substr($db_basename, -3, 3) == '.db');
		$is_bz2 = (substr($db_basename, -7, 7) == '.db.bz2');

		// Read-only access: don't need to go through WP_Filesystem
		if ($is_plain) {
			$dbhandle = fopen($working_dir_localpath.'/'.$db_basename, 'r');
		} elseif ($is_bz2) {
			if (!function_exists('bzopen')) {
				$updraftplus->log_e("Your web server's PHP installation has these functions disabled: %s.", 'bzopen');
				$updraftplus->log_e('Your hosting company must enable these functions before %s can work.', __('restoration', 'updraftplus'));
			}
			$dbhandle = bzopen($working_dir_localpath.'/'.$db_basename, 'r');
		} else {
			$dbhandle = gzopen($working_dir_localpath.'/'.$db_basename, 'r');
		}
		if (!$dbhandle) return new WP_Error('dbopen_failed',__('Failed to open database file','updraftplus'));

		$this->line = 0;

		if (true == $this->use_wpdb) {
			$updraftplus->log_e('Database access: Direct MySQL access is not available, so we are falling back to wpdb (this will be considerably slower)');
		} else {
			$updraftplus->log("Using direct MySQL access; value of use_mysqli is: ".($this->use_mysqli ? '1' : '0'));
			if ($this->use_mysqli) {
				@mysqli_query($this->mysql_dbh, 'SET SESSION query_cache_type = OFF;');
			} else {
				@mysql_query('SET SESSION query_cache_type = OFF;', $this->mysql_dbh );
			}
		}

		// Find the supported engines - in case the dump had something else (case seen: saved from MariaDB with engine Aria; imported into plain MySQL without)
		$supported_engines = $wpdb->get_results("SHOW ENGINES", OBJECT_K);

		$this->errors = 0;
		$this->statements_run = 0;
		$this->insert_statements_run = 0;
		$this->tables_created = 0;

		$sql_line = "";
		$sql_type = -1;

		$this->start_time = microtime(true);

		$old_wpversion = '';
		$this->old_siteurl = '';
		$this->old_home = '';
		$this->old_content = '';
		$this->old_uploads = '';
		$this->old_table_prefix = (defined('UPDRAFTPLUS_OVERRIDE_IMPORT_PREFIX') && UPDRAFTPLUS_OVERRIDE_IMPORT_PREFIX) ? UPDRAFTPLUS_OVERRIDE_IMPORT_PREFIX : '';
		$old_siteinfo = array();
		$gathering_siteinfo = true;

		$this->create_forbidden = false;
		$this->drop_forbidden = false;
		$this->lock_forbidden = false;

		$this->last_error = '';
		$random_table_name = 'updraft_tmp_'.rand(0, 9999999).md5(microtime(true));

		// The only purpose in funnelling queries directly here is to be able to get the error number
		if ($this->use_wpdb) {
			$req = $wpdb->query("CREATE TABLE $random_table_name (test INT)");
			if (!$req) $this->last_error = $wpdb->last_error;
			$this->last_error_no = false;
		} else {
			if ($this->use_mysqli) {
				$req = mysqli_query($this->mysql_dbh, "CREATE TABLE $random_table_name (test INT)");
			} else {
				$req = mysql_unbuffered_query("CREATE TABLE $random_table_name (test INT)", $this->mysql_dbh);
			}
			if (!$req) {
				$this->last_error = ($this->use_mysqli) ? mysqli_error($this->mysql_dbh) : mysql_error($this->mysql_dbh);
				$this->last_error_no = ($this->use_mysqli) ? mysqli_errno($this->mysql_dbh) : mysql_errno($this->mysql_dbh);
			}
		}

		if (!$req && ($this->use_wpdb || 1142 === $this->last_error_no)) {
			$this->create_forbidden = true;
			# If we can't create, then there's no point dropping
			$this->drop_forbidden = true;
			echo '<strong>'.__('Warning:', 'updraftplus').'</strong> ';
			$updraftplus->log_e('Your database user does not have permission to create tables. We will attempt to restore by simply emptying the tables; this should work as long as a) you are restoring from a WordPress version with the same database structure, and b) Your imported database does not contain any tables which are not already present on the importing site.');
			$updraftplus->log('Error was: '.$this->last_error.' ('.$this->last_error_no.')');
		} else {
		
			if (1142 === $this->lock_table($random_table_name)) {
				$this->lock_forbidden = true;
				$updraftplus->log("Database user has no permission to lock tables - will not lock after CREATE");
			}
		
			if ($this->use_wpdb) {
				$req = $wpdb->query("DROP TABLE $random_table_name");
				if (!$req) $this->last_error = $wpdb->last_error;
				$this->last_error_no = false;
			} else {
				if ($this->use_mysqli) {
					$req = mysqli_query($this->mysql_dbh, "DROP TABLE $random_table_name");
				} else {
					$req = mysql_unbuffered_query("DROP TABLE $random_table_name", $this->mysql_dbh);
				}
				if (!$req) {
					$this->last_error = ($this->use_mysqli) ? mysqli_error($this->mysql_dbh) : mysql_error($this->mysql_dbh);
					$this->last_error_no = ($this->use_mysqli) ? mysqli_errno($this->mysql_dbh) : mysql_errno($this->mysql_dbh);
				}
			}
			if (!$req && ($this->use_wpdb || $this->last_error_no === 1142)) {
				$this->drop_forbidden = true;
				echo '<strong>'.__('Warning:','updraftplus').'</strong> ';
				$updraftplus->log_e('Your database user does not have permission to drop tables. We will attempt to restore by simply emptying the tables; this should work as long as you are restoring from a WordPress version with the same database structure (%s)', ' ('.$this->last_error.', '.$this->last_error_no.')');
			}
		}

		$restoring_table = '';

		$this->max_allowed_packet = $updraftplus->get_max_packet_size();

		$updraftplus->log("Entering maintenance mode");
		$this->maintenance_mode(true);

		// N.B. There is no such function as bzeof() - we have to detect that another way
		while (($is_plain && !feof($dbhandle)) || (!$is_plain && (($is_bz2) || (!$is_bz2 && !gzeof($dbhandle))))) {
			// Up to 1Mb
			if ($is_plain) {
				$buffer = rtrim(fgets($dbhandle, 1048576));
			} elseif ($is_bz2) {
				if (!isset($bz2_buffer)) $bz2_buffer = '';
				$buffer = '';
				if (strlen($bz2_buffer) < 524288) $bz2_buffer .= bzread($dbhandle, 1048576);
				if (bzerrno($dbhandle) !== 0) {
					$updraftplus->log("bz2 error: ".bzerrstr($dbhandle)." (code: ".bzerrno($bzhandle).")");
					break;
				}
				if (false !== $bz2_buffer && '' !== $bz2_buffer) {
					if (false !== ($p = strpos($bz2_buffer, "\n"))) {
						$buffer .= substr($bz2_buffer, 0, $p+1);
						$bz2_buffer = substr($bz2_buffer, $p+1);
					} else {
						$buffer .= $bz2_buffer;
						$bz2_buffer = '';
					}
				} else {
					break;
				}
				$buffer = rtrim($buffer);
			} else {
				$buffer = rtrim(gzgets($dbhandle, 1048576));
			}

			// Discard comments
			if (empty($buffer) || substr($buffer, 0, 1) == '#' || preg_match('/^--(\s|$)/', substr($buffer, 0, 3))) {
				if ('' == $this->old_siteurl && preg_match('/^\# Backup of: (http(.*))$/', $buffer, $matches)) {
					$this->old_siteurl = untrailingslashit($matches[1]);
					$updraftplus->log_e('<strong>Backup of:</strong> %s', htmlspecialchars($this->old_siteurl));
					do_action('updraftplus_restore_db_record_old_siteurl', $this->old_siteurl);

					$this->save_configuration_bundle();

				} elseif (false === $this->created_by_version && preg_match('/^\# Created by UpdraftPlus version ([\d\.]+)/', $buffer, $matches)) {
					$this->created_by_version = trim($matches[1]);
					echo '<strong>'.__('Backup created by:', 'updraftplus').'</strong> '.htmlspecialchars($this->created_by_version).'<br>';
					$updraftplus->log('Backup created by: '.$this->created_by_version);
				} elseif ('' == $this->old_home && preg_match('/^\# Home URL: (http(.*))$/', $buffer, $matches)) {
					$this->old_home = untrailingslashit($matches[1]);
					if ($this->old_siteurl && $this->old_home != $this->old_siteurl) {
						echo '<strong>'.__('Site home:', 'updraftplus').'</strong> '.htmlspecialchars($this->old_home).'<br>';
						$updraftplus->log('Site home: '.$this->old_home);
					}
					do_action('updraftplus_restore_db_record_old_home', $this->old_home);
				} elseif ('' == $this->old_content && preg_match('/^\# Content URL: (http(.*))$/', $buffer, $matches)) {
					$this->old_content = untrailingslashit($matches[1]);
					echo '<strong>'.__('Content URL:', 'updraftplus').'</strong> '.htmlspecialchars($this->old_content).'<br>';
					$updraftplus->log('Content URL: '.$this->old_content);
					do_action('updraftplus_restore_db_record_old_content', $this->old_content);
				} elseif ('' == $this->old_uploads && preg_match('/^\# Uploads URL: (http(.*))$/', $buffer, $matches)) {
					$this->old_uploads = untrailingslashit($matches[1]);
					echo '<strong>'.__('Uploads URL:', 'updraftplus').'</strong> '.htmlspecialchars($this->old_uploads).'<br>';
					$updraftplus->log('Uploads URL: '.$this->old_uploads);
					do_action('updraftplus_restore_db_record_old_uploads', $this->old_uploads);
				} elseif ('' == $this->old_table_prefix && (preg_match('/^\# Table prefix: (\S+)$/', $buffer, $matches) || preg_match('/^-- Table Prefix: (\S+)$/i', $buffer, $matches))) {
					# We also support backwpup style:
					# -- Table Prefix: wp_
					$this->old_table_prefix = $matches[1];
					echo '<strong>'.__('Old table prefix:', 'updraftplus').'</strong> '.htmlspecialchars($this->old_table_prefix).'<br>';
					$updraftplus->log("Old table prefix: ".$this->old_table_prefix);
				} elseif ($gathering_siteinfo && preg_match('/^\# Site info: (\S+)$/', $buffer, $matches)) {
					if ('end' == $matches[1]) {
						$gathering_siteinfo = false;
						// Sanity checks
						if (isset($old_siteinfo['multisite']) && !$old_siteinfo['multisite'] && is_multisite()) {
							// Just need to check that you're crazy
							//if (!defined('UPDRAFTPLUS_EXPERIMENTAL_IMPORTINTOMULTISITE') ||  UPDRAFTPLUS_EXPERIMENTAL_IMPORTINTOMULTISITE != true) {
							//	return new WP_Error('multisite_error', $this->strings['multisite_error']);
							//}
							// Got the needed code?
							if (!class_exists('UpdraftPlusAddOn_MultiSite') || !class_exists('UpdraftPlus_Addons_Migrator')) {
								return new WP_Error('missing_addons', sprintf(__('To import an ordinary WordPress site into a multisite installation requires %s.', 'updraftplus'), 'UpdraftPlus Premium'));	
							}
						}
					} elseif (preg_match('/^([^=]+)=(.*)$/', $matches[1], $kvmatches)) {
						$key = $kvmatches[1];
						$val = $kvmatches[2];
						echo '<strong>'.__('Site information:','updraftplus').'</strong>'.' '.htmlspecialchars($key).' = '.htmlspecialchars($val).'<br>';
						$updraftplus->log("Site information: ".$key."=".$val);
						$old_siteinfo[$key]=$val;
						if ('multisite' == $key) {
							$this->ud_backup_is_multisite = ($val) ? 1 : 0;
						}
					}
				}
				continue;
			}
			
			// Detect INSERT commands early, so that we can split them if necessary
			if (preg_match('/^\s*(insert into \`?([^\`]*)\`?\s+(values|\())/i', $sql_line.$buffer, $matches)) {
				$this->table_name = $matches[2];
				$sql_type = 3;
				$insert_prefix = $matches[1];
			}

			// Deal with case where adding this line will take us over the MySQL max_allowed_packet limit - must split, if we can (if it looks like consecutive rows)
			// Allow a 100-byte margin for error (including searching/replacing table prefix)
			if (3 == $sql_type && $sql_line && strlen($sql_line.$buffer) > ($this->max_allowed_packet - 100) && preg_match('/,\s*$/', $sql_line) && preg_match('/^\s*\(/', $buffer)) {
				// Remove the final comma; replace with semi-colon
				$sql_line = substr(rtrim($sql_line), 0, strlen($sql_line)-1).';';
				if ('' != $this->old_table_prefix && $import_table_prefix != $this->old_table_prefix) $sql_line = $updraftplus->str_replace_once($this->old_table_prefix, $import_table_prefix, $sql_line);
				# Run the SQL command; then set up for the next one.
				$this->line++;
				echo __("Split line to avoid exceeding maximum packet size", 'updraftplus')." (".strlen($sql_line)." + ".strlen($buffer)." : ".$this->max_allowed_packet.")<br>";
				$updraftplus->log("Split line to avoid exceeding maximum packet size (".strlen($sql_line)." + ".strlen($buffer)." : ".$this->max_allowed_packet.")");
				$do_exec = $this->sql_exec($sql_line, $sql_type, $import_table_prefix);
				if (is_wp_error($do_exec)) return $do_exec;
				# Reset, then carry on
				$sql_line = $insert_prefix." ";
			}

			$sql_line .= $buffer;
			# Do we have a complete line yet? We used to just test the final character for ';' here (up to 1.8.12), but that was too unsophisticated
			if (
				(3 == $sql_type && !preg_match('/\)\s*;$/', substr($sql_line, -3, 3)))
				|| (3 != $sql_type && ';' != substr($sql_line, -1, 1))
			) continue;

			$this->line++;

			# We now have a complete line - process it

			if (3 == $sql_type && $sql_line && strlen($sql_line) > $this->max_allowed_packet) {
				$this->log_oversized_packet($sql_line);
				# Reset
				$sql_line = '';
				$sql_type = -1;
				# If this is the very first SQL line of the options table, we need to bail; it's essential
				if (0 == $this->insert_statements_run && $restoring_table && $restoring_table == $import_table_prefix.'options') {
					$updraftplus->log("Leaving maintenance mode");
					$this->maintenance_mode(false);
					return new WP_Error('initial_db_error', sprintf(__('An error occurred on the first %s command - aborting run', 'updraftplus'), 'INSERT (options)'));
				}
				continue;
			}

			// The timed overhead of this is negligible
			if (preg_match('/^\s*drop table (if exists )?\`?([^\`]*)\`?\s*;/i', $sql_line, $matches)) {
				$sql_type = 1;

				if (!isset($printed_new_table_prefix)) {
					$import_table_prefix = $this->pre_sql_actions($import_table_prefix);
					if (false===$import_table_prefix || is_wp_error($import_table_prefix)) return $import_table_prefix;
					$printed_new_table_prefix = true;
				}

				$this->table_name = $matches[2];
				
				// Legacy, less reliable - in case it was not caught before
				if ('' == $this->old_table_prefix && preg_match('/^([a-z0-9]+)_.*$/i', $this->table_name, $tmatches)) {
					$this->old_table_prefix = $tmatches[1].'_';
					echo '<strong>'.__('Old table prefix:', 'updraftplus').'</strong> '.htmlspecialchars($this->old_table_prefix).'<br>';
					$updraftplus->log("Old table prefix (detected from first table): ".$this->old_table_prefix);
				}

				$this->new_table_name = ($this->old_table_prefix) ? $updraftplus->str_replace_once($this->old_table_prefix, $import_table_prefix, $this->table_name) : $this->table_name;

				if ('' != $this->old_table_prefix && $import_table_prefix != $this->old_table_prefix) {
					$sql_line = $updraftplus->str_replace_once($this->old_table_prefix, $import_table_prefix, $sql_line);
				}
				
				if (empty($matches[1])) {
					// Seen with some foreign backups
					$sql_line = preg_replace('/drop table/i', 'drop table if exists', $sql_line, 1);
				}
				
				$this->tables_been_dropped[] = $this->new_table_name;

			} elseif (preg_match('/^\s*create table \`?([^\`\(]*)\`?\s*\(/i', $sql_line, $matches)) {

				$sql_type = 2;
				$this->insert_statements_run = 0;
				$this->table_name = $matches[1];

				// Legacy, less reliable - in case it was not caught before. We added it in here (CREATE) as well as in DROP because of SQL dumps which lack DROP statements.
				if ('' == $this->old_table_prefix && preg_match('/^([a-z0-9]+)_.*$/i', $this->table_name, $tmatches)) {
					$this->old_table_prefix = $tmatches[1].'_';
					echo '<strong>'.__('Old table prefix:', 'updraftplus').'</strong> '.htmlspecialchars($this->old_table_prefix).'<br>';
					$updraftplus->log("Old table prefix (detected from creating first table): ".$this->old_table_prefix);
				}

				// MySQL 4.1 outputs TYPE=, but accepts ENGINE=; 5.1 onwards accept *only* ENGINE=
				$sql_line = $updraftplus->str_lreplace('TYPE=', 'ENGINE=', $sql_line);

				if (empty($printed_new_table_prefix)) {
					$import_table_prefix = $this->pre_sql_actions($import_table_prefix);
					if (false === $import_table_prefix || is_wp_error($import_table_prefix)) return $import_table_prefix;
					$printed_new_table_prefix = true;
				}

				$this->new_table_name = ($this->old_table_prefix) ? $updraftplus->str_replace_once($this->old_table_prefix, $import_table_prefix, $this->table_name) : $this->table_name;

				// This CREATE TABLE command may be the de-facto mark for the end of processing a previous table (which is so if this is not the first table in the SQL dump)
				if ($restoring_table) {

					# Attempt to reconnect if the DB connection dropped (may not succeed, of course - but that will soon become evident)
					$updraftplus->check_db_connection($this->wpdb_obj);

					// After restoring the options table, we can set old_siteurl if on legacy (i.e. not already set)
					if ($restoring_table == $import_table_prefix.'options') {
						if ('' == $this->old_siteurl || '' == $this->old_home || '' == $this->old_content) {
							global $updraftplus_addons_migrator;
							if (!empty($updraftplus_addons_migrator->new_blogid)) switch_to_blog($updraftplus_addons_migrator->new_blogid);

							if ('' == $this->old_siteurl) {
								$this->old_siteurl = untrailingslashit($wpdb->get_row("SELECT option_value FROM $wpdb->options WHERE option_name='siteurl'")->option_value);
								do_action('updraftplus_restore_db_record_old_siteurl', $this->old_siteurl);
							}
							if ('' == $this->old_home) {
								$this->old_home = untrailingslashit($wpdb->get_row("SELECT option_value FROM $wpdb->options WHERE option_name='home'")->option_value);
								do_action('updraftplus_restore_db_record_old_home', $this->old_home);
							}
							if ('' == $this->old_content) {
								$this->old_content = $this->old_siteurl.'/wp-content';
								do_action('updraftplus_restore_db_record_old_content', $this->old_content);
							}
							if (!empty($updraftplus_addons_migrator->new_blogid)) restore_current_blog();
						}
					}
					
					if ($restoring_table != $this->new_table_name) $this->restored_table($restoring_table, $import_table_prefix, $this->old_table_prefix);

				}

				$engine = "(?)"; $engine_change_message = '';
				if (preg_match('/ENGINE=([^\s;]+)/', $sql_line, $eng_match)) {
					$engine = $eng_match[1];
					if (isset($supported_engines[$engine])) {
						#echo sprintf(__('Requested table engine (%s) is present.', 'updraftplus'), $engine);
						if ('myisam' == strtolower($engine)) {
							$sql_line = preg_replace('/PAGE_CHECKSUM=\d\s?/', '', $sql_line, 1);
						}
					} else {
						$engine_change_message = sprintf(__('Requested table engine (%s) is not present - changing to MyISAM.', 'updraftplus'), $engine)."<br>";
						$sql_line = $updraftplus->str_lreplace("ENGINE=$eng_match", "ENGINE=MyISAM", $sql_line);
						// Remove (M)aria options
						if ('maria' == strtolower($engine) || 'aria' == strtolower($engine)) {
							$sql_line = preg_replace('/PAGE_CHECKSUM=\d\s?/', '', $sql_line, 1);
							$sql_line = preg_replace('/TRANSACTIONAL=\d\s?/', '', $sql_line, 1);
						}
					}
				}

				echo '<strong>'.sprintf(__('Restoring table (%s)','updraftplus'), $engine).":</strong> ".htmlspecialchars($this->table_name);
				$logline = "Restoring table ($engine): ".$this->table_name;
				if ('' != $this->old_table_prefix && $import_table_prefix != $this->old_table_prefix) {
					if (!isset($this->restore_this_table[$this->table_name]) || $this->restore_this_table[$this->table_name]) {
						echo ' - '.__('will restore as:', 'updraftplus').' '.htmlspecialchars($this->new_table_name);
						$logline .= " - will restore as: ".$this->new_table_name;
					} else {
						$logline .= ' - skipping';
					}
					$sql_line = $updraftplus->str_replace_once($this->old_table_prefix, $import_table_prefix, $sql_line);
				}
				$updraftplus->log($logline);
				$restoring_table = $this->new_table_name;
				echo '<br>';
				if ($engine_change_message) echo $engine_change_message;

			} elseif (preg_match('/^\s*(insert into \`?([^\`]*)\`?\s+(values|\())/i', $sql_line, $matches)) {
				$sql_type = 3;
				$this->table_name = $matches[2];
				if ('' != $this->old_table_prefix && $import_table_prefix != $this->old_table_prefix) $sql_line = $updraftplus->str_replace_once($this->old_table_prefix, $import_table_prefix, $sql_line);
			} elseif (preg_match('/^\s*(\/\*\!40000 )?(alter|lock) tables? \`?([^\`\(]*)\`?\s+(write|disable|enable)/i', $sql_line, $matches)) {
				# Only binary mysqldump produces this pattern (LOCK TABLES `table` WRITE, ALTER TABLE `table` (DISABLE|ENABLE) KEYS)
				$sql_type = 4;
				if ('' != $this->old_table_prefix && $import_table_prefix != $this->old_table_prefix) $sql_line = $updraftplus->str_replace_once($this->old_table_prefix, $import_table_prefix, $sql_line);
			} elseif (preg_match('/^(un)?lock tables/i', $sql_line)) {
				# BackWPup produces these
				$sql_type = 5;
			} elseif (preg_match('/^(create|drop) database /i', $sql_line)) {
				# WPB2D produces these, as do some phpMyAdmin dumps
				$sql_type = 6;
			} elseif (preg_match('/^use /i', $sql_line)) {
				# WPB2D produces these, as do some phpMyAdmin dumps
				$sql_type = 7;
			} elseif (preg_match('#/\*\!40\d+ SET NAMES (.*)\*\/#', $sql_line, $smatches)) {
				$sql_type = 8;
				$this->set_names = rtrim($smatches[1]);
			} else {
				# Prevent the previous value of $sql_type being retained for an unknown type
				$sql_type = 0;
			}
			
// 			if (5 !== $sql_type) {
			if ($sql_type != 6 && $sql_type != 7) {
				$do_exec = $this->sql_exec($sql_line, $sql_type);
				if (is_wp_error($do_exec)) return $do_exec;
			} else {
				$updraftplus->log("Skipped SQL statement (unwanted type=$sql_type): $sql_line");
			}

			# Reset
			$sql_line = '';
			$sql_type = -1;

		}

		if (!empty($this->lock_forbidden)) {
			$updraftplus->log("Leaving maintenance mode");
		} else {
			$updraftplus->log("Unlocking database and leaving maintenance mode");
			$this->unlock_tables();
		}
		$this->maintenance_mode(false);

		if ($restoring_table) $this->restored_table($restoring_table, $import_table_prefix, $this->old_table_prefix);

		$time_taken = microtime(true) - $this->start_time;
		$updraftplus->log_e('Finished: lines processed: %d in %.2f seconds', $this->line, $time_taken);
		if ($is_plain) {
			fclose($dbhandle);
		} elseif ($is_bz2) {
			bzclose($dbhandle);
		} else {
			gzclose($dbhandle);
		}

		global $wp_filesystem;

		$wp_filesystem->delete($working_dir.'/'.$db_basename, false, 'f');
		return true;

	}

	private function lock_table($table) {
	
		// Not yet working
		return true;
	
		global $updraftplus;
		$table = $updraftplus->backquote($table);
		
		if ($this->use_wpdb) {
			$req = $wpdb->query("LOCK TABLES $table WRITE;");
		} else {
			if ($this->use_mysqli) {
				$req = mysqli_query($this->mysql_dbh, "LOCK TABLES $table WRITE;");
			} else {
				$req = mysql_unbuffered_query("LOCK TABLES $table WRITE;", $this->mysql_dbh);
			}
			if (!$req) {
				$lock_error_no = $this->use_mysqli ? mysqli_errno($this->mysql_dbh) : mysql_errno($this->mysql_dbh);
			}
		}
		if (!$req && ($this->use_wpdb || $lock_error_no === 1142)) {
			// Permission denied
			return 1142;
		}
		return true;
	}
	
	public function unlock_tables() {
		return;
		// Not yet working
		if ($this->use_wpdb) {
			$$wpdb->query("UNLOCK TABLES;");
		} elseif ($this->use_mysqli) {
			$req = mysqli_query($this->mysql_dbh, "UNLOCK TABLES;");
		} else {
			$req = mysql_unbuffered_query("UNLOCK TABLES;");
		}
	}
	
	// Save configuration bundle, ready to restore it once the options table has been restored
	private function save_configuration_bundle() {
		$this->configuration_bundle = array();
		// Some items must always be saved + restored; others only on a migration
		// Remember, if modifying this, that a restoration can include restoring a destroyed site from a backup onto a fresh WP install on the same URL. So, it is not necessarily desirable to retain the current settings and drop the ones in the backup.
		$keys_to_save = array('updraft_remotesites', 'updraft_migrator_localkeys', 'updraft_central_localkeys');

		if ($this->old_siteurl != $this->our_siteurl) {
			global $updraftplus;
			$keys_to_save = array_merge($keys_to_save, $updraftplus->get_settings_keys());
			$keys_to_save[] = 'updraft_backup_history';
		}

		foreach ($keys_to_save as $key) {
			$this->configuration_bundle[$key] = UpdraftPlus_Options::get_updraft_option($key);
		}
	}

	// The table here is just for logging/info. The actual restoration itself is done via the standard options class.
	private function restore_configuration_bundle($table) {
		if (!is_array($this->configuration_bundle)) return;
		global $updraftplus;
		$updraftplus->log("Restoring prior UD configuration (table: $table; keys: ".count($this->configuration_bundle).")");
		foreach ($this->configuration_bundle as $key => $value) {
			UpdraftPlus_Options::delete_updraft_option($key, $value);
			UpdraftPlus_Options::update_updraft_option($key, $value);
		}
	}

	private function log_oversized_packet($sql_line) {
		global $updraftplus;
		$logit = substr($sql_line, 0, 100);
		$updraftplus->log(sprintf("An SQL line that is larger than the maximum packet size and cannot be split was found: %s", '('.strlen($sql_line).', '.$logit.' ...)'));
		echo '<strong>'.__('Warning:', 'updraftplus').'</strong> '.sprintf(__("An SQL line that is larger than the maximum packet size and cannot be split was found; this line will not be processed, but will be dropped: %s", 'updraftplus'), '('.strlen($sql_line).', '.$this->max_allowed_packet.', '.$logit.' ...)')."<br>";
	}

	# UPDATE is sql_type=5 (not used in the function, but used in Migrator and so noted here for reference)
	# $import_table_prefix is only use in one place in this function (long INSERTs), and otherwise need/should not be supplied
	public function sql_exec($sql_line, $sql_type, $import_table_prefix = '', $check_skipping = true) {

		global $wpdb, $updraftplus;

		if ($check_skipping && !empty($this->table_name)) {
			if (!isset($this->restore_this_table[$this->table_name])) {
				// Valid values: true (= yes, restore it); false (= no, don't restore it, and log the fact); 0 (= don't restore, and don't log)
				$this->restore_this_table[$this->table_name] = apply_filters('updraftplus_restore_this_table', true, substr($this->table_name, strlen($this->old_table_prefix)), $this->ud_restore_options);
			}
			if (false === $this->restore_this_table[$this->table_name]) {
				$updraftplus->log_e('Skipping table %s: this table will not be restored', $this->table_name);
				$this->restore_this_table[$this->table_name] = 0;
			}
			if (!$this->restore_this_table[$this->table_name]) return;
		}
		
		$ignore_errors = false;
		# Type 2 = CREATE TABLE
		if (2 == $sql_type && $this->create_forbidden) {
			$updraftplus->log_e('Cannot create new tables, so skipping this command (%s)', htmlspecialchars($sql_line));
			$req = true;
		} else {

			if (2 == $sql_type && !$this->drop_forbidden) {
				# We choose, for now, to be very conservative - we only do the apparently-missing drop if we have never seen any drop - i.e. assume that in SQL dumps with missing DROPs, that it's because there are no DROPs at all
				if (!in_array($this->new_table_name, $this->tables_been_dropped)) {
					$updraftplus->log_e('Table to be implicitly dropped: %s', $this->new_table_name);
					$this->sql_exec('DROP TABLE IF EXISTS '.esc_sql($this->new_table_name), 1, '', false);
					$this->tables_been_dropped[] = $this->new_table_name;
				}
			}

			// Type 1 = DROP TABLE
			if (1 == $sql_type) {
				if ($this->drop_forbidden) {
					$sql_line = "DELETE FROM ".$updraftplus->backquote($this->new_table_name);
					$updraftplus->log_e('Cannot drop tables, so deleting instead (%s)', $sql_line);
					$ignore_errors = true;
				}
			}

			if (3 == $sql_type && $sql_line && strlen($sql_line) > $this->max_allowed_packet) {
				$this->log_oversized_packet($sql_line);
				# If this is the very first SQL line of the options table, we need to bail; it's essential
				$this->errors++;
				if (0 == $this->insert_statements_run && $this->new_table_name && $this->new_table_name == $import_table_prefix.'options') {
					$updraftplus->log("Leaving maintenance mode");
					$this->maintenance_mode(false);
					return new WP_Error('initial_db_error', sprintf(__('An error occurred on the first %s command - aborting run','updraftplus'), 'INSERT (options)'));
				}
				return false;
			}

			if ($this->use_wpdb) {
				$req = $wpdb->query($sql_line);
				// WPDB, for several query types, returns the number of rows changed; in distinction from an error, indicated by (bool)false
				if (0 === $req) { $req = true; }
				if (!$req) $this->last_error = $wpdb->last_error;
			} else {
				if ($this->use_mysqli) {
					$req = mysqli_query($this->mysql_dbh, $sql_line);
					if (!$req) $this->last_error = mysqli_error($this->mysql_dbh);
				} else {
					$req = mysql_unbuffered_query($sql_line, $this->mysql_dbh);
					if (!$req) $this->last_error = mysql_error($this->mysql_dbh);
				}
			}
			if (3 == $sql_type) $this->insert_statements_run++;
			if (1 == $sql_type) $this->tables_been_dropped[] = $this->new_table_name;
			$this->statements_run++;
		}

		if (!$req) {
			if (!$ignore_errors) $this->errors++;
			$print_err = (strlen($sql_line) > 100) ? substr($sql_line, 0, 100).' ...' : $sql_line;
			echo sprintf(_x('An error (%s) occurred:', 'The user is being told the number of times an error has happened, e.g. An error (27) occurred', 'updraftplus'), $this->errors)." - ".htmlspecialchars($this->last_error)." - ".__('the database query being run was:','updraftplus').' '.htmlspecialchars($print_err).'<br>';
			$updraftplus->log("An error (".$this->errors.") occurred: ".$this->last_error." - SQL query was (type=$sql_type): ".substr($sql_line, 0, 65536));

			// First command is expected to be DROP TABLE
			if (1 == $this->errors && 2 == $sql_type && 0 == $this->tables_created) {
				if ($this->drop_forbidden) {
					$updraftplus->log_e("Create table failed - probably because there is no permission to drop tables and the table already exists; will continue");
				} else {
					$updraftplus->log("Leaving maintenance mode");
					$this->maintenance_mode(false);
					return new WP_Error('initial_db_error', sprintf(__('An error occurred on the first %s command - aborting run','updraftplus'), 'CREATE TABLE'));
				}
			} elseif (2 == $sql_type && 0 == $this->tables_created && $this->drop_forbidden) {
				// Decrease error counter again; otherwise, we'll cease if there are >=50 tables
				if (!$ignore_errors) $this->errors--;
			} elseif (8 == $sql_type && 1 == $this->errors) {
				$updraftplus->log("Aborted: SET NAMES ".$this->set_names." failed: maintenance mode");
				$this->maintenance_mode(false);
				$extra_msg = '';
				$dbv = $wpdb->db_version();
				if (strtolower($this->set_names) == 'utf8mb4' && $dbv && version_compare($dbv, '5.2.0', '<=')) {
					$extra_msg = ' '.__('This problem is caused by trying to restore a database on a very old MySQL version that is incompatible with the source database.', 'updraftplus').' '.sprintf(__('This database needs to be deployed on MySQL version %s or later.', 'updraftplus'), '5.5');
				}
				return new WP_Error('initial_db_error', sprintf(__('An error occurred on the first %s command - aborting run','updraftplus'), 'SET NAMES').'. '.sprintf(__('To use this backup, your database server needs to support the %s character set.', 'updraftplus'), $this->set_names).$extra_msg);
			}
			
			if ($this->errors > 49) {
				$this->maintenance_mode(false);
				return new WP_Error('too_many_db_errors', __('Too many database errors have occurred - aborting','updraftplus'));
			}
		} elseif ($sql_type == 2) {
			if (!$this->lock_forbidden) $this->lock_table($this->new_table_name);
			$this->tables_created++;
		}

		if ($this->line >0 && ($this->line)%50 == 0) {
			if ($this->line > $this->line_last_logged && (($this->line)%250 == 0 || $this->line < 250)) {
				$this->line_last_logged = $this->line;
				$time_taken = microtime(true) - $this->start_time;
				$updraftplus->log_e('Database queries processed: %d in %.2f seconds',$this->line, $time_taken);
			}
		}
		return $req;
	}

// 	function option_filter($which) {
// 		if (strpos($which, 'pre_option') !== false) { echo "OPT_FILT: $which<br>\n"; }
// 		return false;
// 	}

	private function flush_rewrite_rules() {

		// We have to deal with the fact that the procedures used call get_option, which could be looking at the wrong table prefix, or have the wrong thing cached

		global $updraftplus_addons_migrator;
		if (!empty($updraftplus_addons_migrator->new_blogid)) switch_to_blog($updraftplus_addons_migrator->new_blogid);

		foreach (array('permalink_structure', 'rewrite_rules', 'page_on_front') as $opt) {
			add_filter('pre_option_'.$opt, array($this, 'option_filter_'.$opt));
		}

		global $wp_rewrite;
		$wp_rewrite->init();
		// Don't do this: it will cause rules created by plugins that weren't active at the start of the restore run to be lost
		# flush_rewrite_rules(true);

		if ( function_exists( 'save_mod_rewrite_rules' ) ) save_mod_rewrite_rules();
		if ( function_exists( 'iis7_save_url_rewrite_rules' ) ) iis7_save_url_rewrite_rules();

		foreach (array('permalink_structure', 'rewrite_rules', 'page_on_front') as $opt) {
			remove_filter('pre_option_'.$opt, array($this, 'option_filter_'.$opt));
		}

		if (!empty($updraftplus_addons_migrator->new_blogid)) restore_current_blog();

	}

	private function restored_table($table, $import_table_prefix, $old_table_prefix) {

		$table_without_prefix = substr($table, strlen($import_table_prefix));
	
		if (isset($this->restore_this_table[$old_table_prefix.$table_without_prefix]) && !$this->restore_this_table[$old_table_prefix.$table_without_prefix]) return;

		global $wpdb, $updraftplus;
		
		if ($table == $import_table_prefix.UpdraftPlus_Options::options_table()) $this->restore_configuration_bundle($table);

		if (preg_match('/^([\d+]_)?options$/', substr($table, strlen($import_table_prefix)), $matches)) {
			// The second prefix here used to have a '!$this->is_multisite' on it (i.e. 'options' table on non-multisite). However, the user_roles entry exists in the main options table on multisite too.
			if (($this->is_multisite && !empty($matches[1])) || $table == $import_table_prefix.'options') {

				$mprefix = (empty($matches[1])) ? '' : $matches[1];

				$new_table_name = $import_table_prefix.$mprefix."options";

				// WordPress has an option name predicated upon the table prefix. Yuk.
				if ($import_table_prefix != $old_table_prefix) {
					$updraftplus->log("Table prefix has changed: changing options table field(s) accordingly (".$mprefix."options)");
					echo sprintf(__('Table prefix has changed: changing %s table field(s) accordingly:', 'updraftplus'),'option').' ';
					if (false === $wpdb->query("UPDATE $new_table_name SET option_name='${import_table_prefix}".$mprefix."user_roles' WHERE option_name='${old_table_prefix}".$mprefix."user_roles' LIMIT 1")) {
						echo __('Error','updraftplus');
						$updraftplus->log("Error when changing options table fields: ".$wpdb->last_error);
					} else {
						$updraftplus->log("Options table fields changed OK");
						echo __('OK', 'updraftplus');
					}
					echo '<br>';
				}

				// Now deal with the situation where the imported database sets a new over-ride upload_path that is absolute - which may not be wanted
				$new_upload_path = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM ${import_table_prefix}".$mprefix."options WHERE option_name = %s LIMIT 1", 'upload_path'));
				$new_upload_path = (is_object($new_upload_path)) ? $new_upload_path->option_value : '';
				// The danger situation is absolute and points somewhere that is now perhaps not accessible at all

				if (!empty($new_upload_path) && $new_upload_path != $this->prior_upload_path && (strpos($new_upload_path, '/') === 0) || preg_match('#^[A-Za-z]:[/\\\]#', $new_upload_path)) {

					// $this->old_siteurl != untrailingslashit(site_url()) is not a perfect proxy for "is a migration" (other possibilities exist), but since the upload_path option should not exist since WP 3.5 anyway, the chances of other possibilities are vanishingly small
					if (!file_exists($new_upload_path) || $this->old_siteurl != $this->our_siteurl) {

						if (!file_exists($new_upload_path)) {
							$updraftplus->log_e("Uploads path (%s) does not exist - resetting (%s)", $new_upload_path, $this->prior_upload_path);
						} else {
							$updraftplus->log_e("Uploads path (%s) has changed during a migration - resetting (to: %s)", $new_upload_path, $this->prior_upload_path);
						}
						if (false === $wpdb->query("UPDATE ${import_table_prefix}".$mprefix."options SET option_value='".esc_sql($this->prior_upload_path)."' WHERE option_name='upload_path' LIMIT 1")) {
							echo __('Error','updraftplus');
							$updraftplus->log("Error when changing upload path: ".$wpdb->last_error);
							$updraftplus->log("Failed");
						}
						#update_option('upload_path', $this->prior_upload_path);
					}
				}

				# TODO:Do on all WPMU tables
				if ($table == $import_table_prefix.'options') {
					# Bad plugin that hard-codes path references - https://wordpress.org/plugins/custom-content-type-manager/
					$cctm_data = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $new_table_name WHERE option_name = %s LIMIT 1", 'cctm_data'));
					if (!empty($cctm_data->option_value)) {
						$cctm_data = maybe_unserialize($cctm_data->option_value);
						if (is_array($cctm_data) && !empty($cctm_data['cache']) && is_array($cctm_data['cache'])) {
							$cctm_data['cache'] = array();
							$updraftplus->log_e("Custom content type manager plugin data detected: clearing option cache");
							update_option('cctm_data', $cctm_data);
						}
					}
					# Another - http://www.elegantthemes.com/gallery/elegant-builder/
					$elegant_data = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $new_table_name WHERE option_name = %s LIMIT 1", 'et_images_temp_folder'));
					if (!empty($elegant_data->option_value)) {
						$dbase = basename($elegant_data->option_value);
						$wp_upload_dir = wp_upload_dir();
						$edir = $wp_upload_dir['basedir'];
						if (!is_dir($edir.'/'.$dbase)) @mkdir($edir.'/'.$dbase);
						$updraftplus->log_e("Elegant themes theme builder plugin data detected: resetting temporary folder");
						update_option('et_images_temp_folder', $edir.'/'.$dbase);
					}
				}

				# The gantry menu plugin sometimes uses too-long transient names, causing the timeout option to be missing; and hence the transient becomes permanent.
				# WP 3.4 onwards has $wpdb->delete(). But we support 3.2 onwards.
				$wpdb->query("DELETE FROM $new_table_name WHERE option_name LIKE '_transient_gantry-menu%' OR option_name LIKE '_transient_timeout_gantry-menu%'");

				# Jetpack: see: https://wordpress.org/support/topic/issues-with-dev-site
				if ($this->old_siteurl != $this->our_siteurl) {
					$wpdb->query("DELETE FROM $new_table_name WHERE option_name = 'jetpack_options'");
				}

			}

		} elseif ($import_table_prefix != $old_table_prefix && preg_match('/^([\d+]_)?usermeta$/', substr($table, strlen($import_table_prefix)), $matches)) {

			// This table is not a per-site table, but per-install

			$updraftplus->log("Table prefix has changed: changing usermeta table field(s) accordingly");
			echo sprintf(__('Table prefix has changed: changing %s table field(s) accordingly:', 'updraftplus'),'usermeta').' ';

			$errors_occurred = false;

			if (false === strpos($old_table_prefix, '_')) {
				// Old, slow way: do it row-by-row
				// By Jul 2015, doing this on the updraftplus.com database took 20 minutes on a slow test machine
				$old_prefix_length = strlen($old_table_prefix);

				$um_sql = "SELECT umeta_id, meta_key 
					FROM ${import_table_prefix}usermeta 
					WHERE meta_key 
					LIKE '".str_replace('_', '\_', $old_table_prefix)."%'";
				$meta_keys = $wpdb->get_results($um_sql);

				foreach ($meta_keys as $meta_key ) {
					//Create new meta key
					$new_meta_key = $import_table_prefix . substr($meta_key->meta_key, $old_prefix_length);
					
					$query = "UPDATE " . $import_table_prefix . "usermeta 
						SET meta_key='".$new_meta_key."' 
						WHERE umeta_id=".$meta_key->umeta_id;

					if (false === $wpdb->query($query)) $errors_occurred = true;
				}
			} else {
				// New, fast way: do it in a single query
				$sql = "UPDATE ${import_table_prefix}usermeta SET meta_key = REPLACE(meta_key, '$old_table_prefix', '${import_table_prefix}') WHERE meta_key LIKE '".str_replace('_', '\_', $old_table_prefix)."%';";
				if (false === $wpdb->query($sql)) $errors_occurred = true;
			}

			if ($errors_occurred) {
				$updraftplus->log("Error when changing usermeta table fields");
				echo __('Error', 'updraftplus');
			} else {
				$updraftplus->log("Usermeta table fields changed OK");
				echo __('OK', 'updraftplus');
			}
			echo "<br>";

		}

		do_action('updraftplus_restored_db_table', $table, $import_table_prefix);

		// Re-generate permalinks. Do this last - i.e. make sure everything else is fixed up first.
		if ($table == $import_table_prefix.'options') $this->flush_rewrite_rules();

	}

}

// The purpose of this is that, in a certain case, we want to forbid the "move" operation from doing a copy/delete if a direct move fails... because we have our own method for retrying (and don't want to risk copying a tonne of data if we can avoid it)
if (!class_exists('WP_Filesystem_Direct')) {
	if (!class_exists('WP_Filesystem_Base')) require_once(ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php');
	require_once(ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php');
}
class UpdraftPlus_WP_Filesystem_Direct extends WP_Filesystem_Direct {

	public function move($source, $destination, $overwrite = false) {
		if ( ! $overwrite && $this->exists($destination) )
			return false;

		// try using rename first. if that fails (for example, source is read only) try copy
		if ( @rename($source, $destination) )
			return true;

		return false;
	}

}

if (!class_exists('WP_Upgrader_Skin')) require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
class Updraft_Restorer_Skin extends WP_Upgrader_Skin {

	public function header() {}
	public function footer() {}
	public function bulk_header() {}
	public function bulk_footer() {}

	public function error($error) {
		if (!$error) return;
		global $updraftplus;
		if (is_wp_error($error)) {
			$updraftplus->log_wp_error($error, true);
		} elseif (is_string($error)) {
			echo '<strong>';
			$updraftplus->log_e($error);
			echo '</strong>';
		}
	}

	public function feedback($string) {

		if ( isset( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if ( $args ) {
				$args = array_map( 'strip_tags', $args );
				$args = array_map( 'esc_html', $args );
				$string = vsprintf($string, $args);
			}
		}
		if ( empty($string) ) return;

		global $updraftplus;
		$updraftplus->log_e($string);
	}
}

// Get a protected property
class UpdraftPlus_WPDB extends wpdb {
	public function updraftplus_getdbh() {
		return $this->dbh;
	}
	public function updraftplus_use_mysqli() {
		return !empty($this->use_mysqli);
	}
}
