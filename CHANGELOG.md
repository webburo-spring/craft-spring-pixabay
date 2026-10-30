# Pixabay plugin by Webburo Spring - Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0-alpha
### Added
- Added support for Craft 6 (no Yii2 adapter needed)

### Changed
- The "Search Pixabay" button is now shown next to the "Upload files" button of asset selector modals
- Selected pictures are uploaded through Craft's own upload, so permissions, filename conflicts and selection work like any other upload
- The settings page now uses Craft's settings form

### Fixed
- Search cache file names no longer break on queries that contain slashes
- Downloads are only allowed from pixabay.com and its subdomains
