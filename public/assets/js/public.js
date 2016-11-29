(function($) {
  "use strict";

  $(function() {

    /* ========================================================================
     * DOM-based Routing
     * Based on http://goo.gl/EUTi53 by Paul Irish
     *
     * Only fires on body classes that match. If a body class contains a dash,
     * replace the dash with an underscore when adding it to the object below.
     *
     * .noConflict()
     * The routing is enclosed within an anonymous function so that you can
     * always reference jQuery with $, even when in .noConflict() mode.
     * ======================================================================== */

    // Use this variable to set up the common and page specific functions. If you
    // rename this variable, you will also need to rename the namespace below.
    var Plugin_Name = {
      common: { // All pages
        init: function() {
          // JavaScript to be fired on all pages
        }
      },
      home: { // Home page
        init: function() {
          // JavaScript to be fired on the home page
        }
      },
      single: { // Single page
        init: function() {
          // JavaScript to be fired on the home page
        }
      },
      single_offer: { // Offer page
        init: function() {
          // JavaScript to be fired on the home page
          DOB_PUBLIC.vote();
          //console.log('single_offer');
          $('#table_analysis_myhier').treetable( {expandable:true} );
          $('#table_analysis_myhier').treetable( 'expandAll' );
        }
      },
      single_elect: { // Elect page
        init: function() {
          // JavaScript to be fired on the home page
          DOB_PUBLIC.vote();
          //console.log('single_elect');
        }
      }
    };

    // The routing fires all common scripts, followed by the page specific scripts.
    // Add additional events for more control over timing e.g. a finalize event
    var UTIL = {
      fire: function(func, funcname, args) {
        var namespace = Plugin_Name;
        funcname = (funcname === undefined) ? 'init' : funcname;
        if (func !== '' && namespace[func] && typeof namespace[func][funcname] === 'function') {
          namespace[func][funcname](args);
        }
      },
      loadEvents: function() {
        UTIL.fire('common');

        $.each(document.body.className.replace(/-/g, '_').split(/\s+/), function(i, classnm) {
          UTIL.fire(classnm);
        });
      }
    };

    $(document).ready(UTIL.loadEvents);

    // Write in console log the PHP value passed in enqueue_js_vars in public/class-plugin-name.php
    console.log( pn_js_vars.alert );
    
    // Place your public-facing JavaScript here
    var DOB_PUBLIC = {
      vote: function() {
        $('#tdVote :checkbox').change(function() {
          var form = document.getElementById('formDob');
          var val = this.value;
          var checked = $(this).prop('checked');
          if ( val == '-1' || val == '0' ) {
            form.dob_form_val.value = ( checked ? val : '' );
            $('#tdVote :checkbox[value!="'+val+'"]').prop('disabled',checked);
          } else {
            var sum = 0;
            $('#tdVote :checkbox:checked[value!="-1"][value!="0"]').each(function(){
              var v = parseInt($(this).val()); //<==== a catch  in here !! read below
              sum += v;
            });
            form.dob_form_val.value = sum;
          }
        });

        var check_dup = function() {
          var form = document.getElementById('formDob');
          if ( form.dob_form_val.value == form.dob_form_old_val.value ) {
            alert('You can NOT vote with same value');
            return true;
          }
        }

        $('#btn_fast,#btn_cart').click( function (e) {
          this.form.dob_form_cart.value = (this.id=='btn_cart') ? 1 : 0;
          if ( check_dup() ) window.location = window.location.href;
          else this.form.submit();
        });
      }
    };


  });

}(jQuery));
