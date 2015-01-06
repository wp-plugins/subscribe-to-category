( function( $ ) {

	$(document).ready(function() {

    $('#stc-resend').change( function() {
      
      if( $(this).is(':checked') ) {
        $('#stc-resend-info').show();
      } else {
        $('#stc-resend-info').hide();
      }

    });

		$('button#stc-force-run').live('click', function(){
			var trigger_btn = $(this);

			trigger_btn.attr('disabled', 'disabled'); // disable button during ajax call
			trigger_btn.before('<span id="stc-spinner" class="spinner" style="display:inline"></span>'); // adding spinner element
			$('#message').remove(); // remove any previous message

        var data = {
          action: 'force_run',
          nonce: ajax_object.ajax_nonce
        };

        $.post( ajax_object.ajaxurl, data, function(response) {
 
					setTimeout(function(){
        		$('#stc-posts-in-que').text('0'); // clear posts in que
        		$('.wrap h2').after('<div id="message"></div>'); // add message element
        		$('#message').addClass('updated').html('<p><strong>' + response + '</strong></p>'); // get text from ajax call
        		$('#stc-spinner').remove(); // remove spinner
        		trigger_btn.attr('disabled', false); // enable button
        	}, 1500);

        }).error(function(){
          alert ("Problem calling: " + action + "\nCode: " + this.status + "\nException: " + this.statusText);
        });
  
      return false;

    });
		

	});

} )( jQuery );

