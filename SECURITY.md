# Security Policy

## Supported Versions

Zerrors does not yet have a formal release/support cadence. Security fixes are applied to the latest commit on `main`; if you're running an older version, please update before reporting an issue you haven't reproduced on `main`.

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Instead, report it privately via [GitHub Security Advisories](../../security/advisories/new) for this repository. This lets us discuss and fix the issue before it's publicly disclosed.

When reporting, please include:

- A description of the vulnerability and its potential impact.
- Steps to reproduce (a minimal repro is ideal).
- Any relevant logs, request/response payloads, or stack traces (redact secrets/tokens).
- The version/commit you tested against.

## What to expect

- We'll acknowledge your report as soon as we can.
- We'll investigate and let you know if it's confirmed, and roughly when to expect a fix.
- Once a fix is released, we'll credit you in the advisory (unless you'd prefer to stay anonymous).

## Scope

This policy covers the Zerrors application code in this repository. Vulnerabilities in third-party dependencies should generally be reported upstream, but feel free to flag them here too if they affect Zerrors specifically (e.g. a vulnerable version pinned in `composer.json`/`package.json`).
