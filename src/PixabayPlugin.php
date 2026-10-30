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

use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\MarkdownContent;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Cms\View\Events\PageTemplateRendering;
use CraftCms\Cms\View\TemplateMode;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;

use function CraftCms\Cms\action_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class PixabayPlugin extends Plugin
{
	public static $plugin;


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


	private function _registerAssets(): void {
		//Register Javascript variables (from the component templates), CSS and JS in CP requests

		if (!currentUser() || !$this->getSettings()->apiKey)
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


	public function settingsForm(FormContext $context = new FormContext): ?Form {
		//Plugin settings page

		$apiKey = $context->values['settings']['apiKey'] ?? null;

		return Form::make([
			MarkdownContent::make('spring-pixabay-description', t('[PLUGINDESCRIPTION]', category: 'spring-pixabay')),
			...(empty($apiKey) ? [
				Callout::make('spring-pixabay-no-api-key', t('Enter an API key for Pixabay and click "Save" to enable this plugin.', category: 'spring-pixabay'))
					->variant('warning'),
			] : []),
			Field::make(
				t('Pixabay API key', category: 'spring-pixabay'),
				Text::make('apiKey'),
			)->instructions(t('See {url}', ['url' => 'https://pixabay.com/api/docs/'], category: 'spring-pixabay')),
		]);
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
}
