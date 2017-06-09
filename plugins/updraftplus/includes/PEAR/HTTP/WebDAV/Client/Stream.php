<?php
require_once "HTTP/Request.php";

require "HTTP/WebDAV/Tools/_parse_propfind_response.php";
require "HTTP/WebDAV/Tools/_parse_lock_response.php";

// WebDAV defines some addition HTTP methods
define('HTTP_REQUEST_METHOD_COPY',      'COPY');
define('HTTP_REQUEST_METHOD_MOVE',      'MOVE');
define('HTTP_REQUEST_METHOD_MKCOL',     'MKCOL');
define('HTTP_REQUEST_METHOD_PROPFIND',  'PROPFIND');
define('HTTP_REQUEST_METHOD_PROPPATCH', 'PROPPATCH');
define('HTTP_REQUEST_METHOD_LOCK',      'LOCK');
define('HTTP_REQUEST_METHOD_UNLOCK',    'UNLOCK');

/**
 * A stream wrapper class for WebDAV access
 *
 * @access public
 */
class HTTP_WebDAV_Client_Stream 
{
     /**
     * User-Agent: header string
     *
     * @access private
     * @var    string
     */
    var $userAgent = "PEAR::HTTP_WebDAV_Client";

    /**
     * Content-type: header string
     *
     * @access private
     * @var    string
     */
    var $contentType = "application/octet-stream";

    /**
     * The http or https resource URL 
     *
     * @access private
     * @var    string  url
     */
    var $url = false;

    /**
     * The resource URL path
     *
     * @access private
     * @var    string  path
     */
    var $path = false;

    /**
     * File position indicator
     *
     * @access private
     * @var    int     offset in bytes
     */
    var $position = 0;

    /**
     * File status information cache
     *
     * @access private
     * @var    array   stat information
     */
    var $stat = array();

    /**
     * User name for authentication
     *
     * @access private
     * @var    string  name
     */
    var $user = false;

    /**
     * Password for authentication
     *
     * @access private
     * @var    string  password
     */
    var $pass = false;

    /**
     * WebDAV protocol levels supported by the server
     *
     * @access private
     * @var    array   level entries
     */
    var $dav_level = array();

    /**
     * HTTP methods supported by the server
     *
     * @access private
     * @var    array   method entries
     */
    var $dav_allow = array();

    /**
     * Directory content cache
     *
     * @access private
     * @var    array   filename entries
     */
    var $dirfiles = false;

    /**
     * Current readdir() position 
     *
     * @access private
     * @var    int
     */
    var $dirpos = 0;

    /**
     * Remember if end of file was reached
     *
     * @access private
     * @var    bool
     */
    var $eof = false;

    /**
     * Lock token 
     *
     * @access private
     * @var    string
     */
    var $locktoken = false;

    var $stream_write_returned_recoverable_error = false;
    var $stream_write_final = false;

