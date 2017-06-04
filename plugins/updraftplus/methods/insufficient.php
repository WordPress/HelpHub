<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

class UpdraftPlus_BackupModule_insufficientphp {

	private $required_php;
	private $error_msg;
	private $method;

	public function __construct($method, $desc, $php, $image = null) {
		$this->method = $method;
		$this->desc = $desc;
		$this->required_php = $php;
		$this->image = $image;
		$this->error_msg = 'This remote storage method ('.$this->desc.') requires PHP '.$this->required_php.' or later';
		$this->error_msg_trans = sprintf(__('This remote storage method (%s) requires PHP %s or later.', 'updraftplus'), $this->desc, $this->required_php);
	}

	private function log_error() {
		global $updraftplus;
		$updraftplus->log($this->error_msg);
		$updraftplus->log($this->error_msg_trans, 'error', 'insufficientphp');
		return false;
	}

	// backup method: takes an array, and shovels them off to the cloud storage
	public function backup($backup_array) {
		return $this->log_error();
	}

	# $match: a substring to require (tested via strpos() !== false)
	public function listfiles($match = 'backup_') {
		return new WP_Error('insufficient_php', $this->error_msg_trans);
	}

	// delete method: takes an array of file names (base name) or a single string, and removes them from the cloud storage
	public function delete($files, $data = false, $sizeinfo = array()) {
		return $this->log_error();
	}

	// download method: takes a file name (base name), and brings it back from the cloud storage into Updraft's directory
	// You can register errors with $updraftplus->log("my error message", 'error')
	public function download($file) {
		return $this->log_error();
	}

	private function extra_config() {
	}

	// config_print: prints out table rows for the configuration screen
	// Your rows need to have a class exactly matching your method (in this example, insufficientphp), and also a class of updraftplusmethod
	// Note that logging is not available from this context; it will do nothing.
	public function config_print() {

		$this->extra_config();
		?>
			<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th><?php echo htmlspecialchars($this->desc);?>:</th>
			<td>
				<em>
					<?php echo ((!empty($this->image)) ? '<p><img src="'.UPDRAFTPLUS_URL.'/images/'.$this->image.'"></p>' : ''); ?>
					<?php echo htmlspecialchars($this->error_msg_trans);?>
					<?php echo htmlspecialchars(__('You will need to ask your web hosting company to upgrade.', 'updraftplus'));?>
					<?php echo sprintf(__('Your %s version: %s.', 'updraftplus'), 'PHP', phpversion());?>
				</em>
			</td>
			</tr>
		<?php

	}

}
