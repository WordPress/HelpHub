<?php
if (!defined('DUPLICATOR_VERSION')) exit; // Exit if accessed directly

require_once (DUPLICATOR_PLUGIN_PATH.'classes/package/class.pack.archive.filters.php');
require_once (DUPLICATOR_PLUGIN_PATH.'classes/package/class.pack.archive.zip.php');
require_once (DUPLICATOR_PLUGIN_PATH.'lib/forceutf8/Encoding.php');

/**
 * Class for handleing archive setup and build process
 *
 * Standard: PSR-2 (almost)
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package DUP
 * @subpackage classes/package
 * @copyright (c) 2017, Snapcreek LLC
 * @license	https://opensource.org/licenses/GPL-3.0 GNU Public License
 * @since 1.0.0
 *
 */
class DUP_Archive
{
    //PUBLIC
    public $FilterDirs;
    public $FilterExts;
    public $FilterDirsAll = array();
    public $FilterExtsAll = array();
    public $FilterOn;
	public $ExportOnlyDB;
    public $File;
    public $Format;
    public $PackDir;
    public $Size          = 0;
    public $Dirs          = array();
    public $Files         = array();
    public $FilterInfo;
    //PROTECTED
    protected $Package;


    /**
     *  Init this object
     */
    public function __construct($package)
    {
        $this->Package		= $package;
        $this->FilterOn		= false;
		$this->ExportOnlyDB = false;
        $this->FilterInfo	= new DUP_Archive_Filter_Info();
    }