    /**
     * Stream wrapper interface open() method
     *
     * @access public
     * @var    string resource URL
     * @var    string mode flags
     * @var    array  not used here
     * @var    string return real path here if suitable
     * @return bool   true on success
     */
    function stream_open($path, $mode, $options, &$opened_path) 
    {

        // rewrite the request URL
        if (!$this->_parse_url($path)) return false;

        // query server for WebDAV options
        if (!$this->_check_options())  return false;

        // now get the file metadata
        // we only need type, size, creation and modification date
        $req = $this->_startRequest(HTTP_REQUEST_METHOD_PROPFIND);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        $req->addHeader("Depth", "0");
        $req->addHeader("Content-Type", "text/xml");
        $req->addRawPostData('<?xml version="1.0" encoding="utf-8"?>
<propfind xmlns="DAV:">
 <prop>
  <resourcetype/>
  <getcontentlength/>
  <getlastmodified />
  <creationdate/>
 </prop>
</propfind>
');
        $req->sendRequest();

        // check the response code, anything but 207 indicates a problem
        switch ($req->getResponseCode()) {
        case 207: // OK
            // now we have to parse the result to get the status info items
            #$propinfo = &new HTTP_WebDAV_Client_parse_propfind_response($req->getResponseBody());
            $propinfo = new HTTP_WebDAV_Client_parse_propfind_response($req->getResponseBody());
            $this->stat = $propinfo->stat();
            unset($propinfo);
            break;

        case 404: // not found is ok in write modes
            if (preg_match('|[aw\+]|', $mode)) {
                break; // write
            } 
            $this->eof = true;
            // else fallthru
        default: 
            trigger_error("file not found: ".$req->getResponseCode());
            return false;
        }
        
        // 'w' -> open for writing, truncate existing files
        if (strpos($mode, "w") !== false) {
            $req = $this->_startRequest(HTTP_REQUEST_METHOD_PUT);

            $req->addHeader('Content-length', 0);

            if (is_string($this->user)) {
                $req->setBasicAuth($this->user, @$this->pass);          
            }

            $req->sendRequest();
        }

        // 'a' -> open for appending
        if (strpos($mode, "a") !== false) {
            $this->position = (!empty($this->stat['size'])) ? $this->stat['size'] : 0;            
            $this->eof = true;
        }

        // we are done :)
        return true;
    }


    /**
     * Streap wrapper interface close() method
     *
     * @access public
     */
    function stream_close() 
    {
        global $updraftplus, $updraftplus_webdav_filepath;
        if ($this->stream_write_returned_recoverable_error) {
            if (!empty($updraftplus_webdav_filepath) && is_readable($updraftplus_webdav_filepath)) {
                $this->position = 0;
                $this->stream_write_final = true;
                if (false === $this->stream_write(file_get_contents($updraftplus_webdav_filepath))) {
                    $this->stream_write_final = false;
                    $updraftplus->log('WebDAV: All-in-one write failed');
                    // The return result is ignored; so, we throw an exception instead
                    throw new Exception('WebDAV: All-in-one write failed');
                    return false;
                } else {
                    $updraftplus->log('WebDAV: All-in-one write succeeded');
                }
                $this->stream_write_final = false;
            } else {
                $updraftplus->log("File not readable: $updraftplus_webdav_filepath");
                throw new Exception("File not readable: $updraftplus_webdav_filepath");
                return false;
            }
        }

        // unlock?
        if ($this->locktoken) {
            $this->stream_lock(LOCK_UN);
        }

        // closing is simple as HTTP is stateless 
        $this->url = false;
    }

    /**
     * Stream wrapper interface stat() method
     *
     * @access public
     * @return array  stat entries
     */
    function stream_stat() 
    {
        // we already have collected the needed information 
        // in stream_open() :)
        return $this->stat;
    }

    /**
     * Stream wrapper interface read() method
     *
     * @access public
     * @param  int    requested byte count
     * @return string read data
     */
    function stream_read($count) 
    {
        // do some math
        $start = $this->position;
        $end   = $start + $count - 1;

        // create a GET request with a range
        $req = &$this->_startRequest(HTTP_REQUEST_METHOD_GET);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        $req->addHeader("Range", "bytes=$start-$end");

        // go! go! go!
        $req->sendRequest();
        $data = $req->getResponseBody();
        $len  = strlen($data);

        // lets see what happened
        switch ($req->getResponseCode()) {
        case 200: 
            // server doesn't support range requests 
            // TODO we should add some sort of cacheing here
            $data = substr($data, $start, $count);
            break;

        case 206:
            // server supports range requests
            break;

        case 416:
            // reading beyond end of file is not an error
            $data = "";
            $len  = 0;
            break;

        default: 
            return false;
        }

        // no data indicates end of file
        if (!$len) {
            $this->eof = true;
        }

        // update position
        $this->position += $len;

        // thats it!
        return $data;
    }

    /**
     * Stream wrapper interface write() method
     *
     * @access public
     * @param  string data to write
     * @return int    number of bytes actually written
     */
    function stream_write($buffer) 
    {
        // do some math
        $start = $this->position;
        $end   = $this->position + strlen($buffer) - 1;
        
        if (((defined('UPDRAFTPLUS_WEBDAV_NEVER_CHUNK') && UPDRAFTPLUS_WEBDAV_NEVER_CHUNK) || $this->stream_write_returned_recoverable_error) && !$this->stream_write_final) {
            $this->position += strlen($buffer);
            return 1 + $end - $start;
        }

		$method = ($start > 0 && defined('UPDRAFTPLUS_WEBDAV_USE_SABRE_APPEND') && UPDRAFTPLUS_WEBDAV_USE_SABRE_APPEND) ? 'PATCH' : HTTP_REQUEST_METHOD_PUT;

        // create a partial PUT request
        $req = $this->_startRequest($method);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }

        if (defined('UPDRAFTPLUS_WEBDAV_USE_SABRE_APPEND') && UPDRAFTPLUS_WEBDAV_USE_SABRE_APPEND) {

		if ($start>0) {
			$req->addHeader('Content-Type', 'application/x-sabredav-partialupdate');
			$req->addHeader("X-Update-Range", "append");
		}
        } else {
        # Special hack to drop Content-Range header for the test file
		if (($start>0 || $end>8) && !$this->stream_write_final) $req->addHeader("Content-Range", "bytes $start-$end/*");
        }
        if ($this->locktoken) {
            $req->addHeader("If", "(<{$this->locktoken}>)");
        }
        $req->addRawPostData($buffer);

        // go! go! go!
        $req->sendRequest();

        // check result
        switch ($req->getResponseCode()) {
        case 200:
        case 201:
        case 204:
            $this->position += strlen($buffer);
            return 1 + $end - $start;

        // New in UD 1.11.13 for ownCloud 8.1.? (strictly, the version of SabreDav in it)
        /*
        <?xml version="1.0" encoding="utf-8"?>
<d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
  <s:exception>Sabre\DAV\Exception\BadRequest</s:exception>
  <s:message>Content-Range on PUT requests are forbidden.</s:message>
</d:error>
        */
        case 400:
            global $updraftplus, $updraftplus_webdav_filepath;
            if (false !== strpos($req->getResponseBody(), 'Content-Range') && !empty($updraftplus_webdav_filepath)) {
                $updraftplus->log('WebDAV server returned 400 due to Content-Range issue; will try all-at-once method');
                $this->stream_write_returned_recoverable_error = true;
                # You lie!
                return 1 + $end - $start;
            } else {
              trigger_error("Unexpected HTTP response code: ".$req->getResponseCode());
              return false;
            }
             

        case 501:
            global $updraftplus, $updraftplus_webdav_filepath;
            if (!empty($updraftplus_webdav_filepath)) {
                $updraftplus->log('WebDAV server returned 501; probably does not support Content-Range; will try all-at-once method');
                $this->stream_write_returned_recoverable_error = true;
                # You lie!
                return 1 + $end - $start;
            } else {
                return false;
            }
            
        default: 
            trigger_error("Unexpected HTTP response code: ".$req->getResponseCode());
            return false;
        }

        /* 
         We do not cope with servers that do not support partial PUTs!
         And we do assume that a server does conform to the following 
         rule from RFC 2616 Section 9.6:

         "The recipient of the entity MUST NOT ignore any Content-* 
         (e.g. Content-Range) headers that it does not understand or 
         implement and MUST return a 501 (Not Implemented) response 
         in such cases."
           
         So the worst case scenario with a compliant server not 
         implementing partial PUTs should be a failed request. A 
         server simply ignoring "Content-Range" would replace 
         file contents with the request body instead of putting
         the data at the requested place but we can blame it 
         for not being compliant in this case ;)

         (TODO: maybe we should do a HTTP version check first?)
 
         we *could* emulate partial PUT support by adding local
         cacheing but for now we don't want to as it adds a lot
         of complexity and storage overhead to the client ...
        */
    }

