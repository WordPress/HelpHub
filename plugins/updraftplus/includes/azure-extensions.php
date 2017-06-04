<?php

namespace WindowsAzure\Common;
use WindowsAzure\Blob\BlobRestProxy;
use WindowsAzure\Common\Internal\Resources;
use WindowsAzure\Common\Internal\Validate;
use WindowsAzure\Common\Internal\Utilities;
use WindowsAzure\Common\Internal\Http\HttpClient;
use WindowsAzure\Common\Internal\Filters\DateFilter;
use WindowsAzure\Common\Internal\Filters\HeadersFilter;
use WindowsAzure\Common\Internal\Filters\AuthenticationFilter;
use WindowsAzure\Common\Internal\Filters\WrapFilter;
use WindowsAzure\Common\Internal\InvalidArgumentTypeException;
use WindowsAzure\Common\Internal\Serialization\XmlSerializer;
use WindowsAzure\Common\Internal\Authentication\SharedKeyAuthScheme;
use WindowsAzure\Common\Internal\Authentication\TableSharedKeyLiteAuthScheme;
use WindowsAzure\Common\Internal\StorageServiceSettings;
use WindowsAzure\Common\Internal\ServiceManagementSettings;
use WindowsAzure\Common\Internal\ServiceBusSettings;
use WindowsAzure\Common\Internal\MediaServicesSettings;
use WindowsAzure\Queue\QueueRestProxy;
use WindowsAzure\ServiceBus\ServiceBusRestProxy;
use WindowsAzure\ServiceBus\Internal\WrapRestProxy;
use WindowsAzure\ServiceManagement\ServiceManagementRestProxy;
use WindowsAzure\Table\TableRestProxy;
use WindowsAzure\Table\Internal\AtomReaderWriter;
use WindowsAzure\Table\Internal\MimeReaderWriter;
use WindowsAzure\MediaServices\MediaServicesRestProxy;
use WindowsAzure\Common\Internal\OAuthRestProxy;
use WindowsAzure\Common\Internal\Authentication\OAuthScheme;

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

/*
UpdraftPlus notes:
  We had to extend the class in order to implement our SSL options; see: https://github.com/Azure/azure-sdk-for-php/issues/758
  Note that we're just implementing the option for caPath; and verification will always take place (i.e. the option for disabling verification is ignored). Using that option would mean extending a lot more, and there's no clear use case for it.
*/

class UpdraftPlus_ServicesBuilder extends ServicesBuilder
{
    private $_updraftplus_capath = '';
    private static $_updraftplus_instance = null;

    // This is what we really wanted to do: pass on a parameter to HttpClient()
    protected function httpClient()
    {
        return new HttpClient('', $this->_updraftplus_capath);
    }

    // Here, we pull something that we've added out of the connection string, before carrying on with the previous processing
    public function createBlobService($connectionString)
    {

        // Remove our bit
        if (false !== ($i = strpos($connectionString, ';SSLCAPath='))) {
            $this->_updraftplus_capath = substr($connectionString, $i + 11);
            $connectionString = substr($connectionString, 0, $i);
        }
        
        return parent::createBlobService($connectionString);
       
    }

    // We modified this because the instance was a private variable, and we also need to invoke ourself, not the parent
    public static function getInstance()
    {
        if (!isset(self::$_updraftplus_instance)) {
            self::$_updraftplus_instance = new UpdraftPlus_ServicesBuilder();
        }

        return self::$_updraftplus_instance;
    }
}