    /**
     * Builds the archive based on the archive type
     *
     * @param obj $package The package object that started this process
     *
     * @return null
     */
    public function build($package)
    {
        try {
            $this->Package = $package;
            if (!isset($this->PackDir) && !is_dir($this->PackDir)) throw new Exception("The 'PackDir' property must be a valid diretory.");
            if (!isset($this->File)) throw new Exception("A 'File' property must be set.");

            $this->Package->setStatus(DUP_PackageStatus::ARCSTART);
            switch ($this->Format) {
                case 'TAR': break;
                case 'TAR-GZIP': break;
                default:
                    if (class_exists(ZipArchive)) {
                        $this->Format = 'ZIP';
                        DUP_Zip::create($this);
                    }
                    break;
            }

            $storePath  = "{$this->Package->StorePath}/{$this->File}";
            $this->Size = @filesize($storePath);
            $this->Package->setStatus(DUP_PackageStatus::ARCDONE);
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }

    /**
     *  Builds a list of files and directories to be included in the archive
     *
     *  Get the directory size recursively, but don't calc the snapshot directory, exclusion diretories
     *  @link http://msdn.microsoft.com/en-us/library/aa365247%28VS.85%29.aspx Windows filename restrictions
     *
     *  @return obj Returns a DUP_Archive object
     */
    public function buildScanStats()
    {
		$this->createFilterInfo();

		$rootPath = DUP_Util::safePath(rtrim(DUPLICATOR_WPROOTPATH, '//'));
        $rootPath = (trim($rootPath) == '') ? '/' : $rootPath;

		//If the root directory is a filter then skip it all
        if (in_array($this->PackDir, $this->FilterDirsAll) || $this->Package->Archive->ExportOnlyDB) {
            $this->Dirs = array();
        } else {
            $this->Dirs[] = $this->PackDir;
			$this->getFileLists($rootPath);
			$this->setDirFilters();
			$this->setFileFilters();
        }

        $this->FilterDirsAll  = array_merge($this->FilterDirsAll, $this->FilterInfo->Dirs->Unreadable);
        $this->FilterFilesAll = array_merge($this->FilterFilesAll, $this->FilterInfo->Files->Unreadable);
        return $this;


    }

    /**
     * Creates the filter info setup data used for filtering the archive
     *
     * @return null
     */
    private function createFilterInfo()
    {
        //FILTER: INSTANCE ITEMS
        //Add the items generated at create time
        if ($this->FilterOn) {
            $this->FilterInfo->Dirs->Instance = array_map('DUP_Util::safePath', explode(";", $this->FilterDirs, -1));
            $this->FilterInfo->Exts->Instance = explode(";", $this->FilterExts, -1);
        }

        //FILTER: CORE ITMES
        //Filters Duplicator free packages & All pro local directories
        $this->FilterInfo->Dirs->Core[] = DUPLICATOR_SSDIR_PATH;

        $this->FilterDirsAll = array_merge($this->FilterInfo->Dirs->Instance, $this->FilterInfo->Dirs->Core);
        $this->FilterExtsAll = array_merge($this->FilterInfo->Exts->Instance, $this->FilterInfo->Exts->Core);
    }

	/**
	 * Get All Directories then filter
	 *
	 * @return null
	 */
    private function setDirFilters()
    {
        $this->FilterInfo->Dirs->Warning    = array();
        $this->FilterInfo->Dirs->Unreadable = array();

        //Filter directories invalid test checks for:
		// - characters over 250
		// - invlaid characters
		// - empty string
		// - directories ending with period (Windows incompatable)
        foreach ($this->Dirs as $key => $val) {
            $name = basename($val);

			//Locate invalid directories and warn
			$invalid_test = strlen($val) > 250
				|| preg_match('/(\/|\*|\?|\>|\<|\:|\\|\|)/', $name)
				|| trim($name) == ''
				|| (strrpos($name, '.') == strlen($name) - 1 && substr($name, -1) == '.')
				|| preg_match('/[^\x20-\x7f]/', $name);

			if ($invalid_test) {
				$this->FilterInfo->Dirs->Warning[] = DUP_Encoding::toUTF8($val);
			}

			//@todo: CJL addEmptyDir works with unreadable dirs, this check maybe unnessary
			//@todo: CJL Move unset logic out of loop
            //Dir is not readble remove and flag
            if (! is_readable($this->Dirs[$key])) {
                unset($this->Dirs[$key]);
                $unreadable_dir = DUP_Encoding::toUTF8($val);
                $this->FilterInfo->Dirs->Unreadable[] = $unreadable_dir;
            }
        }
    }

	/**
	 * Get all files and filter out error prone subsets
	 *
	 * @return null
	 */
    private function setFileFilters()
    {
        //Init for each call to prevent concatination from stored entity objects
        $this->Size                          = 0;
        $this->FilterInfo->Files->Size       = array();
        $this->FilterInfo->Files->Warning    = array();
        $this->FilterInfo->Files->Unreadable = array();

		foreach ($this->Files as $key => $filePath) {

			$fileName = basename($filePath);

			//@todo: CJL Move unset logic out of loop
			if (! is_readable($filePath)) {
				unset($this->Files[$key]);
				$this->FilterInfo->Files->Unreadable[] = $filePath;
				continue;
			}

			$invalid_test = strlen($filePath) > 250
				|| preg_match('/(\/|\*|\?|\>|\<|\:|\\|\|)/', $fileName)
				|| trim($fileName) == ""
				|| preg_match('/[^\x20-\x7f]/', $fileName);

			if ($invalid_test) {
				$filePath = DUP_Encoding::toUTF8($filePath);
				$this->FilterInfo->Files->Warning[] = $filePath;
			}


			$fileSize = @filesize($filePath);
			$fileSize = empty($fileSize) ? 0 : $fileSize;
			$this->Size += $fileSize;

			if ($fileSize > DUPLICATOR_SCAN_WARNFILESIZE) {
				  $this->FilterInfo->Files->Size[] = $filePath.' ['.DUP_Util::byteSize($fileSize).']';
			 }
		}
    }

	/**
     * Recursive function to get all directories in a wp install
     *
     * @notes:
	 *	Older PHP logic which is more stable on older version of PHP
     *	NOTE RecursiveIteratorIterator is problematic on some systems issues include:
     *  - error 'too many files open' for recursion
     *  - $file->getExtension() is not reliable as it silently fails at least in php 5.2.17
     *  - issues with when a file has a permission such as 705 and trying to get info (had to fallback to pathinfo)
     *  - basic conclusion wait on the SPL libs untill after php 5.4 is a requiremnt
     *  - tight recursive loop use caution for speed
     *
     * @return array	Returns an array of directories to include in the archive
     */
	private function getFileLists($path)
    {
		$handle = @opendir($path);

		if ($handle) {
			while (($file = readdir($handle)) !== false) {

				if ($file == '.' || $file == '..') {
					continue;
				}

				$fullPath = str_replace("\\", '/', "{$path}/{$file}");

				// @todo: Don't leave it like this. Convert into an option on the package to not follow symbolic links
				// if (is_dir($fullPath) && (is_link($fullPath) == false))
				if (is_dir($fullPath)) {

					$add = true;
					//Remove path filter directories
					foreach ($this->FilterDirsAll as $val) {
						$trimmedFilterDir = rtrim($val, '/');
						if ($fullPath == $trimmedFilterDir || strpos($fullPath, $trimmedFilterDir . '/') !== false) {
							$add = false;
							break;
						}
					}

					if ($add) {
						$this->getFileLists($fullPath);
						$this->Dirs[] = $fullPath;
					}
				} else {
					// Note: The last clause is present to perform just a filename check
					if ( ! in_array(pathinfo($file, PATHINFO_EXTENSION) , $this->FilterExtsAll)) {
						$this->Files[] = $fullPath;
					}
				}
			}
			closedir($handle);
		}
		return $this->Dirs;
	}


}