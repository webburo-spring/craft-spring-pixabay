<?php
namespace webburospring\pixabay\models;

use CraftCms\Cms\Plugin\PluginSettings;

class SettingsModel extends PluginSettings
{
	//The API key is saved in the .env file as CRAFT_SPRING_PIXABAY_KEY (see PixabayPlugin::beforeSaveSettings()), this is only set by earlier versions or the settings page
	public ?string $apiKey = null;

	//Handles of the asset fields the Pixabay metadata is saved to
	public ?string $authorField = null;
	public ?string $authorUrlField = null;
	public ?string $pageUrlField = null;


	public function validationData(): array {
		//Leave out the API key when it's not set, so it's not saved in the project config and the settings page shows the key from the .env file

		$data = parent::validationData();

		if ($this->apiKey === null)
			unset($data['apiKey']);

		return $data;
	}
}
