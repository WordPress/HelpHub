<?php

// This is a compatibility library, using Amazon's official PHP SDK (PHP 5.3.3+), but providing the methods of Donovan Schönknecht's S3.php library (which we used to always use) - but we've only cared about making code-paths in UpdraftPlus work, so be careful if re-reploying this in another project. And, we have a few bits of UpdraftPlus-specific code below, for logging.

/**
*
* Copyright (c) 2012-5, David Anderson (http://www.simbahosting.co.uk).  All rights reserved.
* Portions copyright (c) 2011, Donovan Schönknecht.  All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
* - Redistributions of source code must retain the above copyright notice,
*   this list of conditions and the following disclaimer.
* - Redistributions in binary form must reproduce the above copyright
*   notice, this list of conditions and the following disclaimer in the
*   documentation and/or other materials provided with the distribution.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* Amazon S3 is a trademark of Amazon.com, Inc. or its affiliates.
*/

require_once(UPDRAFTPLUS_DIR.'/vendor/autoload.php');

# SDK uses namespacing - requires PHP 5.3 (actually the SDK states its requirements as 5.3.3)
use Aws\S3;

/**
* Amazon S3 PHP class
*
* @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
* @version 0.5.0-dev
*/
class UpdraftPlus_S3_Compat
{
	// ACL flags
	const ACL_PRIVATE = 'private';
	const ACL_PUBLIC_READ = 'public-read';
	const ACL_PUBLIC_READ_WRITE = 'public-read-write';
	const ACL_AUTHENTICATED_READ = 'authenticated-read';

	const STORAGE_CLASS_STANDARD = 'STANDARD';
	const STORAGE_CLASS_RRS = 'REDUCED_REDUNDANCY';

	private $config = array('scheme' => 'https', 'service' => 's3');

	private $__accessKey = null; // AWS Access key
	private $__secretKey = null; // AWS Secret key
	private $__sslKey = null;

	public $endpoint = 's3.amazonaws.com';
	public $proxy = null;
	private $region = 'us-east-1';

	// Added to cope with a particular situation where the user had no pernmission to check the bucket location, which necessitated using DNS-based endpoints.
	public $use_dns_bucket_name = false;

	public $useSSL = false;
	public $useSSLValidation = true;
	public $useExceptions = false;

	private $_serverSideEncryption = null;

	// SSL CURL SSL options - only needed if you are experiencing problems with your OpenSSL configuration
	public $sslKey = null;
	public $sslCert = null;
	public $sslCACert = null;

	/**
	* Constructor - if you're not using the class statically
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param boolean $useSSL Enable SSL
	* @param string|boolean $sslCACert - certificate authority (true = bundled Guzzle version; false = no verify, 'system' = system version; otherwise, path)
	* @return void
	*/
	public function __construct($accessKey = null, $secretKey = null, $useSSL = true, $sslCACert = true, $endpoint = null)
	{
		if ($accessKey !== null && $secretKey !== null)
			$this->setAuth($accessKey, $secretKey);

		$this->useSSL = $useSSL;
		$this->sslCACert = $sslCACert;

		$opts = array(
			'key' => $accessKey,
			'secret' => $secretKey,
			'scheme' => ($useSSL) ? 'https' : 'http',
			// Using signature v4 requires a region
// 			'signature' => 'v4',
// 			'region' => $this->region
// 			'endpoint' => 'somethingorother.s3.amazonaws.com'
		);

		if ($endpoint) {
			// Can't specify signature v4, as that requires stating the region - which we don't necessarily yet know.
			$this->endpoint = $endpoint;
			$opts['endpoint'] = $endpoint;
		} else {
			// Using signature v4 requires a region. Also, some regions (EU Central 1, China) require signature v4 - and all support it, so we may as well use it if we can.
			$opts['signature'] = 'v4';
			$opts['region'] = $this->region;
		}

		if ($useSSL) $opts['ssl.certificate_authority'] = $sslCACert;

		$this->client = Aws\S3\S3Client::factory($opts);
	}

	/**
	* Set AWS access key and secret key
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @return void
	*/
	public function setAuth($accessKey, $secretKey)
	{
		$this->__accessKey = $accessKey;
		$this->__secretKey = $secretKey;
	}

	// Example value: 'AES256'. See: https://docs.aws.amazon.com/AmazonS3/latest/dev/SSEUsingPHPSDK.html
	// Or, false to turn off.
	public function setServerSideEncryption($value)
	{
		$this->_serverSideEncryption = $value;
	}

