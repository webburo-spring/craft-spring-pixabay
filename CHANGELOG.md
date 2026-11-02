# Pixabay plugin by Webburo Spring - Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## Unreleased
### Changed
- Searches now use the control panel user's language, if Pixabay supports it

### Fixed
- Fixed searches taking minutes in environments without IPv6 connectivity, such as Docker / DDEV ([#18](https://github.com/webburo-spring/craft-spring-pixabay/issues/18))
- Fixed caching search results for queries whose base64 encoding contains a slash
- Fixed error message being replaced by "No results" when a search fails
- Fixed `folderId:` / `volumeId:` upload targets not being recognised

## 1.2.0 - 2022-10-06
### Added
- Added support for Craft 4

## 1.1.1 - 2022-05-19
### Fixed
- Fixed filtering by image type when changing pages

## 1.1.0 - 2022-05-19
### Added
- Added support for filtering by image type

## 1.0.3 - 2021-09-06
### Fixed
- Fixed checks for CP template mode (in addition to CP requests)

## 1.0.2 - 2020-10-14
### Fixed
- Fixed support for uploading assets to subfolders

## 1.0.1 - 2020-10-07
### Fixed
- Hotfix release: plugin assets were not always loading correctly

## 1.0.0 - 2020-09-30
### Added
- Initial release
