#Contributing to the Docs Team (HelpHub)

### Ways to Contribute

1. Write documentation
2. Find bugs and create issues
3. Help code and fix issues
4. Propose design suggestions and improvements

----------

### Write documentation
There are always teams that need help with writing documentation. The Docs Team can help connect you to the people who need the help. Join our discussions in the [#docs](https://make.wordpress.org/docs/tag/docs/) slack channel on **Thursdays from 17:00 - 18:00 UTC** and offer your help.

### Find bugs and create issues
Take a look at the HelpHub site located at [wp-helphub.com](https://wp-helphub.com/). If you see any bugs or issues, please create an issue on our Github repo here: https://github.com/Kenshino/HelpHub/issues. 

### Help code and fix issues

**How to use this repo on your local computer**

1. Install WordPress locally.
2. Empty out the `wp-content` folder and clone this repo into it.
3. Add this line `define( 'WPORGPATH', 'https://wordpress.org/' ); ` to your site's `wp-config.php` file.
4. Make sure your `php.ini` file includes these lines below as `On`.
```
allow_url_fopen = On
allow_url_include = On
```

**Import database**
Included in this repo is the file `helphub.wordpress.2017-06-15.xml`. Import this using the WordPress Importer from within the `/wp-admin` of your local site.

1. Go to: `Tools -> Import` and click "Install Now" under WordPress at the bottom. This will install the WordPress Importer.
2. Click "Run Importer"
3. Choose the file mentioned above and click the button, "Upload file and import"
4. Set all the authors to a user account on your local site.
5. Check the box to "Download and import file attachments".
6. Click the button to begin. It may take a while to complete. If there are some failed imports, it should still be okay.
7. Your local install of HelpHub should now be ready to view in your browser.

### Propose design suggestions and improvements
Join our discussions in the [#docs](https://make.wordpress.org/docs/tag/docs/) slack channel on **Thursdays from 17:00 - 18:00 UTC** and offer your help. Or submit your ideas on our Github repo here: https://github.com/Kenshino/HelpHub/issues. 