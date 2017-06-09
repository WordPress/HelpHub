<?php

class WSAL_Views_LogInUsers extends WSAL_AbstractView {
    
    public function GetTitle()
    {
        return __('User Sessions Management Add-On', 'wp-security-audit-log');
    }
    
    public function GetIcon()
    {
        return 'dashicons-external';
    }
    
    public function GetName()
    {
        return __('Logged In Users', 'wp-security-audit-log');
    }
    
    public function GetWeight()
    {
        return 8;
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
         <div class="wrap-advertising-page-single">
            <div class="icon" style='background-image:url("<?=$this->_plugin->GetBaseUrl();?>/img/monitoring.jpg");'></div>
            <h3><?php _e('Users login and Management', 'wp-security-audit-log'); ?></h3>
            <p>
                <?php _e('This premium add-on allows you to see who is logged in to your WordPress,<br> block multiple same-user WordPress sessions and more.', 'wp-security-audit-log'); ?>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/user-sessions-management-wp-security-audit-log/?utm_source=plugin&utm_medium=loginspage&utm_campaign=logins'; ?>
            <p>
                <a class="button-primary" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
            </p>
            <div class="clear"></div>
            <p>
                <span class="description">
                    <strong><span class="text-red">70% Off</span> when you purchase this add-on as part of the All Add-On bundle.</strong> 
                </span>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=alladdons'; ?>
            <a class="button-blue" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Buy all Add-Ons Bundle', 'wp-security-audit-log'); ?></a>
        </div>
        <?php
    }
}
