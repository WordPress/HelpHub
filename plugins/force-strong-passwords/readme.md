# Force Strong Passwords

Contributors: boogah, gyrus, simonwheatley, sparanoid, jpry, zyphonic  
Tags: passwords, security, users, profile  
Requires at least: 3.5  
Tested up to: 4.6  
Stable tag: 1.7  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

## Description

The WordPress user profile editor includes a JavaScript-powered password strength indicator. However, there is nothing currently built into WordPress core to prevent users from entering weak passwords. Users changing their password to something weak is one of the most vulnerable aspects of a WordPress installation.

With Force Strong Passwords activated, strong passwords are enforced for users with `publish_posts`, `upload_files` & `edit_published_posts` capabilities. Should a user with these capabilities (normally an Author, Editor or Administrator) attempt to change their password, the strong password enforcement will be triggered.

To customize the list of [capabilities](http://codex.wordpress.org/Roles_and_Capabilities) Force Strong Passwords checks for, use the `slt_fsp_caps_check` filter.

**IMPORTANT:** As of WordPress 3.7, the password strength meter in core is based on the [`zxcvbn` JavaScript library](https://tech.dropbox.com/2012/04/zxcvbn-realistic-password-strength-estimation/) from Dropbox. Force Strong Passwords simply passes the results of the client-side `zxcvbn` check along for the server to decide if an error should be thrown. Be aware that a technically savvy user *could* disable this check in the browser.

## Filters

**`slt_fsp_caps_check` (should return an array)**

Modifies the array of capabilities so that strong password enforcement will be triggered for any matching users.

**Ex:** To make sure users who can update WordPress core require strong passwords:

```
	add_filter( 'slt_fsp_caps_check', 'my_caps_check' );
	function my_caps_check( $caps ) {
		$caps[] = 'update_core';
		return $caps;
	}
```

**Ex:** To trigger strong password enforcement for *all* users:

```
	if ( function_exists( 'slt_fsp_init' ) ) {
		//plugin is activated
		add_filter( 'slt_fsp_caps_check', '__return_empty_array' );
	}
```

**`slt_fsp_error_message` (should return a string)**

Modifies the default error message.

**`slt_fsp_weak_roles` (should return an array)**

Modifies the array of roles that are considered "weak", and for which strong password enforcement is skipped *when creating a new user*. In this situation, the user object has yet to be created. This means that there are no capabilities to go by. Because of this, Force Strong Passwords has to use the role that has been set on the Add New User form.

The default array includes: `subscriber` and `contributor`.

## Manual Installation

1. Upload the `force-strong-passwords` directory into the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Changelog

### 1.7

* Work on tidying up code.
* Update hint link to use official zxcvbn test site. (thanks [EmTeedee](https://github.com/EmTeedee)!)

### 1.6.5
* Fix problem with non wp-error object:$errors (thanks [Terrance Orletsky](https://github.com/EarthmanWeb)!)

### 1.6.4
* Add a new `FSP_PLUGIN_VERSION` constant

### 1.6.3
* Updated `wp_enqueue_script` version argument

### 1.6.2
* Fixed issue where password resets weren't working
* Tested to WordPress 4.3.1

### 1.6.1
* Fixing the i18n fix

### 1.6
* I18n fix courtesy of [John Dittmar](https://github.com/JohnDittmar/)
* Added German translation (thanks [Becki Beckmann](https://github.com/beckspaced)!)
* Added Brazilian Portuguese translation (thanks [Alexandre Kozoubsky](https://github.com/akozoubsky)!)
* Documentation improvements
* Tested to WordPress 4.3

### 1.5.2
* Clean up documentation
* Test for WordPress 4.2.2
* Change donation link to [Girl Develop It](https://www.girldevelopit.com)

### 1.5.1
* Enforce strong passwords on password reset form (thanks [Steve Bruner](https://github.com/sbruner)!)

### 1.5
* Added French translation (thanks [Damien Piquet](https://github.com/dpiquet)!)
* Added input sanitization (thanks [Jenny Wong](https://github.com/missjwo)!)

### 1.4
* Enforce on multisite network admin screens (thanks [Damien Piquet](https://github.com/dpiquet)!)

### 1.3.4
* Updated Chinese Simplified Language translation (thanks sparanoid!)

### 1.3.3
* zxcvbn password hints.
* Now allows for non-Latin character set encoding when comparing zxcvbn meter result (thanks jpry!)

### 1.3.2
* Added Serbo-Croatian translation (thanks Borisa Djuraskovic!)

### 1.3.1
* Fixed so zxcvbn check respects localization (thanks lakrisgubben!)

### 1.3
* Switched to JS-aided enforcement of new zxcvbn check in WP 3.7+
* Added Japanese translation (thanks Fumito Mizuno!)

### 1.2.2
* Added Chinese Simplified Language support (thanks sparanoid!)

### 1.2.1
* Fixed bug that triggered enforcement on profile update even when no password is being set

### 1.2
* Added `slt_fsp_error_message` filter to customize error message
* Deprecated `SLT_FSP_CAPS_CHECK` constant; added `slt_fsp_caps_check` filter
* Added `slt_fsp_weak_roles` filter

### 1.1
* Used new `validate_password_reset` 3.5 hook to implement checking on reset password form (thanks simonwheatley!)
* PHPDoc for callable functions
* Improved function naming
* Added control over capabilities that trigger strong password enforcement via `SLT_FSP_CAPS_CHECK` constant

### 1.0
* First version
