[![Version](http://img.shields.io/packagist/v/contao-community-alliance/build-system-tool-author-validation.svg?style=flat-square)](https://packagist.org/packages/contao-community-alliance/build-system-tool-author-validation)
[![Stable Build Status](http://img.shields.io/travis/contao-community-alliance/build-system-tool-author-validation/master.svg?style=flat-square)](https://travis-ci.org/contao-community-alliance/build-system-tool-author-validation)
[![Upstream Build Status](http://img.shields.io/travis/contao-community-alliance/build-system-tool-author-validation/develop.svg?style=flat-square)](https://travis-ci.org/contao-community-alliance/build-system-tool-author-validation)
[![License](http://img.shields.io/packagist/l/contao-community-alliance/build-system-tool-author-validation.svg?style=flat-square)](https://github.com/contao-community-alliance/build-system-tool-author-validation/blob/master/LICENSE)
[![Downloads](http://img.shields.io/packagist/dt/contao-community-alliance/build-system-tool-author-validation.svg?style=flat-square)](https://packagist.org/packages/contao-community-alliance/build-system-tool-author-validation)

Validate the author information within PHP files, composer.json, bower.json or packages.json.
=============================================================================================

This is useful to ensure that all authors (from git history) mentioned in all PHP files, the `composer.json`,
`bower.json` and `packages.json`.

Usage
-----

Add to your `composer.json` in the `require-dev` section:
```
"contao-community-alliance/build-system-tool-author-validation": "~1.0"
```

Call the binary:
```
./vendor/bin/check-author.php
```

Optionally pass a path to check:
```
./vendor/bin/check-author.php /path/to/some/repository/also/with/subdir
```
