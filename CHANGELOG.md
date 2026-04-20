# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.1.2]

### Fixed
- Reduced memory usage when scanning photos: stream only 64 KB per file instead of loading the full JPEG
- Photo search now filters by date at the database level, avoiding full scans of all user photos

## [1.1.0]

### Added
- Photo integration for cycling and hiking activities — JPEG photos stored in Nextcloud whose GPS coordinates fall within 200 m of the activity route are automatically discovered
- Photos appear as a thumbnail strip below the map and as 📷 pins on the route map
- Nearby photos are clustered into a single pin with a count badge
- Clicking a photo opens it in Nextcloud's native viewer overlay (falls back to a full-screen overlay when the Viewer app is unavailable)
- Only GPS-tagged photos are included; images without EXIF coordinates are ignored

## [1.0.3]

### Changed
- Added application screenshots to app description

## [1.0.2]

### Changed
- Fixed issue with selecting a folder in Settings

## [1.0.1]

### Fixed
- Improved app store description with detailed feature overview and screenshots

## [1.0.0]

### Added
- Initial release
- Activity tracking for Running, Cycling, Hiking, Swimming, Gym, Breathwork, Meditation, and Skiing
- Dashboard with monthly activity calendar and last-7-days bar chart
- Activity detail view with interactive GPS map, elevation profile, heart rate chart, and lap table
- Sleep tracking with automatic detection of Garmin sleep files
- Sleep list with last-7-nights stacked bar chart (Deep / REM / Light / Awake)
- Sleep detail view with timeline, stage table, sleep score, and HRV score
- Dashboard widget showing last sleep session summary
- Settings page to configure the source folder for .fit files
