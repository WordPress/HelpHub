=== UpdraftPlus WordPress Backup Plugin ===
Contributors: Backup with UpdraftPlus, DavidAnderson, DNutbourne
Tags: backup, backups, restore, amazon backup, s3 backup, dropbox backup, google drive backup, rackspace cloud files, rackspace backup, dreamhost, dreamobjects backup, ftp backup, webdav backup, google cloud storage, onedrive, azure, back up, multisite, restoration, sftp backup, ftps, scp backup, migrate, duplicate, copy, mysql backup, database backup, db backups, website backup, wordpress backup, full backup, openstack backup, sicherung
Requires at least: 3.2
Tested up to: 4.5
Stable tag: 1.12.12
Author URI: https://updraftplus.com
Donate link: http://david.dw-perspective.org.uk/donate
License: GPLv3 or later

Backup and restoration made easy. Complete backups; manual or scheduled (backup to S3, Dropbox, Google Drive, Rackspace, FTP, SFTP, email + others).

== Description ==

<a href="https://updraftplus.com">UpdraftPlus</a> simplifies backups (and restoration). Backup into the cloud (Amazon S3 (or compatible), Dropbox, Google Drive, Rackspace Cloud, DreamObjects, FTP, Openstack Swift, UpdraftPlus Vault and email) and restore with a single click. Backups of files and database can have separate schedules. The paid version also backs up to Microsoft OneDrive, Microsoft Azure, Google Cloud Storage, SFTP, SCP, and WebDAV.

<strong>Top-quality:</strong> UpdraftPlus is the highest-ranking backup plugin on wordpress.org, with <strong>over 700,000 currently active installs</strong>. Widely tested and reliable, this is the world's #1 most popular and mostly highly rated scheduled backup plugin. Millions of backups completed!