    /**
     * Stream wrapper interface eof() method
     *
     * @access public
     * @return bool   true if end of file was reached
     */
    function stream_eof() 
    {
        // another simple one 
        return $this->eof;
    }

    /**
     * Stream wrapper interface tell() method
     *
     * @access public
     * @return int    current file position
     */
    function stream_tell() 
    {
        // just return the current position
        return $this->position;
    }

    /**
     * Stream wrapper interface seek() method
     *
     * @access public
     * @param  int    position to seek to
     * @param  int    seek mode
     * @return bool   true on success
     */
    function stream_seek($pos, $whence) 
    {
        switch ($whence) {
        case SEEK_SET:
            // absolute position
            $this->position = $pos;
            break;
        case SEEK_CUR:
            // relative position
            $this->position += $pos;
            break;
        case SEEK_END:
            // relative position form end
            $this->position = $this->stat['size'] + $pos;
            break;
        default: 
            return false;
        }

        // TODO: this is rather naive (check how libc handles this)
        $this->eof = false;

        return true;
    }


    /**
     * Stream wrapper interface URL stat() method
     *
     * @access public
     * @param  string URL to get stat information for
     * @return array  stat information
     */
    function url_stat($url) 
    {
        // we map this one to open()/stat()/close()
        // there won't be much gain in inlining this
        if (!$this->stream_open($url, "r", array(), $dummy)) {
            return false;
        }
        $stat =  $this->stream_stat();
        $this->stream_close();

        return $stat;
    }





