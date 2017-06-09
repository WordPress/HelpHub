module.exports = function(grunt) {
	grunt.initConfig({
		qunit: {
			files: ['tests/**/*.html']
		},
		jshint: {
			files: [
				'Gruntfile.js',
				'src/**/*.js',
				'tests/qunit/**/*.js',
				'!tests/qunit/vendor/**'
			],
			options: grunt.file.readJSON('.jshintrc')
		},
		jscs: {
			src: [
				'src/qunit/**/*.js',
				'tests/qunit/**/*.js',
				'!tests/qunit/vendor/**'
			],
			options: {
				verbose: true,
				preset: 'wordpress'
			}
		},
		phpcs: {
			files: 'src/**/*.php',
			options: {
				bin: 'vendor/bin/phpcs',
				verbose: true,
				showSniffCodes: true,
				standard: 'codesniffer.ruleset.xml'
			}
		},
		watch: {
			js: {
				files: ['<%= jshint.files %>'],
				tasks: ['jshint', 'jscs', 'qunit']
			},
			php: {
				files: ['<%= phpcs.files %>'],
				tasks: ['phpcs']
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-qunit');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-phpcs');
	grunt.loadNpmTasks('grunt-jscs');

	grunt.registerTask('default', ['jshint', 'jscs', 'qunit', 'phpcs']);
};
