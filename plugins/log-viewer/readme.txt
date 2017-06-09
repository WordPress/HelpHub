=== Log Viewer ===
Tags: debug, log, advanced, admin, development
Contributors: mfisc
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CDNHTJWQEP5S2
Tested up to: 3.9
Requires at least: 3.4
Stable Tag: 14.05.04
Latest Version: 14.05.04-1559
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin provides an easy way to view *.log files directly in the admin panel.

== Description ==

** FULLY COMPATIBLE WITH MULTISITE INSTALLATIONS **

** The plugin is recommended to use only in development. **

This plugin provides an easy way to view any *.log files directly in admin panel. Also you can perform simple actions like empty the file or deleting it.

To activate Wordpress logging to file you have to set `define( 'WP_DEBUG_LOG', true );` in your wp-config.php file.
In Multisite installations you have to be Super Admin for using this plugin.
Additionally in Singlesite installations you have to have the 'edit_plugins' capability which is by default only granted to admins.

There is an first integration for a panel to the [Debug Bar Plugin](https://wordpress.org/plugins/debug-bar/ "Debug Bar"). The integration could be deactivated by setting ENABLE_DEBUGBAR_INTEGRATION to false in log-viewer.php.

If you're experiencing problems please report through support forum or check FAQ section. If you have suggestions feel free to submit your view.
Log-Viewer is also listed on the excellent [Developer Plugin](http://wordpress.org/extend/plugins/developer/ "WordPress Developer Plugin") which comes directly by the awesome guys at Automattic!

**Known limitations / Bugs:**

* Autorefresh is currently fixed at 15 seconds if enabled - will let you choose custom timing soon
* after an action in files view a wp_redirect should be called but there's already output present so not working. Workaround is to unset all variables.
* User settings stored "manually"; switch to wordpress own *_user_setting functions but currently problems on cookie/header_sent limiting
* User settings stored in wp_options ( thats ok ) but on multisite installations they are stored in each wp_*_options table

**ToDo:**

* Adding Dashboard functionality ( and/or File View in Dashboard menu (WP_NETWORK_ADMIN) )
* Translations ( DE )
* Cleanup on uninstalling
* Message if WP_DEBUG not set ( on activation? )

== Changelog ==

= 14.05.04 =
* Fixed : error calling method static

= 14.04.22 =
* Added first Debug Bar integration

= 14.04.21 =
* nothing changed ( tag for Debug-Bar functionality )

= 13.12.22 =
* rewrite branch merged to trunk
* full Multisite support ( currently only super admin! )

= 13.11.11 =
* rewrite based on the great [WordPress-Plugin-Boilerplate](https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate) of Tom McFarlin
* optimizations for Multisite installations
* securing Singlesite installations

= 13.11.09 =
* moved to PhpStorm for development
* changed build script to Phing
* started complete rewrite for MU optimizations

= 13.6.25 =
* changed version string for better readability

= 2013.05.19 =
* added Display Options above file list
* added Autorefresh Option ( currently fixed at every 15 seconds )
* added FIFO / FILO Option ( FIFO = displays file as is; FILO = displays file reversed )

= 2013.04.02 =
* moved from sublime text to netbeans for development
* modified structure for standard compliance ( Support Topic by nickdaugherty )

= 2012.10.06 =
* added more files ( currently only WP_CONTENT_DIR and *.log )
* added file info
* started revamp of class structure

= 2012.10.01 =
* check if file is writeable; if not cancel actions / display message
* adjusting wp-plugin contents

= 2012.09.30 =
* initial Wordpress.org Plugins commit
* restructured for svn and wp-plugins hosting
* solved problems with wp-plugins site

= 2012.09.29 =
* submit for Wordpress.org approvement


== Installation ==

1. Upload to your plugins folder, usually found at 'wp-content/plugins/'
2. Activate the plugin on the plugins screen
3. You may want to activate WP logging setting WP_DEBUG_LOG to TRUE in your wp-config.php file
4. Navigate to Tools > Log Viewer to show and view log files

== Frequently Asked Questions ==

= I am admin! Why can't i see the Tools > Log Viewer menu entry? =
If your on a Multisite installation you have to be a Super Admin.
If your on a Singlesite installation you additionally have to have the 'edit_plugins' role.

= But i am a Super Admin with super powers and still can't see the Tools > Log Viewer menu entry! =
Pow! Slam! Donk! ... as stated you have to have 'edit_plugins' role. There are Wordpress constants like 'DISALLOW_FILE_EDIT' which deactivates this even for the greatest of the admins.
Have a look at [http://codex.wordpress.org/Roles_and_Capabilities](http://codex.wordpress.org/Roles_and_Capabilities) or do a websearch for 'wordpress.org DISALLOW_FILE_EDIT' and have a talk to your site maintainer.

= How to enable debug.log =
Simply add `define( 'WP_DEBUG_LOG', true );` in your wp-config.php file. This is not recommended on production environments!

= I changed my error_log to something other than WP default =
That's ok ... as long as the file extension is .log and it's located in WP_CONTENT_DIR. Other sources or extensions aren't supported for now.

= Can i show other files? =
Yes you can! As long as they are located in WP_CONTENT_DIR and have a .log extension. Other sources or extensions aren't supported for now.

= In Files View i only get the error message "Could not load file." or "No files found." =
It looks like there isn't a *.log file in WP_CONTENT_DIR. Which could mean there are no errors. Yay!
If there are files, it could be that they are not readable ( check your permissions ) or it's a bug ... Booo!

= I don't see File Actions options =
The options are only displayed if the file is writeable. Check your permissions.

== Upgrade Notice ==

= None yet.

== Screenshots ==

1. Screenshot shows the file view screen ( with MP6 / WordPress > 3.8 )
2. Screenshot shows the file view screen
3. Screenshot shows Debug Bar integration
