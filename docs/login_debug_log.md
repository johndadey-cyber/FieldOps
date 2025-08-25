# Login Debug Log

The login API writes debug information to `logs/login_debug.log`. Each entry includes the identifier, client IP, whether the CSRF token was valid, and if a matching user was found. Passwords are redacted.

When CSRF verification fails, the sanitized payload is logged separately and a brief "CSRF validation failed" note is appended to `login_debug.log`. The API returns a `422 Unprocessable Entity` status for these failures.

Check this file when troubleshooting login issues.
