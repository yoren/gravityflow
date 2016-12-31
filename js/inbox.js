(function (GravityFlowInbox, $) {

	$(document).ready(function () {

		$('.gravityflow-action').click( function() {
			var $this = $(this),
				$unlock = $this.siblings('.gravityflow-actions-unlock'),
				$lock = $this.siblings('.gravityflow-actions-lock');

			if ( $this.hasClass( 'gravityflow-action-processed' ) ) {
				return;
			}

			if ( $this.parent('.gravityflow-actions').hasClass( 'gravityflow-actions-locked' ) ) {
				$this.parent('.gravityflow-actions').removeClass( 'gravityflow-actions-locked' );
				$lock.hide();
				$unlock.show();
				setTimeout(function () {
					if ( ! $this.hasClass( 'gravityflow-action-processing' ) ) {
						$this.parent('.gravityflow-actions').addClass( 'gravityflow-actions-locked' );
						$lock.show();
						$unlock.hide();
					}
				}, 2000);
				return;
			}

			var entryId = parseInt($this.data('entry_id')),
				restBase = $this.data('rest_base'),
				action = $this.data('action'),
				url = gravityflow_inbox_strings.restUrl,
				nonce = gravityflow_inbox_strings.nonce,
				$spinner = $this.siblings('.gravityflow-actions-spinner');

			$.ajax({
				method: "POST",
				url: url + 'gf/v2/entries/' + entryId + '/workflow/' + restBase,
				data: { 'action' : action },
				beforeSend: function ( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					$this.siblings().andSelf().hide();
					$this.addClass('gravityflow-action-processing');
					$spinner.show();
				},
				success : function( response ) {
					$spinner.hide();
					$this.show();
					$this.removeClass('gravityflow-action-processing');
					$this.addClass('gravityflow-action-processed');
					$this.parent('.gravityflow-actions').removeClass( 'gravityflow-actions-locked' );
				},
				fail : function( response ) {
					$spinner.hide();
					$unlock.hide();
					$lock.show();
					$this.removeClass('gravityflow-action-processing');
					$this.siblings('.gravityflow-actions').andSelf().show();
					alert( response );
				}

			});
		});
	});

}(window.GravityFlowInbox = window.GravityFlowInbox || {}, jQuery));
