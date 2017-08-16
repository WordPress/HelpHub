/*jslint node: true */
"use strict";

var gulp         = require( "gulp" );
var plumber      = require( "gulp-plumber" );
var autoprefixer = require( "gulp-autoprefixer" );
var sass         = require( "gulp-sass" );
var livereload   = require( "gulp-livereload" );
global.notify    = require( "gulp-notify" );

gulp.task( 'css', function() {
	return gulp.src( 'sass/style.scss' )
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
		.pipe( gulp.dest('.'))
		.pipe( livereload() );
});

/**
 * Watcher
 *
 * Watch for any changes in our src files and run
 * appropriate task
 */
gulp.task( 'watch', function() {
	livereload.listen();
	gulp.watch( 'sass/**/*.scss', ['css'] );
});
