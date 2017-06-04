<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

// Files can easily get too big for this method

class UpdraftPlus_BackupModule_email {

	public function backup($backup_array) {

		global $updraftplus, $updraftplus_backup;

		$updraft_dir = trailingslashit($updraftplus->backups_dir_location());

		$email = $updraftplus->just_one_email(UpdraftPlus_Options::get_updraft_option('updraft_email'), true);

		if (!is_array($email)) $email = array($email);

		foreach ($backup_array as $type => $file) {

			$descrip_type = (preg_match('/^(.*)\d+$/', $type, $matches)) ? $matches[1] : $type;

			$fullpath = $updraft_dir.$file;

			if (file_exists($fullpath) && filesize($fullpath) > UPDRAFTPLUS_WARN_EMAIL_SIZE) {
				$size_in_mb_of_big_file = round(filesize($fullpath)/1048576, 1);
				$toobig_hash = md5($file);
				$updraftplus->log($file.': '.sprintf(__('This backup archive is %s MB in size - the attempt to send this via email is likely to fail (few email servers allow attachments of this size). If so, you should switch to using a different remote storage method.', 'updraftplus'), $size_in_mb_of_big_file), 'warning', 'toobigforemail_'.$toobig_hash);
			}

			$any_attempted = false;
			$any_sent = false;
			foreach ($email as $ind => $addr) {

				if (!apply_filters('updraftplus_email_wholebackup', true, $addr, $ind, $type)) continue;

				foreach (explode(',', $addr) as $sendmail_addr) {

					$send_short = (strlen($sendmail_addr)>5) ? substr($sendmail_addr, 0, 5).'...' : $sendmail_addr;
					$updraftplus->log("$file: email to: $send_short");
					$any_attempted = true;

					$subject = __("WordPress Backup", 'updraftplus').': '.get_bloginfo('name').' (UpdraftPlus '.$updraftplus->version.') '.get_date_from_gmt(gmdate('Y-m-d H:i:s', $updraftplus->backup_time), 'Y-m-d H:i');

					$sent = wp_mail(trim($sendmail_addr), $subject, sprintf(__("Backup is of: %s.",'updraftplus'), site_url().' ('.$descrip_type.')'), null, array($fullpath));
					if ($sent) $any_sent = true;
				}
			}
			if ($any_sent) {
				if (isset($toobig_hash)) {
					$updraftplus->log_removewarning('toobigforemail_'.$toobig_hash);
					// Don't leave it still set for the next archive
					unset($toobig_hash);
				}
				$updraftplus->uploaded_file($file);
			} elseif ($any_attempted) {
				$updraftplus->log('Mails were not sent successfully');
				$updraftplus->log(__('The attempt to send the backup via email failed (probably the backup was too large for this method)', 'updraftplus'), 'error');
			} else {
				$updraftplus->log('No email addresses were configured to send to');
			}
		}
		return null;
	}

	public function config_print() {
		?>
		<tr class="updraftplusmethod email">
			<th><?php _e('Note:', 'updraftplus');?></th>
			<td><?php

				$used = apply_filters('updraftplus_email_whichaddresses',
					sprintf(__("Your site's admin email address (%s) will be used.", 'updraftplus'), get_bloginfo('admin_email').' - <a href="'.esc_attr(admin_url('options-general.php')).'">'.__("configure it here", 'updraftplus').'</a>').
					' <a href="https://updraftplus.com/shop/reporting/">'.sprintf(__('For more options, use the "%s" add-on.', 'updraftplus'), __('Reporting', 'updraftplus')).'</a>'
				);

				echo $used.' '.sprintf(__('Be aware that mail servers tend to have size limits; typically around %s MB; backups larger than any limits will likely not arrive.','updraftplus'), '10-20');?>
			</td>
		</tr>
		<?php
	}

	public function delete($files, $data = null, $sizeinfo = array()) {
		return true;
	}

}
