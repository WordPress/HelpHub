<?php

global $wpsf_settings;

// General Settings section
$wpsf_settings[] = array(
    'section_id' => 'general',
    'section_title' => 'Maintenance Mode Settings',
    //'section_description' => 'Some intro description about this section.',
    'section_order' => 5,
    'fields' => array(
       array(
            'id' => 'is_maintenance_mode',
            'title' => 'Maintenance Mode',
            'desc' => 'test Check here to turn maintenance mode on.  All non-logged in users will see this page.',
            'type' => 'checkbox',
            'std' => 0
        ),
       array(
            'id' => 'logo',
            'title' => 'Logo',
            'desc' => 'Upload your logo here.',
            'type' => 'file',
            'std' => ''
        ),
        array(
            'id' => 'message',
            'title' => 'Message',
            'desc' => 'Put your maintenance notice here.',
            'type' => 'text',
            'std' => 'Our site is temporarily down.  We will be back online soon.'
        ),
        array(
            'id' => 'message_font_color',
            'title' => 'Message Color',
            //'desc' => 'This is a description.',
            'type' => 'color',
            'std' => '#ffffff'
        ),
        
    )
);


?>