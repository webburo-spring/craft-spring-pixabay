<?php
namespace webburospring\pixabay\models;

use craft\base\Model;

class SettingsModel extends Model
{
	public $apiKey;

	//Handles of the asset fields the Pixabay metadata is saved to
	public $authorField;
	public $authorUrlField;
	public $pageUrlField;
}
