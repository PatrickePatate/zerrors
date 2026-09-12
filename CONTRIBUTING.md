# Contributing to Zerrors

Thanks for taking the time to contribute! This guide covers how to get set up locally and how to submit changes.

## Getting set up

Follow the [Getting started](README.md#getting-started) section in the README to install dependencies and run the app locally.

## Making a change

1. Fork the repo and create a branch from `main`.
2. Make your change, following the existing code conventions (check sibling files/classes for structure and naming before introducing something new).
3. Add or update tests for any behavior change:
   ```bash
   php artisan test
   ```
4. Format PHP code with [Laravel Pint](https://laravel.com/docs/pint) before committing:
   ```bash
   vendor/bin/pint
   ```
5. Open a pull request describing what changed and why. Link any related issue.

## Reporting bugs

Open a [GitHub issue](../../issues) with:

- What you expected to happen vs. what actually happened.
- Steps to reproduce.
- Relevant logs/stack traces (redact anything sensitive).
- Your environment (PHP version, database, whether you're running under Octane).

## Proposing features

Open an issue or discussion first for anything non-trivial, so we can agree on the approach before you invest time in an implementation.

## Security issues

Do not open a public issue for security vulnerabilities — see [SECURITY.md](SECURITY.md).

## Code of Conduct

This project follows a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you're expected to uphold it.
