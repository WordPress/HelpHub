/*jslint node: true */
"use strict";

var gulp         = require( "gulp" );
var concat       = require( "gulp-concat" );
var sass         = require( "gulp-sass" );
var sourcemaps   = require( "gulp-sourcemaps" );
var autoprefixer = require( "gulp-autoprefixer" );

gulp.task( 'css', function() {
    return gulp.src( 'sass/style.scss' )
        .pipe( sourcemaps.init() )
        .pipe( sass().on( 'error', sass.logError ) )
        .pipe( sourcemaps.write() )
        .pipe( autoprefixer() )
        .pipe( concat( 'style.css' ) )
        .pipe( gulp.dest( '.' ) );
});
