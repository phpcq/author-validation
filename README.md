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

Configuration
-------------

Optionally you can pass the path to a config file (defaults to .check-author.yml) which shall be used.

```
# Example .check-author.yml

# Map multiple authors to a single one (aliasing of contributors).
mapping:
  # original: alias
  "John Doe <john@example.org>": "John Doe <jd@example.org>"
  # or original: [multiple aliases]
  "John Doe <john@example.org>": ["John Doe <jd@example.org>", "Acme Inc <info@example.org>"]
  # or
  "John Doe <john@example.org>":
    - "John Doe <jd@example.org>"
    - "Acme Inc <info@example.org>"

# Ignore commits from these authors (equivalent to cmd line parameter --ignore=...)
ignore:
  - Build Bot <bot@example.org>

# If present, scan only these and not the whole base dir (equivalent to cmd line arguments).
# Values must either be absolute paths or relative to the current directory.
include:
  - src

# Paths to exclude from scanning (equivalent to cmd line parameter --exclude=...)
exclude:
  - Foo.php
  - /tests/*
  - */languages

# Enforce copy-left author for certain files.
copy-left:
  "John Doe <jd@example.org>": "/library"
  # or
  "John Doe <jd@example.org>": ["/library", "File.php"]
  # or
  "John Doe <jd@example.org>":
    - "File.php"
```
