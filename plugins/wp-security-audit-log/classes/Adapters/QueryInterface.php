<?php

interface WSAL_Adapters_QueryInterface
{
	public function Execute($query);
	public function Count($query);
	public function Delete($query);
}
