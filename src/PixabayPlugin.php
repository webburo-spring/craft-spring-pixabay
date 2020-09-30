<?php
namespace webburospring\pixabay;

use webburospring\pixabay\assets\PixabayAssets;
use webburospring\pixabay\models\SettingsModel;

use Craft;
use craft\i18n\PhpMessageSource;
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
		
		//Register asset bundle and Javascript variables (from template macros) in CP requests
		if (Craft::$app->request->isCpRequest && Craft::$app->user->identity && $this->settings->apiKey) {

			$logo = Craft::$app->assetManager->getPublishedUrl('@webburospring/pixabay/assets/dist/pixabay.svg', true);
			$defaultText = Craft::t('spring-pixabay', 'Use the search box above to search Pixabay.');
			
			Craft::$app->view->registerTranslations('spring-pixabay', [
				'An error occured while loading Pixabay data',
				'No results for "{query}"',
				'{n,plural,=1{# picture} other{# pictures}} selected',
				'Downloading pictures...',
			]);
			
			$jsVars = [
				'PixabayDefaultText' => $defaultText,
				'PixabayButton' => Craft::$app->view->renderTemplateMacro('spring-pixabay/_macros', 'pixabayButton'),
				'PixabayModal' => Craft::$app->view->renderTemplateMacro('spring-pixabay/_macros', 'pixabayModal', ['logo' => $logo, 'defaultText' => $defaultText]),
				'PixabayLoading' => Craft::$app->view->renderTemplateMacro('spring-pixabay/_macros', 'pixabayLoading'),
			];
			
			$script = '';
			foreach ($jsVars as $var => $val)
				$script .= ($script ? ', ' : 'var ') . $var . ' = ' . json_encode($val);
			
			Craft::$app->view->registerScript($script);
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