<?php

final class WSAL_SensorManager extends WSAL_AbstractSensor {
    
    /**
     * @var WSAL_AbstractSensor[]
     */
    protected $sensors = array();
    
    public function __construct(WpSecurityAuditLog $plugin) {
        parent::__construct($plugin);
        
        foreach (glob(dirname(__FILE__) . '/Sensors/*.php') as $file) {
            $this->AddFromFile($file);
        }
        /**
         * Load Custom Sensor files from /wp-content/uploads/wp-security-audit-log/custom-sensors/
         */
        $upload_dir = wp_upload_dir();
        $uploadsDirPath = trailingslashit($upload_dir['basedir']) . 'wp-security-audit-log' . DIRECTORY_SEPARATOR . 'custom-sensors' . DIRECTORY_SEPARATOR;
        // Check directory
        if (is_dir($uploadsDirPath) && is_readable($uploadsDirPath)) {
            foreach (glob($uploadsDirPath . '*.php') as $file) {
                require_once($file);
                $file = substr($file, 0, -4);
                $class = "WSAL_Sensors_" . str_replace($uploadsDirPath, '', $file);
                $this->AddFromClass($class);
            }
        }
    }
    
    public function HookEvents() {
        foreach ($this->sensors as $sensor) {
            $sensor->HookEvents();
        }
    }
    
    public function GetSensors() {
        return $this->sensors;
    }
    
    /**
     * Add new sensor from file inside autoloader path.
     * @param string $file Path to file.
     */
    public function AddFromFile($file) {
        $this->AddFromClass($this->plugin->GetClassFileClassName($file));
    }
    
    /**
     * Add new sensor given class name.
     * @param string $class Class name.
     */
    public function AddFromClass($class) {
        $this->AddInstance(new $class($this->plugin));
    }
    
    /**
     * Add newly created sensor to list.
     * @param WSAL_AbstractSensor $sensor The new sensor.
     */
    public function AddInstance(WSAL_AbstractSensor $sensor) {
        $this->sensors[] = $sensor;
    }
}
