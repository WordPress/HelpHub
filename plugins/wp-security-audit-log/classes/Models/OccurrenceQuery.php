<?php

class WSAL_Models_OccurrenceQuery extends WSAL_Models_Query
{
    protected $arguments = array();

    public function addArgument($field, $value)
    {
        $this->arguments[$field] = $value;
        return $this;
    }

    public function clearArguments()
    {
        $this->arguments = array();
        return $this;
    }

    public function __construct()
    {
        parent::__construct();

        //TO DO: Consider if Get Table is the right method to call given that this is mysql specific
        $this->addFrom(
            $this->getConnector()->getAdapter("Occurrence")->GetTable()
        );
    }
    
}
