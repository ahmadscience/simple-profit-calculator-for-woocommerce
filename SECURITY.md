# Security Policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 2.0.x (latest) | ✅ Security fixes |
| < 2.0  | ❌ Please upgrade |

## Reporting a vulnerability

**Do not report security issues in public GitHub issues, discussions or the WordPress.org support forum.**

Report privately through GitHub:

👉 [**Report a vulnerability**](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/security/advisories/new)

If you can't use GitHub, report through the [Patchstack](https://patchstack.com/database/report) or [Wordfence](https://www.wordfence.com/threat-intel/vulnerabilities/submit) disclosure programs. They forward reports to the author.

Please include:

- plugin version, WordPress, WooCommerce and PHP versions, and whether HPOS is enabled,
- the **user role** an attacker needs (logged out, customer, Shop Manager, …),
- what the attacker can achieve,
- steps to reproduce or a proof of concept,
- your name or handle for the credits (optional).

## What happens next

| Step | Target |
|---|---|
| Acknowledgement | within 3 working days |
| Confirmation and severity (CVSS) | within 7 days |
| Fix released | within 30 days for High/Critical, 90 days otherwise |
| Public advisory and CVE (via GitHub) | after the fix is released, coordinated with you |

You'll be credited in the advisory and changelog unless you prefer otherwise.

## Scope

**In scope**: this plugin's code, for example:

- access to profit data or settings by users without the required capability,
- CSRF, XSS, SQL injection, open redirects, CSV/formula injection,
- data loss or corruption caused by the plugin,
- the plugin reading or modifying data it shouldn't (orders, other plugins' data).

**Out of scope:**

- issues that require the attacker to already be an Administrator (e.g. an admin entering script in a field and attacking themselves). Administrators can run code by design in WordPress,
- vulnerabilities in WordPress, WooCommerce or other plugins (report those to their maintainers),
- missing security headers, rate limiting or best-practice findings without a demonstrated impact,
- social engineering, physical attacks, denial of service by flooding.

## Safe harbour

Good-faith research that follows this policy is welcome. Please only test on sites you own or have permission to test, don't access or change other people's data, and give a reasonable time to fix before disclosing. We won't pursue legal action for research done this way.

## How the plugin is secured

See [docs/security.md](docs/security.md) for the security model, the controls in place, the audit log and how each release is verified.
