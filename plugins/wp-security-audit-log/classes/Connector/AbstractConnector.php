<?php
//require_once('ConnectorInterface.php');
require_once('wp-db-custom.php');

abstract class WSAL_Connector_AbstractConnector
{
    protected $connection = null;
    protected $adaptersBasePath = null;
    protected $adaptersDirName = null;

    public function __construct($adaptersDirName = null)
    {
        $this->adaptersBasePath =  __DIR__ . DIRECTORY_SEPARATOR .'..'. DIRECTORY_SEPARATOR .'Adapters'. DIRECTORY_SEPARATOR;

        //require_once($this->adaptersBasePath . 'ActiveRecordInterface.php');
        //require_once($this->adaptersBasePath . 'MetaInterface.php');
        //require_once($this->adaptersBasePath . 'OccurrenceInterface.php');
        //require_once($this->adaptersBasePath . 'QueryInterface.php');

        if (!empty($adaptersDirName)) {
            $this->adaptersDirName = $adaptersDirName;
            require_once($this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . 'ActiveRecordAdapter.php');
            require_once($this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . 'MetaAdapter.php');
            require_once($this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . 'OccurrenceAdapter.php');
            require_once($this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . 'QueryAdapter.php');
            require_once($this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . 'TmpUserAdapter.php');
        }
    }

    public function getAdaptersDirectory()
    {
        if (!empty($this->adaptersBasePath) && !empty($this->adaptersDirName)) {
            return $this->adaptersBasePath . $this->adaptersDirName;
        } else {
            return false;
        }
    }
}
