# Shared Scripts

A Composer plugin that provides reusable scripts for PHP projects, including environment variable synchronization between `.env.example` and `.env`.

## Installation

Install the package using Composer:

```bash
composer require sushan-jobins/shared-scripts

composer require --dev sushan-jobins/shared-scripts:dev-main
```

The Composer plugin is automatically activated after installation.

You can verify that the plugin is active by running:

```bash
composer show sushan-jobins/shared-scripts
```

## copy-missing-env

The `copy-missing-env` script compares `.env.example` with `.env`, displays environment variable changes, and asks for confirmation before updating `.env`.

### Basic usage

```bash
composer copy-missing-env
```

### Filter by status

```bash
composer copy-missing-env -- --status=all
```

Available statuses:

* `all`
* `added`
* `changed`
* `not_changed_on_env`
* `only_on_env`
* `same`

Example:

```bash
composer copy-missing-env -- --status=changed
```

## Requirements

* PHP 8.1+
* Composer 2.x

## License

See the package license for details.


# Shared Scripts

A collection of shared development scripts that can be used across Laravel projects.

## Requirements

- Composer
- Laravel project

## Installation

This package can be installed directly from GitHub using Composer.

Add the following repository to your Laravel project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:sushan-jobins/shared-scripts.git"
    }
]