	/**
	* Set the service region
	*
	* @param string $region Region
	* @return void
	*/
	public function setRegion($region)
	{
		$this->region = $region;
		if ('eu-central-1' == $region || 'cn-north-1' == $region) {
// 			$this->config['signature'] =  new Aws\S3\S3SignatureV4('s3');
// 			$this->client->setConfig($this->config);
		}
		$this->client->setRegion($region);
	}

	/**
	* Set the service endpoint
	*
	* @param string $host Hostname
	* @return void
	*/
	public function setEndpoint($host, $region)
	{
		$this->endpoint = $host;
		$this->region = $region;
		$this->config['endpoint_provider'] = $this->return_provider();
		$this->client->setConfig($this->config);
	}

	public function return_provider() {
		$our_endpoints = array(
			'endpoint' => $this->endpoint
		);
		if ($this->region == 'eu-central-1' || $this->region == 'cn-north-1') $our_endpoints['signatureVersion'] = 'v4';
		$endpoints = array(
			'version' => 2,
			'endpoints' => array(
				"*/s3" => $our_endpoints
			)
		);
		return new Aws\Common\RulesEndpointProvider($endpoints);
	}

	/**
	* Set SSL on or off
	*
	* @param boolean $enabled SSL enabled
	* @param boolean $validate SSL certificate validation
	* @return void
	*/
	// This code relies upon the particular pattern of SSL options-setting in s3.php in UpdraftPlus
	public function setSSL($enabled, $validate = true)
	{
		$this->useSSL = $enabled;
		$this->useSSLValidation = $validate;
		// http://guzzle.readthedocs.org/en/latest/clients.html#verify
		if ($enabled) {

			// Do nothing - in UpdraftPlus, setSSLAuth will be called later, and we do the calls there

// 			$verify_peer = ($validate) ? true : false;
// 			$verify_host = ($validate) ? 2 : 0;
// 
// 			$this->config['scheme'] = 'https';
// 			$this->client->setConfig($this->config);
// 
// 			$this->client->setSslVerification($validate, $verify_peer, $verify_host);


		} else {
			$this->config['scheme'] = 'http';
// 			$this->client->setConfig($this->config);
		}
		$this->client->setConfig($this->config);
	}

	public function getuseSSL() 
	{
		return $this->useSSL;
	}

	/**
	* Set SSL client certificates (experimental)
	*
	* @param string $sslCert SSL client certificate
	* @param string $sslKey SSL client key
	* @param string $sslCACert SSL CA cert (only required if you are having problems with your system CA cert)
	* @return void
	*/
	public function setSSLAuth($sslCert = null, $sslKey = null, $sslCACert = null)
	{
		if (!$this->useSSL) return;

		if (!$this->useSSLValidation) {
			$this->client->setSslVerification(false);
		} else {
			if (!$sslCACert) {
				$client = $this->client;
				$this->config[$client::SSL_CERT_AUTHORITY] = false;
				$this->client->setConfig($this->config);
			} else {
				$this->client->setSslVerification(realpath($sslCACert), true, 2);
			}
		}

// 		$this->client->setSslVerification($sslCACert, $verify_peer, $verify_host);
// 		$this->config['ssl.certificate_authority'] = $sslCACert;
// 		$this->client->setConfig($this->config);
	}

	/**
	* Set proxy information
	*
	* @param string $host Proxy hostname and port (localhost:1234)
	* @param string $user Proxy username
	* @param string $pass Proxy password
	* @param constant $type CURL proxy type
	* @return void
	*/
	public function setProxy($host, $user = null, $pass = null, $type = CURLPROXY_SOCKS5, $port = null)
	{

		$this->proxy = array('host' => $host, 'type' => $type, 'user' => $user, 'pass' => $pass, 'port' => $port);

		if (!$host) return;

		$wp_proxy = new WP_HTTP_Proxy(); 
		if ($wp_proxy->send_through_proxy('https://s3.amazonaws.com'))
		{

			global $updraftplus;
			$updraftplus->log("setProxy: host=$host, user=$user, port=$port");

			// N.B. Currently (02-Feb-15), only support for HTTP proxies has ever been requested for S3 in UpdraftPlus
			$proxy_url = 'http://';
			if ($user) {
				$proxy_url .= $user;
				if ($pass) $proxy_url .= ":$pass";
				$proxy_url .= "@";
			}

			$proxy_url .= $host;

			if ($port) $proxy_url .= ":$port";

			$this->client->setDefaultOption('proxy', $proxy_url);
		}

	}

