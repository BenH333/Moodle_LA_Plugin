
//
// * Javascript
// *
// * @package    learninganalytics
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax'], function(core, $, bootstrap, ajax) {

  // -----------------------------
  $(document).ready(function() {

    //  toggle event
    $('#id_courses').change(function() {
      // get current value then call ajax to get new data
     
    });

    $('#apply').click(function(){
        
        var activity = $("#activities").val();
        var course = $("#course").val();
        $.ajax({
            url: 'ajax/display.php',
            cache: false,
            data: {keyname:activity, value:course},
            success: function( data ){
                $('#responsecontainer').html( data );
            }
        });
    });
    
  });

});


