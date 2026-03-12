# CEFI Reservation System — Improvements & Integration Readiness Plan

Build and finalize the core reservation system with all improvements from [improvements.txt](file:///c:/xampp/htdocs/cefi_reservation/improvements.txt), while architecting the API layer for seamless future integration with **n8n** and **Facebook for Developers (Chatbot)**.

---

## User Review Required

> [!IMPORTANT]
> **Phased approach**: This plan is split into 4 phases (Critical → High → Medium → Integration). Each phase can be done in order. I recommend doing **Phase 1 first** since those are security/stability issues that could cause crashes or vulnerabilities.

> [!WARNING]
> **Phase 1, Item 1 (database.sql update)**: The current [database.sql](file:///c:/xampp/htdocs/cefi_reservation/database.sql) is severely out of sync with the live database. The updated file is for **fresh installs only** — it will NOT be run against your existing database. Your live data stays untouched.

---

## Proposed Changes

### Phase 1: Critical Fixes

---

#### Item 1: Update [database.sql](file:///c:/xampp/htdocs/cefi_reservation/database.sql)

#### [MODIFY] [database.sql](file:///c:/xampp/htdocs/cefi_reservation/database.sql)

The SQL file is missing ~15+ columns that the code depends on. A fresh deployment would crash. Update the schema to match the live database:

**`facilities` table** — add columns:
- `price_per_hour`, `price_per_day`, `open_time`, `close_time`
- `advance_days_required`, `min_duration_hours`, `max_duration_hours`
- `allowed_days`, `image`

**`reservations` table** — add columns:
- `duration_hours`, `total_cost`, `price_type`
- `reject_reason`, `cancel_reason`
- `user_email`, `user_phone`
- `reservation_type` (ENUM: `ONLINE`, `WALK_IN`)
- `user_type`, `id_number`, `host_person`
- `verification_deadline`
- Update `status` ENUM to include: `PENDING`, `APPROVED`, `REJECTED`, `CANCELLED`, `EXPIRED`, `ON_HOLD`, `PENDING_VERIFICATION`, `WAITLISTED`

---

#### Item 2: Add CSRF Token Protection

#### [NEW] [csrf.php](file:///c:/xampp/htdocs/cefi_reservation/config/csrf.php)

Create a CSRF helper with two functions:
- `generateCSRFToken()` — generates and stores a token in `$_SESSION['csrf_token']`
- `validateCSRFToken($token)` — validates the submitted token and regenerates

#### [MODIFY] All form pages — Add `<input type="hidden" name="csrf_token">` and server-side validation:

| File | Forms affected |
|------|---------------|
| [reservations/index.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/index.php) | Reject, Cancel, Conflict resolve |
| [reservations/create.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/create.php) | Create reservation |
| [reservations/edit.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/edit.php) | Edit reservation |
| [reservations/walkin_create.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/walkin_create.php) | Walk-in reservation |
| [facilities/create.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/create.php) | Create facility |
| [facilities/edit.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/edit.php) | Edit facility |
| [auth/login.php](file:///c:/xampp/htdocs/cefi_reservation/auth/login.php) | Login form |

---

#### Item 3: Convert DELETE & CANCEL to POST

#### [MODIFY] [reservations/delete.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/delete.php)
- Change from `$_GET["id"]` to `$_POST["id"]`
- Add CSRF validation
- Return 405 for GET requests

#### [MODIFY] [facilities/delete.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/delete.php)
- Same GET→POST conversion + CSRF check

#### [MODIFY] [reservations/index.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/index.php)
- Update delete modal: change `<a href="delete.php?id=X">` to a `<form method="POST" action="delete.php">` with hidden fields
- Update approve action from GET to POST (currently `?approve=X`)

#### [MODIFY] [facilities/index.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/index.php)
- Update delete links to POST forms

#### [MODIFY] [dashboard.php](file:///c:/xampp/htdocs/cefi_reservation/dashboard.php)
- Update any delete links to POST forms

---

### Phase 2: High Priority

---

#### Item 4: Add Pagination to Reservations

#### [MODIFY] [reservations/index.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/index.php)

- Add `$page` and `$per_page` (default 15) variables
- Add `COUNT(*)` query for total records
- Add `LIMIT/OFFSET` to the main `SELECT` query
- Render pagination controls (prev/next + page numbers) below the table

#### [MODIFY] [style/reservations.css](file:///c:/xampp/htdocs/cefi_reservation/style/reservations.css)
- Add pagination styling (consistent with existing design system)

---

#### Item 5: Add Search & Filter to Reservations

#### [MODIFY] [reservations/index.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/index.php)

Add a filter bar above the table with:
- Text search (name)
- Status dropdown (All / Pending / Approved / Rejected / Cancelled)
- Facility dropdown (populated from DB)
- Date range picker (from/to)
- Filters persist via GET params and work with pagination

---

#### Item 6: Fix XSS in Facility Error Display

#### [MODIFY] [facilities/create.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/create.php)

Line 96: Change from:
```php
echo "<p class='error'>$error</p>";
```
To:
```php
echo "<p class='error'>" . htmlspecialchars($error) . "</p>";
```

#### [MODIFY] [facilities/edit.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/edit.php)
- Same `htmlspecialchars()` fix for `$error` output

---

#### Item 7: Remove Dead Code

#### [MODIFY] [reservations/index.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/index.php)

Line 21: Remove the stray prepared statement:
```php
$conn->prepare("UPDATE reservations SET status = 'APPROVED' WHERE id = ?")->bind_param("i", $approve_id) || true;
```
This executes, gets discarded, and does nothing useful (the actual UPDATE is on lines 22–24).

---

### Phase 3: Medium Priority

---

#### Item 8: Add Session Timeout

#### [MODIFY] [config/db.php](file:///c:/xampp/htdocs/cefi_reservation/config/db.php)

Add a session management block that runs at `session_start()`:
- Set `$_SESSION['last_activity']` on each request
- If idle > 30 minutes (configurable), destroy session and redirect to login
- This will be included via a new shared include or added to the existing auth check pattern

#### [NEW] [config/session.php](file:///c:/xampp/htdocs/cefi_reservation/config/session.php)
- Centralized session management: timeout check, CSRF integration, session config
- All pages include this instead of calling `session_start()` directly

---

#### Item 9: Protect API Endpoints

#### [MODIFY] [api/check_availability.php](file:///c:/xampp/htdocs/cefi_reservation/api/check_availability.php)
#### [MODIFY] [api/get_facility_details.php](file:///c:/xampp/htdocs/cefi_reservation/api/get_facility_details.php)
#### [MODIFY] [api/create_reservation.php](file:///c:/xampp/htdocs/cefi_reservation/api/create_reservation.php)

Add dual-auth: accept either **session auth** (admin panel AJAX calls) or **API key** auth (for chatbot/n8n). This follows the existing pattern in [get_chatbot_calendar.php](file:///c:/xampp/htdocs/cefi_reservation/api/get_chatbot_calendar.php).

#### [NEW] [config/api_auth.php](file:///c:/xampp/htdocs/cefi_reservation/config/api_auth.php)
- Shared API authentication helper
- Supports: session auth, API key (header `X-API-Key` or query param `api_key`)
- Returns `401` or `403` JSON response on failure
- This prepares the system for n8n and Facebook chatbot integration

---

#### Item 10: Add Success Feedback After Creating a Facility

#### [MODIFY] [facilities/create.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/create.php)
- Line 55: Change `header("Location: index.php")` to `header("Location: index.php?msg=Facility created successfully.")`

#### [MODIFY] [facilities/index.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/index.php)
- Add success message display (matching the pattern already used in reservations)

---

#### Item 11: Add CSV Export

#### [NEW] [reservations/export.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/export.php)
- Export reservations as CSV with the current filter applied
- Headers: Name, Facility, Date, Time, Duration, Cost, Status, Type, Contact

#### [NEW] [audit_export.php](file:///c:/xampp/htdocs/cefi_reservation/audit_export.php)
- Export audit logs as CSV
- Headers: Timestamp, Admin, Action, Entity Type, Entity ID, Details

#### [MODIFY] [reservations/index.php](file:///c:/xampp/htdocs/cefi_reservation/reservations/index.php)
- Add "Export CSV" button next to filter bar

#### [MODIFY] [audit_trail.php](file:///c:/xampp/htdocs/cefi_reservation/audit_trail.php)
- Add "Export CSV" button

---

#### Item 12: Make Dashboard Stat Cards Clickable

#### [MODIFY] [dashboard.php](file:///c:/xampp/htdocs/cefi_reservation/dashboard.php)
- Wrap stat cards and status cards in `<a>` tags linking to filtered views:
  - "Pending" → `reservations/index.php?status=PENDING`
  - "Approved" → `reservations/index.php?status=APPROVED`
  - "Rejected" → `reservations/index.php?status=REJECTED`
  - "Cancelled" → `reservations/index.php?status=CANCELLED`
  - "Total Facilities" → [facilities/index.php](file:///c:/xampp/htdocs/cefi_reservation/facilities/index.php)
  - "Today's Reservations" → `reservations/index.php?date_from=TODAY&date_to=TODAY`

---

### Phase 4: n8n & Facebook Integration Readiness

> [!NOTE]
> This phase ensures the API layer is **ready** for external integration. No n8n or Facebook connection is made yet — only the endpoints and contracts are built.

---

#### Item 13: Unified Chatbot API Layer

#### [MODIFY] [api/create_reservation.php](file:///c:/xampp/htdocs/cefi_reservation/api/create_reservation.php)
- Add API key auth (using `config/api_auth.php`)
- Accept JSON body in addition to form-encoded POST
- Include all new fields (`user_email`, `user_phone`, `user_type`, etc.)
- Return structured JSON with `reservation_id` on success

#### [MODIFY] [api/check_availability.php](file:///c:/xampp/htdocs/cefi_reservation/api/check_availability.php)
- Add API key auth
- Accept JSON body

#### [NEW] [api/get_reservation_status.php](file:///c:/xampp/htdocs/cefi_reservation/api/get_reservation_status.php)
- Query reservation status by `id` or `fb_user_id`
- Returns: status, facility name, date/time, cost, reject/cancel reason
- API key required

#### [NEW] [api/list_facilities.php](file:///c:/xampp/htdocs/cefi_reservation/api/list_facilities.php)
- Returns all available facilities with pricing, hours, capacity
- API key required
- This enables the chatbot to present facility options to users

---

#### Item 14: Webhook Endpoint for n8n Notifications

#### [NEW] [api/webhook_notify.php](file:///c:/xampp/htdocs/cefi_reservation/api/webhook_notify.php)
- Outbound webhook configuration table/settings
- When a reservation is approved/rejected, this endpoint can be called by n8n to trigger Facebook Messenger notifications
- Stores webhook URLs in a `webhook_config` table or `config/webhooks.php`
- Future: n8n will poll or receive webhooks from this endpoint

#### [MODIFY] [database.sql](file:///c:/xampp/htdocs/cefi_reservation/database.sql)
- Add `webhook_config` table: `id`, `event_type`, `url`, `is_active`, `created_at`

---

#### Item 15: API Documentation

#### [NEW] [API_DOCS.md](file:///c:/xampp/htdocs/cefi_reservation/API_DOCS.md)
- Document all API endpoints with request/response contracts
- Include authentication requirements
- Include example n8n webhook payloads
- Include Facebook Messenger integration notes

---

## Verification Plan

### Automated / Browser Testing

Since this is a PHP + MySQL system without a test framework, verification will be done via browser testing:

1. **CSRF Protection**: Attempt form submissions without CSRF token → expect rejection
2. **POST Delete**: Try accessing `delete.php?id=X` via GET → expect 405 or redirect
3. **Pagination**: Create sufficient test records, verify page navigation works
4. **Search/Filter**: Test each filter individually and in combination
5. **XSS**: Enter `<script>alert(1)</script>` in facility name → verify it's escaped
6. **Session Timeout**: Login, wait 30+ min (or lower timeout for testing), verify redirect to login
7. **API Auth**: Call unprotected endpoints without API key → expect 401/403
8. **CSV Export**: Export data and verify file opens correctly in Excel/Sheets
9. **Clickable Stats**: Click each stat card → verify correct filtered view

### Manual Verification

**Please verify after each phase:**
1. Login to the admin panel
2. Create/edit/delete a reservation (tests CSRF + POST)
3. Browse the reservations list with filters and pagination
4. Create a facility and check for success message
5. Check session expires after timeout
6. Test API endpoints with and without API key using browser/Postman
7. Export CSV from reservations and audit trail
8. Click dashboard stat cards and verify they link to the correct filtered views