[vimeo https://vimeo.com/154870690]

* Supports WordPress backups to UpdraftPlus Vault, Amazon S3 (or compatible), Dropbox, Rackspace Cloud Files, Google Drive, Google Cloud Storage, DreamHost DreamObjects, FTP, OpenStack (Swift) and email. Also (via a paid add-on) backup to Microsoft OneDrive, Microsoft Azure, Google Cloud Storage, FTP over SSL, SFTP, SCP, and WebDAV (and compatible services, e.g. Yandex, Cubby, OwnCloud). Examples of S3-compatible providers: Cloudian, Connectria, Constant, Eucalyptus, Nifty, Nimbula, Cloudn.
* Quick restore (both file and database backups)
* Backup automatically on a repeating schedule
* Site duplicator/migrator: can copy sites, and (with add-on) duplicate them at new locations
* Restores and migrates backup sets from other backup plugins (Premium) (currently supported: BackWPUp, BackupWordPress, Simple Backup, WordPress Backup To Dropbox)
* Files and database backups can have separate schedules
* Remotely control your backups on every site from a single dashboard with UpdraftCentral - <a href="https://updraftcentral.com">hosted for you</a> or <a href="https://wordpress.org/plugins/updraftcentral/">self-hosted</a>
* Failed uploads are automatically resumed/retried
* Large sites can be split into multiple archives
* Select which files to backup (plugins, themes, content, other)
* Select which components of a backup to restore
* Download backup archives direct from your WordPress dashboard
* Database backups can be encrypted for security (Premium)
* Debug mode - full logging of the backup
* Internationalised (translations welcome - see below)
* <a href="https://updraftplus.com">Premium version and support available (including free remote backup storage) - https://updraftplus.com</a>
* Supported on all current PHP versions (5.2 - 7.0)

From our <a href="https://www.youtube.com/user/UpdraftPlus/videos">YouTube channel</a>, here's how to install:

https://www.youtube.com/watch?v=7ReY7Z19h2I&rel=0

= Don't risk your backups on anything less =

Your WordPress backups are worth the same as your entire investment in your website. The day may come when you get hacked, or your hosting company does, or they go bust - without good backups, you lose everything. Do you really want to entrust all your work to a backup plugin with only a few thousand downloads, or that has no professional backup or support? Believe us - writing a reliable backup plugin that works consistently across the huge range of WordPress deployments is hard.

= UpdraftPlus Premium =

UpdraftPlus Backup/Restore is not crippled in any way - it is fully functional for backing up and restoring your site. What we do have is various extra features (including site cloning), and guaranteed support, available <a href="https://updraftplus.com/">from our website, updraftplus.com</a>. See <a href="https://updraftplus.com/comparison-updraftplus-free-updraftplus-premium/">a comparison of the free/Premium versions, here</a>.

If you need WordPress multisite backup compatibility (you'll know if you do), <a href="https://updraftplus.com/shop/">then you need UpdraftPlus Premium</a>.

= UpdraftCentral - Remote control =

As well as controlling your backups from within WordPress, you can also control all your sites' backups from a single dashboard, with <a href="https://updraftcentral.com">UpdraftCentral</a>. UpdraftCentral can control both free and Premium versions of UpdraftPlus, and comes in two versions:

* Hosted dashboard: <a href="https://updraftplus.com/my-account/updraftcentral-remote-control/">a ready-to-go dashboard on updraftplus.com</a>, with 5 free licences for everyone (<a href="https://updraftcentral.com">read more here</a>).
* Host your own: Host the dashboard on your own WP install, with <a href="https://wordpress.org/plugins/updraftcentral/">the free self-install plugin</a>

= Professional / Enterprise support agreements available =

UpdraftPlus Backup/Restore is written by professional WordPress developers. If your site needs guaranteed support, then we are available. Just  <a href="https://updraftplus.com/shop/">go to our shop.</a>

= More premium plugins =

If you are in the market for other WordPress premium plugins (especially WooCommerce addons), then try our shop, here: https://www.simbahosting.co.uk/s3/shop/

= Are you multi-lingual? Can you translate? =

Are you able to translate UpdraftPlus into another language? Are you ready to help speakers of your language? UpdraftPlus Backup/Restore itself is ready and waiting - the only work needed is the translating. The translation process is easy, and web-based - go here for instructions: <a href="https://updraftplus.com/translate/">https://updraftplus.com/translate/</a>. (Or if you're an expert WordPress translator already, then just pick out the .pot file from the wp-content/plugins/updraftplus/languages/ directory - if you scan for translatable strings manually, then you need to get these functions: _x(), __(), _e(), _ex(), log_e()).

Many thanks to the existing translators - listed at: https://updraftplus.com/translate/

= Other support =

We hang out in the WordPress support forum for this plugin - https://wordpress.org/support/plugin/updraftplus - however, to save time so that we can spend it on development, please read the plugin's FAQs - <a href="https://updraftplus.com/support/frequently-asked-questions/">https://updraftplus.com/support/frequently-asked-questions/</a> - before going there, and ensure that you have updated to the latest released version of UpdraftPlus backup/restore.

== Installation ==

<a href="https://updraftplus.com/download/">Full instructions for installing this plugin.</a>

== Frequently Asked Questions ==

<a href="https://updraftplus.com/support/frequently-asked-questions/"><strong>Please go here for the full FAQs - there are many more than below.</strong></a> Below are just a handful which particularly apply to the free wordpress.org version, or which bear repeating.

= Can UpdraftPlus do (something)? =

Check out <a href="https://updraftplus.com/updraftplus-full-feature-list/">our full list of features</a>, and our <a href="https://updraftplus.com/shop/">add-ons shop</a> and <a href="https://updraftplus.com/comparison-updraftplus-free-updraftplus-premium/">free/Premium comparison table</a>.

= I found a bug. What do I do? =

Note - this FAQ is for users of the free plugin. If you're a paying customer, then you should go here: https://updraftplus.com/support/ - please don't ask question in the WordPress.Org forum about purchases, as that's against their rules.

Next, please make sure you read this FAQ through - it may already have the answer you need. If it does, then please consider a donation (e.g. buy our "No Adverts" add-on - <a href="https://updraftplus.com/shop/">https://updraftplus.com/shop/</a>); it takes time to develop this plugin and FAQ.

If it does not, then contact us (<a href="http://wordpress.org/support/plugin/updraftplus">the forum is the best way</a>)! This is a complex backup plugin and the only way we can ensure it's robust is to get bug reports and fix the problems that crop up. Please make sure you are using the latest version of the plugin, and that you include the version in your bug report - if you are not using the latest, then the first thing you will be asked to do is upgrade.

Please include the backup log if you can find it (there are links to download logs on the UpdraftPlus settings page; or you may be emailed it; failing that, it is in the directory wp-content/updraft, so FTP in and look for it there). If you cannot find the log, then I may not be able to help so much, but you can try - include as much information as you can when reporting (PHP version, your blog's site, the error you saw and how you got to the page that caused it, any other relevant plugins you have installed, etcetera). http://pastebin.com is a good place to post the log.

If you know where to find your PHP error logs (often a file called error_log, possibly in your wp-admin directory (check via FTP)), then that's even better (don't send multi-megabytes; just send the few lines that appear when you run a backup, if any).

If you are a programmer and can debug and send a patch, then that's even better.

= Anything essential to know? =

After you have set up UpdraftPlus, you must check that your WordPress backups are taking place successfully. WordPress is a complex piece of software that runs in many situations. Don't wait until you need your backups before you find out that they never worked in the first place. Remember, there's no warranty and no guarantees - this is free software.

= My enormous website is hosted by a dirt-cheap provider who starve my account of resources, and UpdraftPlus runs out of time! Help! Please make UpdraftPlus deal with this situation so that I can save two dollars! =

UpdraftPlus supports resuming backup runs right from the beginning, so that it does not need to do everything in a single go; but this has limits. If your website is huge and your web hosting company gives your tiny resources on an over-loaded server, then go into the "Expert settings" and reduce the size at which zip files are split (versions 1.6.53 onwards). UpdraftPlus is known to successfully back up websites that run into the multiple-gigabytes on web servers that are not resource-starved.

= My site was hacked, and I have no backups! I thought UpdraftPlus was working! Can I kill you? =

No, there's no warranty or guarantee, etc. It's completely up to you to verify that UpdraftPlus is creating your backups correctly. If it doesn't then that's unfortunate, but this is a free plugin.

= I am not running the most recent version of UpdraftPlus. Should I upgrade? =

Yes; especially before you submit any support requests.

= Do you have any other free plugins? =

Thanks for asking; yes, we've got a few. Check out this profile page - https://profiles.wordpress.org/DavidAnderson/ .

== Changelog ==

The <a href="https://updraftplus.com/news/">UpdraftPlus backup blog</a> is the best place to learn in more detail about any important changes.

N.B. Paid versions of UpdraftPlus Backup / Restore have a version number which is 1 higher in the first digit, and has an extra component on the end, but the changelog below still applies. i.e. changes listed for 1.12.12 of the free version correspond to changes made in 2.12.12.x of the paid version.

= Development version (not yet released/supported)

* TWEAK: Extend cacheing of enumeration of uploads that was introduced in 1.11.1 to other data in wp-content also

= 1.12.13 - 07/Jun/2016 =

* TWEAK: Default the S3 secret key field type to 'password' instead of 'text'
* TWEAK: Do more checks for active output buffers prior to spooling files to the browser (to prevent memory overflows)
* TWEAK: Update bundled UDRPC library to version 1.4.7

= 1.12.12 - 25/May/2016 =

* FIX: When restoring a plugins backup on multisite, old plugins were inactivated but not always removed
* TWEAK: Use POST instead of GET for OneDrive token requests - some new accounts seem to have begun requiring this
* TWEAK: When backing up user-configured directories, don't log confusing/misleading messages for unzippable directory symlinks
* TRANSLATIONS: wordpress.org is now serving up translations for fr_FR, pt_PT and ro_RO, so these can/have been removed from the plugin zip (1.2Mb released)

= 1.12.11 - 19/May/2016 =

* FIX: 1.12.8 (paid versions only) contained a regression that prevented S3 access if the user had a custom policy that did not include location permission. This fix means that the work-around of adding that permission to the policy is no longer required.
* FIX: Fix a regression in 1.12.8 that prevented non-existent DreamObjects buckets from being created
* FIX: Fix inaccurate reporting of the current Vault quota usage in the report email since 1.12.8
* FIX: The short-lived 1.12.10 had a duplicate copy of the plugin in the release zip
* TWEAK: Detect a particular obscure PHP bug in some versions that is triggered by the Amazon S3 SDK, and automatically switch to the older SDK if it is hit (N.B. Not compatible with Frankfurt region).
* TWEAK: Audit/update all use of wp_remote_ functions to reflect API changes in the upcoming WP 4.6
* TWEAK: Tweak to the settings saving, to avoid a false-positive trigger of a particular rule found in some mod_security installs
* TWEAK Update bundled UDRPC library to version 1.4.5

= 1.12.9 - 11/May/2016 =

* FIX: In yesterday's 1.12.8, some previously accessible Amazon S3 buckets could no longer be accessed

= 1.12.8 - 10/May/2016 =

* FEATURE: Support S3's "infrequent access" storage class (Premium)
* FIX: Fix bug in SFTP uploading algorithm that would corrupt archives if a resumption was necessary
* TWEAK: Add information on UpdraftVault quota to reporting emails
* TWEAK: Update the bundled AWS library to version 2.8.30
* TWEAK: Update the bundled Symfony library to version 2.8.5
* TWEAK: Update the bundled phpseclib library to version 1.0.2 (which includes a fix for SFTP on PHP 5.3)
* TWEAK: Improve the overlapping runs detection when writing out individual database tables, for helping servers with huge tables without mysqldump
* TWEAK: Prevent restoration from replacing the local record of keys of remote sites to send backups to (Migrator add-on)
* TWEAK: Re-order the classes in class-zip.php, to help misbehaving XCache (and perhaps other opcode cache) instances
* TWEAK: Do not include transient update availability data in the backup (which will be immediately out-of-date)
* TWEAK: Updated the URLs of various S3-compatible providers to use SSL, where available
* TWEAK: Added an endpoint drop-down for Dreamobjects, using their new/updated endpoint (currently only one choice, but they will have more in future)
* TWEAK: Suppress a log message from UpdraftVault when that message is not in use
* TWEAK: When key creation times out in the Migrator, display the error message in the UI

= 1.12.6 - 30/Apr/2016 =

* FIX: UpdraftVault quota usage was being shown incorrectly in recounts on sites connected to accounts backing up multiple sites
* TWEAK: In accordance with Barracuda's previous announcement, copy.com no longer exists - https://techlib.barracuda.com/CudaDrive/EOL
* TWEAK: Allow particular log lines to be cancelled
* TWEAK: Explicitly set the separator when calling http_build_query(), to prevent problems with non-default configurations
* TWEAK: Tweak the algorithm for sending data to a remote UD installation to cope with eventually-consistent filesystems that are temporarily inconsistent
* TWEAK: Make the automatic backups advert prettier
* TWEAK: Detect and combine file and database backups running on different schedules which coincide
* TWEAK: Update bundled Select2 to version 4.0.2
* TWEAK: Update UDRPC library to version 1.4.3

Older changes are found in the changelog.txt file in the plugin directory.

== Screenshots ==

1. Main dashboard - screenshots are from UpdraftPlus Premium, so may reference some features that are not part of the free version

2. Configuring your backups

3. Restoring from a backup

4. Showing and downloading backup sets


== License ==

    Copyright 2011-16 David Anderson

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Furthermore, reliance upon any non-English translation is at your own risk. UpdraftPlus can give no guarantees that translations from the original English are accurate.

We recognise and thank the following for code and/or libraries used and/or modified under the terms of their open source licences; see: https://updraftplus.com/acknowledgements/


== Upgrade Notice ==
* 1.12.12: Various small updates and fixes
