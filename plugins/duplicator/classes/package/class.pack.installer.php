<?php
if (!defined('DUPLICATOR_VERSION')) exit; // Exit if accessed directly

class DUP_Installer
{
    //PUBLIC
    public $File;
    public $Size = 0;
    public $OptsDBHost;
    public $OptsDBPort;
    public $OptsDBName;
    public $OptsDBUser;
    public $OptsSSLAdmin;
    public $OptsSSLLogin;
    public $OptsCacheWP;
    public $OptsCachePath;
    public $OptsURLNew;
    //PROTECTED
    protected $Package;

    /**
     *  Init this object
     */
    function __construct($package)
    {
        $this->Package = $package;
    }

    /**
     *  Build the installer script
     *
     *  @param obj $package A reference to the package that this installer object belongs to
     *
     *  @return null
     */
    public function build($package)
    {

        $this->Package = $package;

        DUP_Log::Info("\n********************************************************************************");
        DUP_Log::Info("MAKE INSTALLER:");
        DUP_Log::Info("********************************************************************************");
        DUP_Log::Info("Build Start");

        $template_uniqid = uniqid('').'_'.time();
        $template_path   = DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP."/installer.template_{$template_uniqid}.php");
        $main_path       = DUP_Util::safePath(DUPLICATOR_PLUGIN_PATH.'installer/build/main.installer.php');
        @chmod($template_path, 0777);
        @chmod($main_path, 0777);

        @touch($template_path);
        $main_data       = file_get_contents("{$main_path}");
        $template_result = file_put_contents($template_path, $main_data);
        if ($main_data === false || $template_result == false) {
            $err_info = "These files may have permission issues. Please validate that PHP has read/write access.\n";
            $err_info .= "Main Installer: '{$main_path}' \nTemplate Installer: '$template_path'";
            DUP_Log::Error("Install builder failed to generate files.", "{$err_info}");
        }

        $embeded_files = array(
            "assets/inc.libs.css.php"				=> "@@INC.LIBS.CSS.PHP@@",
            "assets/inc.css.php"					=> "@@INC.CSS.PHP@@",
            "assets/inc.libs.js.php"				=> "@@INC.LIBS.JS.PHP@@",
            "assets/inc.js.php"						=> "@@INC.JS.PHP@@",
            "classes/utilities/class.u.php"			=> "@@CLASS.U.PHP@@",
            "classes/class.server.php"				=> "@@CLASS.SERVER.PHP@@",
            "classes/class.db.php"					=> "@@CLASS.DB.PHP@@",
            "classes/class.logging.php"				=> "@@CLASS.LOGGING.PHP@@",
            "classes/class.engine.php"				=> "@@CLASS.ENGINE.PHP@@",
            "classes/config/class.conf.wp.php"		=> "@@CLASS.CONF.WP.PHP@@",
            "classes/config/class.conf.srv.php"		=> "@@CLASS.CONF.SRV.PHP@@",
			"ctrls/ctrl.step1.php"					=> "@@CTRL.STEP1.PHP@@",
            "ctrls/ctrl.step2.php"					=> "@@CTRL.STEP2.PHP@@",
            "ctrls/ctrl.step3.php"					=> "@@CTRL.STEP3.PHP@@",
            "view.step1.php"						=> "@@VIEW.STEP1.PHP@@",
            "view.step2.php"						=> "@@VIEW.STEP2.PHP@@",
            "view.step3.php"						=> "@@VIEW.STEP3.PHP@@",
            "view.step4.php"						=> "@@VIEW.STEP4.PHP@@",
            "view.help.php"							=> "@@VIEW.HELP.PHP@@",);

        foreach ($embeded_files as $name => $token) {
            $file_path = DUPLICATOR_PLUGIN_PATH."installer/build/{$name}";
            @chmod($file_path, 0777);

            $search_data = @file_get_contents($template_path);
            $insert_data = @file_get_contents($file_path);
            file_put_contents($template_path, str_replace("${token}", "{$insert_data}", $search_data));
            if ($search_data === false || $insert_data == false) {
                DUP_Log::Error("Installer generation failed at {$token}.");
            }
            @chmod($file_path, 0644);
        }

        @chmod($template_path, 0644);
        @chmod($main_path, 0644);

