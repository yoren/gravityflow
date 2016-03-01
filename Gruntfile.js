
module.exports = function(grunt) {
    'use strict';

    var config;

	if ( grunt.file.exists('config.json') ) {
		config = grunt.file.readJSON('config.json');
	} else {
		config = {
			"dropbox" : {
				"access_token" : process.env.DROPBOX_TOKEN,
				"upload_path" : process.env.DROPBOX_UPLOAD_PATH
			},
			"s3InlineDocs" : {
				"accessKeyId" : process.env.AWS_ACCESS_KEY_ID,
				"secretAccessKey" : process.env.AWS_SECRET_ACCESS_KEY,
				"bucket" : process.env.AWS_S3_BUCKET_INLINE_DOCS,
				"region" : process.env.AWS_DEFAULT_REGION
			},
			"slackUpload" : {
				"token" : process.env.SLACK_TOKEN_UPLOAD,
				"channel" : process.env.SLACK_CHANNEL_UPLOAD
			}
		};
	}
    var gfVersion = '';

    require('matchdep').filterDev('grunt-*').forEach( grunt.loadNpmTasks );

    grunt.getVersion = function(){
        var p = 'gravityflow.php';
        if(gfVersion == '' && grunt.file.exists(p)){
            var source = grunt.file.read(p);
            var found = source.match(/Version:\s(.*)/);
            gfVersion = found[1];
        }

        return gfVersion;
    };

    grunt.getDropboxConfig = function(){
        var key = config.dropbox.upload_path;
        var obj = {};
		key += '/Releases';
		obj[key] = [ 'gravityflow_<%= grunt.getVersion() %>.zip', 'gravityflow_docs_latest.zip'];
        return obj;
    };

    grunt.initConfig({

        /**
         * Generate a POT file.
         */
        makepot: {
            all: {
                options: {
                    cwd: '.',
                    mainFile: 'gravityflow.php',
                    domainPath: 'languages',
                    potComments: 'Copyright 2015-{year} Steven Henty.',
                    potHeaders: {
                        'language-team': 'Steven Henty <support@gravityflow.io>',
                        'last-translator': 'Steven Henty <support@gravityflow.io>',
                        'report-msgid-bugs-to': 'https://www.gravityflow.io',
                        'Project-Id-Version': 'gravityflow',
                        'language': 'en_US',
                        'plural-forms': 'nplurals=2; plural=(n != 1);',
                        'x-poedit-basepath': '../',
                        'x-poedit-bookmarks': '',
                        'x-poedit-country': 'United States',
                        'x-poedit-keywordslist': true,
                        'x-poedit-searchpath-0': '.',
                        'x-poedit-sourcecharset': 'utf-8',
                        'x-textdomain-support': 'yes',
                        'x-generator' : 'Gravity Flow Build Server'
                    },
                    type: 'wp-plugin',
                    updateTimestamp: true
                }
            }
        },

        /**
         * Unit tests
         */
		phpunit: {
			classes: {
				dir: ''
			},
			options: {
				bin: 'vendor/bin/phpunit',
				bootstrap: process.cwd() + '/tests/bootstrap.php',
				colors: true
			}
		},

        /**
         * Minify JavaScript source files.
         */
        uglify: {
            gravityflow: {
                expand: true,
                ext: '.min.js',
                src: [
                    'js/*.js',

                    // Exclusions
                    '!js/*.min.js',
                ]
            }
        },
        /**
         * Minify CSS source files.
         */
        cssmin: {
            gravityflow: {
                expand: true,
                ext: '.min.css',
                src: [
                    'css/*.css',
                    // Exclusions
                    '!css/*.min.css',
                ]
            }
        },

        /**
         * Compression tasks
         */
        compress: {
            gravityflow: {
                options: {
                    archive: 'gravityflow_<%= grunt.getVersion() %>.zip'
                },
                files: [
                    { src: 'includes/**', dest: 'gravityflow/' },
                    { src: 'js/**', dest: 'gravityflow/'  },
                    { src: 'css/**', dest: 'gravityflow/'  },
                    { src: 'images/**', dest: 'gravityflow/'  },
                    { src: 'languages/**', dest: 'gravityflow/'  },
                    { src: 'readme.txt', dest: 'gravityflow/'  },
                    { src: 'gravityflow.php', dest: 'gravityflow/'  },
                    { src: 'class-gravity-flow.php', dest: 'gravityflow/'  },
                    { src: 'index.php', dest: 'gravityflow/' }
                ]
            },
            docs: {
                options: {
                    archive: 'gravityflow_docs_latest.zip'
                },
                files: [
                    { src: 'docs/**' }
                ]
            }
        },

        /**
         * Cleaning - removing temp files
         */
        clean: {
            options: {
                force: true
            },
            all: [ 'apigen_tmp', 'docs', 'gravityflow_<%= grunt.getVersion() %>.zip', 'gravityflow_docs_latest.zip' ]
        },

        /**
         * Shell commands
         */
        shell: {
            options: {
                stdout: true,
                stderr: true
            },
            apigen: {
                command: [
                    'cd vendor/bin/',
                    'export TMPDIR=' + process.cwd() + '/apigen_tmp',
                    'php apigen generate --config="../../apigen.neon"'
                ].join('&&')
            },
            transifex:{
                command: [
                    'tx pull -a -f --minimum-perc=1'
                ].join('&&')
            }
        },

        dropbox: {
            options: {
                cwd: 'docs',
                access_token: config.dropbox.access_token
            },
            upload: {
                files: grunt.getDropboxConfig()
            }
        },

        aws_s3: {
            options: {
                uploadConcurrency: 5, // 5 simultaneous uploads
                downloadConcurrency: 5 // 5 simultaneous downloads
            },
            inlinedocs: {
                options: {
                    accessKeyId: config.s3InlineDocs.accessKeyId, // Use the variables
                    secretAccessKey: config.s3InlineDocs.secretAccessKey, // You can also use env variables
                    region: config.s3InlineDocs.region,
                    bucket: config.s3InlineDocs.bucket,
                    access: 'public-read',
                    differential: true // Only uploads the files that have changed
                },
                files: [
                    {expand: true, cwd: 'docs', src: ['**'], dest: ''},
                ]
            }
        },

        potomo: {
            dist: {
                options: {
                    poDel: false
                },
                files: [{
                    expand: true,
                    cwd: 'languages',
                    src: ['*.po'],
                    dest: 'languages',
                    ext: '.mo',
                    nonull: true
                }]
            }
        },

		slack_upload: {
			gravityflow: {
				options: {
					token: config.slackUpload.token,
					filetype: 'zip',
					file: 'gravityflow_<%= grunt.getVersion() %>.zip',
					title:'gravityflow_<%= grunt.getVersion() %>.zip',
					channels: config.slackUpload.channel
				}
			}
		}

	});

	grunt.registerTask('minimize', [ 'uglify:gravityflow', 'cssmin:gravityflow' ]);
	grunt.registerTask('translations', [ 'makepot', 'shell:transifex', 'potomo' ]);
	grunt.registerTask('default', [ 'clean', 'minimize', 'translations', 'compress' ]);
	grunt.registerTask('build', [ 'clean', 'minimize', 'translations', 'phpunit', 'compress', 'dropbox', 'clean' ]);
	grunt.registerTask('publish', [ 'clean', 'minimize', 'translations', 'phpunit', 'shell:apigen', 'compress', 'aws_s3', 'clean' ]);
};
