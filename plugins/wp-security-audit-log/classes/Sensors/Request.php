<?php

class WSAL_Sensors_Request extends WSAL_AbstractSensor 
{
    public function HookEvents()
    {
        if ($this->plugin->settings->IsRequestLoggingEnabled()) {
            add_action('shutdown', array($this, 'EventShutdown'));
        }
    }
    
    public function EventShutdown()
    {
        $upload_dir = wp_upload_dir();
        $uploadsDirPath = trailingslashit($upload_dir['basedir']).'wp-security-audit-log/';
        if (!$this->CheckDirectory($uploadsDirPath)) {
            wp_mkdir_p($uploadsDirPath);
        }

        $file = $uploadsDirPath . 'Request.log.php';
        
        $line = '['.date('Y-m-d H:i:s').'] '
            . $_SERVER['REQUEST_METHOD'] . ' '
            . $_SERVER['REQUEST_URI'] . ' '
            . (!empty($_POST) ? str_pad(PHP_EOL, 24) . json_encode($_POST) : '')
            . (!empty(self::$envvars) ? str_pad(PHP_EOL, 24) . json_encode(self::$envvars) : '')
            . PHP_EOL;
        
        if (!file_exists($file) && !file_put_contents($file, '<'.'?php die(\'Access Denied\'); ?>' . PHP_EOL)) {
            return $this->LogError('Could not initialize request log file', array('file' => $file));
        }
        
        $f = fopen($file, 'a');
        if ($f) {
            if (!fwrite($f, $line)) {
                $this->LogWarn('Could not write to log file', array('file' => $file));
            }
            if (!fclose($f)) {
                $this->LogWarn('Could not close log file', array('file' => $file));
            }
        } else {
            $this->LogWarn('Could not open log file', array('file' => $file));
        }
    }
    
    protected static $envvars = array();
    
    public static function SetVar($name, $value)
    {
        self::$envvars[$name] = $value;
    }
    
    public static function SetVars($data)
    {
        foreach ($data as $name => $value) {
            self::SetVar($name, $value);
        }
    }
}
