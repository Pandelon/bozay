        module.exports = function(grunt) {

    // Project configuration.
    grunt.initConfig({
        // Grunt-sass
        sass: {
            app: {
                // Takes every file that ends with .scss from the scss
                // directory and compile them into the css directory.
                // Also changes the extension from .scss into .css.
                // Note: file name that begins with _ are ignored automatically
                files: [{
                    expand: true,
                    cwd: 'scss',
                    src: ['*.scss'],
                    dest: 'css',
                    ext: '.css'
                }]
            },
            options: {
                sourceMap: true,
                // :nested :compact :expanded :compressed
                outputStyle: 'expanded',
                imagePath: "../",
                includePaths: [ 'sass/' ],
            }
        },

        // Grunt-contrib-watch
        watch: {
            sass: {
                // Watches all Sass or Scss files within the scss folder and one level down.
                // If you want to watch all scss files instead, use the "**/*" globbing pattern
                files: ['scss/{,**/*}*.{scss,sass}'],
                // runs the task `sass` whenever any watched file changes
                tasks: ['sass']
            }
        }
    });

          // Loads Grunt Tasks
    // grunt.loadNpmTasks('grunt-browser-sync');
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-contrib-watch');

    // Default task(s).
    grunt.registerTask('default', ['sass', 'watch']);
};
