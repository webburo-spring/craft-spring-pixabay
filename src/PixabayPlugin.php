<?php
namespace webburospring\pixabay;

use webburospring\pixabay\assets\PixabayAssets;
use webburospring\pixabay\models\SettingsModel;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

class PixabayPlugin extends Plugin
{
	public static $plugin;


	public function init() {

		$this->hasCpSection = false;
		$this->hasCpSettings = true;
		
		parent::init();
		self::$plugin = $this;
		
		//Register asset bundle in CP requests
		if (Craft::$app->request->isCpRequest && Craft::$app->user->identity) {
			Craft::$app->view->registerAssetBundle(PixabayAssets::class);
		}
	
	}


	protected function createSettingsModel() {
		//Plugin settings model
		
		return new SettingsModel();
	}


	protected function settingsHtml() {
		//Plugin settings page template
		
		return Craft::$app->view->renderTemplate('spring-pixabay/_settings', ['settings' => $this->settings]);
	}


	public static function cachePath() {
		//Create a subfolder for Pixabay temporary files in the craft/storage directory (or if that can't be found, the plugin directory) and return the location
		
		$storageDir = Craft::$app->path->storagePath;
		
		if (is_dir($storageDir))
			$storageDir .= DIRECTORY_SEPARATOR . 'pixabay';
		else
			$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
		
		if (!is_dir($storageDir))
			mkdir($storageDir);
		
		return $storageDir;
	}
}