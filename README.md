# SOCA for TYPO3

An installable SOCA OpenID Connect and CIMD integration for TYPO3 frontend users on one configured site, with backend users excluded. Version 0.1.0 is an alpha review candidate. It uses authorisation code flow, mandatory S256 PKCE and ES256 `private_key_jwt`, with explicit issuer/subject account links.

Ordinary SSO and account creation start disabled. Public document checks and an operator-bound candidate test must pass for the current configuration before ordinary sign-in is admitted. Existing local administrator authentication remains available.

1. Add this repository as a Composer VCS repository and require `resoauth/typo3-soca-plugin:0.1.0` from a release or reviewed commit. The package is not yet published on Packagist or TER.
2. Run `vendor/bin/typo3 extension:setup` and `vendor/bin/typo3 cache:flush` with the normal deployment user. Use the native backend to create a frontend group and a user storage page.
3. Set `SOCA_CONFIG_FILE` in the PHP worker environment. Set `site_id`, `storage_pid` and `group_id` for that site.
4. Link to `/soca/login` from your existing frontend login page. The extension registers native authentication services and a PSR-15 middleware, without changing backend authentication.

Start with [config.example.json](config.example.json), then follow [configuration and activation](docs/configuration.md). The native settings page is **System → SOCA sign-in in the existing TYPO3 backend**. Every setting is deployment managed and the GUI shows redacted, read-only effective values.

Run `demo/run.sh` for a disposable HTTPS host, or follow [the demo guide](demo/README.md). Run `composer install` and `composer test` for the 129-assertion protocol suite and host coordination tests. The [dated verification record](docs/verification.md) separates synthetic protocol results, actual host tests and outstanding real-provider logins. The [requirements assessment](docs/requirements.md) covers every F-01–F-25 and N-01–N-09 requirement and its remaining gap.

See [operations and recovery](docs/operations.md), [the threat model](docs/threat-model.md), [security reporting](SECURITY.md) and [the AGPL-3.0 licence](LICENSE). Do not roll this candidate into production before completing the documented live-provider, browser, accessibility and independent security acceptance gates.
