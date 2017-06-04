<?php
if (!defined('DUPLICATOR_VERSION')) exit; // Exit if accessed directly

require_once (DUPLICATOR_PLUGIN_PATH.'classes/utilities/class.u.php');
require_once (DUPLICATOR_PLUGIN_PATH.'classes/package/class.pack.archive.php');
require_once (DUPLICATOR_PLUGIN_PATH.'classes/package/class.pack.installer.php');
require_once (DUPLICATOR_PLUGIN_PATH.'classes/package/class.pack.database.php');

final class DUP_PackageStatus
{

    private function __construct()
    {

    }
    const START    = 10;
    const DBSTART  = 20;
    const DBDONE   = 30;
    const ARCSTART = 40;
    const ARCDONE  = 50;
    const COMPLETE = 100;

}

final class DUP_PackageType
{
    const MANUAL    = 0;
    const SCHEDULED = 1;

}

/**
 * Class used to store and process all Package logic
 *
 * @package Dupicator\classes
 */
class DUP_Package
{
    const OPT_ACTIVE = 'duplicator_package_active';

    //Properties
    public $Created;
    public $Version;
    public $VersionWP;
    public $VersionDB;
    public $VersionPHP;
    public $VersionOS;
    public $ID;
    public $Name;
    public $Hash;
    public $NameHash;
    public $Type;
    public $Notes;
    public $StorePath;
    public $StoreURL;
    public $ScanFile;
    public $Runtime;
    public $ExeSize;
    public $ZipSize;
    public $Status;
    public $WPUser;
    //Objects
    public $Archive;
    public $Installer;
    public $Database;

    /**
     *  Manages the Package Process
     */
    function __construct()
    {

        $this->ID      = null;
        $this->Version = DUPLICATOR_VERSION;

        $this->Type      = DUP_PackageType::MANUAL;
        $this->Name      = self::getDefaultName();
        $this->Notes     = null;
        $this->StoreURL  = DUP_Util::snapshotURL();
        $this->StorePath = DUPLICATOR_SSDIR_PATH_TMP;
        $this->Database  = new DUP_Database($this);
        $this->Archive   = new DUP_Archive($this);
        $this->Installer = new DUP_Installer($this);
    }

    /**
     * Generates a json scan report
     *
     * @return array of scan results
     *
     * @notes: Testing = /wp-admin/admin-ajax.php?action=duplicator_package_scan
     */
    public function runScanner()
    {
        $timerStart     = DUP_Util::getMicrotime();
        $report         = array();
        $this->ScanFile = "{$this->NameHash}_scan.json";

        $report['RPT']['ScanTime'] = "0";
        $report['RPT']['ScanFile'] = $this->ScanFile;

        //SERVER
        $srv           = DUP_Server::getChecks();
        $report['SRV'] = $srv['SRV'];

        //FILES
        $this->Archive->buildScanStats();
        $dirCount  = count($this->Archive->Dirs);
        $fileCount = count($this->Archive->Files);
        $fullCount = $dirCount + $fileCount;

        $report['ARC']['Size']      = DUP_Util::byteSize($this->Archive->Size) or "unknown";
        $report['ARC']['DirCount']  = number_format($dirCount);
        $report['ARC']['FileCount'] = number_format($fileCount);
        $report['ARC']['FullCount'] = number_format($fullCount);

        $report['ARC']['FilterInfo']['Dirs']  = $this->Archive->FilterInfo->Dirs;
        $report['ARC']['FilterInfo']['Files'] = $this->Archive->FilterInfo->Files;
        $report['ARC']['FilterInfo']['Exts']  = $this->Archive->FilterInfo->Exts;

        $report['ARC']['Status']['Size']  = ($this->Archive->Size > DUPLICATOR_SCAN_SITE) ? 'Warn' : 'Good';
        $report['ARC']['Status']['Names'] = (count($this->Archive->FilterInfo->Files->Warning) + count($this->Archive->FilterInfo->Dirs->Warning)) ? 'Warn' : 'Good';
        $report['ARC']['Status']['Big']   = count($this->Archive->FilterInfo->Files->Size) ? 'Warn' : 'Good';

        $report['ARC']['Dirs']  = $this->Archive->Dirs;
        $report['ARC']['Files'] = $this->Archive->Files;

        //DATABASE
        $db           = $this->Database->getScanData();
        $report['DB'] = $db;

        $warnings = array($report['SRV']['WEB']['ALL'],
            $report['SRV']['PHP']['ALL'],
            $report['SRV']['WP']['ALL'],
            $report['ARC']['Status']['Size'],
            $report['ARC']['Status']['Names'],
            $report['ARC']['Status']['Big'],
            $db['Status']['Size'],
            $db['Status']['Rows'],
            $db['Status']['Case']);

        //array_count_values will throw a warning message if it has null values,
        //so lets replace all nulls with empty string
        foreach ($warnings as $i => $value) {
            if (is_null($value)) {
                $warnings[$i] = '';
            }
        }
        $warn_counts               = is_array($warnings) ? array_count_values($warnings) : 0;
        $report['RPT']['Warnings'] = $warn_counts['Warn'];
        $report['RPT']['Success']  = $warn_counts['Good'];
        $report['RPT']['ScanTime'] = DUP_Util::elapsedTime(DUP_Util::getMicrotime(), $timerStart);
        $fp                        = fopen(DUPLICATOR_SSDIR_PATH_TMP."/{$this->ScanFile}", 'w');
        fwrite($fp, json_encode($report));
        fclose($fp);

        return $report;
    }

