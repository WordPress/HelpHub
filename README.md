# HelpHub

[![Build Status](https://travis-ci.org/Kenshino/HelpHub.svg?branch=master)](https://travis-ci.org/Kenshino/HelpHub) 


HelpHub is going to be the new portal for all WordPress user documentation that currently resides on the [WordPress Codex](https://codex.wordpress.org/). This repo is where we will be managing development of this new portal.

## Get Involved

You can get involved in development (or any other aspect of the project) by attending our weekly meetings in the #docs channel of [the Making WordPress Slack](https://make.wordpress.org/chat/) every Tuesday at 14:00 UTC.

## How to use this repo

To use this repo, simply create a new WordPress site on your local machine (using whatever development environment suits you), then empty out the `wp-content` folder and clone this repo into it. You will also need to add the following line to your site's `wp-config.php` file:

```
define( 'WPORGPATH', 'https://wordpress.org/' );
```

## Workflow

Anyone is welcome to fork the repo and send pull requests, but the project collaborators (listed below) have push access directly to the repo. **All pull requests will be be reviewed by at least one collaborator with commit access and the PR must pass tests. Collaborators will be responsible for merging their own pull requests once the reviews has been approved. Major feature development will require a review from the project lead, minor ones do not.**

We use ZenHub for project management, see https://github.com/Kenshino/HelpHub/issues/79 for help on using it
 
### Feature development

All features are to be built in individual branches named `feature-abc` (where `abc` is a brief descriptor of the feature) and submitted via pull request to the `master` branch. For best results, features should be separated into their own plugins, but the project lead will evaluate this for each pull request depending on the requirements and scope of the feature.

### Bug fixes

Any fixes that do not qualify as new features are to done in individual branches named `fix-abc` (where `abc` is a brief descriptor of the fix) and submitted via pull request to the `master` branch.

### Development guidelines

As this is a WordPress community project, all development must have a strong committment to accessibility and responsive design. We will also be following the [WordPress coding standards](https://codex.wordpress.org/WordPress_Coding_Standards) throughout the project.

Given that we will ultimately need to localise the whole site for different languages, please use `helphub` as the text domain for all text strings.

A database export is available here - https://github.com/Kenshino/HelpHub/blob/master/helphub.wordpress.2017-06-15.xml

### Design guidelines

See the [HelpHub wireframes](https://wp-commhub.mybalsamiq.com/projects/helphub/grid) for a guide on the design and layout of the project and note that all design must be consistent with the rest of [WordPress.org](https://wordpress.org/).

## Project collaborators

Project Lead: [Jon Ang](https://profiles.wordpress.org/kenshino)

The following people are active developers on the project and are all listed as collaborators on this repo:

| Name               	| GitHub username   	| Slack username 	|
|--------------------	|-------------------	|----------------	|
| Jon Ang           	| @kenshino          	| kenshino       	|
| Carl Alberto          | @carl-alberto         | carlalberto       |
| Marius Jensen         | @clorith              | clorith           |
| Jude Rosario          | @JudeRosario          | lumberhack        |
| Milana Cap            | @zzap                 | zzap              |
| Akira Tachibana       | @atachibana           | atachibana        |
| Stephen Edgar         | @ntwb                 | netweb            |