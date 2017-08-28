# Contributing to the Docs Team (HelpHub)

### Ways to Contribute

1. [Write documentation](#write-documentation)
2. [Find bugs and create issues](#find-bugs-and-create-issues)
3. [Help code and fix issues](#help-code-and-fix-issues)
    1. [Local Install](#local-install)
    2. [HelpHub Theme](#helphub-theme)
4. [Propose design suggestions and improvements](#propose-design-suggestions-and-improvements)

----------

### Write documentation
There are always teams that need help with writing documentation. The Docs Team can help connect you to the people who need the help. Join our discussions in the [#docs](https://make.wordpress.org/docs/tag/docs/) slack channel on **Thursdays from 17:00 - 18:00 UTC** and offer your help.

### Find bugs and create issues
Take a look at the HelpHub site located at [wp-helphub.com](https://wp-helphub.com/). If you see any bugs or issues, please create an issue on our GitHub repo here: https://github.com/Kenshino/HelpHub/issues.

### Help code and fix issues

#### Local Install

**How to use this repo on your local computer**

1. Install WordPress locally.
2. Empty out the `wp-content` folder and clone this repo into it.
3. Compile theme's `.scss` files into `style.css` [see below](#helphub-theme)
4. Activate the HelpHub Theme from within `/wp-admin`.
5. Activate the necessary plugins from within `/wp-admin`; *HelpHub Post Types*, •HelpHub Read Time*, *SyntaxHighlighter Evolved*, and *Table of Contents Lite*.
6. Under `Settings -> Permalinks` in the `/wp-admin`, change to "Post Name" option, and save changes.
7. Add this line `define( 'WPORGPATH', 'https://wordpress.org/' ); ` to your site's `wp-config.php` file.
8. Make sure your `php.ini` file includes these lines below as `On`.

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

Once you have a local install of HelpHub up and running, you can contribute with pull requests either from your own fork or after you've added as a contributor directly in this repository. We are using [Travis CL](https://travis-ci.org/) for tests on every pull request. You can, also, run these tests locally before pushing your code (more on this later). Development covers work on both, theme and plugins and requires following [best practices](https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/) and [WordPress Coding Standards](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards).

#### HelpHub

**Requirements:**

- [npm](https://www.npmjs.com/get-npm)
- [Grunt](https://gruntjs.com/)
- [Sass](http://sass-lang.com/)

HelpHub uses a task runner called [Grunt](https://gruntjs.com/). Grunt contains automated tasks for the project (which can be anything - building svg sprites, minifying css and js files etc). We are using to verify and check the integrity of the CSS, JavaScript, and Sass files within this repository along with compiling the themes Sass files into CSS.

To be able to run Grunt you need [npm](https://www.npmjs.com/get-npm). We will assume that you already have npm.


First we need to install dependencies from `package.json` file:
```
npm install
```

After this command has run in your terminal you'll have another folder in the root, `node_modules`. This folder is ignored in `.gitignore` and contains all the tools we need for running Grunt tasks, defined in `Gruntfile.js`.


The HelpHub theme uses [Sass](http://sass-lang.com/) for applying styles as it provides possibility for breaking one large `style.css` file into smaller partials and, therefore, reduce possible Git conflicts caused with multiple modifications of the same file in different branches. Maintenance is, also, greatly improved. Once modified, Sass (`.scss`) files need to be compiled into `style.css`.

The Grunt `sass` task compiles all `.scss` files into `style.css`. This means that every time you run this task `style.css` will be overridden with new code from `.scss` files, located in the themes `sass` folder. Hence, instead modifying `style.css` directly, all CSS changes for the HelpHub theme are to be added into appropriate `.scss` partial after which you should run compiler in order to see your changes. Compiling is done with running following command in terminal:

```
grunt sass
```

If, however, you are adding a lot of CSS changes, instead of running a lot of `sass` tasks, you can run `watch` task, like so:

```
grunt watch
```

This task tells compiler to watch all changes created in `.scss` files and rebuild a new `style.css` every time you save the file.

While you can use regular CSS syntax in `.scss` files (as long as Travis tests are passed), we would like to encourage you to [learn](http://sass-lang.com/guide) and use Sass as much as possible. It's good for you and for project.

### Propose design suggestions and improvements

Join our discussions in the [#docs](https://wordpress.slack.com/messages/docs/) [slack](https://make.wordpress.org/chat/) channel on:

- **Tuesdays from 15:00 - 16:00 UTC** - content, design and development discussion,
- **Thursdays from 17:00 - 17:30 UTC** - bug scrubs,

or submit your ideas on our GitHub repo [here](https://github.com/Kenshino/HelpHub/issues).