    /**
     * Starts the package build process
     *
     * @return obj Retuns a DUP_Package object
     */
    public function runBuild()
    {

        global $wp_version;
        global $wpdb;
        global $current_user;

        $timerStart = DUP_Util::getMicrotime();

        $this->Archive->File   = "{$this->NameHash}_archive.zip";
        $this->Installer->File = "{$this->NameHash}_installer.php";
        $this->Database->File  = "{$this->NameHash}_database.sql";
        $this->WPUser          = isset($current_user->user_login) ? $current_user->user_login : 'unknown';

        //START LOGGING
        DUP_Log::Open($this->NameHash);
        $php_max_time   = @ini_get("max_execution_time");
        $php_max_memory = @ini_set('memory_limit', DUPLICATOR_PHP_MAX_MEMORY);
        $php_max_time   = ($php_max_time == 0) ? "(0) no time limit imposed" : "[{$php_max_time}] not allowed";
        $php_max_memory = ($php_max_memory === false) ? "Unabled to set php memory_limit" : DUPLICATOR_PHP_MAX_MEMORY." ({$php_max_memory} default)";

        $info = "********************************************************************************\n";
        $info .= "DUPLICATOR-LITE PACKAGE-LOG: ".@date("Y-m-d H:i:s")."\n";
        $info .= "NOTICE: Do NOT post to public sites or forums \n";
        $info .= "********************************************************************************\n";
        $info .= "VERSION:\t".DUPLICATOR_VERSION."\n";
        $info .= "WORDPRESS:\t{$wp_version}\n";
        $info .= "PHP INFO:\t".phpversion().' | '.'SAPI: '.php_sapi_name()."\n";
        $info .= "SERVER:\t\t{$_SERVER['SERVER_SOFTWARE']} \n";
        $info .= "PHP TIME LIMIT: {$php_max_time} \n";
        $info .= "PHP MAX MEMORY: {$php_max_memory} \n";
        $info .= "MEMORY STACK: ".DUP_Server::getPHPMemory();
        DUP_Log::Info($info);
        $info = null;

        //CREATE DB RECORD
        $packageObj = serialize($this);
        if (!$packageObj) {
            DUP_Log::Error("Unable to serialize pacakge object while building record.");
        }

        $this->ID = $this->getHashKey($this->Hash);

        if ($this->ID != 0) {
            $this->setStatus(DUP_PackageStatus::START);
        } else {
            $results = $wpdb->insert($wpdb->prefix."duplicator_packages",
                array(
                'name' => $this->Name,
                'hash' => $this->Hash,
                'status' => DUP_PackageStatus::START,
                'created' => current_time('mysql', get_option('gmt_offset', 1)),
                'owner' => isset($current_user->user_login) ? $current_user->user_login : 'unknown',
                'package' => $packageObj)
            );
            if ($results === false) {

                $wpdb->print_error();
                DUP_Log::Error("Duplicator is unable to insert a package record into the database table.", "'{$wpdb->last_error}'");
            }
            $this->ID = $wpdb->insert_id;
        }

        //START BUILD
        //PHPs serialze method will return the object, but the ID above is not passed
        //for one reason or another so passing the object back in seems to do the trick
        $this->Database->build($this);
        $this->Archive->build($this);
        $this->Installer->build($this);


        //INTEGRITY CHECKS
        DUP_Log::Info("\n********************************************************************************");
        DUP_Log::Info("INTEGRITY CHECKS:");
        DUP_Log::Info("********************************************************************************");
        $dbSizeRead  = DUP_Util::byteSize($this->Database->Size);
        $zipSizeRead = DUP_Util::byteSize($this->Archive->Size);
        $exeSizeRead = DUP_Util::byteSize($this->Installer->Size);

        DUP_Log::Info("SQL File: {$dbSizeRead}");
        DUP_Log::Info("Installer File: {$exeSizeRead}");
        DUP_Log::Info("Archive File: {$zipSizeRead} ");

        if (!($this->Archive->Size && $this->Database->Size && $this->Installer->Size)) {
            DUP_Log::Error("A required file contains zero bytes.", "Archive Size: {$zipSizeRead} | SQL Size: {$dbSizeRead} | Installer Size: {$exeSizeRead}");
        }

        //Validate SQL files completed
        $sql_tmp_path     = DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP.'/'.$this->Database->File);
        $sql_complete_txt = DUP_Util::tailFile($sql_tmp_path, 3);
        if (!strstr($sql_complete_txt, 'DUPLICATOR_MYSQLDUMP_EOF')) {
            DUP_Log::Error("ERROR: SQL file not complete.  The end of file marker was not found.  Please try to re-create the package.");
        }

