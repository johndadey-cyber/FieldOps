# Availability Event Payload

The availability API returns recurring availability windows as simple event
objects for the requested week.

Example request:

```
GET /api/availability/index.php?employee_id=123&week_start=2024-05-20
```

Example response:

```
{
  "ok": true,
  "availability": [
    { "id": 1, "start": "2024-05-20T09:00", "end": "2024-05-20T17:00" },
    { "id": 2, "start": "2024-05-21T09:00", "end": "2024-05-21T17:00" }
  ],
  "overrides": []
}
```

Each object inside `availability` contains:

* `id` – numeric identifier of the availability window.
* `start` – ISO 8601 start timestamp within the selected week.
* `end` – ISO 8601 end timestamp within the selected week.

This format allows clients to use the payload directly in calendar widgets or
other scheduling tools without additional processing.

