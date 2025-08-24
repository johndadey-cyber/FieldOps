# Availability Manager Troubleshooting Checklist

Use this checklist to verify session handling and CSRF protection when working with the availability manager.

1. **Start the local server**
   - Run `make serve` or `php -S 127.0.0.1:8010 -t public` from the project root.
   - Open [http://127.0.0.1:8010/availability_manager.php](http://127.0.0.1:8010/availability_manager.php) in your browser.

2. **Inspect cookies and CSRF token**
   - In the browser console, run `document.cookie` and note the session identifier.
   - View page source and confirm the `CSRF` JavaScript constant matches the hidden `csrf_token` field in the form.

3. **Perform an availability action**
   - Make a change such as adding or copying availability.
   - In DevTools, capture the network request and response for this action.

4. **Review server logs**
   - Check the logs in `logs/` (for example, `logs/availability_error.log`).
   - Locate entries corresponding to your request and record the session ID and token.

5. **Compare session IDs**
   - Verify the session ID in the server logs matches the browser cookie captured earlier.

Share this checklist with the team to help standardize troubleshooting steps.

## Module Structure

The availability manager JavaScript has been split into ES modules located in `public/js`:

- `availability-fetch.js` – helpers to request availability and job data from the API.
- `list-render.js` – renders the availability list and exposes alert helpers.
- `calendar-render.js` – initialises FullCalendar and fills it with availability, overrides, and jobs.
- `override-handlers.js` – opens override modals and handles deletion logic.
- `availability-manager.js` – page bootstrap that wires UI events and combines the above modules.

Use these modules when extending the page to keep concerns separated and logic testable.

## Calendar tab rendering

1. **Verify the `#calendar-tab` listener**
   - In the browser console, run `getEventListeners(document.querySelector('#calendar-tab'))` and confirm a `click` handler is registered.
   - Click the Calendar tab and ensure the handler triggers by watching for network requests or console logs.

2. **Look for the loading indicator**
   - A spinner should appear while availability data loads. If the indicator never shows, the listener or fetch may be failing.

3. **Check debug logs**
   - Open the console and look for messages tagged `calendar` to trace rendering. Enable verbose output with `localStorage.debug = 'calendar:*'` before reloading.

## Upgrading core schema

Run the core schema migration after pulling updates to ensure new columns are present:

```bash
php bin/ensure_core_schema.php
```

When the `type` column is missing from `employee_availability_overrides`, the script reports:

```
[..] Adding `type` column to employee_availability_overrides ...
```

Include this command in deployment scripts so production databases receive the migration and avoid runtime errors.