        $timerEnd = DUP_Util::getMicrotime();
        $timerSum = DUP_Util::elapsedTime($timerEnd, $timerStart);

        $this->Runtime = $timerSum;
        $this->ExeSize = $exeSizeRead;
        $this->ZipSize = $zipSizeRead;

        $this->buildCleanup();

        //FINAL REPORT
        $info = "\n********************************************************************************\n";
        $info .= "RECORD ID:[{$this->ID}]\n";
        $info .= "TOTAL PROCESS RUNTIME: {$timerSum}\n";
        $info .= "PEAK PHP MEMORY USED: ".DUP_Server::getPHPMemory(true)."\n";
        $info .= "DONE PROCESSING => {$this->Name} ".@date("Y-m-d H:i:s")."\n";

        DUP_Log::Info($info);
        DUP_Log::Close();

        $this->setStatus(DUP_PackageStatus::COMPLETE);
        return $this;
    }

    /**
     *  Saves the active options associted with the active(latest) package.
     *
     *  @see DUP_Package::getActive
     *
     *  @param $_POST $post The Post server object
     * 
     *  @return null
     */
    public function saveActive($post = null)
    {
        global $wp_version;

        if (isset($post)) {
            $post = stripslashes_deep($post);

            $name_chars = array(".", "-");
            $name       = ( isset($post['package-name']) && !empty($post['package-name'])) ? $post['package-name'] : self::getDefaultName();
            $name       = substr(sanitize_file_name($name), 0, 40);
            $name       = str_replace($name_chars, '', $name);

            $filter_dirs = isset($post['filter-dirs']) ? $this->parseDirectoryFilter($post['filter-dirs']) : '';
            $filter_exts = isset($post['filter-exts']) ? $this->parseExtensionFilter($post['filter-exts']) : '';
            $tablelist   = isset($post['dbtables']) ? implode(',', $post['dbtables']) : '';
            $compatlist  = isset($post['dbcompat']) ? implode(',', $post['dbcompat']) : '';
            $dbversion   = DUP_DB::getVersion();
            $dbversion   = is_null($dbversion) ? '- unknown -' : $dbversion;
            $dbcomments  = DUP_DB::getVariable('version_comment');
            $dbcomments  = is_null($dbcomments) ? '- unknown -' : $dbcomments;

            //PACKAGE
            $this->Created    = date("Y-m-d H:i:s");
            $this->Version    = DUPLICATOR_VERSION;
            $this->VersionOS  = defined('PHP_OS') ? PHP_OS : 'unknown';
            $this->VersionWP  = $wp_version;
            $this->VersionPHP = phpversion();
            $this->VersionDB  = $dbversion;
            $this->Name       = $name;
            $this->Hash       = $this->makeHash();
            $this->NameHash   = "{$this->Name}_{$this->Hash}";

            $this->Notes                    = esc_html($post['package-notes']);
            //ARCHIVE
            $this->Archive->PackDir         = rtrim(DUPLICATOR_WPROOTPATH, '/');
            $this->Archive->Format          = 'ZIP';
            $this->Archive->FilterOn        = isset($post['filter-on']) ? 1 : 0;
			$this->Archive->ExportOnlyDB    = isset($post['export-onlydb']) ? 1 : 0;
            $this->Archive->FilterDirs      = esc_html($filter_dirs);
            $this->Archive->FilterExts      = str_replace(array('.', ' '), "", esc_html($filter_exts));
            //INSTALLER
            $this->Installer->OptsDBHost    = esc_html($post['dbhost']);
            $this->Installer->OptsDBPort    = esc_html($post['dbport']);
            $this->Installer->OptsDBName    = esc_html($post['dbname']);
            $this->Installer->OptsDBUser    = esc_html($post['dbuser']);
            $this->Installer->OptsSSLAdmin  = isset($post['ssl-admin']) ? 1 : 0;
            $this->Installer->OptsSSLLogin  = isset($post['ssl-login']) ? 1 : 0;
            $this->Installer->OptsCacheWP   = isset($post['cache-wp']) ? 1 : 0;
            $this->Installer->OptsCachePath = isset($post['cache-path']) ? 1 : 0;
            $this->Installer->OptsURLNew    = esc_html($post['url-new']);
            //DATABASE
            $this->Database->FilterOn       = isset($post['dbfilter-on']) ? 1 : 0;
            $this->Database->FilterTables   = esc_html($tablelist);
            $this->Database->Compatible     = $compatlist;
            $this->Database->Comments       = $dbcomments;

            update_option(self::OPT_ACTIVE, $this);
        }
    }

    /**
     * Save any property of this class through reflection
     *
     * @param $property     A valid public property in this class
     * @param $value        The value for the new dynamic property
     *
     * @return null
     */
    public function saveActiveItem($property, $value)
    {
        $package = self::getActive();

        $reflectionClass = new ReflectionClass($package);
        $reflectionClass->getProperty($property)->setValue($package, $value);
        update_option(self::OPT_ACTIVE, $package);
    }

    /**
     * Sets the status to log the state of the build
     *
     * @param $status The status level for where the package is
     *
     * @return void
     */
    public function setStatus($status)
    {
        global $wpdb;

        $packageObj = serialize($this);

        if (!isset($status)) {
            DUP_Log::Error("Package SetStatus did not receive a proper code.");
        }

        if (!$packageObj) {
            DUP_Log::Error("Package SetStatus was unable to serialize package object while updating record.");
        }

        $wpdb->flush();
        $table = $wpdb->prefix."duplicator_packages";
        $sql   = "UPDATE `{$table}` SET  status = {$status}, package = '{$packageObj}'	WHERE ID = {$this->ID}";
        $wpdb->query($sql);
    }

    /**
     * Does a hash already exisit
     *
     * @param string $hash An existing hash value
     *
     * @return int Returns 0 if no hash is found, if found returns the table ID
     */
    public function getHashKey($hash)
    {
        global $wpdb;

        $table = $wpdb->prefix."duplicator_packages";
        $qry   = $wpdb->get_row("SELECT ID, hash FROM `{$table}` WHERE hash = '{$hash}'");
        if (strlen($qry->hash) == 0) {
            return 0;
        } else {
            return $qry->ID;
        }
    }

    /**
     *  Makes the hashkey for the package files
	 *  Rare cases will need to fall back to GUID
     *
     *  @return string  Returns a unique hashkey
     */
    public function makeHash()
    {
		try {
			if (function_exists('random_bytes') && DUP_Util::$on_php_53_plus) {
				return bin2hex(random_bytes(8)).mt_rand(1000, 9999).date("ymdHis");
			} else {
				return DUP_Util::GUIDv4();
			}
		} catch (Exception $exc) {
			return DUP_Util::GUIDv4();
		}
    }

    /**
     * Gets the active package which is defined as the package that was lasted saved.
     * Do to cache issues with the built in WP function get_option moved call to a direct DB call.
     *
     * @see DUP_Package::saveActive
     *
     * @return obj  A copy of the DUP_Package object
     */
    public static function getActive()
    {
        global $wpdb;

        $obj = new DUP_Package();
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s LIMIT 1", self::OPT_ACTIVE));
        if (is_object($row)) {
            $obj = @unserialize($row->option_value);
        }
        //Incase unserilaize fails
        $obj = (is_object($obj)) ? $obj : new DUP_Package();
	
        return $obj;
    }

    /**
     * Gets the Package by ID
     *  
     * @param int $id A valid package id form the duplicator_packages table
     *
     * @return obj  A copy of the DUP_Package object
     */
    public static function getByID($id)
    {

        global $wpdb;
        $obj = new DUP_Package();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}duplicator_packages` WHERE ID = %s", $id));
        if (is_object($row)) {
            $obj         = @unserialize($row->package);
            $obj->Status = $row->status;
        }
        //Incase unserilaize fails
        $obj = (is_object($obj)) ? $obj : null;
        return $obj;
    }

    /**
     *  Gets a default name for the package
     *
     *  @return string   A default packagename such as 20170218_blogname
     */
    public static function getDefaultName()
    {
        //Remove specail_chars from final result
        $special_chars = array(".", "-");
        $name          = date('Ymd').'_'.sanitize_title(get_bloginfo('name', 'display'));
        $name          = substr(sanitize_file_name($name), 0, 40);
        $name          = str_replace($special_chars, '', $name);
        return $name;
    }

    /**
     *  Cleanup all tmp files
     *
     *  @param all empty all contents
     *
     *  @return null
     */
    public static function tempFileCleanup($all = false)
    {
        //Delete all files now
        if ($all) {
            $dir = DUPLICATOR_SSDIR_PATH_TMP."/*";
            foreach (glob($dir) as $file) {
                @unlink($file);
            }
        }
        //Remove scan files that are 24 hours old
        else {
            $dir = DUPLICATOR_SSDIR_PATH_TMP."/*_scan.json";
            foreach (glob($dir) as $file) {
                if (filemtime($file) <= time() - 86400) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     *  Provides various date formats
     * 
     *  @param $date    The date to format
     *  @param $format  Various date formats to apply
     * 
     *  @return a formated date based on the $format
     */
    public static function getCreatedDateFormat($date, $format = 1)
    {
        $date = new DateTime($date);
        switch ($format) {
            //YEAR
            case 1: return $date->format('Y-m-d H:i');
                break;
            case 2: return $date->format('Y-m-d H:i:s');
                break;
            case 3: return $date->format('y-m-d H:i');
                break;
            case 4: return $date->format('y-m-d H:i:s');
                break;
            //MONTH
            case 5: return $date->format('m-d-Y H:i');
                break;
            case 6: return $date->format('m-d-Y H:i:s');
                break;
            case 7: return $date->format('m-d-y H:i');
                break;
            case 8: return $date->format('m-d-y H:i:s');
                break;
            //DAY
            case 9: return $date->format('d-m-Y H:i');
                break;
            case 10: return $date->format('d-m-Y H:i:s');
                break;
            case 11: return $date->format('d-m-y H:i');
                break;
            case 12: return $date->format('d-m-y H:i:s');
                break;
            default :
                return $date->format('Y-m-d H:i');
        }
    }

    /**
     *  Cleans up all the tmp files as part of the package build process
     */
    private function buildCleanup()
    {

        $files   = DUP_Util::listFiles(DUPLICATOR_SSDIR_PATH_TMP);
        $newPath = DUPLICATOR_SSDIR_PATH;

        if (function_exists('rename')) {
            foreach ($files as $file) {
                $name = basename($file);
                if (strstr($name, $this->NameHash)) {
                    rename($file, "{$newPath}/{$name}");
                }
            }
        } else {
            foreach ($files as $file) {
                $name = basename($file);
                if (strstr($name, $this->NameHash)) {
                    copy($file, "{$newPath}/{$name}");
                    @unlink($file);
                }
            }
        }
    }

    /**
     *  Properly creates the directory filter list that is used for filtering directories
     *
     *  @param string $dirs A semi-colon list of dir paths
     *  /path1_/path/;/path1_/path2/;
     *
     *  @returns string A cleaned up list of directory filters
     */
    private function parseDirectoryFilter($dirs = "")
    {
        $dirs        = str_replace(array("\n", "\t", "\r"), '', $dirs);
        $filter_dirs = "";
        $dir_array   = array_unique(explode(";", $dirs));
        foreach ($dir_array as $val) {
            if (strlen($val) >= 2) {
                $filter_dirs .= DUP_Util::safePath(trim(rtrim($val, "/\\"))).";";
            }
        }
        return $filter_dirs;
    }

    /**
     *  Properly creates the extension filter list that is used for filtering extensions
     *
     *  @param string $dirs A semi-colon list of dir paths
     *  .jpg;.zip;.gif;
     *
     *  @returns string A cleaned up list of extension filters
     */
    private function parseExtensionFilter($extensions = "")
    {
        $filter_exts = "";
        if (strlen($extensions) >= 1 && $extensions != ";") {
            $filter_exts = str_replace(array(' ', '.'), '', $extensions);
            $filter_exts = str_replace(",", ";", $filter_exts);
            $filter_exts = DUP_Util::appendOnce($extensions, ";");
        }
        return $filter_exts;
    }
}
?>
