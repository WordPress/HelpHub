/*jslint node: true */
"use strict";

var gulp         = require( "gulp" );
var del          = require( "del" );
var plumber      = require( "gulp-plumber" );
var autoprefixer = require( "gulp-autoprefixer" );
var sass         = require( "gulp-sass" );
var livereload   = require( "gulp-livereload" );
var merge        = require( "merge-stream" );
global.notify    = require( "gulp-notify" );

/**
 * CSS build tasks
 */
// Delete all css files
gulp.task( 'clean:css', function(cb) {
	return del([
		'admin/css/**',
		'public/css/**'
	], cb);
});
// CSS Assets
gulp.task( 'assets:css', function() {
	return gulp.src( 'node_modules/select2/dist/css/**.css' )
		.pipe( plumber() )
		.pipe( gulp.dest( 'admin/css/' ) )
		.pipe( livereload() );
});
// Build plugin's css files, run clean and assets tasks
gulp.task( 'css', ['clean:css', 'assets:css'], function() {
	var admin_css = gulp.src( 'src/sass/helphub-contributors-admin.scss' )
		.pipe( plumber({
			errorHandler: notify.onError({
				title: 'SCSS',
				message: function( err ) {
					return 'Error: ' + err.message;
				}
			})
		}))
		.pipe( autoprefixer({
			browsers: ['last 5 versions', 'ie >= 9']
		}))
		.pipe( sass({
			outputStyle: 'nested'
		}))
		.pipe( gulp.dest( 'admin/css/' ) )
		.pipe( livereload() );

	var public_css = gulp.src( 'src/sass/helphub-contributors-public.scss' )
		.pipe( plumber({
			errorHandler: notify.onError({
				title: 'SCSS',
				message: function( err ) {
					return 'Error: ' + err.message;
				}
			})
		}))
		.pipe( autoprefixer({
			browsers: ['last 5 versions', 'ie >= 9']
		}))
		.pipe( sass({
			outputStyle: 'nested'
		}))
		.pipe( gulp.dest( 'public/css/' ) )
		.pipe( livereload() );

	return merge( admin_css, public_css );
});

/**
 * JS build tasks
 */
// Delete all js files
gulp.task( 'clean:js', function(cb) {
	return del([
		'admin/js/**/*',
		'public/js/**/*'
	], cb);
});
// JS Assets
gulp.task( 'assets:js', function() {
	return gulp.src( 'node_modules/select2/dist/js/**' )
		.pipe( plumber() )
		.pipe( gulp.dest( 'admin/js/' ) )
		.pipe( livereload() );
});
// Build plugin's files, run clean and assets tasks
gulp.task( 'js', ['clean:js', 'assets:js'], function() {
	var admin_js = gulp.src( 'src/js/helphub-contributors-admin.js' )
		.pipe( plumber() )
		.pipe( gulp.dest( 'admin/js/' ) )
		.pipe( livereload() );

	var public_js = gulp.src( 'src/js/helphub-contributors-public.js' )
		.pipe( plumber() )
		.pipe( gulp.dest( 'public/js/' ) )
		.pipe( livereload() );

	return merge( admin_js, public_js );
});
/**
 * Watcher
 *
 * Watch for any changes in our src files and run
 * appropriate task
 */
gulp.task( 'watch', function() {
	livereload.listen();
	gulp.watch( 'src/sass/*.scss', ['css'] );
	gulp.watch( 'src/js/**/*', ['js'] );
});

// Build task
gulp.task( 'build', ['css', 'js']);