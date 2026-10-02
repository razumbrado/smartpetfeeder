# Smart Pet Feeder — Web-Based Feeding Control & Monitoring

A complete PHP/MySQL web application for a pet owner/admin to manage and monitor a
smart pet feeder. **The hardware (ESP32 + servo + load cell/HX711 food-level sensor) is not built
yet.** The web side is fully functional; every hardware action is recorded as
**"Pending Hardware"** and the API is prepared for future ESP32 integration.

Nothing in this system claims the feeder is physically online or that food was
actually dispensed until a real device reports back.

---

## Tech stack

HTML · CSS · vanilla JavaScript · PHP 8 · MySQL / MariaDB (XAMPP)

---

## Folder structure

```
smartpetfeeder/
├── index.php                  # entry — redirects to login or dashboard
├── config/
│   ├── config.php             # DB credentials, constants, HARDWARE_CONNECTED flag
│   └── .htaccess              # blocks direct web access
├── includes/
│   ├── auth.php               # session login/logout, CSRF
│   ├── functions.php          # food level, schedules, alerts, dashboard stats
│   ├── layout_top.php         # page shell + dark-teal sidebar + topbar
│   ├── layout_bottom.php
│   └── .htaccess
├── auth/
│   ├── login.php              # admin login (email OR username + password, MySQL-validated)
│   ├── forgot_password.php    # forgot password: send OTP to email
│   ├── verify_reset_otp.php   #   … check the 6-digit code
│   ├── reset_password.php     #   … save the new password
│   └── logout.php
├── pages/
│   ├── dashboard.php          # food dispensed today, last fed, next meal, food level, recent activity
│   ├── feed.php               # portion picker, Feed Now, Quick Feed, Ready to Feed, schedules
│   ├── schedule.php           # add / edit / delete / enable-disable feeding schedules
│   ├── alerts.php             # low food, reminders, pending, completed, hardware not connected
│   └── important.php          # system status, manual food-level demo, ESP32 integration guide
├── actions/                   # form handlers (POST only, CSRF-protected)
│   ├── feed_now.php           # records a feeding command as "Pending Hardware"
│   ├── schedule_save.php      # add + edit
│   ├── schedule_delete.php
│   ├── alert_read.php
│   ├── food_level_set.php     # manual/demo food level (replaced by sensor later)
│   ├── profile_update.php     # My Account → Edit Profile
│   └── password_change.php    # My Account → Change Password
├── api/                       # LIVE endpoints, also the ESP32 contract
│   ├── index.php              # endpoint list
│   ├── _helpers.php           # JSON output, device-key auth
│   ├── device/
│   │   ├── heartbeat.php      # POST — ESP32 check-in
│   │   └── status.php         # GET  — device + food-level snapshot
│   ├── feeding/
│   │   ├── pending.php        # GET  — queued feed commands for the device
│   │   └── ack.php            # POST — device reports command_sent / dispensed / failed
│   ├── schedules.php          # GET  — feeding schedules + next meal
│   ├── food-level.php         # GET (read) / POST (ESP32 sensor writes)
│   └── README.md              # API reference + ESP32 firmware checklist
├── assets/
│   ├── css/style.css
│   └── js/main.js
└── database/
    └── smartpetfeeder.sql     # schema + sample data
```

---

## Setup with XAMPP

1. **Copy the project** so it lives at `C:\xampp\htdocs\smartpetfeeder`.
   (If you use a different folder name, update `BASE_URL` in `config/config.php`.)

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Create the database.** Either:
   - phpMyAdmin → *Import* → choose `database/smartpetfeeder.sql` → *Go*, **or**
   - command line:
     ```
     C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\smartpetfeeder\database\smartpetfeeder.sql
     ```

4. **Create your private settings file.** Copy `config/secrets.example.php`
   to `config/secrets.php` and fill it in (database login — XAMPP defaults
   `root` / empty password are already there — the ESP32 `DEVICE_API_KEY`, and
   the Gmail SMTP settings). `config/secrets.php` is in `.gitignore`, so your
   passwords and keys are never pushed to GitHub.

5. **Open** `http://localhost/smartpetfeeder/`

6. **Log in:**
   | Field | Value |
   |-------|-------|
   | Username or Email | `admin` (or `admin@petfeeder.local`) |
   | Password | `admin123` |

   Change this password after first use — click your name in the top-right corner
   of any page → **My Account** → **Change Password** (or **Edit Profile** to
   update your first name, last name and email). No direct DB edit needed.

