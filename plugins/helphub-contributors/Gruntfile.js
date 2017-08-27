/* jshint node:true */
/* global module */
module.exports = function( grunt ) {
	var HH_CSS = [
			'public/css/helphub-contributors-public.css'
		],

		HH_SCSS = [
			'src/sass/helphub-contributors-public.scss'
		],

		autoprefixer = require( 'autoprefixer' ),

		matchdep = require( 'matchdep' ),

		stylelintConfig = require( 'stylelint-config-wordpress/index.js' ),
		scssStylelintConfig = require( 'stylelint-config-wordpress/scss.js' );

	// Load tasks.
	matchdep.filterDev( 'grunt-*' ).forEach( grunt.loadNpmTasks );

	// Project configuration.
	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),
		checkDependencies: {
			options: {
				packageManager: 'npm'
			},
			src: {}
		},
		postcss: {
			options: {
				map: false,
				processors: [
					autoprefixer( {
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
					} )
				],
				failOnError: false
			},
			helphub: {
				expand: true,
				src: 'src/sass/helphub-contributors-public.scss'
			}
		},
		sass: {
			helphub: {
				expand: true,
				ext: '.css',
				cwd: 'src/sass/',
				dest: 'public/css/',
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
		watch: {
			config: {
				files: 'Gruntfile.js'
			},
			sass: {
				files: HH_SCSS,
				tasks: [ 'sass:helphub', 'postcss:helphub' ]
			}
		},
		copy: {
			main: {
				files: [
					{
						expand: true,
						src: ['node_modules/select2/dist/**'],
						dest: 'admin/assets/'
					}
				]
			}
		}
	});

	// CSS test task.
	grunt.registerTask( 'csstest', 'Runs all CSS tasks.', [ 'stylelint' ] );

	// Default task.
	grunt.registerTask( 'default', [
		'checkDependencies',
		'csstest',
		'sass',
		'postcss'
	] );
};
