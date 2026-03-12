# CEFI Reservation System — API Documentation

> Base URL: `http://localhost/cefi_reservation/api/`  
> Authentication: All protected endpoints require either an **admin session** cookie OR the `X-API-Key` header.

---

## Authentication

### API Key (for n8n / Chatbot)
```
X-API-Key: cefi_api_2026_secure_key
```
Alternatively pass as a query param: `?api_key=cefi_api_2026_secure_key`

> ⚠️ Change this key in `config/api_auth.php` → `CEFI_API_KEY` before going to production.

---

## Endpoints

### 1. Check Availability
**`POST /check_availability.php`** 🔒 Auth required

Check if a facility is available for a given date and time.

**Request** (JSON or form-encoded):
| Field | Type | Required |
|-------|------|----------|
| `facility_id` | int | ✅ |
| `reservation_date` | string `YYYY-MM-DD` | ✅ |
| `start_time` | string `HH:MM` | ✅ |
| `end_time` | string `HH:MM` | ✅ |

**Response:**
```json
{ "status": "available", "message": "Facility is available." }
{ "status": "unavailable", "message": "Facility already booked for this time." }
```

---

### 2. Get Facility Details
**`GET /get_facility_details.php?id={id}`** 🔒 Auth required

Returns full details for a single facility.

**Response:**
```json
{
  "status": "success",
  "facility": {
    "id": 1, "name": "Gymnasium", "capacity": 200,
    "price_per_hour": 500.00, "price_per_day": 3000.00,
    "open_time": "07:00", "close_time": "20:00",
    "advance_days_required": 2,
    "min_duration_hours": 1, "max_duration_hours": 8,
    "allowed_days": "1,2,3,4,5,6", "image": "gym.jpg"
  }
}
```

---

### 3. List Facilities
**`GET /list_facilities.php`** 🔒 Auth required

Returns all facilities. Designed for chatbot browsing.

**Query params:**
| Param | Default | Values |
|-------|---------|--------|
| `status` | `AVAILABLE` | `AVAILABLE`, `MAINTENANCE`, `CLOSED`, `ALL` |

**Response:**
```json
{
  "success": true, "count": 3,
  "facilities": [
    {
      "id": 1, "name": "Gymnasium", "status": "AVAILABLE",
      "price_per_hour": 500.0, "price_per_day": 3000.0,
      "open_time": "07:00", "close_time": "20:00",
      "allowed_days": ["Mon","Tue","Wed","Thu","Fri","Sat"]
    }
  ]
}
```

---

### 4. Create Reservation
**`POST /create_reservation.php`** 🔒 Auth required

Submit a new reservation (status will be `PENDING`, awaiting admin approval).

**Request** (JSON or form-encoded):
| Field | Type | Required |
|-------|------|----------|
| `fb_name` | string | ✅ |
| `fb_user_id` | string | ✅ |
| `facility_id` | int | ✅ |
| `reservation_date` | string `YYYY-MM-DD` | ✅ |
| `start_time` | string `HH:MM` | ✅ |
| `end_time` | string `HH:MM` | ✅ |
| `purpose` | string | — |
| `user_email` | string | — |
| `user_phone` | string | — |
| `user_type` | string | — (`Student`, `Staff`, `Outside`) |
| `num_attendees` | int | — |

**Response:**
```json
{
  "success": true,
  "reservation_id": 42,
  "message": "Reservation submitted. Pending admin approval.",
  "status": "PENDING"
}
```

---

### 5. Get Reservation Status
**`GET /get_reservation_status.php`** 🔒 Auth required

Check the status of a reservation by ID or Facebook user ID.

**Query params** (one required):
| Param | Description |
|-------|-------------|
| `id` | Reservation ID |
| `fb_user_id` | Returns the latest reservation for this user |

**Response:**
```json
{
  "success": true,
  "reservation": {
    "id": 42, "status": "APPROVED",
    "facility": "Gymnasium",
    "date": "2026-03-15",
    "start_time": "09:00", "end_time": "12:00",
    "cost": 1500.00,
    "reject_reason": null,
    "cancel_reason": null
  }
}
```

---

### 6. Webhook Notify  *(for n8n)*
**`POST /webhook_notify.php`** 🔒 Auth required

Called by n8n to confirm that a reservation event (approval/rejection) notification has been received. Logs to audit trail.

**Request** (JSON):
| Field | Type | Required |
|-------|------|----------|
| `event` | string | ✅ (`APPROVED`, `REJECTED`, `CANCELLED`, `PENDING`, `EXPIRED`) |
| `reservation_id` | int | ✅ |
| `fb_user_id` | string | — |
| `message` | string | — (Messenger message text) |

**Response:**
```json
{
  "success": true,
  "received_at": "2026-03-12T15:44:00+08:00",
  "reservation_id": 42,
  "event": "APPROVED",
  "fb_name": "Juan Dela Cruz",
  "note": "Webhook logged. n8n should now send the Messenger notification."
}
```

---

## n8n Integration Example

**Workflow: Notify user when reservation is approved**

1. **Trigger**: Webhooks / Poll CEFI API every 5 min for status changes (or use a MySQL trigger)
2. **Action**: Call `GET /get_reservation_status.php?id={{reservation_id}}`  
3. **Condition**: If `status == "APPROVED"`
4. **Action**: Send Facebook Messenger message via Facebook for Developers API
5. **Confirm**: Call `POST /webhook_notify.php` with the event details

---

## Error Responses

| Code | Meaning |
|------|---------|
| `400` | Bad request — missing or invalid parameters |
| `401` | Unauthorized — missing or invalid API key |
| `403` | Forbidden — CSRF or session issue |
| `404` | Not found |
| `405` | Method not allowed |
| `500` | Server/database error |

All errors return:
```json
{ "success": false, "error": "Description of the error." }
```
