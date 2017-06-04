<?php

interface WSAL_Adapters_MetaInterface {
	/**
	 * Create a meta object
	 * @param $metaData Array of meta data
	 * @return int ID of the new meta data
	 */
	public function deleteByOccurenceIds($occurenceIds);

	public function loadByNameAndOccurenceId($metaName, $occurenceId);
	
}
