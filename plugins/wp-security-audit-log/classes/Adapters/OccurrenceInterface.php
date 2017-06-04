<?php

interface WSAL_Adapters_OccurrenceInterface
{
    public function GetMeta($occurence);
    public function GetNamedMeta($occurence, $name);
    public function GetFirstNamedMeta($occurence, $names);
    public static function GetNewestUnique($limit = PHP_INT_MAX);
    public function CheckKnownUsers($args = array());
    public function CheckUnKnownUsers($args = array());
}
