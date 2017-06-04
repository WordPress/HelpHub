<?php
DUP_Util::hasCapability('manage_options');
global $wpdb;

//COMMON HEADER DISPLAY
$current_tab = isset($_REQUEST['tab']) ? esc_html($_REQUEST['tab']) : 'detail';
$package_id  = isset($_REQUEST["id"])  ? esc_html($_REQUEST["id"]) : 0;

$package			= DUP_Package::getByID($package_id);
$err_found		    = ($package == null || $package->Status < 100);
$link_log			= "{$package->StoreURL}{$package->NameHash}.log";
$err_link_log		= "<a target='_blank' href='{$link_log}' >" . __('package log', 'duplicator') . '</a>';
$err_link_faq		= '<a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/">' . __('FAQ', 'duplicator') . '</a>';		
$err_link_ticket	= '<a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-resource">' . __('resources page', 'duplicator') . '</a>';	
?>

<style>
    .narrow-input { width: 80px; }
    .wide-input {width: 400px; } 
	 table.form-table tr td { padding-top: 25px; }
	 div.all-packages {float:right; margin-top: -30px; }
	 div.all-packages a.add-new-h2 {font-size: 16px}
</style>

<div class="wrap">
    <?php 
		duplicator_header(__("Package Details &raquo; {$package->Name}", 'duplicator')); 
	?>
	
	<?php if ($err_found) :?>
	<div class="error">
		<p>
			<?php echo __('This package contains an error.  Please review the ', 'duplicator') . $err_link_log .  __(' for details.', 'duplicator'); ?> 
			<?php echo __('For help visit the ', 'duplicator') . $err_link_faq . __(' and ', 'duplicator') . $err_link_ticket; ?> 
		</p>
	</div>
	<?php endif; ?>
	
    <h2 class="nav-tab-wrapper">  
        <a href="?page=duplicator&action=detail&tab=detail&id=<?php echo $package_id ?>" class="nav-tab <?php echo ($current_tab == 'detail') ? 'nav-tab-active' : '' ?>"> 
			<?php _e('Details', 'duplicator'); ?>
		</a> 
		<a href="?page=duplicator&action=detail&tab=transfer&id=<?php echo $package_id ?>" class="nav-tab <?php echo ($current_tab == 'transfer') ? 'nav-tab-active' : '' ?>"> 
			<?php _e('Transfer', 'duplicator'); ?>
		</a> 		
    </h2>
	<div class="all-packages"><a href="?page=duplicator" class="add-new-h2"><i class="fa fa-archive"></i> <?php _e('All Packages', 'duplicator'); ?></a></div>
	
    <?php
    switch ($current_tab) {
        case 'detail': include('detail.php');            
            break;
		case 'transfer': include('transfer.php');
            break; 
    }
    ?>
</div>
