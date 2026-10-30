<?php
//Move the Pixabay API key from the plugin settings to the .env file (CRAFT_SPRING_PIXABAY_KEY)

use CraftCms\Cms\ProjectConfig\ProjectConfig;
use Illuminate\Database\Migrations\Migration;
use webburospring\pixabay\PixabayPlugin;

return new class extends Migration
{
	public function up(): void {

		$path = ProjectConfig::PATH_PLUGINS . '.spring-pixabay.settings.apiKey';
		$apiKey = (string)app(ProjectConfig::class)->get($path);

		//A setting that references an environment variable ($VAR) keeps working (see PixabayPlugin::apiKey())
		$isEnvReference = str_starts_with($apiKey, '$');

		PixabayPlugin::writeApiKeyEnv($isEnvReference ? '' : $apiKey);

		//Only change the project config once, the other environments get the change when applying it
		$schemaVersion = app(ProjectConfig::class)->get(ProjectConfig::PATH_PLUGINS . '.spring-pixabay.schemaVersion', true);
		if ($apiKey !== '' && !$isEnvReference && version_compare((string)$schemaVersion, '2.0.0', '<'))
			app(ProjectConfig::class)->remove($path, 'Move the Pixabay API key to the .env file');
	}


	public function down(): void {
	}
};