7. **Forgot password email (optional):** the login page's *Forgot your password?*
   sends a 6-digit code (valid 1 minute) by email, then lets the user set a new
   password. Fill in the `SMTP_*` values in `config/secrets.php` — for Gmail, use
   your address plus a 16-character **App Password** (Google Account → Security
   → 2-Step Verification → App passwords). Until then the page shows
   "Email sending is not set up yet". PHPMailer is bundled in `vendor/phpmailer/`.

---

## How the "no hardware" model works

| Concern | Current behavior |
|---|---|
| **Feed Now / Quick Feed** | Inserts a row in `feeding_records` with status **`pending_hardware`** ("Pending Hardware"). No motor is driven. Shows *"Feeding command recorded."* |
| **Dashboard "Food Dispensed Today"** | Sum of grams of **commands recorded** today. Increments on every Feed Now. |
| **"Last Fed"** | Time of the most recent recorded command. |
| **Recent Feeding Activity** | Lists recent `feeding_records` with their status badge. |
| **Food Level** | Read from the `food_level` table (initial sample **75%**). Editable on the *Important* page for demo. Low Food alert at ≤ 20%. |
| **Device status** | Always **"Not Connected"** while `HARDWARE_CONNECTED = false`, even if something calls the heartbeat endpoint. |
| **Alerts** | Low food, schedule reminders, feeding pending, feeding completed, hardware not connected. Always shows *"ESP32 is not connected."* |

### Feeding status lifecycle

```
pending_hardware  →  command_sent  →  dispensed
        │                                 
        └────────────────→  failed
```

Only the ESP32 (via `POST /api/feeding/ack.php`) can move a record to
`dispensed`. The UI never shows "Dispensed Successfully" on its own.

---

## Database tables

- **users** — admin / owner accounts (`first_name`, `last_name`, unique `username` & `email`, `created_at`).
- **device_status** — one row per feeder; `status` = `not_connected` / `online` / `offline`, `last_seen`.
- **food_level** — history of readings (`level_percent`, `food_grams` = load-cell weight or NULL, `source` = sample/manual/sensor, `created_at`), FK → `device_status`.
- **feeding_schedules** — `feed_date`, `feed_time` (one feeding on a specific date; existing DBs: run `database/migrate_schedule_dates.sql`), `portions`, `grams`, `enabled`; FK → `users`.
- **feeding_records** — every feed command: `portions`, `grams`, `source` (manual/quick/schedule), `status`, `note`, `created_at`, `dispensed_at`; FKs → `users`, `feeding_schedules`.
- **alerts** — `type`, `severity`, `title`, `message`, `is_read`, `created_at`.

Portion model: **1 portion = 40 g** → 2 = 80 g, 3 = 120 g, 4 = 160 g (`GRAMS_PER_PORTION` in config).

---

## Future ESP32 integration

The PHP API endpoints already exist under `/smartpetfeeder/api/`. The ESP32
firmware will talk to these:

| Endpoint | Method | Used by | Purpose |
|---|---|---|---|
| `/api/device/heartbeat.php` | POST | ESP32 → server | Register device, report firmware/IP, mark it Online |
| `/api/device/status.php` | GET | Web / ESP32 | Read current device status |
| `/api/feeding/pending.php` | GET | ESP32 → server | Fetch queued feed commands to dispense (re-checked against the latest load-cell weight) |
| `/api/feeding/ack.php` | POST | ESP32 → server | Report result: `command_sent` / `dispensed` / `failed` (+ fresh `food_grams`) |
| `/api/schedules.php` | GET | ESP32 → server | Download feeding schedules to run locally |
| `/api/food-level.php` | GET / POST | Web (GET) / ESP32 (POST) | Read the food level, or submit the load-cell weight (grams) |

Device authentication: send header `X-Device-Key: <DEVICE_API_KEY>` (configured
in `config/secrets.php`).

Planned data flow:

```
Feeding:     Web App → PHP API (feeding/pending) → ESP32 → Servo Motor → Food Dispensed
                              ↑                                   │
                              └─────── PHP API (feeding/ack) ──────┘

Food level:  Load cell → HX711 → ESP32 → PHP API (food-level POST, grams) → MySQL → Web App
```

**Everything on the PHP side is already in place.** See `api/README.md` for the
full endpoint reference and firmware checklist. To go live:

1. Flash the ESP32 to call the endpoints in `api/` using the `DEVICE_API_KEY`
   header (`X-Device-Key`). Set a real key in `config/secrets.php` first.
2. Point it at `http://<your-pc-ip>/smartpetfeeder/api/`.
3. When it is reliably checking in, set `HARDWARE_CONNECTED = true` in
   `config/config.php`. The UI then reports the feeder as **Online** and new
   feed commands start at `command_sent`.

No web/PHP code changes are required for basic integration — only the config
flag and the firmware.
