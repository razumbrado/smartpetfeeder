# Smart Pet Feeder — API (prepared for ESP32)

All endpoints are **live now** and read/write the same MySQL tables the web app
uses. They are also the contract the future ESP32 firmware will use.
**No endpoint drives real hardware** — the ESP32 will do that and report results back.

Base URL (XAMPP default): `http://localhost/smartpetfeeder/api`

## Authentication

Device endpoints require a shared key from `config/secrets.php` → `DEVICE_API_KEY`.

Send it as a header (preferred):

```
X-Device-Key: CHANGE-ME-ESP32-SECRET-KEY
```

or as `?device_key=...` (GET) / `device_key` form field (POST).

`GET /api/schedules.php` and `GET /api/food-level.php` also accept a logged-in
browser session (so the web UI can call them).

---

## Endpoints

### `GET /api/device/status.php`
Current device + food-level snapshot. No auth needed for the web app.

### `POST /api/device/heartbeat.php`  *(device key)*
ESP32 check-in. Call every 30–60 s.
```json
{ "firmware": "1.0.0", "food_grams": 1440 }
```
`food_grams` (load cell + HX711 weight) is optional; `food_level` (percent) is
still accepted as a fallback. Response includes `pending_commands` count.

### `GET /api/feeding/pending.php`  *(device key)*
Feed commands the device must dispense (`status = pending_hardware`).
Each command is re-checked against the **latest load-cell weight** first: any
command the hopper can't cover is marked `failed` ("Insufficient food"), raises
an alert, and is **not returned** — so the servo never runs for it. If there is
no fresh reading, no commands are returned until one arrives.
```json
{ "ok": true, "count": 1,
  "commands": [ { "command_id": 12, "portions": 2, "grams": 80, "source": "manual", "created_at": "..." } ] }
```

### `POST /api/feeding/ack.php`  *(device key)*
Report the outcome after dispensing.
```json
{ "command_id": 12, "status": "dispensed", "food_grams": 1240 }
```
`status`: `command_sent` | `dispensed` | `failed`.
`food_grams` (optional, recommended): a fresh load-cell weight taken **after**
dispensing, so the dashboard's remaining grams / percentage update right away.
`dispensed` sets `dispensed_at` and raises a "Feeding completed" alert.

### `GET /api/schedules.php`  *(session or device key)*
All feeding schedules + the next upcoming meal. The ESP32 can cache these and
run them from its RTC.

### `GET /api/food-level.php`  *(session or device key)*
Latest stored food level: `percent`, `available_grams` (load-cell weight, or
percent × capacity for manual values), `capacity_grams`, `from_load_cell`,
`is_fresh`. The Feed page uses this right before every Feed Now.

### `POST /api/food-level.php`  *(device key)*
Load cell + HX711 reading from the ESP32: the **net weight of food in the
hopper**, in grams.
```json
{ "grams": 1360 }
```
The server converts it to a percentage of `HOPPER_CAPACITY_GRAMS`
(`config/config.php`, default 2000 g → 1360 g = 68%). Negative values (HX711
drift around zero) are stored as 0. `{ "percent": 68 }` is still accepted as a
fallback. Levels ≤ `LOW_FOOD_THRESHOLD` (20%) auto-raise a Low Food alert.

**HX711 setup notes:** tare with the empty hopper mounted, calibrate the scale
factor with a known weight, and average several readings (e.g. 10) before
sending to smooth out noise from the servo/vibration.

---

## ESP32 firmware checklist (later)

1. On boot: `POST /api/device/heartbeat.php` with firmware version.
2. Loop every 30–60 s:
   - `POST /api/device/heartbeat.php` (+ current `food_grams` from the load cell / HX711).
   - `GET /api/feeding/pending.php` → for each command: **read the load cell
     again; if the weight is below `grams`, do NOT move the servo** and ack
     `failed`. Otherwise drive the servo to dispense `grams`, re-read the load
     cell, then `POST /api/feeding/ack.php` with `dispensed` + `food_grams`.
3. Once per hour (or on change): `GET /api/schedules.php` and update the local table.
4. When the device is reliably checking in, set `HARDWARE_CONNECTED = true` in
   `config/config.php` so the UI shows the feeder as **Online** and new feed
   commands start at `command_sent` instead of `pending_hardware`.

### Where hardware code will be added
- **Servo control**: ESP32 firmware only (not in this repo). Triggered by the
  result of `GET /api/feeding/pending.php`.
- **Food-level read (load cell + HX711)**: ESP32 firmware → `POST /api/food-level.php` with `grams`.
- **PHP side is already done** — no new PHP files are required for basic
  integration, only the config flag flip above.
