<?php

/*

This is a bare-bones to get you started with developing an access method. The methods provided below are all ones you will want to use (though note that the provided email.php method is an example of truly bare-bones for a method that cannot delete or download and has no configuration).

Read the existing methods for help. There is no hard-and-fast need to put all your code in this file; it is just for increasing convenience and maintainability; there are no bonus points for 100% elegance. If you need access to some part of WordPress that you can only reach through the main plugin file (updraftplus.php), then go right ahead and patch that.

Some handy tips:
- Search-and-replace "template" for the name of your access method
- You can also add the methods config_print_javascript_onready and credentials_test if you like
- Name your file accordingly (it is now template.php)
- Add the method to the array $backup_methods in updraftplus.php when ready
- Use the constant UPDRAFTPLUS_DIR to reach Updraft's plugin directory
- Call $updraftplus->log("my log message") to log things, which greatly helps debugging
- UpdraftPlus is licenced under the GPLv3 or later. In order to combine your backup method with UpdraftPlus, you will need to licence to anyone and everyone that you distribute it to in a compatible way.

*/

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

class UpdraftPlus_BackupModule_template {

	// backup method: takes an array, and shovels them off to the cloud storage
	public function backup($backup_array) {

		global $updraftplus;

		foreach ($backup_array as $file) {

		// Do our uploading stuff...

		// If successful, then you must do this:
		// $updraftplus->uploaded_file($file);

		}

	}

	# $match: a substring to require (tested via strpos() !== false)
	public function listfiles($match = 'backup_') {
		# This function needs to return an array of arrays. The keys for the sub-arrays are name (a path-less filename, i.e. a basename), (optional)size, and should be a list of matching files from the storage backend. A WP_Error object can also be returned; and the error code should be no_settings if that is relevant.
		return array();
	}

	// delete method: takes an array of file names (base name) or a single string, and removes them from the cloud storage
	public function delete($files, $data = false, $sizeinfo = array()) {

		global $updraftplus;

		if (is_string($files)) $files = array($files);

	}

	// download method: takes a file name (base name), and brings it back from the cloud storage into Updraft's directory
	// You can register errors with $updraftplus->log("my error message", 'error')
	public function download($file) {

		global $updraftplus;

	}

	// config_print: prints out table rows for the configuration screen
	// Your rows need to have a class exactly matching your method (in this example, template), and also a class of updraftplusmethod
	// Note that logging is not available from this context; it will do nothing.
	public function config_print() {

		?>
			<tr class="updraftplusmethod template">
			<th>My Method:</th>
			<td>
				
			</td>
			</tr>

		<?php


	}

}
