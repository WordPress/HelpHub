<?php

/**
 * OAuth storage handler using WordPress options
 * This can only be used if you have a WordPress environment loaded, such that the (get|update|delete)_option functions are available
 * See an example usage in http://wordpress.org/extend/plugins/updraftplus
 * @author David Anderson <david@wordshell.net>
 * @link http://wordshell.net
 * @package Dropbox\Oauth
 * @subpackage Storage
 */

class Dropbox_WordPress implements Dropbox_StorageInterface
{
    /**
     * Option name
     * @var string
     */
    protected $option_name_prefix = 'dropbox_token';
    
    /**
     * Option name (array storage)
     * @var string
     */
    protected $option_array = '';
    
    /**
     * Encyption object
     * @var Encrypter|null
     */
    protected $encrypter = null;
    
    /**
     * Check if an instance of the encrypter is passed, set the encryption object
     * @return void
     */
    public function __construct(Dropbox_Encrypter $encrypter = null, $option_name_prefix = 'dropbox_token', $option_array = 'dropbox')
    {
        if ($encrypter instanceof Dropbox_Encrypter) {
            $this->encrypter = $encrypter;
        }

	$this->option_name_prefix = $option_name_prefix;
	$this->option_array = $option_array;

    }
    
    /**
     * Get an OAuth token from the database
     * If the encryption object is set then decrypt the token before returning
     * @param string $type Token type to retrieve
     * @return array|bool
     */
    public function get($type)
    {
        if ($type != 'request_token' && $type != 'access_token') {
            throw new Dropbox_Exception("Expected a type of either 'request_token' or 'access_token', got '$type'");
        } else {
            if (false !== ($opts = UpdraftPlus_Options::get_updraft_option($this->option_array))) {
                if (!empty($opts[$this->option_name_prefix.$type])) {
                    $gettoken = $opts[$this->option_name_prefix.$type];
                    $token = $this->decrypt($gettoken);
                    return $token;
                }
            }
            return false;
        }
    }
    
    /**
     * Set an OAuth token in the database by type
     * If the encryption object is set then encrypt the token before storing
     * @param \stdClass Token object to set
     * @param string $type Token type
     * @return void
     */
    public function set($token, $type)
    {
        if ($type != 'request_token' && $type != 'access_token') {
            throw new Dropbox_Exception("Expected a type of either 'request_token' or 'access_token', got '$type'");
        } else {
            $token = $this->encrypt($token);
            $opts = UpdraftPlus_Options::get_updraft_option($this->option_array);
            $opts[$this->option_name_prefix.$type] = $token;
            UpdraftPlus_Options::update_updraft_option($this->option_array, $opts);
        }
    }
    
    /**
     * Delete the request and access tokens currently stored in the database
     * @return bool
     */
    public function delete()
    {
        $opts = UpdraftPlus_Options::get_updraft_option($this->option_array);
        $opts[$this->option_name_prefix.'request_token'] = '';
        $opts[$this->option_name_prefix.'access_token'] = '';
        UpdraftPlus_Options::update_updraft_option($this->option_array, $opts);
        return true;
    }
    
    /**
     * Use the Encrypter to encrypt a token and return it
     * If there is not encrypter object, return just the 
     * serialized token object for storage
     * @param stdClass $token OAuth token to encrypt
     * @return stdClass|string
     */
    protected function encrypt($token)
    {
        // Serialize the token object
        $token = serialize($token);
        
        // Encrypt the token if there is an Encrypter instance
        if ($this->encrypter instanceof Dropbox_Encrypter) {
            $token = $this->encrypter->encrypt($token);
        }
        
        // Return the token
        return $token;
    }
    
    /**
     * Decrypt a token using the Encrypter object and return it
     * If there is no Encrypter object, assume the token was stored
     * serialized and return the unserialized token object
     * @param stdClass $token OAuth token to encrypt
     * @return stdClass|string
     */
    protected function decrypt($token)
    {
        // Decrypt the token if there is an Encrypter instance
        if ($this->encrypter instanceof Dropbox_Encrypter) {
            $token = $this->encrypter->decrypt($token);
        }
        
        // Return the unserialized token
        return @unserialize($token);
    }
}
