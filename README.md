# Shared Scripts

Reusable development scripts for Laravel projects.

This package provides common development scripts that can be installed and reused across multiple Laravel projects.

Currently available:

* `copy-missing-env` — compares `.env` with `.env.example`, displays environment variable statuses, and optionally updates `.env`.

---

## Requirements

* PHP `8.1+`
* Composer `2.x`
* Laravel project

---

## Installation

Add the repository to your Laravel project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:sushan-jobins/shared-scripts.git"
    }
]
```

Then install the package:

```bash
composer require --dev sushan-jobins/shared-scripts:dev-main
```

Because this package is a Composer plugin, Composer may ask you to allow the plugin to execute:

```text
Do you trust "sushan-jobins/shared-scripts" to execute code?
```

Select `y` to allow it.

Add the script to your Laravel project's `composer.json`

```json
"scripts": {
    "copy-missing-env": "@php vendor/sushan-jobins/shared-scripts/scripts/copy-missing-env.php",
}
```

---

## Verify Installation

Check that Composer installed the package:

```bash
composer show sushan-jobins/shared-scripts
```

You should see:

```text
name     : sushan-jobins/shared-scripts
versions : * dev-main
type     : composer-plugin
```

You can also check the installed files:

```bash
ls -la vendor/sushan-jobins/shared-scripts
```

---

## Run the script

```bash
composer copy-missing-env
```

The script displays the environment variable status table.

If changes are available, it asks:

```text
⚠ .env changes are available.

Apply these changes to .env? (yes/no) [no]:
```

Enter:

```text
yes
```

or:

```text
y
```

to apply the changes.

Any other value skips the update.

---

# Status Filters

The following statuses are supported:

```text
all
added
changed
not_changed_on_env
only_on_env
same
```

## Show all statuses

```bash
composer copy-missing-env -- --status=all
```

This displays all environment variables, including `same`.

---

## Show added variables

```bash
composer copy-missing-env -- --status=added
```

`added` means the environment variable did not exist in the previous `.env` but exists in the current/generated `.env`.

---

## Show changed variables

```bash
composer copy-missing-env -- --status=changed
```

`changed` means the environment variable existed before, but its value has changed.

---

## Show variables not changed in `.env`

```bash
composer copy-missing-env -- --status=not_changed_on_env
```

`not_changed_on_env` means:

* The `.env` value has not changed.
* The value differs from `.env.example`.

---

## Show only_on_env variables

```bash
composer copy-missing-env -- --status=only_on_env
```

`only_on_env` means no change or update was detected for the environment variable.

---

## Show variables with the same value

```bash
composer copy-missing-env -- --status=same
```

`same` means the current `.env` value is exactly the same as the `.env.example` value.

---

# Status Information

To see the meaning of each status:

```bash
composer copy-missing-env -- --info-status
```

The command displays information for:

```text
ADDED
CHANGED
NOT_CHANGED_ON_ENV
SAME
UNCHANGED
```

---

# Dry Run

To check which environment variables are missing without modifying `.env`:

```bash
composer copy-missing-env -- --dry
```

The dry run only displays variables that exist in `.env.example` but are missing from `.env`.

No changes are made to `.env`.

---

# Available Commands

| Purpose               | Command                                                    |
| --------------------- | ---------------------------------------------------------- |
| Run script            | `composer copy-missing-env`                                |
| Show all              | `composer copy-missing-env -- --status=all`                |
| Added                 | `composer copy-missing-env -- --status=added`              |
| Changed               | `composer copy-missing-env -- --status=changed`            |
| Not changed on `.env` | `composer copy-missing-env -- --status=not_changed_on_env` |
| Only  on `.env`       | `composer copy-missing-env -- --status=only_on_env`        |
| Same                  | `composer copy-missing-env -- --status=same`               |
| Status information    | `composer copy-missing-env -- --info-status`               |
| Dry run               | `composer copy-missing-env -- --dry`                       |

---

# Updating the Shared Package

After changes are pushed to the `shared-scripts` repository, update the package in your Laravel project:

```bash
composer update sushan-jobins/shared-scripts
```

Then verify:

```bash
composer show sushan-jobins/shared-scripts
```

---


# Quick Start

For a new Laravel project:

### 1. Add repository

```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:sushan-jobins/shared-scripts.git"
    }
]
```

### 2. Install

```bash
composer require --dev sushan-jobins/shared-scripts:dev-main
```

### 3. Run

```bash
composer copy-missing-env
```

### 4. Show all statuses

```bash
composer copy-missing-env -- --status=all
```

### 5. Dry run

```bash
composer copy-missing-env -- --dry
```

### 6. Show status descriptions

```bash
composer copy-missing-env -- --info-status
```

---