# Login Debug Log

The login API writes debug information to `logs/login_debug.log`. Each entry includes the identifier, client IP, whether the CSRF token was valid, and if a matching user was found. Passwords are redacted.

Check this file when troubleshooting login issues.