        DUP_Log::Info("Build Finished");
        $this->createFromTemplate($template_path);
        $storePath  = "{$this->Package->StorePath}/{$this->File}";
        $this->Size = @filesize($storePath);
        $this->addBackup();
    }

    /**
     *  Puts an installer zip file in the archive for backup purposes.
     *
     * @return null
     */
    private function addBackup()
    {

        $zipPath   = DUP_Util::safePath("{$this->Package->StorePath}/{$this->Package->Archive->File}");
        $installer = DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP)."/{$this->Package->NameHash}_installer.php";

        $zipArchive = new ZipArchive();
        if ($zipArchive->open($zipPath, ZIPARCHIVE::CREATE) === TRUE) {
            if ($zipArchive->addFile($installer, "installer-backup.php")) {
                DUP_Log::Info("Added to archive");
            } else {
                DUP_Log::Info("Unable to add installer-backup.php to archive.", "Installer File Path [{$installer}]");
            }
            $zipArchive->close();
        }
    }

    /**
     * Generates the final installer file from the template file
     *
     * @param string $template The path to the installer template which is orginally copied from main.installer.php
     *
     * @return null
     */
    private function createFromTemplate($template)
    {

        global $wpdb;

        DUP_Log::Info("Preping for use");
        $installer = DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP)."/{$this->Package->NameHash}_installer.php";

        //Option values to delete at install time
        $deleteOpts = $GLOBALS['DUPLICATOR_OPTS_DELETE'];

        $replace_items = Array(
            //COMPARE VALUES
            "fwrite_created" => $this->Package->Created,
            "fwrite_version_dup" => DUPLICATOR_VERSION,
            "fwrite_version_wp" => $this->Package->VersionWP,
            "fwrite_version_db" => $this->Package->VersionDB,
            "fwrite_version_php" => $this->Package->VersionPHP,
            "fwrite_version_os" => $this->Package->VersionOS,
            //GENERAL
            "fwrite_url_old" => get_option('siteurl'),
            "fwrite_archive_name" => "{$this->Package->NameHash}_archive.zip",
			"fwrite_archive_onlydb" => $this->Package->Archive->ExportOnlyDB,
            "fwrite_package_notes" => $this->Package->Notes,
            "fwrite_secure_name" => $this->Package->NameHash,
            "fwrite_url_new" => $this->Package->Installer->OptsURLNew,
            "fwrite_dbhost" => $this->Package->Installer->OptsDBHost,
            "fwrite_dbport" => $this->Package->Installer->OptsDBPort,
            "fwrite_dbname" => $this->Package->Installer->OptsDBName,
            "fwrite_dbuser" => $this->Package->Installer->OptsDBUser,
            "fwrite_dbpass" => '',
            "fwrite_ssl_admin" => $this->Package->Installer->OptsSSLAdmin,
            "fwrite_ssl_login" => $this->Package->Installer->OptsSSLLogin,
            "fwrite_cache_wp" => $this->Package->Installer->OptsCacheWP,
            "fwrite_cache_path" => $this->Package->Installer->OptsCachePath,
            "fwrite_wp_tableprefix" => $wpdb->prefix,
            "fwrite_opts_delete" => json_encode($deleteOpts),
            "fwrite_blogname" => esc_html(get_option('blogname')),
            "fwrite_wproot" => DUPLICATOR_WPROOTPATH,
			"fwrite_wplogin_url" => wp_login_url(),
            "fwrite_duplicator_version" => DUPLICATOR_VERSION);

        if (file_exists($template) && is_readable($template)) {
            $err_msg     = "ERROR: Unable to read/write installer. \nERROR INFO: Check permission/owner on file and parent folder.\nInstaller File = <{$installer}>";
            $install_str = $this->parseTemplate($template, $replace_items);
            (empty($install_str)) ? DUP_Log::Error("{$err_msg}", "DUP_Installer::createFromTemplate => file-empty-read") : DUP_Log::Info("Template parsed with new data");

            //INSTALLER FILE
            $fp = (!file_exists($installer)) ? fopen($installer, 'x+') : fopen($installer, 'w');
            if (!$fp || !fwrite($fp, $install_str, strlen($install_str))) {
                DUP_Log::Error("{$err_msg}", "DUP_Installer::createFromTemplate => file-write-error");
            }

            @fclose($fp);
        } else {
            DUP_Log::Error("Installer Template missing or unreadable.", "Template [{$template}]");
        }
        @unlink($template);
        DUP_Log::Info("Complete [{$installer}]");
    }

    /**
     *  Tokenize a file based on an array key 
     *
     *  @param string $filename		The filename to tokenize
     *  @param array  $data			The array of key value items to tokenize
     */
    private function parseTemplate($filename, $data)
    {
        $q = file_get_contents($filename);
        foreach ($data as $key => $value) {
            //NOTE: Use var_export as it's probably best and most "thorough" way to
            //make sure the values are set correctly in the template.  But in the template,
            //need to make things properly formatted so that when real syntax errors
            //exist they are easy to spot.  So the values will be surrounded by quotes

            $find = array("'%{$key}%'", "\"%{$key}%\"");
            $q    = str_replace($find, var_export($value, true), $q);
            //now, account for places that do not surround with quotes...  these
            //places do NOT need to use var_export as they are not inside strings
            $q    = str_replace('%'.$key.'%', $value, $q);
        }
        return $q;
    }
}
?>