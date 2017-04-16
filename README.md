[![Version](http://img.shields.io/packagist/v/phpcq/author-validation.svg?style=flat-square)](https://packagist.org/packages/phpcq/author-validation)
[![Stable Build Status](http://img.shields.io/travis/phpcq/author-validation/master.svg?style=flat-square)](https://travis-ci.org/phpcq/author-validation)
[![Upstream Build Status](http://img.shields.io/travis/phpcq/author-validation/develop.svg?style=flat-square)](https://travis-ci.org/phpcq/author-validation)
[![License](http://img.shields.io/packagist/l/phpcq/author-validation.svg?style=flat-square)](https://github.com/phpcq/author-validation/blob/master/LICENSE)
[![Downloads](http://img.shields.io/packagist/dt/phpcq/author-validation.svg?style=flat-square)](https://packagist.org/packages/phpcq/author-validation)

Validate the author information within PHP files, composer.json, bower.json or packages.json.
=============================================================================================

This is useful to ensure that all authors (from git history) mentioned in all PHP files, the `composer.json`,
`bower.json` and `packages.json`.

Usage
-----

Add to your `composer.json` in the `require-dev` section:
```
"phpcq/author-validation": "~1.0"
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
    
# Add additional author metadata. It is used by some comparator handlers when outputting diff format.
# Author metadata of the config file is prioritized over extracted metadata from the files.

metadata:
  "John Doe <jd@example.org>":
    role:     "Translator"
    homepage: "http:/www.example.org"
```
