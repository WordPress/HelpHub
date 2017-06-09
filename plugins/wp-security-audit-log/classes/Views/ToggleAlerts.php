<?php
class WSAL_Views_ToggleAlerts extends WSAL_AbstractView 
{
    
    public function GetTitle() {
        return __('Enable/Disable Alerts', 'wp-security-audit-log');
    }
    
    public function GetIcon() {
        return 'dashicons-forms';
    }
    
    public function GetName() {
        return __('Enable/Disable Alerts', 'wp-security-audit-log');
    }
    
    public function GetWeight() {
        return 2;
    }
    
    protected function GetSafeCatgName($name) {
        return strtolower(
            preg_replace('/[^A-Za-z0-9\-]/', '-', $name)
        );
    }
    
    public function Render()
    {
        if (!$this->_plugin->settings->CurrentUserCan('edit')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-security-audit-log'));
        }
        $alert = new WSAL_Alert(); // IDE type hinting
        $groupedAlerts = $this->_plugin->alerts->GetCategorizedAlerts();
        $safeNames = array_map(array($this, 'GetSafeCatgName'), array_keys($groupedAlerts));
        $safeNames = array_combine(array_keys($groupedAlerts), $safeNames);
        if (isset($_POST['submit']) && isset($_POST['alert'])) {
            check_admin_referer('wsal-togglealerts');
            try {
                $enabled = array_map('intval', $_POST['alert']);
                $disabled = array();
                foreach ($this->_plugin->alerts->GetAlerts() as $alert)
                    if (!in_array($alert->type, $enabled))
                        $disabled[] = $alert->type;
                $this->_plugin->alerts->SetDisabledAlerts($disabled);
                ?><div class="updated"><p><?php _e('Settings have been saved.', 'wp-security-audit-log'); ?></p></div><?php
            } catch (Exception $ex) {
                ?><div class="error"><p><?php _e('Error: ', 'wp-security-audit-log'); ?><?php echo $ex->getMessage(); ?></p></div><?php
            }
            $this->_plugin->SetGlobalOption('log-404', isset($_REQUEST['log_404']) ? 'on' : 'off');
            $this->_plugin->SetGlobalOption('purge-404-log', isset($_REQUEST['purge_log']) ? 'on' : 'off');
        }
        ?><h2 id="wsal-tabs" class="nav-tab-wrapper"><?php
            foreach ($safeNames as $name => $safe) {
                ?><a href="#tab-<?php echo $safe; ?>" class="nav-tab"><?php echo $name; ?></a><?php
            }
        ?></h2>
        <form id="audit-log-viewer" method="post">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php wp_nonce_field('wsal-togglealerts'); ?>
            
            <div class="nav-tabs"><?php
                foreach ($groupedAlerts as $name => $group) { ?>
                    <div class="wsal-tab" id="tab-<?php echo $safeNames[$name]; ?>">
                    <h2 class="nav-tab-wrapper wsal-sub-tabs">
                    <?php
                    foreach ($group as $subname => $alerts) {
                        ?>
                        <a href="#tab-<?php echo $this->GetSafeCatgName($subname); ?>" class="nav-tab" data-parent="tab-<?php echo $safeNames[$name]; ?>"><?php echo $subname; ?></a>
                        <?php
                    } ?>
                    </h2>
                    <?php
                    foreach ($group as $subname => $alerts) {
                        $active = array();
                        $allactive = true;
                        foreach ($alerts as $alert) {
                            if ($alert->type <= 0006) continue; // <- ignore php alerts
                            if ($alert->type == 9999) continue; // <- ignore promo alerts
                            $active[$alert->type] = $this->_plugin->alerts->IsEnabled($alert->type);
                            if (!$active[$alert->type]) $allactive = false;
                        }
                        ?><table class="wp-list-table wsal-tab widefat fixed wsal-sub-tab" cellspacing="0" id="tab-<?php echo $this->GetSafeCatgName($subname); ?>">
                            <thead>
                                <tr>
                                    <th width="48"><input type="checkbox"<?php if ($allactive) echo 'checked="checked"'; ?>/></th>
                                    <th width="80"><?php _e('Code', 'wp-security-audit-log'); ?></th>
                                    <th width="100"><?php _e('Type', 'wp-security-audit-log'); ?></th>
                                    <th><?php _e('Description', 'wp-security-audit-log'); ?></th>
                                </tr>
                            </thead>
                            <tbody><?php
                                foreach ($alerts as $alert) {
                                    if ($alert->type <= 0006) continue; // <- ignore php alerts
                                    if ($alert->type == 9999) continue; // <- ignore promo alerts
                                    $attrs = '';
                                    switch (true) {
                                        case !$alert->mesg:
                                            $attrs = ' title="'. __('Not Implemented', 'wp-security-audit-log') . '" class="alert-incomplete"';
                                            break;
                                        case false:
                                            $attrs = ' title="'. __('Not Available', 'wp-security-audit-log') . '" class="alert-unavailable"';
                                            break;
                                    }
                                    ?><tr<?php echo $attrs; ?>>
                                        <th><input name="alert[]" type="checkbox" <?php if ($active[$alert->type]) echo 'checked="checked"'; ?> value="<?php echo (int)$alert->type; ?>"></th>
                                        <td><?php echo str_pad($alert->type, 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo $this->_plugin->constants->GetConstantBy('value', $alert->code)->name; ?></td>
                                        <td><?php echo esc_html($alert->desc); ?></td>
                                    </tr><?php
                                    if ($alert->type == 6007) {
                                        $log_404 = $this->_plugin->GetGlobalOption('log-404');
                                        $purge_log = $this->_plugin->GetGlobalOption('purge-404-log');
                                        ?><tr>
                                            <td></td>
                                            <td><input name="log_404" type="checkbox" class="check_log" value="1" <?php if ($log_404 == 'on') echo 'checked="checked"'; ?>></td>
                                            <td colspan="2"><?php _e('Capture 404 requests to file (the log file are created in the /wp-content/uploads/wp-security-audit-log/404s/ directory)', 'wp-security-audit-log'); ?></td>
                                        </tr>
                                        <tr>
                                            <td></td>
                                            <td><input name="purge_log" type="checkbox" class="check_log" value="1" <?php if ($purge_log == 'on') echo 'checked="checked"'; ?>></td>
                                            <td colspan="2"><?php _e('Purge log files older than one month', 'wp-security-audit-log'); ?></td>
                                        </tr><?php
                                    }
                                }
                            ?></tbody>
                        </table><?php
                    }
                ?>
                </div>
                <?php }
            ?></div>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr(__('Save Changes', 'wp-security-audit-log')); ?>"></p>
        </form><?php
    }
    
    public function Header()
    {
        ?><style type="text/css">
            .wsal-tab {
                display: none;
            }
            .wsal-tab tr.alert-incomplete td {
                color: #9BE;
            }
            .wsal-tab tr.alert-unavailable td {
                color: #CCC;
            }
            .wsal-sub-tabs {
                padding-left: 20px;
            }
            .wsal-sub-tabs .nav-tab-active {
                background-color: #fff;
                border-bottom: 1px solid #fff;
            }
        </style><?php
    }
    
    public function Footer()
    {
        ?><script type="text/javascript">
            jQuery(document).ready(function(){
                var scrollHeight = jQuery(document).scrollTop();
                // tab handling code
                jQuery('#wsal-tabs>a').click(function(){
                    jQuery('#wsal-tabs>a').removeClass('nav-tab-active');
                    jQuery('.wsal-tab').hide();
                    jQuery(jQuery(this).addClass('nav-tab-active').attr('href')).show();
                    jQuery(jQuery(this).attr('href')+' .wsal-sub-tabs>a:first').click();
                    setTimeout(function() {
                        jQuery(window).scrollTop(scrollHeight);
                    }, 1);
                });
                // sub tab handling code
                jQuery('.wsal-sub-tabs>a').click(function(){
                    jQuery('.wsal-sub-tabs>a').removeClass('nav-tab-active');
                    jQuery('.wsal-sub-tab').hide();
                    jQuery(jQuery(this).addClass('nav-tab-active').attr('href')).show();
                    setTimeout(function() {
                        jQuery(window).scrollTop(scrollHeight);
                    }, 1);
                });
                // checkbox handling code
                jQuery('.wsal-tab>thead>tr>th>:checkbox').change(function(){
                    jQuery(this).parents('table:first').find('tbody>tr>th>:checkbox').attr('checked', this.checked);
                });
                jQuery('.wsal-tab>tbody>tr>th>:checkbox').change(function(){
                    var allchecked = jQuery(this).parents('tbody:first').find('th>:checkbox:not(:checked)').length === 0;
                    jQuery(this).parents('table:first').find('thead>tr>th:first>:checkbox:first').attr('checked', allchecked);
                });
                
                var hashlink = jQuery('#wsal-tabs>a[href="' + location.hash + '"]');
                var hashsublink = jQuery('.wsal-sub-tabs>a[href="' + location.hash + '"]');
                if (hashlink.length) {
                    // show relevant tab
                    hashlink.click();
                } else if (hashsublink.length) {
                    // show relevant sub tab
                    jQuery('#wsal-tabs>a[href="#' + hashsublink.data('parent') + '"]').click();
                    hashsublink.click();
                } else {
                    jQuery('#wsal-tabs>a:first').click();
                    jQuery('.wsal-sub-tabs>a:first').click();
                }
                
                // Specific for alert 6007
                jQuery("input[value=6007]").on("change", function(){
                    var check = jQuery("input[value=6007]").is(":checked");
                    if(check) {
                        jQuery(".check_log").attr ( "checked" ,"checked" );
                    } else {
                        jQuery(".check_log").removeAttr('checked');
                    }
                }); 
            });
        </script><?php
    }
}
