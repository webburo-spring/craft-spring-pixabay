<?php
namespace webburospring\pixabay\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PixabayAssets extends AssetBundle
{
	public function init() {
		//Define asset bundle and files
		
		$this->sourcePath = '@webburospring/pixabay/assets/dist';
		$this->depends = [CpAsset::class];

		$this->js = ['pixabay.js'];
		$this->css = ['pixabay.css'];

		parent::init();
	}
}