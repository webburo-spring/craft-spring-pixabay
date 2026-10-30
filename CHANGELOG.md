# Pixabay plugin by Webburo Spring - Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0-alpha
### Added
- Added support for Craft 6 (no Yii2 adapter needed)
- The author name, author profile URL and Pixabay page URL of downloaded pictures can be saved to asset fields, selected in the plugin settings ([#19](https://github.com/webburo-spring/craft-spring-pixabay/issues/19))

### Changed
- The Pixabay API key is now set with the `CRAFT_SPRING_PIXABAY_KEY` environment variable instead of the plugin settings. The variable is added to the `.env` file on install, and an API key from the settings is moved there on update. The API key field in the settings saves the key to the `.env` file
- The "Search Pixabay" button is now shown next to the "Upload files" button of asset selector modals
- Selected pictures are uploaded through Craft's own upload, so permissions, filename conflicts and selection work like any other upload
- The settings page now uses Craft's settings form

### Fixed
- Search cache file names no longer break on queries that contain slashes
- Downloads are only allowed from pixabay.com and its subdomains
