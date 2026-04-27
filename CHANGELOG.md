# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.1.3]

### Added
- Walking is now a separate sport type (🚶) distinct from Hiking (🥾), detected from FIT file sport/sub-sport fields
- Photo popup on the map now shows a single-image carousel with ‹ › navigation buttons when a cluster contains multiple photos
- Activity calendar date selection now syncs the week chart to the selected week

### Changed
- Week chart renamed from "Last 7 Days" to "This Week"; shows the current Mon–Sun week with ‹ › arrows to browse past weeks
- Activity cards now show a from–to time range (e.g. 07:30 – 08:45) instead of repeating the date
- All sport labels, icons, and colors are defined once in a central module and reused across all components
- Photo time window tightened from ±26 hours to activity start–end ±1 hour to avoid unrelated photos appearing

### Fixed
- Activity timestamps are now stored and displayed in ISO 8601 UTC format, preventing browsers from misinterpreting UTC as local time
- Photos in the photo strip are now sorted by EXIF capture time

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
