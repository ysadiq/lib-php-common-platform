# DreamFactory Services Platform&trade; Change Log

## v1.4.1 (Released 2014-03-03)

### Bug Fixes
* Fix api login session bug 

## v1.4.0 (Released 2014-02-28)

### Major Foundational Changes
* Restructure of the project tree
	* The config/schema and contained files have been moved here from dsp-core
	* Moved swagger directory to public storage on hosted (was previously hidden in private storage)
* Adding welcome platform state and modifying initAdmin to be cleaner. NOTE: Requires new DSP version

### New Features
* New AciTreeFormatter class. Use Guzzle instead of Curl class for grabbing DSP versions
* Added all required PHPUnit dependencies to composer.json's "require-dev" section so they will not be installed into a production system.
* Added schema, models, and API for device management, affecting system and user services
* Uncoupled various (login, etc.) services from UI forms and routing control

### Bug Fixes
* Fix content type determination on file management
* Model corrections for swagger
* Better check for multi-row configs
* Fix some include problems, add search both directions to array_diff in Utility
* Override to setNativeFormat() to avoid invalid argument exception
* Fixes #57 by returning all columns from the model when constructing the response
* Added new LocalStorageTypes constant for "SWAGGER", added new method: Platform::getSwaggerPath($append)
* Hosted DSPs no longer check github for versioning
* Added $singleUser property to base provider to indicate global credentials are used for all users
