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
		return 'https://pixabay.com/api/?key=' . urlencode($apiKey) . '&q=' . urlencode($query) . '&page=' . ((int)$page) . '&image_type=' . ((string)$type) . '&per_page=20';
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

		$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . base64_encode(strtolower(trim($query))) . ((int)$page) . ((string)$type) . '.json';

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
		$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . base64_encode(strtolower(trim($query))) . ((int)$page) . ((string)$type) . '.json';

		file_put_contents($cacheFile, json_encode($data));
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

		$pixabayData = @json_decode(@file_get_contents($url), true);
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
		$folderId = Craft::$app->request->getRequiredParam('folder');

		//Find the folder, either by id or by uid
		$folder = Craft::$app->assets->findFolder([(is_numeric($folderId) ? 'id' : 'uid') => $folderId]);
		if (!$folder)
			throw new \Error('Folder not found: ' . $folderId);

		//Check if user has permission to save assets here (permission name depends on Craft version). We use requireAdmin as a fallback in case of future changes.
		if (!Craft::$app->user->checkPermission('saveAssetInVolume:' . $folder->volume->uid) && !Craft::$app->user->checkPermission('saveAssetInVolume:' . $folder->volumeId))
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
		$asset->setVolumeId($folder->volumeId);
		$asset->uploaderId = Craft::$app->user->id;
		$asset->avoidFilenameConflicts = true;
		$asset->setScenario(Asset::SCENARIO_CREATE);

		$success = Craft::$app->elements->saveElement($asset);

		return $this->asJson(['success' => $success, 'assetId' => $asset->id, 'errors' => $asset->getErrors()]);

	}
}
