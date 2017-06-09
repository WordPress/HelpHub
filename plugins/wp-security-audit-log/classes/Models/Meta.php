<?php

class WSAL_Models_Meta extends WSAL_Models_ActiveRecord {
	
	protected $adapterName = "Meta"; 

	public $id = 0;
	public $occurrence_id = 0;
	public $name = '';
	public $value = array(); // force mixed type

	public function SaveMeta()
    {
        $this->_state = self::STATE_UNKNOWN;
        $updateId = $this->getId();
        $result = $this->getAdapter()->Save($this);

        if ($result !== false) {
            $this->_state = (!empty($updateId))?self::STATE_UPDATED:self::STATE_CREATED;
        }
        return $result;
    }

    public function UpdateByNameAndOccurenceId($name, $value, $occurrenceId)
    {
        $meta = $this->getAdapter()->LoadByNameAndOccurenceId($name, $occurrenceId);
        if (!empty($meta)) {
            $this->id = $meta['id'];
            $this->occurrence_id = $meta['occurrence_id'];
            $this->name = $meta['name'];
            $this->value = $value;
            $this->saveMeta();
        } else {
            $this->occurrence_id = $occurrenceId;
            $this->name = $name;
            $this->value = $value;
            $this->SaveMeta();
        }
    }
}
