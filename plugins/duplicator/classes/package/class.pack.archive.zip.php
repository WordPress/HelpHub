<?php
if (!defined('DUPLICATOR_VERSION')) exit; // Exit if accessed directly
require_once (DUPLICATOR_PLUGIN_PATH.'classes/package/class.pack.archive.php');

/**
 *  Creates a zip file using the built in PHP ZipArchive class
 */
class DUP_Zip extends DUP_Archive
{
    //PRIVATE
    private static $compressDir;
    private static $countDirs  = 0;
    private static $countFiles = 0;
    private static $sqlPath;
    private static $zipPath;
    private static $zipFileSize;
    private static $zipArchive;
    private static $limitItems   = 0;
    private static $networkFlush = false;
    private static $scanReport;

    /**
     *  Creates the zip file and adds the SQL file to the archive
     */
    public static function create(DUP_Archive $archive)
    {
        try {
            $timerAllStart     = DUP_Util::getMicrotime();
            $package_zip_flush = DUP_Settings::Get('package_zip_flush');

            self::$compressDir  = rtrim(DUP_Util::safePath($archive->PackDir), '/');
            self::$sqlPath      = DUP_Util::safePath("{$archive->Package->StorePath}/{$archive->Package->Database->File}");
            self::$zipPath      = DUP_Util::safePath("{$archive->Package->StorePath}/{$archive->File}");
            self::$zipArchive   = new ZipArchive();
            self::$networkFlush = empty($package_zip_flush) ? false : $package_zip_flush;

            $filterDirs       = empty($archive->FilterDirs) ? 'not set' : $archive->FilterDirs;
            $filterExts       = empty($archive->FilterExts) ? 'not set' : $archive->FilterExts;
            $filterOn         = ($archive->FilterOn) ? 'ON' : 'OFF';
            $filterDirsFormat = rtrim(str_replace(';', "\n      ", $filterDirs));
            $lastDirSuccess   = self::$compressDir;

            //LOAD SCAN REPORT
            $json             = file_get_contents(DUPLICATOR_SSDIR_PATH_TMP."/{$archive->Package->NameHash}_scan.json");
            self::$scanReport = json_decode($json);

            DUP_Log::Info("\n********************************************************************************");
            DUP_Log::Info("ARCHIVE (ZIP):");
            DUP_Log::Info("********************************************************************************");
            $isZipOpen = (self::$zipArchive->open(self::$zipPath, ZIPARCHIVE::CREATE) === TRUE);
            if (!$isZipOpen) {
                DUP_Log::Error("Cannot open zip file with PHP ZipArchive.", "Path location [".self::$zipPath."]");
            }
            DUP_Log::Info("ARCHIVE DIR:  ".self::$compressDir);
            DUP_Log::Info("ARCHIVE FILE: ".basename(self::$zipPath));
            DUP_Log::Info("FILTERS: *{$filterOn}*");
            DUP_Log::Info("DIRS: {$filterDirsFormat}");
            DUP_Log::Info("EXTS:  {$filterExts}");

            DUP_Log::Info("----------------------------------------");
            DUP_Log::Info("COMPRESSING");
            DUP_Log::Info("SIZE:\t".self::$scanReport->ARC->Size);
            DUP_Log::Info("STATS:\tDirs ".self::$scanReport->ARC->DirCount." | Files ".self::$scanReport->ARC->FileCount);

            //ADD SQL
            $isSQLInZip = self::$zipArchive->addFile(self::$sqlPath, "database.sql");
            if ($isSQLInZip) {
                DUP_Log::Info("SQL ADDED: ".basename(self::$sqlPath));
            } else {
                DUP_Log::Error("Unable to add database.sql to archive.", "SQL File Path [".self::$sqlath."]");
            }
            self::$zipArchive->close();
            self::$zipArchive->open(self::$zipPath, ZipArchive::CREATE);

            //ZIP DIRECTORIES
            $info = '';
            foreach (self::$scanReport->ARC->Dirs as $dir) {
                if (is_readable($dir) && self::$zipArchive->addEmptyDir(ltrim(str_replace(self::$compressDir, '', $dir), '/'))) {
                    self::$countDirs++;
                    $lastDirSuccess = $dir;
                } else {
                    //Don't warn when dirtory is the root path
                    if (strcmp($dir, rtrim(self::$compressDir, '/')) != 0) {
                        $dir_path = strlen($dir) ? "[{$dir}]" : "[Read Error] - last successful read was: [{$lastDirSuccess}]";
                        $info .= "DIR: {$dir_path}\n";
                    }
                }
            }

            //LOG Unreadable DIR info
            if (strlen($info)) {
                DUP_Log::Info("\nWARNING: Unable to zip directories:");
                DUP_Log::Info($info);
            }

            /* ZIP FILES: Network Flush
             *  This allows the process to not timeout on fcgi 
             *  setups that need a response every X seconds */
            $info = '';
            if (self::$networkFlush) {
                foreach (self::$scanReport->ARC->Files as $file) {
                    if (is_readable($file) && self::$zipArchive->addFile($file, ltrim(str_replace(self::$compressDir, '', $file), '/'))) {
                        self::$limitItems++;
                        self::$countFiles++;
                    } else {
                        $info .= "FILE: [{$file}]\n";
                    }
                    //Trigger a flush to the web server after so many files have been loaded.
                    if (self::$limitItems > DUPLICATOR_ZIP_FLUSH_TRIGGER) {
                        $sumItems         = (self::$countDirs + self::$countFiles);
                        self::$zipArchive->close();
                        self::$zipArchive->open(self::$zipPath);
                        self::$limitItems = 0;
                        DUP_Util::fcgiFlush();
                        DUP_Log::Info("Items archived [{$sumItems}] flushing response.");
                    }
                }
            }
            //Normal
            else {
                foreach (self::$scanReport->ARC->Files as $file) {
                    if (is_readable($file) && self::$zipArchive->addFile($file, ltrim(str_replace(self::$compressDir, '', $file), '/'))) {
                        self::$countFiles++;
                    } else {
                        $info .= "FILE: [{$file}]\n";
                    }
                }
            }

            //LOG Unreadable FILE info
            if (strlen($info)) {
                DUP_Log::Info("\nWARNING: Unable to zip files:");
                DUP_Log::Info($info);
                unset($info);
            }

            DUP_Log::Info(print_r(self::$zipArchive, true));

            //--------------------------------
            //LOG FINAL RESULTS
            DUP_Util::fcgiFlush();
            $zipCloseResult = self::$zipArchive->close();
            ($zipCloseResult) ? DUP_Log::Info("COMPRESSION RESULT: '{$zipCloseResult}'") : DUP_Log::Error("ZipArchive close failure.",
                        "This hosted server may have a disk quota limit.\nCheck to make sure this archive file can be stored.");

            $timerAllEnd = DUP_Util::getMicrotime();
            $timerAllSum = DUP_Util::elapsedTime($timerAllEnd, $timerAllStart);


            self::$zipFileSize = @filesize(self::$zipPath);
            DUP_Log::Info("COMPRESSED SIZE: ".DUP_Util::byteSize(self::$zipFileSize));
            DUP_Log::Info("ARCHIVE RUNTIME: {$timerAllSum}");
            DUP_Log::Info("MEMORY STACK: ".DUP_Server::getPHPMemory());
        } catch (Exception $e) {
            DUP_Log::Error("Runtime error in class.pack.archive.zip.php constructor.", "Exception: {$e}");
        }
    }
}
?>