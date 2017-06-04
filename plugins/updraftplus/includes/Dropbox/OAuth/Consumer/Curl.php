<?php

/**
* OAuth consumer using PHP cURL
* @author Ben Tadiar <ben@handcraftedbyben.co.uk>
* @link https://github.com/benthedesigner/dropbox
* @package Dropbox\OAuth
* @subpackage Consumer
*/

class Dropbox_Curl extends Dropbox_ConsumerAbstract
{    
    /**
     * Default cURL options
     * @var array
     */
    protected $defaultOptions = array(
        CURLOPT_VERBOSE        => true,
        CURLOPT_HEADER         => true,
        CURLINFO_HEADER_OUT    => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
    );
    
     /**
     * Store the last response form the API
     * @var mixed
     */
    protected $lastResponse = null;
    
    /**

    /**
     * Set properties and begin authentication
     * @param string $key
     * @param string $secret
     * @param \Dropbox\OAuth\Consumer\StorageInterface $storage
     * @param string $callback
     */
    public function __construct($key, $secret, Dropbox_StorageInterface $storage, $callback = null)
    {
        // Check the cURL extension is loaded
        if (!extension_loaded('curl')) {
            throw new Dropbox_Exception('The cURL OAuth consumer requires the cURL extension. Please speak to your web hosting provider so that this missing PHP component can be installed.');
        }
        
        $this->consumerKey = $key;
        $this->consumerSecret = $secret;
        $this->storage = $storage;
        $this->callback = $callback;
        $this->authenticate();
    }

    /**
     * Execute an API call
     * @todo Improve error handling
     * @param string $method The HTTP method
     * @param string $url The API endpoint
     * @param string $call The API method to call
     * @param array $additional Additional parameters
     * @return string|object stdClass
     */
    public function fetch($method, $url, $call, array $additional = array())
    {
        // Get the signed request URL
        $request = $this->getSignedRequest($method, $url, $call, $additional);
        
        // Initialise and execute a cURL request
        $handle = curl_init($request['url']);
        
        // Get the default options array
        $options = $this->defaultOptions;
        if (!UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts')) {
            $options[CURLOPT_CAINFO] = UPDRAFTPLUS_DIR.'/includes/cacert.pem';
        }
        if (UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify')) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
        } else {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
        }

        if (!class_exists('WP_HTTP_Proxy')) require_once(ABSPATH.WPINC.'/class-http.php');
        $proxy = new WP_HTTP_Proxy();

        if ($proxy->is_enabled()) {
            # WP_HTTP_Proxy returns empty strings if nothing is set
            $user = $proxy->username();
            $pass = $proxy->password();
            $host = $proxy->host();
            $port = (int)$proxy->port();
            if (empty($port)) $port = 8080;
            if (!empty($host) && $proxy->send_through_proxy($request['url'])) {
                  $options[CURLOPT_PROXY] = $host;
                  $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
                  $options[CURLOPT_PROXYPORT] = $port;
                  if (!empty($user) && !empty($pass)) {
                        $options[CURLOPT_PROXYAUTH] = CURLAUTH_ANY;
                        $options[CURLOPT_PROXYUSERPWD] = sprintf('%s:%s', $user, $pass);
                  }
            }
        }
        
        if ($method == 'GET' && $this->outFile) { // GET
            $options[CURLOPT_RETURNTRANSFER] = false;
            $options[CURLOPT_HEADER] = false;
            $options[CURLOPT_FILE] = $this->outFile;
            $options[CURLOPT_BINARYTRANSFER] = true;
            $options[CURLOPT_FAILONERROR] = true;
            if (isset($additional['headers'])) $options[CURLOPT_HTTPHEADER] = $additional['headers'];
            $this->outFile = null;
        } elseif ($method == 'POST') { // POST
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $request['postfields'];
        } elseif ($method == 'PUT' && $this->inFile) { // PUT
            $options[CURLOPT_PUT] = true;
            $options[CURLOPT_INFILE] = $this->inFile;
            // @todo Update so the data is not loaded into memory to get its size
            $options[CURLOPT_INFILESIZE] = strlen(stream_get_contents($this->inFile));
            fseek($this->inFile, 0);
            $this->inFile = null;
        }

        // Set the cURL options at once
        curl_setopt_array($handle, $options);
        
        // Execute, get any error and close
        $response = curl_exec($handle);
        $error = curl_error($handle);
        $getinfo = curl_getinfo($handle);

        curl_close($handle);
        
        //Check if a cURL error has occured
        if ($response === false) {
            throw new Dropbox_CurlException($error);
        } else {
            // Parse the response if it is a string
            if (is_string($response)) {
                $response = $this->parse($response);
            }
            
            // Set the last response
            $this->lastResponse = $response;

            $code = (!empty($response['code'])) ? $response['code'] : $getinfo['http_code'];
            
            // The API doesn't return an error message for the 304 status code...
            // 304's are only returned when the path supplied during metadata calls has not been modified
            if ($code == 304) {
                $response['body'] = new stdClass;
                $response['body']->error = 'The folder contents have not changed';
            }
            
            // Check if an error occurred and throw an Exception
            if (!empty($response['body']->error)) {
                // Dropbox returns error messages inconsistently...
                if ($response['body']->error instanceof stdClass) {
                    $array = array_values((array) $response['body']->error);
                    $message = $array[0];
                } else {
                    $message = $response['body']->error;
                }
                     
                // Throw an Exception with the appropriate with the appropriate message and code
                switch ($code) {
                    case 304:
                        throw new Dropbox_NotModifiedException($message, 304);
                    case 400:
                        throw new Dropbox_BadRequestException($message, 400);
                    case 404:
                        throw new Dropbox_NotFoundException($message, 404);
                    case 406:
                        throw new Dropbox_NotAcceptableException($message, 406);
                    case 415:
                        throw new Dropbox_UnsupportedMediaTypeException($message, 415);
                    default:
                        throw new Dropbox_Exception($message, $code);
                }
            }
                
            return $response;
        }
    }
    
