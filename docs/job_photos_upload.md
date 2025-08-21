# Job Photo Upload Limits

The `/api/job_photos_upload.php` endpoint enforces limits to protect server
resources:

- **Maximum 50 photos** per request.
- **Maximum combined size of 20 MB** across all files.

Requests exceeding these thresholds are rejected with JSON error responses:

- More than 50 photos ⇒ HTTP 422 with `"error": "Too many photos (max 50)"`.
- Total size above 20 MB ⇒ HTTP 413 with `"error": "Total upload size exceeds 20MB"`.

Clients should break large uploads into smaller batches that respect these
limits.

