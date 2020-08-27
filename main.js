
// wait for the document to load so we can access the H5P selector
(function ($) {
	$(document).ready(() => {
		setTimeout(() => {
			if(H5P) {
				// every time something happens, log it to the console
				H5P.externalDispatcher.on('xAPI', function (event) {
					const result = event.data.statement.result;
					const hasParent = event.data.statement.context.contextActivities.parent;
					const category = event.data.statement.context.contextActivities.category;

					if(result && result.score && result.completion && !hasParent) {
						// if score is defined and completion is true then they completed the question, if they have a parent ignore
						if(result.score.scaled === 1 || (category && category[0].id === 'http://h5p.org/libraries/H5P.Summary-1.10')) {
							// activity completed successfully

							const dataObj = {
								action: ajaxData.action,
								_ajax_nonce: ajaxData.nonce,
								url: document.URL
							}

							jQuery.ajax({
								type: 'post',
								dataType: 'json',
								url: ajaxData.ajax_url,
								data: dataObj,
								error: function(e) {
									console.log(`something went wrong: ${e.statusText}`);
								},
								success: function(response) {
									console.log('response: ' + response.type);
								}
							})
						}
					}
				});
			}
		},0);
	});
})(jQuery);
