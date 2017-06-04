<?php

class WSAL_Views_Help extends WSAL_AbstractView {
    
    public function GetTitle() {
        return __('Help', 'wp-security-audit-log');
    }
    
    public function GetIcon() {
        return 'dashicons-sos';
    }
    
    public function GetName() {
        return __('Help', 'wp-security-audit-log');
    }
    
    public function GetWeight() {
        return 5;
    }
    
    public function Render(){
        ?><div class="metabox-holder" style="position: relative;">
        
            <div class="postbox" style="margin-right: 270px;">
                <div class="inside">
                    <div class="activity-block">
                        <h2><?php _e('Plugin Support', 'wp-security-audit-log'); ?></h2>
                        <p>
                            <?php _e('Have you encountered or noticed any issues while using WP Security Audit Log plugin?', 'wp-security-audit-log'); ?>
                            <?php _e('Or you want to report something to us? Click any of the options below to post on the plugin\'s forum or contact our support directly.', 'wp-security-audit-log'); ?>
                        </p><p>
                            <a class="button" href="https://wordpress.org/support/plugin/wp-security-audit-log" target="_blank"><?php _e('Free Support Forum', 'wp-security-audit-log'); ?></a>
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <a class="button" href="http://www.wpsecurityauditlog.com/contact/" target="_blank"><?php _e('Free Support Email', 'wp-security-audit-log'); ?></a>
                        </p>
                    </div>

                    <div class="activity-block">
                        <h2><?php _e('Plugin Documentation', 'wp-security-audit-log'); ?></h2>
                        <p>
                            <?php _e('For more detailed information about WP Security Audit Log you can visit the plugin website.', 'wp-security-audit-log'); ?>
                            <?php _e('You can also visit the official list of WordPress Security Alerts for more information about all of the WordPress activity and changes you can monitor with WP Security Audit Log.', 'wp-security-audit-log'); ?>
                        </p><p>
                            <a class="button" href="http://www.wpsecurityauditlog.com/?utm_source=plugin&utm_medium=helppage&utm_campaign=support" target="_blank"><?php _e('Plugin Website', 'wp-security-audit-log'); ?></a>
                            &nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="https://www.wpsecurityauditlog.com/documentation/?utm_source=plugin&utm_medium=helppage&utm_campaign=support" target="_blank"><?php _e('Plugin Documenation', 'wp-security-audit-log'); ?></a>
                            &nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="https://www.wpsecurityauditlog.com/documentation/frequently-asked-questions-faqs/?utm_source=plugin&utm_medium=helppage&utm_campaign=support" target="_blank"><?php _e('FAQs', 'wp-security-audit-log'); ?></a>
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <a class="button" href="http://www.wpsecurityauditlog.com/documentation/list-monitoring-wordpress-security-alerts-audit-log/?utm_source=plugin&utm_medium=helppage&utm_campaign=support" target="_blank"><?php _e('List of WordPress Security Alerts', 'wp-security-audit-log'); ?></a>
                        </p>
                    </div>

                    <div class="">
                        <h2><?php _e('Keep Yourself Up-to-Date with WordPress Security', 'wp-security-audit-log'); ?></h2>
                        <p>
                            <?php _e('Keep yourself informed with what is happening in the WordPress security ecosystem, which are the new vulnerabilities, which plugins you need to update and what are the latest WordPress security hacks so you can stay one step ahead of the hackers.', 'wp-security-audit-log'); ?>
                        </p>
                        <a class="button" href="http://www.wpwhitesecurity.com/blog/" target="_blank"><?php _e('Read the WP White Security Blog', 'wp-security-audit-log'); ?></a>
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        <a class="button" href="http://www.wpsecuritybloggers.com" target="_blank"><?php _e('Subscribe to WP Security Bloggers (An Aggregate of WordPress Security Blogs)', 'wp-security-audit-log'); ?></a>
                    </div>
                </div>
            </div>

            <div style="position: absolute; right: 70px; width: 180px; top: 10px;">
                <div class="postbox">
                    <h3 class="hndl"><span><?php _e('WP Security Audit Log in your Language!', 'wp-security-audit-log'); ?></span></h3>
                    <div class="inside">
                        <?php _e('If you are interested in translating our plugin please drop us an email on', 'wp-security-audit-log'); ?>
                        <a href="mailto:plugins@wpwhitesecurity.com">plugins@wpwhitesecurity.com</a>.
                    </div>
                </div>
            </div>
            
        </div><?php
    }
    
}