jQuery(document).ready(function(){

	jQuery("input[id*='edd_settings[integration]']").hide();
	jQuery("input[id*='edd_settings[vendor_id]']").closest('tr').hide();
	jQuery("input[id*='edd_settings[vendor_auth_code]']").closest('tr').hide();

	jQuery('#manualEntry').on('click', function(event) {
		event.preventDefault();
		/* Act on the event */
		jQuery("input[id*='edd_settings[vendor_id]']").closest('tr').show();
		jQuery("input[id*='edd_settings[vendor_auth_code]']").closest('tr').show();
		jQuery(this).closest('tr').hide();
	});

    jQuery('.open_paddle_integration_window').on('click', function(event) {
    	event.preventDefault();
    	/* Act on the event */
    	window.open(integration_popup.url,'integrationwindow','location=no,status=0,scrollbars=0,width=500,height=500');

    	// handle message sent from popup
		jQuery(window).on('message', function(event) {
			jQuery(this).closest('tr').hide();
			var arrayOfData = event.originalEvent.data.split(' ');
			jQuery("input[id*='edd_settings[vendor_id]']").val(arrayOfData[0]);
			jQuery("input[id*='edd_settings[vendor_auth_code]']").val(arrayOfData[1]);
			jQuery('#manualEntry').click();
		});
    });

});