    /**
     * Parse a cURL response
     * @param string $response 
     * @return array
     */
    private function parse($response)
    {
        // Explode the response into headers and body parts (separated by double EOL)
        list($headers, $response) = explode("\r\n\r\n", $response, 2);
        
        // Explode response headers
        $lines = explode("\r\n", $headers);
        
        // If the status code is 100, the API server must send a final response
        // We need to explode the response again to get the actual response
        if (preg_match('#^HTTP/1.1 100#i', $lines[0])) {
            list($headers, $response) = explode("\r\n\r\n", $response, 2);
            $lines = explode("\r\n", $headers);
        }
        
        // Get the HTTP response code from the first line
        $first = array_shift($lines);
        $pattern = '#^HTTP/1.1 ([0-9]{3})#i';
        preg_match($pattern, $first, $matches);
        $code = $matches[1];
        
        // Parse the remaining headers into an associative array
        $headers = array();
        foreach ($lines as $line) {
            list($k, $v) = explode(': ', $line, 2);
            $headers[strtolower($k)] = $v;
        }
        
        // If the response body is not a JSON encoded string
        // we'll return the entire response body
        if (!$body = json_decode($response)) {
            $body = $response;
        }

         if (is_string($body)) {
             $body_lines = explode("\r\n", $body);
             if (preg_match('#^HTTP/1.1 100#i', $body_lines[0]) && preg_match('#^HTTP/1.#i', $body_lines[2])) {
             return $this->parse($body);
             }
         }
        
        return array('code' => $code, 'body' => $body, 'headers' => $headers);
    }

    /**
     * Return the response for the last API request
     * @return mixed
     */
    public function getlastResponse()
    {
       return $this->lastResponse;
     }
}
