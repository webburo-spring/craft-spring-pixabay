<?php
namespace webburospring\pixabay\models;

use CraftCms\Cms\Plugin\PluginSettings;

class SettingsModel extends PluginSettings
{
	public ?string $apiKey = null;
}
