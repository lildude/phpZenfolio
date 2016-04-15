# Change Log
All notable changes to this project will be documented in this file.
As of 2.0.0, this project adheres to [Semantic Versioning](http://semver.org/) and the format is based on the suggestions a <http://keepachangelog.com/>.

## [2.0] - TBC
### Added

### Changed

### Removed

## [1.3] - 2016-04-16
### Changed
- Default API version is now 1.8 and the rest of the library takes this API version into account.
- The README is Markdown instead of plaintext.

## [1.2] - 2012-06-10
### Added
- Ability to perform ALL API requests over HTTPS (Ticket #4)

### Changed
- The API endpoint uses api.zenfolio.com as requested by Zenfolio
- The default API version is now 1.6

## [1.1] - 2011-03-28
### Added

### Changed
- Use md5 to generate a uniq ID for each request instead of using `intval()` to ensure correct and consistent behaviour on 32-bit and 64-bit platforms. (Ticket #1)
- Corrected check for safe_dir OR open_basedir so fails over to socket connection correctly (Ticket #2)
- Cache only successful requests
- Improved connection settings

### Removed
- Removed erroneous re-instantiation of processor when setting adapter.

## [1.0] - 2010-10-01
- Initial release.
