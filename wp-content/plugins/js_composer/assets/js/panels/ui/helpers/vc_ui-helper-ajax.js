( function () {
	'use strict';

	window.vc.HelperAjax = {
		ajax: false,
		checkAjax: function () {
			if ( this.ajax ) {
				this.ajax.abort();
			}
		},
		resetAjax: function () {
			this.ajax = false;
		}
	};
})();
