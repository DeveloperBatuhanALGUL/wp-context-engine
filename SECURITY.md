# Security Policy

## Supported Versions

| Version | Supported |
|---|---|
| 0.1.x | yes |

## Reporting a Vulnerability

Do not open a public GitHub issue for security vulnerabilities.

Send a detailed report to: **batuhanalgul@proton.me**

Include: affected version, description, reproduction steps, potential impact, and suggested fix if any.

You will receive a response within 72 hours. If the issue is confirmed, a patch will be released as soon as possible and you will be credited in the changelog unless you prefer otherwise.

## Security Design Notes

- OpenAI API key is never stored in the database — it must be defined as a constant in wp-config.php
- The public /ask endpoint is rate limited per IP (10 requests per 60 seconds)
- Cloudflare IP validation is opt-in via WPCE_TRUST_CLOUDFLARE constant
- All REST inputs are sanitized and length-capped before processing
- Vector ranking is bounded at 2000 chunks to prevent memory exhaustion
- No user data is sent to external services except the embedding text sent to OpenAI
