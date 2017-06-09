<?php

// if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

require_once('wp-security-audit-log.php');
WpSecurityAuditLog::GetInstance()->Uninstall();