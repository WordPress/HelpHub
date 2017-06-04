<?php

    // helper class for parsing LOCK request bodies
class HTTP_WebDAV_Client_parse_lock_response
{
    var $locktoken = "";
    var $collect_locktoken = false;
        
    function HTTP_WebDAV_Client_parse_lock_response($response) 
    {
        $xml_parser = xml_parser_create_ns("UTF-8", " ");
        xml_set_element_handler($xml_parser,
                                array(&$this, "_startElement"),
                                array(&$this, "_endElement"));
        xml_set_character_data_handler($xml_parser,
                                       array(&$this, "_data"));
        xml_parser_set_option($xml_parser,
                              XML_OPTION_CASE_FOLDING, false);

        $this->success = xml_parse($xml_parser, $response, true);
    
        xml_parser_free($xml_parser);
    }
    

    function _startElement($parser, $name, $attrs) 
    {
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
        } else {
            $ns  = "";
            $tag = $name;
        }

        if ($ns == "DAV:") {
            switch ($tag) {
            case "locktoken":
                $this->collect_locktoken = true;
                break;
            }
        }
    }

    function _data($parser, $data) 
    {
        if ($this->collect_locktoken) {
            $this->locktoken .= $data;
        }
    }

    function _endElement($parser, $name) 
    {
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
        } else {
            $ns  = "";
            $tag = $name;
        }

        switch ($tag) {
        case "locktoken":
            $this->collect_locktoken = false;
            $this->locktoken = trim($this->locktoken);
            break;
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
