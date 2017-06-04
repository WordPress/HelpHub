<?php


class WSAL_Views_Extensions extends WSAL_AbstractView
{
    
    public function GetTitle() {
        return __('WP Security Audit Log Add-Ons', 'wp-security-audit-log');
    }
    
    public function GetIcon() {
        return 'dashicons-external';
    }
    
    public function GetName() {
        return __(' Add Functionality', 'wp-security-audit-log');
    }
    
    public function GetWeight() {
        return 3.5;
    }

    public function Header() {
        wp_enqueue_style(
            'extensions',
            $this->_plugin->GetBaseUrl() . '/css/extensions.css',
            array(),
            filemtime($this->_plugin->GetBaseDir() . '/css/extensions.css')
        );
    }
    
    public function Render()
    {
        ?>
        <p><?php _e('The below add-ons allow you to extend the functionality of WP Security Audit Log plugin and enable you to get more benefits out of the WordPress security audit, such as configurable email alerts, ability to search using free text based searches & filters, and generate user activity reports to meet regulatory compliance requirements.', 'wp-security-audit-log'); ?>
        </p>
        <div class="wrap-advertising-page">
            <div class="extension all">
                <?php $link = 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=alladdons'; ?>
                <a target="_blank" href="<?php echo esc_attr($link); ?>">
                    <h3><?php _e('All Add-Ons Bundle', 'wp-security-audit-log'); ?></h3>
                </a>
                <p><?php _e('Benefit from a 60% discount when you purchase all the add-ons as a single bundle.', 'wp-security-audit-log'); ?>
                </p>
                <p>
                    <a target="_blank" href="<?php echo esc_attr($link); ?>" class="button-primary"><?php _e('Get this Bundle', 'wp-security-audit-log'); ?>          
                    </a>
                </p>
            </div>
            <?php if (!class_exists('WSAL_User_Management_Plugin')) { ?>
                <div class="extension user-managment">
                    <?php $link = 'https://www.wpsecurityauditlog.com/extensions/user-sessions-management-wp-security-audit-log/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=logins'; ?>
                    <a target="_blank" href="<?php echo esc_attr($link); ?>">
                        <h3><?php _e('Users Logins and Management', 'wp-security-audit-log'); ?></h3>
                    </a>
                    <p><?php _e('See who is logged in to your WordPress and manage user sessions and logins.', 'wp-security-audit-log'); ?>
                        
                    </p>
                    <p>
                        <a target="_blank" href="<?php echo esc_attr($link); ?>" class="button-primary"><?php _e('Get this extension', 'wp-security-audit-log'); ?>
                        </a>
                    </p>
                </div>
            <?php } ?>
            <?php if (!class_exists('WSAL_NP_Plugin')) { ?>
                <div class="extension email-notifications">
                    <?php $link = 'https://www.wpsecurityauditlog.com/extensions/wordpress-email-notifications-add-on/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=notifications'; ?>
                    <a target="_blank" href="<?php echo esc_attr($link); ?>">
                        <h3><?php _e('Email Notifications', 'wp-security-audit-log'); ?></h3>
                    </a>
                    <p><?php _e('Get notified instantly via email when important changes are made on your WordPress!', 'wp-security-audit-log'); ?>
                        
                    </p>
                    <p>
                        <a target="_blank" href="<?php echo esc_attr($link); ?>" class="button-primary"><?php _e('Get this extension', 'wp-security-audit-log'); ?>
                        </a>
                    </p>
                </div>
            <?php } ?>
            <?php if (!class_exists('WSAL_Rep_Plugin')) { ?>
                <div class="extension reports">
                    <?php $link = 'https://www.wpsecurityauditlog.com/extensions/compliance-reports-add-on-for-wordpress/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=reports'; ?>
                    <a target="_blank" href="<?php echo esc_attr($link); ?>">
                        <h3><?php _e('Reports', 'wp-security-audit-log'); ?></h3>
                    </a>
                    <p><?php _e('Generate any type of user,site and activity report to keep track of user productivity and to meet  regulatory compliance requirements.', 'wp-security-audit-log'); ?>
                        
                    </p>
                    <p>
                        <a target="_blank" href="<?php echo esc_attr($link); ?>" class="button-primary"><?php _e('Get this extension', 'wp-security-audit-log'); ?>
                        </a>
                    </p>
                </div>
            <?php } ?>
            <?php if (!class_exists('WSAL_SearchExtension')) { ?>
                <div class="extension search-ext">
                    <?php $link = 'https://www.wpsecurityauditlog.com/extensions/search-add-on-for-wordpress-security-audit-log/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=search'; ?>
                    <a target="_blank" href="<?php echo esc_attr($link); ?>">
                        <h3><?php _e('Search', 'wp-security-audit-log'); ?></h3>
                    </a>
                    <p><?php _e('Do free-text based searches for specific activity in the WordPress audit trail. You can also use filters to fine-tune your searches.', 'wp-security-audit-log'); ?>
                        
                    </p>
                    <p>
                        <a target="_blank" href="<?php echo esc_attr($link); ?>" class="button-primary"><?php _e('Get this extension', 'wp-security-audit-log'); ?>
                        </a>
                    </p>
                </div>
            <?php } ?>
            <?php if (!class_exists('WSAL_Ext_Plugin')) { ?>
                <div class="extension external-db">
                    <?php $link = 'https://www.wpsecurityauditlog.com/extensions/external-database-for-wp-security-audit-log/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=externaldb'; ?>
                    <a target="_blank" href="<?php echo esc_attr($link); ?>">
                        <h3><?php _e('External DB', 'wp-security-audit-log'); ?></h3>
                    </a>
                    <p><?php _e('Store the WordPress audit trial in an external database for a more secure and faster WordPress website.', 'wp-security-audit-log'); ?>
                        
                    </p>
                    <p>
                        <a target="_blank" href="<?php echo esc_attr($link); ?>" class="button-primary"><?php _e('Get this extension', 'wp-security-audit-log'); ?>
                        </a>
                    </p>
                </div>
            <?php } ?>
        </div>
        <?php
    }
}