	/**
	* Set the error mode to exceptions
	*
	* @param boolean $enabled Enable exceptions
	* @return void
	*/
	public function setExceptions($enabled = true)
	{
		$this->useExceptions = $enabled;
	}

	// A no-op in this compatibility layer (for now - not yet found a use)...
	public function useDNSBucketName($use = true, $bucket = '')
	{
		$this->use_dns_bucket_name = $use;
		if ($use && $bucket) {
			$this->setEndpoint($bucket.'.s3.amazonaws.com', $this->region);
		}
		return true;
	}

	/*
	* Get contents for a bucket
	*
	* If maxKeys is null this method will loop through truncated result sets
	*
	* @param string $bucket Bucket name
	* @param string $prefix Prefix
	* @param string $marker Marker (last file listed)
	* @param string $maxKeys Max keys (maximum number of keys to return)
	* @param string $delimiter Delimiter
	* @param boolean $returnCommonPrefixes Set to true to return CommonPrefixes
	* @return array | false
	*/
	// N.B. UpdraftPlus does not use the $delimiter or $returnCommonPrefixes parameters (nor set $prefix or $marker to anything other than null)
	// $returnCommonPrefixes is not implemented below
	public function getBucket($bucket, $prefix = null, $marker = null, $maxKeys = null, $delimiter = null, $returnCommonPrefixes = false)
	{
		try {
			if ($maxKeys == 0) $maxKeys = null;
			
			$vars = array('Bucket' => $bucket);
			if ($prefix !== null && $prefix !== '') $vars['Prefix'] = $prefix;
			if ($marker !== null && $marker !== '') $vars['Marker'] = $marker;
			if ($maxKeys !== null && $maxKeys !== '') $vars['MaxKeys'] = $maxKeys;
			if ($delimiter !== null && $delimiter !== '') $vars['Delimiter'] = $delimiter;
			$result = $this->client->listObjects($vars);

			if (!is_a($result, 'Guzzle\Service\Resource\Model')) {
				return false;
			}

			$results = array();
			$nextMarker = null;
			// http://docs.aws.amazon.com/AmazonS3/latest/dev/ListingObjectKeysUsingPHP.html
			// UpdraftPlus does not use the 'hash' result
			if (empty($result['Contents'])) $result['Contents'] = array();
			foreach ($result['Contents'] as $c)
			{
				$results[(string)$c['Key']] = array(
					'name' => (string)$c['Key'],
					'time' => strtotime((string)$c['LastModified']),
					'size' => (int)$c['Size'],
// 					'hash' => trim((string)$c['ETag'])
// 					'hash' => substr((string)$c['ETag'], 1, -1)
				);
				$nextMarker = (string)$c['Key'];
			}

			if (isset($result['IsTruncated']) && empty($result['IsTruncated'])) return $results;

			if (isset($result['NextMarker'])) $nextMarker = (string)$result['NextMarker'];

			// Loop through truncated results if maxKeys isn't specified
			if ($maxKeys == null && $nextMarker !== null && !empty($result['IsTruncated']))
			do
			{
				$vars['Marker'] = $nextMarker;
				$result = $this->client->listObjects($vars);

				if (!is_a($result, 'Guzzle\Service\Resource\Model') || empty($result['Contents'])) break;

				foreach ($result['Contents'] as $c)
				{
					$results[(string)$c['Key']] = array(
						'name' => (string)$c['Key'],
						'time' => strtotime((string)$c['LastModified']),
						'size' => (int)$c['Size'],
//	 					'hash' => trim((string)$c['ETag'])
// 						'hash' => substr((string)$c['ETag'], 1, -1)
					);
					$nextMarker = (string)$c['Key'];
				}

// 				if ($returnCommonPrefixes && isset($response->body, $response->body->CommonPrefixes))
// 					foreach ($response->body->CommonPrefixes as $c)
// 						$results[(string)$c->Prefix] = array('prefix' => (string)$c->Prefix);

				if (isset($response['NextMarker']))
					$nextMarker = (string)$response['NextMarker'];

			} while (is_a($result, 'Guzzle\Service\Resource\Model') && !empty($result['Contents']) && !empty($result['IsTruncated']));

			return $results;

		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
	}

	// This is crude - nothing is returned
	public function waitForBucket($bucket) {
		try {
			$this->client->waitUntil('BucketExists', array('Bucket' => $bucket));
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
	}

	/**
	* Put a bucket
	*
	* @param string $bucket Bucket name
	* @param constant $acl ACL flag
	* @param string $location Set as "EU" to create buckets hosted in Europe
	* @return boolean
	*/
	public function putBucket($bucket, $acl = self::ACL_PRIVATE, $location = false)
	{
		if (!$location) {
			$location = $this->region;
		} else {
			$this->setRegion($location);
		}
		$bucket_vars = array(
			'Bucket' => $bucket,
			'ACL' => $acl,
		);
		// http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.S3.S3Client.html#_createBucket
		$location_constraint = apply_filters('updraftplus_s3_putbucket_defaultlocation', $location);
		if ('us-east-1' != $location_constraint) $bucket_vars['LocationConstraint'] = $location_constraint;
		try {
			$result = $this->client->createBucket($bucket_vars);
			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('RequestId')) {
				$this->client->waitUntil('BucketExists', array('Bucket' => $bucket));
				return true;
			}
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
	}

	/**
	* Initiate a multi-part upload (http://docs.amazonwebservices.com/AmazonS3/latest/API/mpUploadInitiate.html)
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param array $requestHeaders Array of request headers or content type as a string
	* @param constant $storageClass Storage class constant
	* @return string | false
	*/
	public function initiateMultipartUpload ($bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array(), $storageClass = self::STORAGE_CLASS_STANDARD)
	{
		$vars = array(
			'ACL' => $acl,
			'Bucket' => $bucket,
			'Key' => $uri,
			'Metadata' => $metaHeaders,
			'StorageClass' => $storageClass
		);

		$vars['ContentType'] = ('.gz' == strtolower(substr($uri, -3, 3))) ? 'application/octet-stream' : 'application/zip';

		if (!empty($this->_serverSideEncryption)) $vars['ServerSideEncryption'] = $this->_serverSideEncryption;

		try {
			$result = $this->client->createMultipartUpload($vars);
			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('UploadId')) return $result->get('UploadId');
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		return false;
	}

	/**
	/* Upload a part of a multi-part set (http://docs.amazonwebservices.com/AmazonS3/latest/API/mpUploadUploadPart.html)
	* The chunk is read into memory, so make sure that you have enough (or patch this function to work another way!)
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param string $uploadId uploadId returned previously from initiateMultipartUpload
	* @param integer $partNumber sequential part number to upload
	* @param string $filePath file to upload content from
	* @param integer $partSize number of bytes in each part (though final part may have fewer) - pass the same value each time (for this particular upload) - default 5Mb (which is Amazon's minimum)
	* @return string (ETag) | false
	*/
	public function uploadPart ($bucket, $uri, $uploadId, $filePath, $partNumber, $partSize = 5242880)
	{
		$vars = array(
			'Bucket' => $bucket,
			'Key' => $uri,
			'PartNumber' => $partNumber,
			'UploadId' => $uploadId
		);

		// Where to begin
		$fileOffset = ($partNumber - 1 ) * $partSize;

		// Download the smallest of the remaining bytes and the part size
		$fileBytes = min(filesize($filePath) - $fileOffset, $partSize);
		if ($fileBytes < 0) $fileBytes = 0;

// 		$rest->setHeader('Content-Type', 'application/octet-stream');
		$data = "";

		if ($handle = fopen($filePath, "rb")) {
			if ($fileOffset >0) fseek($handle, $fileOffset);
			$bytes_read = 0;
			while ($fileBytes>0 && $read = fread($handle, max($fileBytes, 131072))) {
				$fileBytes = $fileBytes - strlen($read);
				$bytes_read += strlen($read);
				$data .= $read;
			}
			fclose($handle);
		} else {
			return false;
		}

		$vars['Body'] = $data;

		try {
			$result = $this->client->uploadPart($vars);
			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('ETag')) return $result->get('ETag');
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		return false;

	}

	/**
	* Complete a multi-part upload (http://docs.amazonwebservices.com/AmazonS3/latest/API/mpUploadComplete.html)
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param string $uploadId uploadId returned previously from initiateMultipartUpload
	* @param array $parts an ordered list of eTags of previously uploaded parts from uploadPart
	* @return boolean
	*/
	public function completeMultipartUpload ($bucket, $uri, $uploadId, $parts)
	{
		$vars = array(
			'Bucket' => $bucket,
			'Key' => $uri,
			'UploadId' => $uploadId
		);

		$partno = 1;
		$send_parts = array();
		foreach ($parts as $etag) {
			$send_parts[] = array('ETag' => $etag, 'PartNumber' => $partno);
			$partno++;
		}

		$vars['Parts'] = $send_parts;

		try {
			$result = $this->client->completeMultipartUpload($vars);
			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('ETag')) return true;
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		return false;
	}

	/**
	* Put an object from a file (legacy function)
	*
	* @param string $file Input file path
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	public function putObjectFile($file, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = null, $storageClass = self::STORAGE_CLASS_STANDARD)
	{
		try {
			$options = array(
				'Bucket' => $bucket,
				'Key' => $uri,
				'SourceFile' => $file,
				'StorageClass' => $storageClass,
				'ACL' => $acl
			);
			if ($contentType) $options['ContentType'] = $contentType;
			if (!empty($this->_serverSideEncryption)) $options['ServerSideEncryption'] = $this->_serverSideEncryption;
			if (!empty($metaHeaders)) $options['Metadata'] = $metaHeaders;
			$result = $this->client->putObject($options);
			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('RequestId')) return true;
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		fclose($fh);
	}


	/**
	* Put an object from a string (legacy function)
	*
	* @param string $string Input data
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	// Only the first 3 parameters vary in UpdraftPlus
	public function putObjectString($string, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = 'text/plain')
	{
		try {
			$result = $this->client->putObject(array(
				'Bucket' => $bucket,
				'Key' => $uri,
				'Body' => $string,
				'ContentType' => $contentType
			));
			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('RequestId')) return true;
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		return false;
	}


	/**
	* Get an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param mixed $saveTo Filename or resource to write to
	* @param boolean resume, if possible
	* @return mixed
	*/
	// In UpdraftPlus, $saveTo is always a filename
	public function getObject($bucket, $uri, $saveTo = false, $resume = false)
	{
		try {
			// SaveAs: "Specify where the contents of the object should be downloaded. Can be the path to a file, a resource returned by fopen, or a Guzzle\Http\EntityBodyInterface object." - http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.S3.S3Client.html#_getObject

			$range_header = false;
			if (file_exists($saveTo)) {
				if ($resume && ($fp = @fopen($saveTo, 'ab')) !== false) {
					$range_header = "bytes=".filesize($saveTo).'-';
				} else {
					throw new Exception('Unable to open save file for writing: '.$saveTo);
				}
			} else {
				if (($fp = @fopen($saveTo, 'wb')) !== false) {
					$range_header = false;
				} else {
					throw new Exception('Unable to open save file for writing: '.$saveTo);
				}
			}

			$vars = array(
				'Bucket' => $bucket,
				'Key' => $uri,
				'SaveAs' => $fp
			);
			if (!empty($range_header)) $vars['Range'] = $range_header;

			$result = $this->client->getObject($vars);

			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('RequestId')) return true;
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		return false;
	}


	/**
	* Get a bucket's location
	*
	* @param string $bucket Bucket name
	* @return string | false
	*/
	public function getBucketLocation($bucket)
	{
		try {
			$result = $this->client->getBucketLocation(array('Bucket' => $bucket));
			$location = $result->get('Location');
			if ($location) return $location;
		} catch (Aws\S3\Exception\NoSuchBucketException $e) {
			return false;
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
	}

	private function trigger_from_exception($e) {
		trigger_error($e->getMessage().' ('.get_class($e).') (line: '.$e->getLine().', file: '.$e->getFile().')', E_USER_WARNING);
		return false;
	}

	/**
	* Delete an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @return boolean
	*/
	public function deleteObject($bucket, $uri)
	{
		try {
			$result = $this->client->deleteObject(array(
				'Bucket' => $bucket,
				'Key' => $uri
			));
			if (is_object($result) && method_exists($result, 'get') && '' != $result->get('RequestId')) return true;
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		return false;
	}

	public function setCORS($policy)
	{
		try {
			$cors = $this->client->putBucketCors($policy);
			if (is_object($cors) && method_exists($cors, 'get') && '' != $cors->get('RequestId')) return true;
		} catch (Exception $e) {
			if ($this->useExceptions) {
				throw $e;
			} else {
				return $this->trigger_from_exception($e);
			}
		}
		return false;
		
	}

}

