<?php
/* ____              __              ____  __              __
  /  __\ ____  ___  /_/___  ____    / __ \/_/_  __  _____ / /_  _____ __  __
  \  \  / __ \/ __\/ / __ \/ __ \  / /_/ / /\ \/ _\/ __  / __ \/ __  / / / /
 __\  \/ /_/ / /  / / / / / /_/ / / ____/ /_/  _/ / /_/ / /_/ / /_/ / /_/ /
/_____/ ____/_/  /_/_/ /_/\__  / /_/   /_/\__/\_\ \__/\/_____/\__/\/\__  /
     /_/                 /____/                                    /____/
*/
namespace webburospring\pixabay;

use webburospring\pixabay\models\SettingsModel;

use CraftCms\Cms\Asset\Events\AssetFileHandling;
use CraftCms\Cms\Field\Link;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\MarkdownContent;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Cms\View\Events\PageTemplateRendering;
use CraftCms\Cms\View\TemplateMode;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

use function CraftCms\Cms\action_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class PixabayPlugin extends Plugin
{
	public static $plugin;

	//Environment variable with the Pixabay API key
	public const API_KEY_ENV = 'CRAFT_SPRING_PIXABAY_KEY';

	//2.0.0: the API key moved from the plugin settings to the .env file (database/migrations)
	public string $schemaVersion = '2.0.0';


	protected static function createSettings(): ?PluginSettings {
		//Plugin settings model

		return new SettingsModel();
	}


	public function register(): void {
		//Routes (routes/actions.php), the spring-pixabay template root and translations are registered by Craft (HasRoutes, HasViews, HasTranslations)

		$this->hasCpSection = false;
		$this->hasCpSettings = true;
	}


	public function boot(): void {

		self::$plugin = $this;

		//Add the Pixabay metadata to assets uploaded from the Pixabay modal
		Event::listen(fn (AssetFileHandling $event) => $this->_applyMetadata($event));

		if (app()->runningInConsole() || !request()->isCpRequest())
			return;

		//The user is only known once the session has started, so register the assets when the page renders:
		//Inertia CP pages are rendered with the "app" view, legacy CP pages with a page template
		View::composer('app', fn () => $this->_registerAssets());
		Event::listen(function (PageTemplateRendering $event) {
			if ($event->templateMode === TemplateMode::Cp)
				$this->_registerAssets();
		});
	}


	public function beforeSaveSettings(): bool {
		//Write the API key from the settings page to the .env file instead of the project config

		$settings = $this->getSettings();

		//An empty field is posted as null (which clears the key), but when saving the settings elsewhere the key isn't posted at all
		if ($settings->apiKey === null && !request()->exists('settings.apiKey'))
			return true;

		if (!self::writeApiKeyEnv(trim((string)$settings->apiKey), true)) {
			$settings->errors()->add('apiKey', t('Could not save the API key to the .env file as {name}.', ['name' => self::API_KEY_ENV], category: 'spring-pixabay'));
			return false;
		}

		$settings->apiKey = null;

		return true;
	}


	protected function afterInstall(): void {
		//Add the (empty) API key variable to the .env file, so it only needs to be filled in

		self::writeApiKeyEnv();
	}


	private function _registerAssets(): void {
		//Register Javascript variables (from the component templates), CSS and JS in CP requests

		if (!currentUser() || !self::apiKey())
			return;

		$defaultText = t('Use the search box above to search Pixabay.', category: 'spring-pixabay');

		$jsVars = [
			'PixabayDefaultText' => $defaultText,
			'PixabaySearchUrl' => action_url('spring-pixabay/pixabay/search'),
			'PixabayDownloadUrl' => action_url('spring-pixabay/pixabay/download'),
			'PixabayButton' => template('spring-pixabay/_components/pixabay-button', [], TemplateMode::Cp),
			'PixabayModal' => template('spring-pixabay/_components/pixabay-modal', ['logo' => action_url('spring-pixabay/pixabay/logo'), 'defaultText' => $defaultText], TemplateMode::Cp),
			'PixabayLoading' => template('spring-pixabay/_components/pixabay-loading', [], TemplateMode::Cp),
		];

		$script = '';
		foreach ($jsVars as $var => $val)
			$script .= ($script ? ', ' : 'var ') . $var . ' = ' . json_encode($val);

		//Keys prevent registering the same assets twice (e.g. a page template rendered inside the app view)
		HtmlStack::js($script, Position::Head, 'spring-pixabay-vars');
		HtmlStack::css(file_get_contents(__DIR__ . '/assets/dist/pixabay.css'), [], 'spring-pixabay');
		HtmlStack::js(file_get_contents(__DIR__ . '/assets/dist/pixabay.js'), Position::BodyEnd, 'spring-pixabay');
	}


	private function _applyMetadata(AssetFileHandling $event): void {
		//The download action saved the metadata of each downloaded picture by file hash; if the uploaded file is one of them, fill the selected fields

		$settings = $this->getSettings();
		$fields = array_filter([
			'user' => $settings->authorField,
			'userUrl' => $settings->authorUrlField,
			'pageUrl' => $settings->pageUrlField,
		]);

		if (!$event->isNew || !$event->asset->uploadSource || !$fields || !glob(self::cachePath() . DIRECTORY_SEPARATOR . 'meta-*.json'))
			return;

		$metadataFile = self::metadataFile(hash_file('sha256', $event->asset->uploadSource->localPath()));
		if (!file_exists($metadataFile))
			return;

		$metadata = @json_decode(file_get_contents($metadataFile), true);
		@unlink($metadataFile);

		foreach ($fields as $key => $handle) {
			if (!empty($metadata[$key]))
				$event->asset->setFieldValue($handle, $metadata[$key]);
		}
	}


	public function settingsForm(FormContext $context = new FormContext): ?Form {
		//Plugin settings page

		return Form::make([
			MarkdownContent::make('spring-pixabay-description', t('[PLUGINDESCRIPTION]', category: 'spring-pixabay')),
			...(self::apiKey() ? [] : [
				Callout::make('spring-pixabay-no-api-key', t('Enter an API key for Pixabay and click "Save" to enable this plugin.', category: 'spring-pixabay'))
					->variant('warning'),
			]),
			Field::make(
				t('Pixabay API key', category: 'spring-pixabay'),
				//The key is saved in the .env file (see beforeSaveSettings()), so it's not one of the settings values
				Text::make('apiKey')->value(self::apiKey() ?? ''),
			)->instructions(t('Saved in the .env file as {name}. See {url}', ['name' => self::API_KEY_ENV, 'url' => 'https://pixabay.com/api/docs/'], category: 'spring-pixabay')),
			Field::make(
				t('Author field', category: 'spring-pixabay'),
				Choice::make('authorField')->options(self::_fieldOptions())->placeholder(t('None', category: 'spring-pixabay')),
			)->instructions(t('The asset field to save the Pixabay username of the author to.', category: 'spring-pixabay')),
			Field::make(
				t('Author URL field', category: 'spring-pixabay'),
				Choice::make('authorUrlField')->options(self::_fieldOptions())->placeholder(t('None', category: 'spring-pixabay')),
			)->instructions(t('The asset field to save the URL of the author’s Pixabay profile to.', category: 'spring-pixabay')),
			Field::make(
				t('Pixabay page URL field', category: 'spring-pixabay'),
				Choice::make('pageUrlField')->options(self::_fieldOptions())->placeholder(t('None', category: 'spring-pixabay')),
			)->instructions(t('The asset field to save the URL of the picture’s Pixabay page to.', category: 'spring-pixabay')),
		]);
	}


	private static function _fieldOptions(): array {
		//Fields the metadata can be saved to (the field also needs to be in the field layout of the volume)
		//Fields in the volume field layouts first, since they can have a different handle there

		$layoutFields = Volumes::getAllVolumes()
			->flatMap(fn ($volume) => $volume->getFieldLayout()->getCustomFields());

		return $layoutFields->merge(Fields::getAllFields())
			->filter(fn ($field) => $field instanceof PlainText || $field instanceof Link)
			->unique(fn ($field) => $field->handle)
			->map(fn ($field) => ['label' => $field->name . ' (' . $field->handle . ')', 'value' => $field->handle])
			->values()
			->all();
	}


	public static function apiKey(): ?string {
		//Pixabay API key from the .env file (or the API key setting of earlier versions, which may reference an environment variable)

		return Env::get(self::API_KEY_ENV) ?: Env::parse(self::$plugin->getSettings()->apiKey) ?: null;
	}


	public static function writeApiKeyEnv(string $value = '', bool $overwrite = false): bool {
		//Add the API key variable to the .env file if it doesn't exist yet (or update it)

		$envFile = app()->environmentFilePath();

		if (!file_exists($envFile))
			return false;

		if (!$overwrite && Env::variableExists(self::API_KEY_ENV, $envFile))
			return true;

		try {
			Env::writeVariable(self::API_KEY_ENV, $value, $envFile, $overwrite);
		} catch (\Throwable $e) {
			Log::warning('Could not write ' . self::API_KEY_ENV . ' to ' . $envFile . ': ' . $e->getMessage());
			return false;
		}

		return true;
	}


	public static function cachePath() {
		//Create a subfolder for Pixabay temporary files in the storage directory (or if that can't be found, the plugin directory) and return the location

		$storageDir = Path::storage();

		if (is_dir($storageDir))
			$storageDir .= DIRECTORY_SEPARATOR . 'pixabay';
		else
			$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';

		if (!is_dir($storageDir))
			mkdir($storageDir);

		return $storageDir;
	}


	public static function metadataFile(string $hash): string {
		//Metadata of a downloaded picture, by file hash (removed with the other cache files after 24 hours)

		return self::cachePath() . DIRECTORY_SEPARATOR . 'meta-' . $hash . '.json';
	}
}
