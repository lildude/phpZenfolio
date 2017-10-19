# Change Log
All notable changes to this project will be documented in this file.
As of 2.0.0, this project adheres to [Semantic Versioning](http://semver.org/) and the format is based on the suggestions a <http://keepachangelog.com/>.

## [2.0.1] - 2017-10-19
### Fixed
- Uploading non-image files to the raw endpoint failed with a `400 Bad Request` error. (Ticket #23) 

## [2.0.0] - 2016-10-16
### Added
- A new example showing how to create a gallery and upload an image to it.

### Changed
- Complete rewrite of phpZenfolio.
- phpZenfolio 2.0.0 is _not_ backwardly compatible with phpZenfolio 1.x and earlier.
- Implemented proper semantic versioning.
- Switched to using Guzzle for requests to the API.  This means more reliable and predictable behaviour and allows for easier future improvements in phpZenfolio without having to worry about maintaining a library that submits requests.
- All requests, except image uploads, use HTTPS. Zenfolio has not implemented image uploads over HTTPS yet.
- Requires PHP 5.6.0 or later.
- All tests are now public and run on Travis CI with every push.
- Licensed under the MIT license.
- Documentation has been split out of the README.md into individual files and moved to a dedicated directory.
- All documentation is licensed under the CC BY 4.0 license.
- PSR-1, PSR-2, and PSR-4 coding standards are implemented and enforced by unit testing.

### Removed
- Caching has been removed from phpZenfolio as there are better Guzzle-friendly implementations that tie-in with phpZenfolio thanks to the use of Guzzle.

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
### Changed
- Cache only successful requests
- Improved connection settings

### Fixed
- Use md5 to generate a uniq ID for each request instead of using `intval()` to ensure correct and consistent behaviour on 32-bit and 64-bit platforms. (Ticket #1)
- Corrected check for safe_dir OR open_basedir so fails over to socket connection correctly (Ticket #2)

### Removed
- Removed erroneous re-instantiation of processor when setting adapter.

## [1.0] - 2010-10-01
- Initial release.
