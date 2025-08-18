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
