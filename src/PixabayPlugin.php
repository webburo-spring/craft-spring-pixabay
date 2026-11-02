<?php
/* ____              __              ____  __              __
  /  __\ ____  ___  /_/___  ____    / __ \/_/_  __  _____ / /_  _____ __  __
  \  \  / __ \/ __\/ / __ \/ __ \  / /_/ / /\ \/ _\/ __  / __ \/ __  / / / /
 __\  \/ /_/ / /  / / / / / /_/ / / ____/ /_/  _/ / /_/ / /_/ / /_/ / /_/ /
/_____/ ____/_/  /_/_/ /_/\__  / /_/   /_/\__/\_\ \__/\/_____/\__/\/\__  /
     /_/                 /____/                                    /____/
*/
namespace webburospring\pixabay;

use webburospring\pixabay\assets\PixabayAssets;
use webburospring\pixabay\models\SettingsModel;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\TemplateEvent;
use craft\web\View;

use yii\base\Event;

class PixabayPlugin extends Plugin
{
	public static $plugin;


	public function init() {

		$this->hasCpSection = false;
		$this->hasCpSettings = true;
		
		parent::init();
		self::$plugin = $this;
		
		Event::on(
			View::class,
			View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
			function (TemplateEvent $e) {
		
				//Register asset bundle and Javascript variables (from template macros) in CP requests
				if (Craft::$app->request->isCpRequest && $e->templateMode == 'cp' && !Craft::$app->user->isGuest && $this->settings->apiKey) {

					$logo = Craft::$app->assetManager->getPublishedUrl('@webburospring/pixabay/assets/dist/pixabay.svg', true);
					
					$defaultText = Craft::t('spring-pixabay', 'Use the search box above to search Pixabay.');
					
					Craft::$app->view->registerTranslations('spring-pixabay', [
						'An error occured while loading Pixabay data',
						'No results for "{query}"',
						'{n,plural,=1{# picture} other{# pictures}} selected',
						'Downloading pictures...',
						'Previous page',
						'Next page',
						'Page {p}',
					]);
					
					$jsVars = [
						'PixabayDefaultText' => $defaultText,
						'PixabayButton' => Craft::$app->view->renderTemplate('spring-pixabay/_components/pixabay-button'),
						'PixabayModal' => Craft::$app->view->renderTemplate('spring-pixabay/_components/pixabay-modal', ['logo' => $logo, 'defaultText' => $defaultText]),
						'PixabayLoading' => Craft::$app->view->renderTemplate('spring-pixabay/_components/pixabay-loading'),
					];
					
					$script = '';
					foreach ($jsVars as $var => $val)
						$script .= ($script ? ', ' : 'var ') . $var . ' = ' . json_encode($val);
					
					Craft::$app->view->registerScript($script);
					
					//Explicitly publish and register these since asset bundles don't work reliably
					$css = Craft::$app->assetManager->getPublishedUrl('@webburospring/pixabay/assets/dist/pixabay.css', true);
					$js = Craft::$app->assetManager->getPublishedUrl('@webburospring/pixabay/assets/dist/pixabay.js', true);
					Craft::$app->view->registerCssFile($css);
					Craft::$app->view->registerJsFile($js);
				}
		
			}
		);
	
	}


	protected function createSettingsModel(): Model {
		//Plugin settings model
		
		return new SettingsModel();
	}


	protected function settingsHtml(): string {
		//Plugin settings page template
		
		return Craft::$app->view->renderTemplate('spring-pixabay/_settings', ['settings' => $this->settings, 'fieldOptions' => self::fieldOptions()]);
	}


	public static function fieldOptions() {
		//Fields the metadata can be saved to (the field also needs to be in the field layout of the volume)
		//Fields in the volume field layouts first, since they can have a different handle there (Craft 5)

		$fields = [];
		foreach (Craft::$app->volumes->getAllVolumes() as $volume) {
			$layout = $volume->getFieldLayout();
			if ($layout)
				$fields = array_merge($fields, self::layoutFields($layout));
		}

		$fields = array_merge($fields, Craft::$app->fields->getAllFields());

		//Plain Text and URL fields (Craft 3 and 4), or Link fields (Craft 5, where Url is an alias of Link)
		$classes = array_filter(['craft\fields\PlainText', 'craft\fields\Url', 'craft\fields\Link'], 'class_exists');

		$options = [['label' => Craft::t('spring-pixabay', 'None'), 'value' => '']];
		$handles = [];

		foreach ($fields as $field) {
			if (in_array($field->handle, $handles, true))
				continue;

			foreach ($classes as $class) {
				if ($field instanceof $class) {
					$handles[] = $field->handle;
					$options[] = ['label' => $field->name . ' (' . $field->handle . ')', 'value' => $field->handle];
					break;
				}
			}
		}

		return $options;
	}


	public static function layoutFields($layout) {
		//Custom fields of a field layout (getFields() in Craft 3, getCustomFields() in Craft 4 and 5)

		return method_exists($layout, 'getCustomFields') ? $layout->getCustomFields() : $layout->getFields();
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