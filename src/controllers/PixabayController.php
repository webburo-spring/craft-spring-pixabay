<?php
namespace webburospring\pixabay\controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use webburospring\pixabay\PixabayPlugin;

class PixabayController
{

	private function _cacheFile($query, $page, $type) {
		//Cache file for these search parameters (md5, since base64 can contain slashes)

		return PixabayPlugin::cachePath() . DIRECTORY_SEPARATOR . md5(strtolower(trim($query)) . '|' . ((int)$page) . '|' . ((string)$type)) . '.json';
	}


	private function _getCache($query, $page, $type) {
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

		$cacheFile = $this->_cacheFile($query, $page, $type);

		if (!file_exists($cacheFile))
			return null;

		return @json_decode(file_get_contents($cacheFile), true);
	}


	private function _saveCache($query, $page, $type, $data) {
		//Save results to cache

		if (!\is_array($data))
			return false;

		$data['_cached'] = (new \DateTime())->format('c');

		file_put_contents($this->_cacheFile($query, $page, $type), json_encode($data));
	}


	private function _isPixabayUrl($url) {
		//Only download from (subdomains of) pixabay.com

		$parts = parse_url($url);
		$host = strtolower($parts['host'] ?? '');

		return \in_array($parts['scheme'] ?? '', ['http', 'https'], true)
			&& ($host == 'pixabay.com' || str_ends_with($host, '.pixabay.com'));
	}


	public function search(Request $request): JsonResponse {
		//Search Pixabay

		$query = (string)$request->query('query', '');
		abort_if(trim($query) === '', 400, 'Missing query');

		$page = max(1, (int)$request->query('page', 1));
		$type = (string)$request->query('type', '');
		if (!\in_array($type, ['', 'all', 'photo', 'illustration', 'vector'], true))
			$type = '';

		//Try to see if we have this query cached
		$cachedData = $this->_getCache($query, $page, $type);
		if ($cachedData)
			return new JsonResponse(['success' => true, 'data' => $cachedData]);

		try {
			$pixabayData = Http::timeout(30)->get('https://pixabay.com/api/', [
				'key' => PixabayPlugin::$plugin->getSettings()->apiKey,
				'q' => $query,
				'page' => $page,
				'image_type' => $type,
				'per_page' => 20,
			])->json();
		} catch (\Throwable $e) {
			$pixabayData = null;
		}

		if (!\is_array($pixabayData))
			$pixabayData = [];

		$pixabayData['_query'] = $query;
		$pixabayData['_page'] = $page;
		$pixabayData['_type'] = $type;

		$success = isset($pixabayData['hits']);

		if ($success)
			$this->_saveCache($query, $page, $type, $pixabayData);

		return new JsonResponse(['success' => $success, 'data' => $pixabayData]);
	}


	public function download(Request $request): BinaryFileResponse {
		//Download a picture from Pixabay and pass it on to the browser, which uploads it to the selected folder like any other file
		//(Craft's upload checks the volume permissions, handles filename conflicts and selects the new asset)

		$url = (string)$request->query('url', '');

		abort_unless($this->_isPixabayUrl($url), 400, 'This is not a Pixabay URL: ' . $url);

		$file = preg_replace('/[^\w.-]/', '', basename(parse_url($url, PHP_URL_PATH) ?? ''));

		abort_if(!$file, 400, 'Could not get filename from URL: ' . $url);

		$target = PixabayPlugin::cachePath() . DIRECTORY_SEPARATOR . uniqid('download-') . '-' . $file;

		//Prevent script timeouts
		set_time_limit(600);

		$response = Http::timeout(600)->withOptions(['sink' => $target])->get($url);

		if (!$response->successful()) {
			@unlink($target);
			abort(502, 'Could not download from Pixabay: ' . $response->status());
		}

		return response()->download($target, $file, ['Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream'])
			->deleteFileAfterSend();
	}
}
