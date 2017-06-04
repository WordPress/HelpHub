<?php

interface WSAL_Connector_ConnectorInterface
{
    public function getAdapter($class_name);
    public function getConnection();
    public function closeConnection();
    public function isInstalled();
    public function canMigrate();
    public function installAll();
    public function uninstallAll();
}
