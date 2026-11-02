<?php
namespace webburospring\pixabay\controllers;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;

use webburospring\pixabay\PixabayPlugin;

class PixabayController extends Controller
{

	private function _searchUrl($query, $page = 1, $type = '') {
		//Build API URL

		$apiKey = PixabayPlugin::$plugin->settings->apiKey;
		return 'https://pixabay.com/api/?key=' . urlencode($apiKey) . '&q=' . urlencode($query) . '&page=' . ((int)$page) . '&image_type=' . ((string)$type) . '&lang=' . $this->_language() . '&per_page=20';
	}


	private function _language() {
		//Search in the CP user's language if Pixabay supports it (https://pixabay.com/api/docs/ ), otherwise in English

		$supported = ['cs', 'da', 'de', 'en', 'es', 'fr', 'id', 'it', 'hu', 'nl', 'no', 'pl', 'pt', 'ro', 'sk', 'fi', 'sv', 'tr', 'vi', 'th', 'bg', 'ru', 'el', 'ja', 'ko', 'zh'];
		$language = strtolower(substr((string)Craft::$app->language, 0, 2));

		return in_array($language, $supported) ? $language : 'en';
	}


	private function _getCache($query, $page,  $type) {
		//Get cached results (required by Pixabay: https://pixabay.com/api/docs/ )

		$cacheDir = PixabayPlugin::cachePath();

		foreach (scandir($cacheDir) as $file) {
			//Delete outdated cache files

			if ($file == '.' || $file == '..' || is_dir($cacheDir . DIRECTORY_SEPARATOR . $file))
				continue;

			$created = filectime($cacheDir . DIRECTORY_SEPARATOR . $file);

			if ($created < time() - 24 * 3600)
				unlink($cacheDir . DIRECTORY_SEPARATOR . $file);
		}

		$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5(strtolower(trim($query))) . ((int)$page) . preg_replace('/[^a-z]/', '', (string)$type) . $this->_language() . '.json';

		if (!file_exists($cacheFile))
			return null;

		return @json_decode(file_get_contents($cacheFile), true);
	}


	private function _saveCache($query, $page, $type, $data) {
		//Save results to cache

		if (!is_array($data))
			return false;

		$data['_cached'] = (new \DateTime())->format('c');

		$cacheDir = PixabayPlugin::cachePath();
		$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5(strtolower(trim($query))) . ((int)$page) . preg_replace('/[^a-z]/', '', (string)$type) . $this->_language() . '.json';

		file_put_contents($cacheFile, json_encode($data));
	}


	private function _getHit($id, $url) {
		//Get the search result for a Pixabay ID: from the cached search results (the picture was just found by searching), or else from the Pixabay API

		$id = (int)$id;
		if ($id <= 0)
			return null;

		$cacheDir = PixabayPlugin::cachePath();

		foreach (scandir($cacheDir) as $file) {
			if (substr($file, -5) != '.json')
				continue;

			$data = @json_decode(file_get_contents($cacheDir . DIRECTORY_SEPARATOR . $file), true);

			//Image URLs are signed per request, so the same picture can have other URLs in other searches
			foreach ($data['hits'] ?? [] as $hit) {
				if (($hit['id'] ?? null) == $id && $this->_hitMatchesUrl($hit, $url))
					return $hit;
			}
		}

		$data = $this->_getCache('id:' . $id, 1, '');

		if (!$data) {
			try {
				$apiKey = PixabayPlugin::$plugin->settings->apiKey;
				$response = Craft::createGuzzleClient(['connect_timeout' => 10, 'timeout' => 30])->get('https://pixabay.com/api/?key=' . urlencode($apiKey) . '&id=' . $id, ['http_errors' => false]);
				$data = @json_decode((string)$response->getBody(), true);
			} catch (\Throwable $e) {
				Craft::error('Pixabay metadata lookup failed: ' . $e->getMessage(), __METHOD__);
				$data = null;
			}

			if (isset($data['hits']))
				$this->_saveCache('id:' . $id, 1, '', $data);
		}

		//Since the image URLs are signed per request, this can't be checked against the downloaded URL
		return $data['hits'][0] ?? null;
	}


	private function _hitMatchesUrl($hit, $url) {
		//Check that the downloaded URL belongs to this search result

		return in_array($url, [$hit['vectorURL'] ?? null, $hit['imageURL'] ?? null, $hit['largeImageURL'] ?? null], true);
	}


	private function _metadata($hit) {
		//Metadata of a picture to save to the asset fields

		//Pictures of deleted accounts have the user ID as username (and no profile page), so there's no author to credit
		$user = (string)($hit['user'] ?? '');
		$hasAuthor = $user !== '' && isset($hit['user_id']) && $user !== (string)$hit['user_id'];

		return [
			'user' => $hasAuthor ? $user : null,
			'userUrl' => $hasAuthor ? 'https://pixabay.com/users/' . rawurlencode($user) . '-' . $hit['user_id'] . '/' : null,
			'pageUrl' => $hit['pageURL'] ?? null,
		];
	}


	private function _applyMetadata(Asset $asset, $id, $url) {
		//Fill the asset fields selected in the plugin settings with the metadata of this picture

		$settings = PixabayPlugin::$plugin->settings;
		$fields = array_filter([
			'user' => $settings->authorField,
			'userUrl' => $settings->authorUrlField,
			'pageUrl' => $settings->pageUrlField,
		]);

		$layout = $asset->getFieldLayout();
		if (!$fields || !$layout)
			return;

		$hit = $this->_getHit($id, $url);
		if (!$hit)
			return;

		$metadata = $this->_metadata($hit);

		//Only fields in the field layout of the volume can be saved
		$layoutHandles = array_map(function ($field) {
			return $field->handle;
		}, PixabayPlugin::layoutFields($layout));

		foreach ($fields as $key => $handle) {
			if (!empty($metadata[$key]) && in_array($handle, $layoutHandles, true))
				$asset->setFieldValue($handle, $metadata[$key]);
		}
	}


