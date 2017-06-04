<?php

class WSAL_Models_Occurrence extends WSAL_Models_ActiveRecord
{
    public $id = 0;
    public $site_id = 0;
    public $alert_id = 0;
    public $created_on = 0.0;
    public $is_read = false;
    public $is_migrated = false;
    protected $adapterName = "Occurrence";
    
    /**
     * Returns the alert related to this occurrence.
     * @return WSAL_Alert
     */
    public function GetAlert()
    {
        return WpSecurityAuditLog::GetInstance()->alerts->GetAlert($this->alert_id);
    }
    
    /**
     * Returns the value of a meta item.
     * @param string $name Name of meta item.
     * @param mixed $default Default value returned when meta does not exist.
     * @return mixed The value, if meta item does not exist $default returned.
     */
    public function GetMetaValue($name, $default = array())
    {
        //get meta adapter
        //call the function ($name, $this->getId())
        $meta = $this->getAdapter()->GetNamedMeta($this, $name);
        return maybe_unserialize($meta['value']);

        //TO DO: re-introduce add is loaded check before running query
        //return $meta->IsLoaded() ? $meta->value : $default;
    }
    
    /**
     * Set the value of a meta item (creates or updates meta item).
     * @param string $name Meta name.
     * @param mixed $value Meta value.
     */
    public function SetMetaValue($name, $value)
    {
        if (!empty($value)) {
            // get meta adapter
            $model = new WSAL_Models_Meta();
            $model->occurrence_id = $this->getId();
            $model->name = $name;
            $model->value = maybe_serialize($value);
            $model->SaveMeta();
        }
    }
    
    public function UpdateMetaValue($name, $value)
    {
        $model = new WSAL_Models_Meta();
        $model->UpdateByNameAndOccurenceId($name, $value, $this->getId());
    }

    /**
     * Returns a key-value pair of meta data.
     * @return array
     */
    public function GetMetaArray()
    {
        $result = array();
        $metas = $this->getAdapter()->GetMultiMeta($this);
        foreach ($metas as $meta) {
            $result[$meta->name] = maybe_unserialize($meta->value);
        }
        return  $result;
    }
    
    /**
     * Creates or updates all meta data passed as an array of meta-key/meta-value pairs.
     * @param array $data New meta data.
     */
    public function SetMeta($data)
    {
        foreach ((array)$data as $key => $val) {
            $this->SetMetaValue($key, $val);
        }
    }
    
    /**
     * @param callable|null $metaFormatter (Optional) Meta formatter callback.
     * @return string Full-formatted message.
     */
    public function GetMessage($metaFormatter = null)
    {
        if (!isset($this->_cachedmessage)) {
            // get correct message entry
            if ($this->is_migrated) {
                $this->_cachedmessage = $this->GetMetaValue('MigratedMesg', false);
            }
            if (!$this->is_migrated || !$this->_cachedmessage) {
                $this->_cachedmessage = $this->GetAlert()->mesg;
            }
            // fill variables in message
            $this->_cachedmessage = $this->GetAlert()->GetMessage($this->GetMetaArray(), $metaFormatter, $this->_cachedmessage);
        }
        return $this->_cachedmessage;
    }
    
    /**
     * Delete occurrence as well as associated meta data.
     * @return boolean True on success, false on failure.
     */
    public function Delete()
    {
        foreach ($this->getAdapter()->GetMeta() as $meta) {
            $meta->Delete();
        }
        return parent::Delete();
    }
    
    /**
     * @return string User's username.
     */
    public function GetUsername()
    {
        $meta = $this->getAdapter()->GetFirstNamedMeta($this, array('Username', 'CurrentUserID'));
        if ($meta) {
            switch (true) {
                case $meta->name == 'Username':
                    return $meta->value;
                case $meta->name == 'CurrentUserID':
                    return ($data = get_userdata($meta->value)) ? $data->user_login : null;
            }
        }
        return null;
    }
    
    /**
     * @return string IP address of request.
     */
    public function GetSourceIP()
    {
        return $this->GetMetaValue('ClientIP', '');
    }
    
    /**
     * @return string IP address of request (from proxies etc).
     */
    public function GetOtherIPs()
    {
        $result = array();
        $data = (array)$this->GetMetaValue('OtherIPs', array());
        foreach ($data as $ips) {
            foreach ($ips as $ip) {
                $result[] = $ip;
            }
        }
        return array_unique($result);
    }
    
    /**
     * @return array Array of user roles.
     */
    public function GetUserRoles()
    {
        return $this->GetMetaValue('CurrentUserRoles', array());
    }
    
    /**
     * @return float Number of seconds (and microseconds as fraction) since unix Day 0.
     * @todo This needs some caching.
     */
    protected function GetMicrotime()
    {
        return microtime(true);// + get_option('gmt_offset') * HOUR_IN_SECONDS;
    }

    /**
     * Finds occurences of the same type by IP and Username within specified time frame
     * @param string $ipAddress
     * @param string $username
     * @param int $alertId Alert type we are lookign for
     * @param int $siteId
     * @param $startTime mktime
     * @param $endTime mktime
     */
    public function CheckKnownUsers($args = array())
    {
        return $this->getAdapter()->CheckKnownUsers($args);
    }

    public function CheckUnKnownUsers($args = array())
    {
        return $this->getAdapter()->CheckUnKnownUsers($args);
    }

    public function GetByPostID($post_id)
    {
        return $this->getAdapter()->GetByPostID($post_id);
    }

    public function CheckAlert404($args = array())
    {
        return $this->getAdapter()->CheckAlert404($args);
    }
}
