'use strict';
module.exports = function(grunt) {

  grunt.initConfig({
    compass: {//{{{
      admin: {
        options: {
          sassDir: 'admin/assets/sass',
          cssDir: 'admin/assets/css',
          environment: 'production',
          relativeAssets: true
        }
      },
      public: {
        options: {
          sassDir: 'public/assets/sass',
          cssDir: 'public/assets/css',
          environment: 'production',
          relativeAssets: true
        }
      },
      adminDev: {//{{{
        options: {
          environment: 'development',
          debugInfo: true,
          noLineComments: false,
          sassDir: 'public/assets/sass',
          cssDir: 'public/assets/css',
          outputStyle: 'expanded',
          relativeAssets: true
        }
      },
      publicDev: {
        options: {
          environment: 'development',
          debugInfo: true,
          noLineComments: false,
          sassDir: 'public/assets/sass',
          cssDir: 'public/assets/css',
          outputStyle: 'expanded',
          relativeAssets: true
        }
      }//}}}
    },//}}}
		concat:{
			admin_js: {
				src: ['admin/assets/js/*.js'], //concat 타겟 설정(순서대로 합쳐진다.)
				dest: 'admin/assets/admin.concat.js' //concat 결과 파일
			},
			admin_css: {
				src: ['admin/assets/css/*.css'], //concat 타겟 설정(순서대로 합쳐진다.)
				dest: 'admin/assets/admin.concat.css' //concat 결과 파일
			},
			public_js: {
				src: ['public/assets/js/*.js'], //concat 타겟 설정(순서대로 합쳐진다.)
				dest: 'public/assets/public.concat.js' //concat 결과 파일
			},
			public_css: {
				src: ['public/assets/css/*.css'], //concat 타겟 설정(순서대로 합쳐진다.)
				dest: 'public/assets/public.concat.css' //concat 결과 파일
			}
		},
		uglify: {//{{{
			options: {
				preserveComments: 'some'
			},
			admin: {
				src: 'admin/assets/admin.concat.js', //uglify할 대상 설정
				dest: 'admin/assets/admin.min.js' //uglify 결과 파일 설정
			},
			public: {
				src: 'public/assets/public.concat.js', //uglify할 대상 설정
				dest: 'public/assets/public.min.js' //uglify 결과 파일 설정
			}
		},//}}}
		cssmin: {//{{{
			options: {
				keepSpecialComments: 0
			},
			admin: {
				src: 'admin/assets/admin.concat.css', //uglify할 대상 설정
				dest: 'admin/assets/admin.min.css' 		//uglify 결과 파일 설정
			},
			public: {
				src: 'public/assets/public.concat.css', //uglify할 대상 설정
				dest: 'public/assets/public.min.css' //uglify 결과 파일 설정
			}
		},//}}}
    watch: {
      compass: {
        files: [
          'admin/assets/sass/*.scss',
          'public/assets/sass/*.scss'
        ],
        tasks: [
					'compass:adminDev', 
					'compass:publicDev'
				]
      },
			concat: {
				files: [
					'admin/assets/js/*.js',
					'admin/assets/css/*.css',
					'public/assets/js/*.js',
					'public/assets/css/*.css'
				],
				tasks: [
					'concat:admin_js',
					'concat:admin_css',
					'concat:public_js',
					'concat:public_css'
				]
			},
			uglify: {
				files: [
					'admin/assets/admin.concat.js',
					'public/assets/public.concat.js'
				],
				tasks: [
					'uglify:admin',
					'uglify:public'
				]
			},
			cssmin: {
				files: [
					'admin/assets/admin.concat.js',
					'public/assets/public.concat.js'
				],
				tasks: [
					'cssmin:admin',
					'cssmin:public'
				]
			}
    }
  });

  // Load tasks
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-compass');
	//grunt.loadNpmTasks('grunt-contrib-less');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-cssmin');

  // Register tasks
  grunt.registerTask('default', [
    'compass:admin', 'compass:public',
		'concat:admin_js', 'concat:admin_css', 
		'concat:public_js', 'concat:public_css',
		'uglify:admin', 'uglify:public',
		'cssmin:admin', 'cssmin:public'
  ]);
  grunt.registerTask('dev', [
    'watch'
  ]);

};
