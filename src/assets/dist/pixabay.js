(function () {

	var inject = function () {
		
		if (typeof Craft == 'object' && Craft !== null && typeof Craft.createElementSelectorModal == 'function') {
			//Craft JS has finished loading
		
			clearInterval(interval);
	
			Craft.createElementSelectorModal = (function (originalFn) {
				//Replace createElementSelectorModal function
				
				return function (type, config) {
					
					//Let the original function create the modal
					var elementModal = originalFn.call(this, type, config);
					
					if (type == 'craft\\elements\\Asset') {
						//Asset modal only
						
						//Create Pixabay button
						var $btn = $(PixabayButton);
						var pixabayModal;
						var selectedImages = [];
						var isDownloading = false;
						
						//On button click
						$btn.click(function (e) {
							
							if (!pixabayModal) {
								//Create modal
								
								var $pixabayModal = $(PixabayModal);
								
								pixabayModal = new Garnish.Modal($pixabayModal, {});
								pixabayModal.on('fadeIn', function (e) {
									$pixabayModal.find('.pixabay-search').focus();
								});
								
								var requestId;
								
								var pixabaySearch = function (query, page) {
									//Search query changed
									
									if (typeof query != 'string' || query.trim() == '') {
										//Empty query
										$pixabayModal.find('.pixabay-results').html(Craft.escapeHtml(PixabayDefaultText));
										return;
									}
									
									if (!page)
										page = 1;
									
									var thisRequest = Math.random().toString().replace('0.', '');
									requestId = thisRequest;
									
									$pixabayModal.find('.pixabay-results').html(PixabayLoading);
									
									$.ajax(Craft.getActionUrl('spring-pixabay/pixabay/search'), {
										data: { query: query, page: page },
										dataType: 'json',
										success: function (result) {
											
											if (requestId != thisRequest) {
												//Old request
												return;
											}
											
											if (!result || !result.success) {
												//No success or unexpected result
												$pixabayModal.find('.pixabay-results').html('<p class="error">' + Craft.escapeHtml(Craft.t('spring-pixabay', 'An error occured while loading Pixabay data') + '</p>'));
												console.error('[PIXABAY]', result);
											}
											
											if (!result.data || !result.data.hits || !result.data.hits.length) {
												//Nothing found
												$pixabayModal.find('.pixabay-results').html(Craft.escapeHtml(Craft.t('spring-pixabay', 'No results for "{query}"', { query: result.data._query })));
												return;
											}
											
											var html = '<div class="flex">';
											
											for (var i = 0; i < result.data.hits.length; i++) {
												//Show pictures
												var pic = result.data.hits[i];
												var url = pic.imageURL ? pic.imageURL : pic.largeImageURL;
												
												html += '<label class="pixabay-item' + (selectedImages.indexOf(url) > -1 ? ' selected' : '') + '" tabindex="0" style="background-image: url(' + Craft.escapeHtml(pic.previewURL) + ');" data-image="' + Craft.escapeHtml(url) + '" title="' + Craft.escapeHtml(pic.tags) + '"></label>';
											}
											
											for (var i = result.data.hits.length; i < 20; i++)
												html += '<div></div>';
											
											var hasNext = (result.data.hits.length + (result.data._page - 1) * 20 < result.data.totalHits);
											var hasPrev = (result.data._page > 1);
											var lastPage = Math.ceil(result.data.totalHits / 20);
											
											//Previous and Next page buttons
											html += '<div class="flex pixabay-pagination"><div class="flex">';
											
											html += '<button class="btn pixabay-pagebtn fullwidth' + (hasPrev ? '' : ' disabled') + '" data-query="' + Craft.escapeHtml(result.data._query) + '" data-page="' + Craft.escapeHtml(result.data._page - 1) + '">' + Craft.escapeHtml(Craft.t('spring-pixabay', 'Previous page')) + '</button>';
											html += '<button class="btn secondary pixabay-pagebtn fullwidth' + (hasNext ? '' : ' disabled') + '" data-query="' + Craft.escapeHtml(result.data._query) + '" data-page="' + Craft.escapeHtml(result.data._page + 1) + '">' + Craft.escapeHtml(Craft.t('spring-pixabay', 'Next page')) + '</button>';
											
											html += '<p class="pixabay-pagelabel fullwidth">' + Craft.escapeHtml(Craft.t('spring-pixabay', 'Page {p}', { p: result.data._page })) + '</p>';
											
											html += '</div>';
											
											html += '</div>';
											
											$pixabayModal.find('.pixabay-results').html(html);
											
											var selectItem = function () {
												//Item select handler
												
												var key = selectedImages.indexOf($(this).data('image'));
												if (key > -1) {
													selectedImages.splice(key, 1);
													$(this).removeClass('selected');
												} else {
													$(this).addClass('selected');
													selectedImages.push($(this).data('image'));
												}
												
												if (selectedImages.length) {
													$pixabayModal.find('.pixabay-download').removeClass('disabled');
													$pixabayModal.find('.pixabay-selected').html(Craft.escapeHtml(Craft.t('spring-pixabay', '{n,plural,=1{# picture} other{# pictures}} selected', { n: selectedImages.length })));
												} else {
													$pixabayModal.find('.pixabay-download').addClass('disabled');
													$pixabayModal.find('.pixabay-selected').html('');
												}
												
											};
											
											$pixabayModal.find('.pixabay-item').click(selectItem);
											$pixabayModal.find('.pixabay-item').on('keydown', function (e) {
												//Select item on enter or space press
												if (e.which == 13 || e.which == 32)
													selectItem.call(this);
											});
											
											$pixabayModal.find('.pixabay-pagebtn').click(function () {
												//Previous and Next page handler
												
												if (!$(this).data('page') || $(this).data('page') < 1 || $(this).data('page') > lastPage)
													return;
												
												pixabaySearch($(this).data('query'), $(this).data('page'));
											});
											
											$pixabayModal.find('.pixabay-download').click(function () {
												//Download button
												
												if (isDownloading || !selectedImages.length)
													return;
												
												$(this).addClass('disabled');
												$pixabayModal.find('.body').addClass('pixabay-progress-body');
												$pixabayModal.find('.body').html('<div class="pixabay-progress progress-shade"><p><b>' + Craft.escapeHtml(Craft.t('spring-pixabay', 'Downloading pictures...')) + '</b></p><div class="progressbar"><div class="progressbar-inner" style="width: 0%;"></div></div><div class="progressbar-status"></div></div>');
												
												isDownloading = true;
												uploadedAssets = [];
												
												pixabayDownload(selectedImages);
												
											});
										}, //success()
										error: function (xhr) {
											
											console.error('[PIXABAY]', xhr);
											$pixabayModal.find('.pixabay-results').html('<p class="error">' + Craft.escapeHtml(xhr.status + ' ' + xhr.statusText) + '</p>');
											
										} //error()
									}); //$.ajax
									
								}; //pixabaySearch
								
								var uploadedAssets = [];
								
								var pixabayDownload = function (urls, n) {
									//Download array of Pixabay URLs and save them to the server
									
									if (!isDownloading)
										return;
									
									if (typeof n == 'undefined')
										n = 0;
									
									if (!urls || typeof urls != 'object' || typeof urls[n] == 'undefined')
										return;
									
									$.ajax(Craft.getActionUrl('spring-pixabay/pixabay/download'), {
										data: { url: urls[n], folder: elementModal.elementIndex.sourceKey.replace('folder:', '') },
										dataType: 'json',
										success: function (result) {
											
											if (typeof result != 'object' || !result) {

												console.error('[PIXABAY]', result);
											
											} else {

												if (result.assetId)
													elementModal.elementIndex.selectElementAfterUpdate(result.assetId);
												
												$pixabayModal.find('.pixabay-progress .progressbar-inner').css('width', ((n + 1) / urls.length * 100) + '%');
												
												if (typeof urls[n + 1] != 'undefined') {
													pixabayDownload(urls, n + 1);
													return;
												}
											}
											
											pixabayModal.hide();
											pixabayModal = null;
											elementModal.elementIndex.updateElements();
												
										}, //success()
										error: function (xhr) {
											
											console.error('[PIXABAY]', xhr);
											
											$pixabayModal.find('.pixabay-progress').html('<p class="error">' + Craft.escapeHtml(xhr.status + ' ' + xhr.statusText) + '</p>');
											
											elementModal.elementIndex.updateElements();
											
										} //error()
									}); //$.ajax
									
								}; //pixabayDownload()
								
								$pixabayModal.find('.pixabay-search').change(function () {
									//Search query changed
									
									selectedImages = [];
									$pixabayModal.find('.pixabay-item.selected').removeClass('selected');
									$pixabayModal.find('.pixabay-selected').html('');
									pixabaySearch(this.value);
								});
								
								$pixabayModal.find('.pixabay-cancel').click(function () {
									//Cancel button
									
									selectedImages = [];
									$pixabayModal.find('.pixabay-item.selected').removeClass('selected');
									$pixabayModal.find('.pixabay-selected').html('');
									pixabayModal.hide();
									if (isDownloading) {
										isDownloading = false;
										pixabayModal = null;
										elementModal.elementIndex.updateElements();
									}
								});
								
							} else {
								//Show previously created modal
								pixabayModal.show();
							}
							
						});
						
						$btn.insertAfter(elementModal.$secondaryButtons);
						
					} //type == craft\\elements\\Asset
					
					return elementModal;
					
				}; //return function
				
			})(Craft.createElementSelectorModal);
			
		} //Craft JS loaded
		
	}; //inject()
	
	//Try to inject until Craft JS is loaded
	var interval = setInterval(inject, 1);

})();