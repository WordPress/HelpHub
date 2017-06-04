<?php

// helper class for parsing PROPFIND response bodies
class HTTP_WebDAV_Client_parse_propfind_response
{
    // get requested properties as array containing name/namespace pairs
    function HTTP_WebDAV_Client_parse_propfind_response($response)
    {
        $this->urls = array();

        $this->_depth = 0;

        $xml_parser = xml_parser_create_ns("UTF-8", " ");
        xml_set_element_handler($xml_parser,
                                array(&$this, "_startElement"),
                                array(&$this, "_endElement"));
        xml_set_character_data_handler($xml_parser,
                                       array(&$this, "_data"));
        xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING,
                              false);
        $this->success = xml_parse($xml_parser, $response, true);
        xml_parser_free($xml_parser);

        unset($this->_depth);
    }


    function _startElement($parser, $name, $attrs)
    {
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
            if ($ns == "")
                $this->success = false;
        } else {
            $ns  = "";
            $tag = $name;
        }

        switch ($this->_depth) {
        case '2':
            switch ($tag) {
            case 'propstat':
                // TODO check is_executable, lockinfo ...
                $this->_tmpprop = array("mode" => 0100666 /* all may read and write (for now) */);
                break;
            }
        }

        $this->_depth++;
    }

    function _endElement($parser, $name)
    {
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
            if ($ns == "")
                $this->success = false;
        } else {
            $ns  = "";
            $tag = $name;
        }

        $this->_depth--;

        switch ($this->_depth) {
        case '1':
            switch ($tag) {
            case 'response':
                $this->urls[$this->_tmphref] = $this->_tmpvals;
                unset($this->_tmphref);
                unset($this->_tmpvals);
                break;
            }
            break;
        case '2':
            switch ($tag) {
            case 'href':
                $this->_tmphref = $this->_tmpdata;
                break;
            }
        case 'propstat':
            if (isset($this->_tmpstat) && strstr($this->_tmpstat, " 200 ")) {
                $this->_tmpvals = $this->_tmpprop;
            }
            unset($this->_tmpstat);
            unset($this->_tmpprop);
            break;
        case '3':
            switch ($tag) {
            case 'status':
                $this->_tmpstat = $this->_tmpdata;
                break;
            }
        case '4':
            switch ($tag) {
            case 'getlastmodified':
                $this->_tmpprop['atime'] = strtotime($this->_tmpdata);
                $this->_tmpprop['mtime'] = strtotime($this->_tmpdata);
                break;
            case 'creationdate':
                $t = preg_split("/[^[:digit:]]/", $this->_tmpdata);
                $this->_tmpprop['ctime'] = mktime($t[3], $t[4], $t[5], $t[1], $t[2], (int)$t[0]);
                unset($t);
                break;
            case 'getcontentlength':
                $this->_tmpprop['size'] = $this->_tmpdata;
                break;
            }
        case '5':
            switch ($tag) {
            case 'collection':
                $this->_tmpprop['mode'] &= ~0100000; // clear S_IFREG
                $this->_tmpprop['mode'] |= 040000; // set S_IFDIR
                break;
            }
        }

        unset($this->_tmpdata);
    }

    function _data($parser, $data)
    {
        $this->_tmpdata = $data;
    }

    function stat($href = false)
    {
        if ($href) {
            // TODO
        } else {
            reset($this->urls);
            return current($this->urls);
        }
    }
}


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode:nil
 * End:
 */
