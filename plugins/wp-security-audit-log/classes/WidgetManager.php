<?php

class WSAL_WidgetManager
{
    /**
     * @var WpSecurityAuditLog
     */
    protected $_plugin;
    
    public function __construct(WpSecurityAuditLog $plugin)
    {
        $this->_plugin = $plugin;
        add_action('wp_dashboard_setup', array($this, 'AddWidgets'));
    }
    
    public function AddWidgets()
    {
        if ($this->_plugin->settings->IsWidgetsEnabled()
        && $this->_plugin->settings->CurrentUserCan('view')) {
            wp_add_dashboard_widget(
                'wsal',
                __('Latest Alerts', 'wp-security-audit-log') . ' | WP Security Audit Log',
                array($this, 'RenderWidget')
            );
        }
    }
    
    public function RenderWidget()
    {
        $query = new WSAL_Models_OccurrenceQuery();

        $bid = (int)$this->get_view_site_id();
        if ($bid) {
            $query->addCondition("site_id = %s ", $bid);
        }
        $query->addOrderBy("created_on", true);
        $query->setLimit($this->_plugin->settings->GetDashboardWidgetMaxAlerts());
        $results = $query->getAdapter()->Execute($query);
            
        ?><div><?php
            if (!count($results)) {
                ?><p><?php _e('No alerts found.', 'wp-security-audit-log'); ?></p><?php
            } else {
                ?><table class="wp-list-table widefat" cellspacing="0" cellpadding="0"
                       style="display: block; overflow-x: auto;">
                    <thead>
                        <th class="manage-column" style="width: 15%;" scope="col"><?php _e('User', 'wp-security-audit-log'); ?></th>
                        <th class="manage-column" style="width: 85%;" scope="col"><?php _e('Description', 'wp-security-audit-log'); ?></th>
                    </thead>
                    <tbody><?php
                        $url = 'admin.php?page=' . $this->_plugin->views->views[0]->GetSafeViewName();
                        $fmt = array(new WSAL_AuditLogListView($this->_plugin), 'meta_formatter');
                        foreach ($results as $entry) {
                            ?><tr>
                                <td><?php
                                    echo ($un = $entry->GetUsername()) ? esc_html($un) : '<i>unknown</i>';
                                ?></td>
                                <td>
                                    <a href="<?php echo $url . '#Event' . $entry->id; ?>"><?php
                                        echo $entry->GetMessage($fmt);
                                    ?></a>
                                </td>
                            </tr><?php
                        }
                    ?></tbody>
                </table><?php
            }
        ?></div><?php
    }
    
    protected function get_view_site_id()
    {
        if (is_super_admin()) {
            return 0;
        } else {
            return get_current_blog_id();
        }
    }
}
