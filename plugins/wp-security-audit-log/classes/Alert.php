<?php

final class WSAL_Alert {
    
    /**
     * Alert type (used when triggering an alert etc).
     * @var integer
     */
    public $type = 0;
    
    /**
     * Alert error level (E_* constant).
     * @var integer
     */
    public $code = 0;
    
    /**
     * Alert category (alerts are grouped by matching categories).
     * @var string
     */
    public $catg = '';

    /**
     * Alert sub category.
     * @var string
     */
    public $subcatg = '';
    
    /**
     * Alert description (ie, describes what happens when alert is triggered).
     * @var string
     */
    public $desc = '';
    
    /**
     * Alert message (variables between '%' are expanded to values).
     * @var string
     */
    public $mesg = '';
    
    public function __construct($type = 0, $code = 0, $catg = '', $subcatg = '', $desc = '', $mesg = '') {
        $this->type = $type;
        $this->code = $code;
        $this->catg = $catg;
        $this->subcatg = $subcatg;
        $this->desc = $desc;
        $this->mesg = $mesg;
    }
    
    /**
     * Retrieves a value for a particular meta variable expression.
     * @param string $expr Expression, eg: User->Name looks for a Name property for meta named User.
     * @param array $metaData (Optional) Meta data relevant to expression.
     * @return mixed The value nearest to the expression.
     */
    protected function GetMetaExprValue($expr, $metaData = array()){
        // TODO Handle function calls (and methods?)
        $expr = explode('->', $expr);
        $meta = array_shift($expr);
        $meta = isset($metaData[$meta]) ? $metaData[$meta] : null;
        foreach($expr as $part){
            if(is_scalar($meta) || is_null($meta))return $meta; // this isn't 100% correct
            $meta = is_array($meta) ? $meta[$part] : $meta->$part;
        }
        return is_scalar($meta) ? (string)$meta : var_export($meta, true);
    }
    
    /**
     * Expands a message with variables by replacing variables with meta data values.
     * @param string $mesg The original message.
     * @param array $metaData (Optional) Meta data relevant to message.
     * @param callable|null $metaFormatter (Optional) Callback for formatting meta values.
     * @param string $afterMeta (Optional) Some text to put after meta values.
     * @return string The expanded message.
     */
    protected function GetFormattedMesg($origMesg, $metaData = array(), $metaFormatter = null){ 
        // tokenize message with regex
        $mesg = preg_split('/(%.*?%)/', (string)$origMesg, -1, PREG_SPLIT_DELIM_CAPTURE);
        if(!is_array($mesg))return (string)$origMesg;
        // handle tokenized message
        foreach($mesg as $i => $token){
            // handle escaped percent sign
            if($token == '%%'){
                $mesg[$i] = '%';
            }else
            // handle complex expressions
            if(substr($token, 0, 1) == '%' && substr($token, -1, 1) == '%'){
                $mesg[$i] = $this->GetMetaExprValue(substr($token, 1, -1), $metaData);
                if($metaFormatter)$mesg[$i] = call_user_func($metaFormatter, $token, $mesg[$i]);
            }
        }
        // compact message and return
        return implode('', $mesg);
    }
    
    /**
     * @param array $metaData (Optional) Meta data relevant to message.
     * @param callable|null $metaFormatter (Optional) Meta formatter callback.
     * @param string|null $mesg (Optional) Override message template to use.
     * @return string Fully formatted message.
     */
    public function GetMessage($metaData = array(), $metaFormatter = null, $mesg = null){
        return $this->GetFormattedMesg(is_null($mesg) ? $this->mesg : $mesg, $metaData, $metaFormatter);
    }
}
