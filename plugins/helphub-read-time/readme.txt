=== Helphub Post Types ===
Contributors: justingreerbbi
Requires at least: 4.3
Tested up to: 4.0.0
Stable tag: 1.0.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Generates a rough read time estimate for a post.

== Description ==

Generates a rough read time estimate for a post.

== Usage ==

Display read time for a single post in the loop. 
`<?php hh_the_read_time(); ?>`

Display the read time for a post outside the loop.
`<?php hh_the_read_time( $post->ID ); ?>`


== Installation ==

Installing "Helphub Read Time" can be done either by searching for "Helphub Read Time" via the "Plugins > Add New" screen in your WordPress dashboard, or by using the following steps:

1. Upload the ZIP file through the "Plugins > Add New > Upload" screen in your WordPress dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit the settings screen and configure, as desired.

== Frequently Asked Questions ==

== Upgrade Notice ==

== Changelog ==

= 1.0.0 =
* Initial release.

= 1.0.1 =
* Adjusted Read Time "Words Per Minute" for documentation style reading
* Added Pre tag adjustment to add more weight for words count in pre tags.