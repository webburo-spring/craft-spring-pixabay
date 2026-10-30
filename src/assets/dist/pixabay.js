(function () {

	//The script can be registered again on later page loads, but the observer only needs to run once
	if (window.PixabayLoaded)
		return;
	window.PixabayLoaded = true;

	var t = function (message, params) {
		return Craft.escapeHtml(Craft.t('spring-pixabay', message, params));
	};

	var actionUrl = function (url, params) {
		//Add query params to an action URL (which may already have a query string)

		var u = new URL(url, window.location.href);
		for (var key in params)
			u.searchParams.set(key, params[key]);
		return u.toString();
	};

	var createPixabayModal = function (selectorModal) {
		//Create the Pixabay modal for an asset selector modal

		var $pixabayModal = $(PixabayModal);
		var pixabayModal = $pixabayModal[0];
		var selectedImages = [];
		var imageIds = {}; //Pixabay ID per image URL, so the server can store the image's metadata
		var requestId;
		var downloadController = null;

		var updateSelected = function () {
			//Update the "X pictures selected" text and download button

			$pixabayModal.find('.pixabay-selected').html(selectedImages.length ? t('{n,plural,=1{# picture} other{# pictures}} selected', { n: selectedImages.length }) : '');
			$pixabayModal.find('.pixabay-download')[0].toggleAttribute('disabled', !selectedImages.length || !!downloadController);
		};

		var showError = function (message) {
			$pixabayModal.find('.pixabay-results').html('<p class="pixabay-error">' + Craft.escapeHtml(message) + '</p>');
		};

		var pixabaySearch = function (query, page, type) {
			//Search query changed

			if (typeof query != 'string' || query.trim() == '') {
				//Empty query
				$pixabayModal.find('.pixabay-results').html(Craft.escapeHtml(PixabayDefaultText));
				return;
			}

			if (!page)
				page = 1;

			if (!type)
				type = '';

			var thisRequest = Math.random().toString().replace('0.', '');
			requestId = thisRequest;

			$pixabayModal.find('.pixabay-results').html(PixabayLoading);

			fetch(actionUrl(PixabaySearchUrl, { query: query, page: page, type: type }), { headers: { Accept: 'application/json' } })
				.then(function (response) {
					if (!response.ok)
						throw new Error(response.status + ' ' + response.statusText);
					return response.json();
				})
				.then(function (result) {

					if (requestId != thisRequest) {
						//Old request
						return;
					}

					if (!result || !result.success) {
						//No success or unexpected result
						console.error('[PIXABAY]', result);
						showError(Craft.t('spring-pixabay', 'An error occured while loading Pixabay data'));
						return;
					}

					if (!result.data.hits.length) {
						//Nothing found
						$pixabayModal.find('.pixabay-results').html(t('No results for "{query}"', { query: result.data._query }));
						return;
					}

					var html = '<div class="pixabay-grid">';

					for (var i = 0; i < result.data.hits.length; i++) {
						//Show pictures
						var pic = result.data.hits[i];
						var url = pic.vectorURL || pic.imageURL || pic.largeImageURL;
						var selected = selectedImages.indexOf(url) > -1;
						imageIds[url] = pic.id;

						html += '<button type="button" class="pixabay-item' + (selected ? ' selected' : '') + '" aria-pressed="' + selected + '" style="background-image: url(' + Craft.escapeHtml(pic.previewURL) + ');" data-image="' + Craft.escapeHtml(url) + '" title="' + Craft.escapeHtml(pic.tags) + '" aria-label="' + Craft.escapeHtml(pic.tags) + '"></button>';
					}

					var hasNext = (result.data.hits.length + (result.data._page - 1) * 20 < result.data.totalHits);
					var hasPrev = (result.data._page > 1);

					//Previous and Next page buttons
					html += '<div class="pixabay-pagination">';

					html += '<craft-button type="button" class="pixabay-pagebtn"' + (hasPrev ? '' : ' disabled') + ' data-page="' + (result.data._page - 1) + '">' + t('Previous page') + '</craft-button>';
					html += '<craft-button type="button" class="pixabay-pagebtn"' + (hasNext ? '' : ' disabled') + ' data-page="' + (result.data._page + 1) + '">' + t('Next page') + '</craft-button>';

					html += '<p class="pixabay-pagelabel">' + t('Page {p}', { p: result.data._page }) + '</p>';

					html += '</div>';

					html += '</div>';

					$pixabayModal.find('.pixabay-results').html(html);

					$pixabayModal.find('.pixabay-item').on('click', function () {
						//Item select handler

						var key = selectedImages.indexOf($(this).data('image'));
						if (key > -1) {
							selectedImages.splice(key, 1);
							$(this).removeClass('selected').attr('aria-pressed', 'false');
						} else {
							selectedImages.push($(this).data('image'));
							$(this).addClass('selected').attr('aria-pressed', 'true');
						}

						updateSelected();
					});

					$pixabayModal.find('.pixabay-pagebtn').on('click', function () {
						//Previous and Next page handler

						if (this.hasAttribute('disabled'))
							return;

						pixabaySearch(result.data._query, $(this).data('page'), result.data._type);
					});
				})
				.catch(function (e) {

					if (requestId != thisRequest)
						return;

					console.error('[PIXABAY]', e);
					showError(e.message);

				});

		}; //pixabaySearch

		var pixabayDownload = function (urls) {
			//Download the Pixabay pictures and hand them to Craft's upload input, so they're uploaded like any other file
			//(Craft checks the permissions, handles filename conflicts and selects the new assets)

			downloadController = new AbortController();
			var signal = downloadController.signal;
			var files = [];
			var n = 0;

			updateSelected();
			$pixabayModal.find('.pixabay-toolbar').hide();
			$pixabayModal.find('.pixabay-results').html('<div class="pixabay-progress"><p><b>' + t('Downloading pictures...') + '</b></p><div class="pixabay-progressbar"><div></div></div></div>');

			var next = function () {

				if (n >= urls.length) {
					//All downloaded: upload them to the selected folder
					var fileInput = selectorModal.querySelector('input[type=file][name="assets-upload"]');
					if (fileInput) {
						var dataTransfer = new DataTransfer();
						files.forEach(function (file) {
							dataTransfer.items.add(file);
						});
						fileInput.files = dataTransfer.files;
						fileInput.dispatchEvent(new Event('change'));
					}
					downloadController = null;
					pixabayModal.removeAttribute('open');
					return;
				}

				var url = urls[n];

				fetch(actionUrl(PixabayDownloadUrl, { url: url, id: imageIds[url] || '' }), { signal: signal })
					.then(function (response) {
						if (!response.ok)
							throw new Error(response.status + ' ' + response.statusText);
						return response.blob();
					})
					.then(function (blob) {
						files.push(new File([blob], url.replace(/[?#].*$/, '').replace(/^.*\//, ''), { type: blob.type }));
						n++;
						$pixabayModal.find('.pixabay-progressbar > div').css('width', (n / urls.length * 100) + '%');
						next();
					})
					.catch(function (e) {
						if (signal.aborted)
							return;
						console.error('[PIXABAY]', e);
						downloadController = null;
						showError(e.message);
					});
			};

			next();

		}; //pixabayDownload()

		$pixabayModal.find('.pixabay-search, .pixabay-search-type').on('change', function () {
			//Search query changed

			selectedImages = [];
			updateSelected();
			pixabaySearch($pixabayModal.find('.pixabay-search').val(), null, $pixabayModal.find('.pixabay-search-type').val());
		});

		$pixabayModal.find('.pixabay-download').on('click', function () {
			//Download button

			if (this.hasAttribute('disabled') || downloadController || !selectedImages.length)
				return;

			pixabayDownload(selectedImages.slice());
		});

		$pixabayModal.find('.pixabay-cancel').on('click', function () {
			//Cancel button
			pixabayModal.requestClose();
		});

		$pixabayModal.on('craft-after-show', function (e) {
			if (e.target === pixabayModal)
				$pixabayModal.find('.pixabay-search').trigger('focus');
		});

		$pixabayModal.on('craft-after-hide', function (e) {
			//Start over next time
			if (e.target !== pixabayModal)
				return;
			if (downloadController)
				downloadController.abort();
			$pixabayModal.remove();
		});

		return pixabayModal;

	}; //createPixabayModal()

	var inject = function () {
		//Add the Pixabay button next to the "Upload files" button of asset selector modals

		document.querySelectorAll('craft-element-selector-modal').forEach(function (selectorModal) {

			//Craft only adds the upload input when files can be uploaded to the selected source
			var fileInput = selectorModal.querySelector('input[type=file][name="assets-upload"]');
			if (!fileInput || fileInput.parentNode.querySelector('.pixabay-btn'))
				return;

			var $btn = $(PixabayButton);

			$btn.on('click', function () {
				//The modal is a dialog (like the asset selector modal), so it can be shown on top of it
				var pixabayModal = createPixabayModal(selectorModal);
				document.body.appendChild(pixabayModal);
				pixabayModal.setAttribute('open', '');
			});

			fileInput.parentNode.appendChild($btn[0]);
		});

	}; //inject()

	//Asset selector modals are created (and their buttons re-rendered) at any time
	new MutationObserver(inject).observe(document.body, { childList: true, subtree: true });
	inject();

})();
