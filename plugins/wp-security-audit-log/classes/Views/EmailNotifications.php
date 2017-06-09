<?php

class WSAL_Views_EmailNotifications extends WSAL_AbstractView {
    
    public function GetTitle()
    {
        return __('Email Notifications Add-On', 'wp-security-audit-log');
    }
    
    public function GetIcon()
    {
        return 'dashicons-external';
    }
    
    public function GetName()
    {
        return __('Notifications Email', 'wp-security-audit-log');
    }
    
    public function GetWeight()
    {
        return 7;
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
            <div class="icon" style='background-image:url("<?=$this->_plugin->GetBaseUrl();?>/img/envelope.jpg");'></div>
            <h3><?php _e('Email Notifications', 'wp-security-audit-log'); ?></h3>
            <p>
                <?php _e('This premium add-on allows you to configure email alerts so you are <br>notified instantly when important changes happen on your WordPress.', 'wp-security-audit-log'); ?>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/wordpress-email-notifications-add-on/?utm_source=plugin&utm_medium=emailpage&utm_campaign=notifications'; ?>
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