    /**
     * Stream wrapper interface opendir() method
     *
     * @access public
     * @param  string directory resource URL
     * @param  array  not used here
     * @return bool   true on success
     */
    function dir_opendir($path, $options) 
    {
        // rewrite the request URL
        if (!$this->_parse_url($path)) return false;

        // query server for WebDAV options
        if (!$this->_check_options())  return false;

        if (!isset($this->dav_allow[HTTP_REQUEST_METHOD_PROPFIND])) {
            return false;
        }

        // now read the directory
        $req = &$this->_startRequest(HTTP_REQUEST_METHOD_PROPFIND);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        $req->addHeader("Depth", "1");
        $req->addHeader("Content-Type", "text/xml");
        $req->addRawPostData('<?xml version="1.0" encoding="utf-8"?>
<propfind xmlns="DAV:">
 <prop>
  <resourcetype/>
  <getcontentlength/>
  <creationdate/>
  <getlastmodified/>
 </prop>
</propfind>
');
        $req->sendRequest();

        switch ($req->getResponseCode()) {
        case 207: // multistatus content
            $this->dirfiles = array();
            $this->dirpos = 0;

            // for all returned resource entries
            foreach (explode("\n", $req->getResponseBody()) as $line) {
            	// Preg_match_all if the whole response is one line!
                if (preg_match_all("/href>([^<]*)/", $line, $matches)) {
                    // skip the directory itself                    
                    foreach ($matches[1] as $match){
                    	// Compare to $this->url too
	                    if ($match == "" || $match == $this->path || $match == $this->url) {
	                    	continue;
	                    }
	                    // just remember the basenames to return them later with readdir()
	                    $this->dirfiles[] = basename($match);
                    }
                }
            }
            return true;

        default: 
            // any other response state indicates an error
            trigger_error("file not found");
            return false;
        }
    }


    /**
     * Stream wrapper interface readdir() method
     *
     * @access public
     * @return string filename
     */
    function dir_readdir() 
    {
        // bailout if directory is empty
        if (!is_array($this->dirfiles)) {
            return false;
        }
        
        // bailout if we already reached end of dir
        if ($this->dirpos >= count($this->dirfiles)) {
            return false;
        }

        // return an entry and move on
        return $this->dirfiles[$this->dirpos++];
    }

    /**
     * Stream wrapper interface rewinddir() method
     *
     * @access public
     */
    function dir_rewinddir() 
    {
        // bailout if directory content info has already
        // been freed
        if (!is_array($this->dirfiles)) {
            return false;
        }

        // rewind to first entry
        $this->dirpos = 0;
    }

    /**
     * Stream wrapper interface closedir() method
     *
     * @access public
     */
    function dir_closedir() 
    {
        // free stored directory content
        if (is_array($this->dirfiles)) {
            $this->dirfiles = false;
            $this->dirpos = 0;
        }
    }


    /**
     * Stream wrapper interface mkdir() method
     *
     * @access public
     * @param  string collection URL to be created
     * @return bool   true on access
     */
    function mkdir($path) 
    {
        // rewrite the request URL
        if (!$this->_parse_url($path)) return false;

        // query server for WebDAV options
        if (!$this->_check_options())  return false;

        $req = &$this->_startRequest(HTTP_REQUEST_METHOD_MKCOL);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        if ($this->locktoken) {
            $req->addHeader("If", "(<{$this->locktoken}>)");
        }
        $req->sendRequest();

        // check the response code, anything but 201 indicates a problem
        $stat = $req->getResponseCode();
        switch ($stat) {
        case 201:
            return true;
        default:
            trigger_error("mkdir failed - ". $stat);
            return false;
        }
    }


    /**
     * Stream wrapper interface rmdir() method
     *
     * @access public
     * @param  string collection URL to be created
     * @return bool   true on access
     */
    function rmdir($path) 
    {
        // TODO: this should behave like "rmdir", currently it is more like "rm -rf"

        // rewrite the request URL
        if (!$this->_parse_url($path)) return false;

        // query server for WebDAV options
        if (!$this->_check_options())  return false;

        $req = &$this->_startRequest(HTTP_REQUEST_METHOD_DELETE);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        if ($this->locktoken) {
            $req->addHeader("If", "(<{$this->locktoken}>)");
        }
        $req->sendRequest();

        // check the response code, anything but 204 indicates a problem
        $stat = $req->getResponseCode();
        switch ($stat) {
        case 204:
            return true;
        default:
            trigger_error("rmdir failed - ". $stat);
            return false;
        }
    }
     

    /**
     * Stream wrapper interface rename() method
     *
     * @access public
     * @param  string resource URL to be moved
     * @param  string resource URL to move to
     * @return bool   true on access
     */
    function rename($path, $new_path) 
    {
        // rewrite the request URL
        if (!$this->_parse_url($path)) return false;

        // query server for WebDAV options
        if (!$this->_check_options())  return false;

        $req = &$this->_startRequest(HTTP_REQUEST_METHOD_MOVE);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        if ($this->locktoken) {
            $req->addHeader("If", "(<{$this->locktoken}>)");
        }
        if (!$this->_parse_url($new_path)) return false;
        $req->addHeader("Destination", $this->url);
        $req->sendRequest();

        // check the response code, anything but 207 indicates a problem
        $stat = $req->getResponseCode();
        switch ($stat) {
        case 201:
        case 204:
            return true;
        default:
            trigger_error("rename failed - ". $stat);
            return false;
        }
    }
     

    /**
     * Stream wrapper interface unlink() method
     *
     * @access public
     * @param  string resource URL to be removed
     * @return bool   true on success
     */
    function unlink($path) 
    {
        // rewrite the request URL
        if (!$this->_parse_url($path)) return false;

        // query server for WebDAV options
        if (!$this->_check_options())  return false;

        // is DELETE supported?
        if (!isset($this->dav_allow[HTTP_REQUEST_METHOD_DELETE])) {
            return false;
        }       

        $req = $this->_startRequest(HTTP_REQUEST_METHOD_DELETE);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        if ($this->locktoken) {
            $req->addHeader("If", "(<{$this->locktoken}>)");
        }
        $req->sendRequest();

        switch ($req->getResponseCode()) {
        case 204: // ok
            return true;
        default: 
            return false;
        }
    }
        

    /**
     * Static helper that registers the wrappers
     *
     * @access public, static
     * @return bool   true on success (even if SSL doesn't work)
     */
    static function register() 
    {
        // check that we have the required feature
        if (!function_exists("stream_register_wrapper")) {
            return false;
        }

        // try to register the non-encrypted WebDAV wrapper
        if (!stream_register_wrapper("webdav", "HTTP_WebDAV_Client_Stream")) {
            return false;
        }

        // now try to register the SSL protocol variant
        // it is not critical if this fails
        // TODO check whether SSL is possible with HTTP_Request
        stream_register_wrapper("webdavs", "HTTP_WebDAV_Client_Stream");

        return true;
    }


    /**
     * Helper function for URL analysis
     *
     * @access private
     * @param  string  original request URL
     * @return bool    true on success else false
     */
    function _parse_url($path) 
    {
        // rewrite the WebDAV url as a plain HTTP url
        $url = parse_url($path);

        // detect whether plain or SSL-encrypted transfer is requested
        $scheme = $url['scheme'];
        switch ($scheme) {
        case "webdav":
            $url['scheme'] = "http";
            break;
        case "webdavs":
            $url['scheme'] = "https";
            break;
        default:
            trigger_error("only 'webdav:' and 'webdavs:' are supported, not '$url[scheme]:'");
            return false;
        }

        if (isset($this->context)) {
            // extract settings from stream context
            $context = stream_context_get_options($this->context);

            // User-Agent
            if (isset($context[$scheme]['user_agent'])) {
                $this->userAgent = $context[$scheme]['user_agent'];
            }

            // Content-Type
            if (isset($context[$scheme]['content_type'])) {
                $this->contentType = $context[$scheme]['content_type'];
            }
            
            // TODO check whether to implement other HTTP specific
            // context settings from http://php.net/manual/en/context.http.php
        }


        // if a TCP port is specified we have to add it after the host
        if (isset($url['port'])) {
            $url['host'] .= ":$url[port]";
        }

        // store the plain path for possible later use
        $this->path = $url["path"];

        // now we can put together the new URL
        $this->url = "$url[scheme]://$url[host]$url[path]";

        // extract authentication information
        if (isset($url['user'])) {
            $this->user = urldecode($url['user']);
        }
        if (isset($url['pass'])) {
            $this->pass = urldecode($url['pass']);
        }

        return true;
    }

    /**
     * Helper function for WebDAV OPTIONS detection
     *
     * @access private
     * @return bool    true on success else false
     */
    function _check_options() 
    {
        // now check OPTIONS reply for WebDAV response headers
        $req = $this->_startRequest(HTTP_REQUEST_METHOD_OPTIONS);
        if (is_string($this->user)) {
            $req->setBasicAuth($this->user, @$this->pass);          
        }
        $req->sendRequest();
        if ($req->getResponseCode() != 200) {
            return false;
        }

        // get the supported DAV levels and extensions
        $dav = $req->getResponseHeader("DAV");
        $this->dav_level = array();
        foreach (explode(",", $dav) as $level) {
            $this->dav_level[trim($level)] = true;
        }
        if (!isset($this->dav_level["1"])) {
            // we need at least DAV Level 1 conformance
            return false;
        }
        
        // get the supported HTTP methods
        // TODO these are not checked for WebDAV compliance yet
        $allow = $req->getResponseHeader("Allow");
        $this->dav_allow = array();
        foreach (explode(",", $allow) as $method) {
            $this->dav_allow[trim($method)] = true;
        }

        // TODO check for required WebDAV methods

        return true;
    }


    /**
     * Stream handler interface lock() method (experimental ...)
     *
     * @access private
     * @return bool    true on success else false
     */
    function stream_lock($mode) 
    {
        /* TODO:
         - think over how to refresh locks
        */
        
        $ret = false;

        // LOCK is only supported by DAV Level 2
        if (!isset($this->dav_level["2"])) {
            return false;
        }

        switch ($mode & ~LOCK_NB) {
        case LOCK_UN:
            if ($this->locktoken) {
                $req = &$this->_startRequest(HTTP_REQUEST_METHOD_UNLOCK);
                if (is_string($this->user)) {
                    $req->setBasicAuth($this->user, @$this->pass);          
                }
                $req->addHeader("Lock-Token", "<{$this->locktoken}>");
                $req->sendRequest();

                $ret = $req->getResponseCode() == 204;
            }
            break;

        case LOCK_SH:
        case LOCK_EX:
            $body = sprintf('<?xml version="1.0" encoding="utf-8" ?> 
<D:lockinfo xmlns:D="DAV:"> 
 <D:lockscope><D:%s/></D:lockscope> 
 <D:locktype><D:write/></D:locktype> 
 <D:owner>%s</D:owner> 
</D:lockinfo>',
                            ($mode & LOCK_SH) ? "shared" : "exclusive",
                            get_class($this)); // TODO better owner string
            $req = &$this->_startRequest(HTTP_REQUEST_METHOD_LOCK);
            if (is_string($this->user)) {
                $req->setBasicAuth($this->user, @$this->pass);          
            }
            if ($this->locktoken) { // needed for refreshing a lock
                $req->addHeader("Lock-Token", "<{$this->locktoken}>");
            }
            $req->addHeader("Timeout", "Infinite, Second-4100000000");
            $req->addHeader("Content-Type", 'text/xml; charset="utf-8"');
            $req->addRawPostData($body);
            $req->sendRequest();

            $ret = $req->getResponseCode() == 200;          

            if ($ret) {
                #$propinfo = &new HTTP_WebDAV_Client_parse_lock_response($req->getResponseBody());               
                $propinfo = new HTTP_WebDAV_Client_parse_lock_response($req->getResponseBody());               
                $this->locktoken = $propinfo->locktoken;
                // TODO deal with timeout
            }
            break;
            
        default:
            break;
        }

        return $ret;
    }

    function _startRequest($method)
    {
        #$req = &new HTTP_Request($this->url);
        $req = new HTTP_Request($this->url);

        $req->addHeader('User-agent',   $this->userAgent);
        $req->addHeader('Content-type', $this->contentType);

        $req->setMethod($method);

        return $req;        
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode:nil
 * End:
 */
