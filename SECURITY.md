# Security Policy

## Supported Versions

AI CaseLab is currently in active initial development (pre-1.0). There are no
tagged releases yet; security fixes apply to the `main` branch only.

| Version | Supported |
|---|---|
| `main` (pre-1.0, active development) | ✅ |

## Reporting a Vulnerability

If you discover a security vulnerability, **please do not open a public
GitHub issue.** Instead, report it privately by emailing:

**lulu502aldossri@gmail.com**

Please include:
- A description of the vulnerability and its potential impact.
- Steps to reproduce, or a proof of concept if available.
- Any suggested remediation, if you have one.

You can expect an initial acknowledgement within a few days. Once a fix is
available, it will be released and credited to the reporter (unless you
prefer to remain anonymous).

## Scope

As an educational/training platform, AI CaseLab does not process real
production data or payment information. Reports related to authentication,
authorization bypass (e.g. a student accessing another student's attempt or
an admin-only route), and injection vulnerabilities (SQL, XSS) are of
particular interest given the platform's use of user-submitted investigation
notes and diagnoses.
