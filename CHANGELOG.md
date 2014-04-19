# DreamFactory Services Platform&trade; Change Log

## v1.5.0 (Released 2014-04-19)


## v1.4.x (Last Updated 2014-03-03)

### Major Foundational Changes
* Project Tree Reorganization
	* The [dsp-core](https://github.com/dreamfactorysoftware/dsp-core/) ```config/schema``` tree has been moved to this library.
	* The [Swagger](https://github.com/zircode/swagger-php/) storage area has been moved to a, now, user-editable location. Previously, it was hidden from hosted DSPs.
	* More functionality moved from launchpad/admin to back-end including login and password management
* Performance Improvements
	* Moved required PHPUnit dependencies ```"require-dev"``` section of ```composer.json``` so they can be more easily excluded in production.
	* Multiple performance improvements from consolidation/caching/removal of repetitive processes
	* Hosted DSPs will no longer check github for versioning

### New Features
* **New Data Formatter**: ```Components\AciTreeFormatter``` for data used in an [AciTree](http://plugins.jquery.com/aciTree/).
* **Guzzle Migration**: Begin migration from our [Curl](https://github.com/lucifurious/kisma/blob/master/src/Kisma/Core/Utility/Curl.php) class to [Guzzle](https://github.com/guzzle/guzzle/) .
* **Foundational Changes**: Laid groundwork for server-side events
* **Device Management**: New schema, models, and API added. Affects system and user services as well.
* **New Constants**:
	* [**LocalStorageTypes::SWAGGER_PATH**](https://bitbucket.org/dreamfactory/lib-php-common-platform/src/4ca33f4915ef2cacc340c1d74bf7ffc93e72fab9/Interfaces/PlatformStates.php?at=master) and [**LocalStorageTypes::TEMPLATE_PATH**](https://bitbucket.org/dreamfactory/lib-php-common-platform/src/4ca33f4915ef2cacc340c1d74bf7ffc93e72fab9/Interfaces/PlatformStates.php?at=master) and associated getters in [Platform](https://bitbucket.org/dreamfactory/lib-php-common-platform/src/4ca33f4915ef2cacc340c1d74bf7ffc93e72fab9/Utility/Platform.php?at=master)
	* [**PlatformStates::WELCOME_REQUIRED**](https://bitbucket.org/dreamfactory/lib-php-common-platform/src/4ca33f4915ef2cacc340c1d74bf7ffc93e72fab9/Interfaces/PlatformStates.php?at=master) platform state and modifying [SystemManager::initAdmin](https://bitbucket.org/dreamfactory/lib-php-common-platform/src/4ca33f4915ef2cacc340c1d74bf7ffc93e72fab9/Services/SystemManager.php?at=master) to be cleaner. NOTE: Requires new DSP version

### Bug Fixes
* Fix api login session bug
* Fix content type determination on file management
* Model corrections for swagger
* Better check for multi-row configs
* Fix some include problems, add search both directions to array_diff in Utility
* Override to setNativeFormat() to avoid invalid argument exception
* Fixes #57 by returning all columns from the model when constructing the response

