<?php

abstract class WSAL_AbstractSensor
{
    /**
     * @var WpSecurityAuditLog
     */
    protected $plugin;

    public function __construct(WpSecurityAuditLog $plugin){
        $this->plugin = $plugin;
    }

    /**
     * @return boolean Whether we are running on multisite or not.
     */
    protected function IsMultisite()
    {
        return function_exists('is_multisite') && is_multisite();
    }
    
    abstract function HookEvents();
    
    protected function Log($type, $message, $args)
    {
        $this->plugin->alerts->Trigger($type, array(
            'Message' => $message,
            'Context' => $args,
            'Trace'   => debug_backtrace(),
        ));
    }
    
    protected function LogError($message, $args)
    {
        $this->Log(0001, $message, $args);
    }
    
    protected function LogWarn($message, $args)
    {
        $this->Log(0002, $message, $args);
    }
    
    protected function LogInfo($message, $args)
    {
        $this->Log(0003, $message, $args);
    }

    /**
     * Check to see whether or not the specified directory is accessible
     * @param string $dirPath
     * @return bool
     */
    protected function CheckDirectory($dirPath)
    {
        if (!is_dir($dirPath)) {
            return false;
        }
        if (!is_readable($dirPath)) {
            return false;
        }
        if (!is_writable($dirPath)) {
            return false;
        }
        return true;
    }
}