	public function actionSearch() {
		//Search Pixabay

		$this->requireAcceptsJson();

		$query = Craft::$app->request->getRequiredParam('query');
		$page = (int)Craft::$app->request->getParam('page', 1);
		if ($page < 1)
			$page = 1;

		$type = Craft::$app->request->getParam('type', '');

		//Try to see if we have this query cached
		$cachedData = $this->_getCache($query, $page, $type);
		if ($cachedData)
			return $this->asJson(['success' => true, 'data' => $cachedData]);

		$url = $this->_searchUrl($query, $page, $type);

		//Use Guzzle (cURL) instead of file_get_contents: cURL quickly falls back to IPv4 when IPv6 is unreachable (e.g. in Docker / DDEV),
		//while file_get_contents waits for default_socket_timeout (60s) on every IPv6 address first
		try {
			$response = Craft::createGuzzleClient(['connect_timeout' => 10, 'timeout' => 30])->get($url, ['http_errors' => false]);
			$pixabayData = @json_decode((string)$response->getBody(), true);
		} catch (\Throwable $e) {
			Craft::error('Pixabay search failed: ' . $e->getMessage(), __METHOD__);
			$pixabayData = null;
		}

		if (!is_array($pixabayData))
			$pixabayData = [];

		$pixabayData['_query'] = $query;
		$pixabayData['_page'] = $page;
		$pixabayData['_type'] = $type;

		$success = isset($pixabayData['hits']);

		if ($success)
			$this->_saveCache($query, $page, $type, $pixabayData);

		return $this->asJson(['success' => $success, 'data' => $pixabayData]);
	}


	public function actionDownload() {
		//Download photo from Pixabay

		$this->requireAcceptsJson();

		$url = Craft::$app->request->getRequiredParam('url');
		$target = Craft::$app->request->getRequiredParam('target');

		$folderId = $volumeId = null;

		foreach (explode('/', $target) as $t) {
			//Find the upload target
			//Depending on Craft version and setup, this can be folderId:X, folderId:XXXX-YYYY-ZZZ, volumeId:XXXX-YYYY-ZZZ, or even volumeId:XXXX-YYYY-ZZZ/folderId:XXXX-YYYY-ZZZ
			$t = explode(':', $t);
			if ($t[0] == 'volume' || $t[0] == 'volumeId')
				$volumeId = $t[1] ?? null;
			if ($t[0] == 'folder' || $t[0] == 'folderId')
				$folderId = $t[1] ?? null;
		}

		if ($folderId) {

			//Find the folder, either by id or by uid
			$folder = Craft::$app->assets->findFolder([(is_numeric($folderId) ? 'id' : 'uid') => $folderId]);
			if (!$folder)
				throw new \Error('Folder not found: ' . $folderId);
			
			$volume = $folder->volume;

		} elseif ($volumeId) {

			//Find the volume, either by id or by uid
			$fn = 'getVolumeBy' . (is_numeric($volumeId) ? 'Id' : 'Uid');
			$volume = Craft::$app->volumes->$fn($volumeId);
			if (!$volume)
				throw new \Error('Volume not found: ' . $volumeId);

			//Find the base folder for this volume
			$folder = Craft::$app->assets->findFolder(['volumeId' => $volume->id, 'parentId' => ':empty:']);

			if (!$folder)
				throw new \Error('Folder not found for volume: ' . $volumeId);
		} else {
			throw new \Error('Unknown upload target: ' . $target);
		}

		//Check if user has permission to save assets here (permission name depends on Craft version). We use requireAdmin as a fallback in case of future changes.
		if (!Craft::$app->user->checkPermission('saveAssets:' . $volume->uid)
		 && !Craft::$app->user->checkPermission('saveAssetInVolume:' . $volume->uid)
		 && !Craft::$app->user->checkPermission('saveAssetInVolume:' . $volume->id))
			$this->requireAdmin();

		if (!preg_match('/^https?\:\/\/[^\/]*pixabay.com\//', $url))
			throw new \Error('This is not a Pixabay URL: ' . $url);

		$cacheDir = PixabayPlugin::cachePath();

		$file = preg_replace('/^.*\//', '', $url);

		if (!$file)
			throw new \Error('Could not get filename from URL: ' . $url);

		if (file_exists($cacheDir . DIRECTORY_SEPARATOR . $file))
			unlink($cacheDir . DIRECTORY_SEPARATOR . $file);

		//Prevent script timeouts
		set_time_limit(600);

		//Download the file with cUrl
		$fp = fopen($cacheDir . DIRECTORY_SEPARATOR . $file, 'w+');
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);

		//Save the downloaded file as a new Asset
		$asset = new Asset();
		$asset->tempFilePath = $cacheDir . DIRECTORY_SEPARATOR . $file;
		$asset->filename = $file;
		$asset->newFolderId = $folder->id;
		$asset->setVolumeId($volume->id);
		$asset->uploaderId = Craft::$app->user->id;
		$asset->avoidFilenameConflicts = true;
		$asset->setScenario(Asset::SCENARIO_CREATE);

		$this->_applyMetadata($asset, Craft::$app->request->getParam('id'), $url);

		$success = Craft::$app->elements->saveElement($asset);

		return $this->asJson(['success' => $success, 'assetId' => $asset->id, 'errors' => $asset->getErrors()]);

	}
}
