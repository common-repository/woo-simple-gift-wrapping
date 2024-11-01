function na_woo_gw_toCat() {
	location.href =  jQuery('#na_gift_wrapping_checked').parent('label').attr('data-link');
}
function na_woo_gw_close() {
	jQuery('.na-overlay').remove();
	jQuery('.na-notification').remove();
}
jQuery(document).ready(function ($) {
	$('#na_gift_wrapping_checked').click(function () {
		var state = null;
		if($(this).is(':checked')) {
			state = 'ADD';
		} else {
			state = 'REMOVE';
		}

		var data = {
			action: 'woocommerce_add_gift_box',
			state: state
		};
		jQuery.ajax({
			type: 'POST',
			url: wc_checkout_params.ajax_url.toString(),
			data: data,
			success: function (response) {
				/*
				var data = JSON.parse(response);
				if(state == 'ADD' && data.result == true) {
					$('body').append(data.data);
					var divH = $('.na-notification').height();
					var wH = $(window).height();
					var wW = $(window).width();
					$('.na-notification').css({
						left: (wW-300)/2,
						top: (wH-200)/2
					});
				}*/
				jQuery('body').trigger('update_checkout');
			},
			dataType: 'html'
		});
	});
});
