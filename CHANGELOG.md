# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project attempts to follow [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## Unreleased

### Changed

* Moved namespace from `Courier\Sparkpost` to `Courier\SparkPost` for consistent casing

## 0.2.0 - 2018-12-27

### Removed

* Dropped support for Courier 0.4 and 0.5

### Changed

* Added support for Courier 0.6

## 0.1.2 - 2018-12-26

### Deprecated

* The `fromEmail` and `fromDomain` variables are deprecated in the template data. Sparkpost now allows for a single
from address to be provided.

### Added

* Added a `fromAddress` variable in template data, representing the full email address (`sample@test.com`)

### Changed

* Extracted inline template logic into separate class in backwards compatible manner.

## 0.1.1 - 2018-12-10

### Added

* Update dependency to support `quartzy/courier` ^0.5.0

## 0.1.0

### Changed

* Update namespace to `Courier\Sparkpost`
* Forked project from [quartzy/courier 0.4.0](https://github.com/quartzy/courier) with just Sparkpost logic.
