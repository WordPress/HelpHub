/* jshint node:true */
/* global module */
module.exports = function( grunt ) {
	var HH_CSS = [
			'plugins/helphub-*/assets/css/*.css',
			'plugins/helphub-contributors/public/css/helphub-contributors-public.css'
		],

		HH_SCSS = [
			'plugins/**/*.scss',
			'themes/helphub/**/*.scss'
		],

		HH_JS = [
			'plugins/helphub-*/**/*.js',
			'themes/helphub/js/*.js'
		],

		autoprefixer = require('autoprefixer'),

		matchdep = require('matchdep'),

		stylelintConfig = require('stylelint-config-wordpress/index.js'),
		scssStylelintConfig = require('stylelint-config-wordpress/scss.js');

	// Load tasks.
	matchdep.filterDev('grunt-*').forEach( grunt.loadNpmTasks );

	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON( 'package.json' ),
		checktextdomain: {
			options: {
				text_domain: 'wporg-forums',
				correct_domain: false,
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'_n:1,2,4d',
					'_ex:1,2c,3d',
					'_nx:1,2,4c,5d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src: [
					'plugins/support-helphub/**/*.php',
					'themes/helphub/**/*.php'
				],
				expand: true
			}
		},
		checkDependencies: {
			options: {
				packageManager: 'npm'
			},
			src: {}
		},
		jscs: {
			src: HH_JS,
			options: {
				config: '.jscsrc',
				fix: false // Autofix code style violations when possible.
			}
		},
		jshint: {
			options: grunt.file.readJSON( '.jshintrc' ),
			grunt: {
				src: [ 'Gruntfile.js' ]
			},
			core: {
				expand: true,
				src: HH_JS
			}
		},
		jsvalidate:{
			options:{
				globals: {},
				esprimaOptions:{},
				verbose: false
			},
			files: {
				src: HH_JS
			}
		},
		postcss: {
			options: {
				map: false,
				processors: [
					autoprefixer({
						browsers: [
							'> 1%',
							'ie >= 11',
							'last 1 Android versions',
							'last 1 ChromeAndroid versions',
							'last 2 Chrome versions',
							'last 2 Firefox versions',
							'last 2 Safari versions',
							'last 2 iOS versions',
							'last 2 Edge versions',
							'last 2 Opera versions'
						],
						cascade: false
					})
				],
				failOnError: false
			},
			helphub: {
				expand: true,
				src: 'themes/wporg-support/style.css'
			},
			contributors: {
				expand: true,
				src: 'plugins/support-helphub/inc/helphub-contributors/public/css/helphub-contributors-public.css'
			}
		},
		sass: {
			helphub: {
				expand: true,
				ext: '.css',
				cwd: 'themes/wporg-support/sass/',
				dest: 'themes/wporg-support/',
				src: [ 'style.scss' ],
				options: {
					indentType: 'tab',
					indentWidth: 1,
					outputStyle: 'expanded'
				}
			},
			contributors: {
				expand: true,
				ext: '.css',
				cwd: 'plugins/support-helphub/inc/helphub-contributors/src/sass/',
				dest: 'plugins/support-helphub/inc/helphub-contributors/public/css/',
				src: [ 'helphub-contributors-public.scss' ],
				options: {
					indentType: 'tab',
					indentWidth: 1,
					outputStyle: 'expanded'
				}
			}
		},
		stylelint: {
			css: {
				options: {
					config: stylelintConfig
				},
				expand: true,
				src: HH_CSS
			},

			scss: {
				options: {
					config: scssStylelintConfig,
					syntax: 'scss'
				},
				expand: true,
				src: HH_SCSS
			}
		},
		copy: {
			main: {
				files: [
					{
						expand: true,
						src: ['node_modules/select2/dist/**'],
						dest: 'plugins/support-helphub/inc/helphub-contributors/admin/assets/'
					}
				]
			}
		},
		watch: {
			config: {
				files: 'Gruntfile.js'
			},
			sass: {
				files: HH_SCSS,
				tasks: [ 'sass', 'postcss:helphub', 'postcss:contributors' ]
			}
		}
	});

	// CSS test task.
	grunt.registerTask( 'csstest', 'Runs all CSS tasks.', [ 'stylelint' ] );

	// JavaScript test task.
	grunt.registerTask( 'jstest', 'Runs all JavaScript tasks.', [ 'jsvalidate', 'jshint', 'jscs' ] );

	// PHP test task.
	grunt.registerTask( 'phptest', 'Runs all PHP tasks.', [ 'checktextdomain' ] );

	// Travis CI Task
	grunt.registerTask( 'travis', 'Runs Travis CI tasks.',[ 'csstest', 'jstest', 'phptest' ] );

	// Default task.
	grunt.registerTask( 'default', [
		'checkDependencies',
		'copy',
		'csstest',
		'jstest',
		'phptest',
		'sass',
		'postcss'
	] );
};
