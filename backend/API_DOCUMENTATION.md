# Hima Backend API Documentation

## Base URL
```
http://127.0.0.1:8000/api
```

## Authentication
All protected routes require:
```
Authorization: Bearer {token}
Accept: application/json
```

---

## Auth Endpoints
| Method | URL     | Auth | Description |
|--------|---------|------|-------------|
| POST | `/register`| No | Register (first_name, email, password, role only) |
| POST | `/login`  | No | Login and get token + is_profile_complete |
| POST | `/logout` | Yes | Logout |
| GET  | `/me`     | Yes | Get current user + role + is_profile_complete |

### Register Body
```json
{
    "first_name": "string (required)",
    "email": "string (required)",
    "password": "string min:8 (required)",
    "password_confirmation": "string (required)",
    "role": "tenant or host (required)"
}
```

### Login Response
```json
{
    "token": "string",
    "role": "tenant | host | admin",
    "user": { ... },
    "is_profile_complete": false
}
```

---

## Profile Management
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| POST | `/profile/complete` | Yes | Complete profile (one time only) |
| PUT | `/profile`            | Yes | Update profile data |
| PUT | `/profile/change-password` | Yes | Change password |
| PUT | `/profile/change-email` | Yes | Change email |

### Complete Profile Body (required before booking or listing)
```json
{
    "second_name": "string (required)",
    "third_name": "string (required)",
    "last_name": "string (required)",
    "national_id": "string unique (required)",
    "phone": "string (required)"
}
```

### Complete Profile Response
```json
{
    "message": "Profile completed successfully.",
    "user": { ... },
    "is_profile_complete": true
}
```

>  Profile must be complete before:
> - Tenant submits a booking request
> - Host lists a property

---

## Password Reset
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| POST | `/forgot-password` | No | Send reset link to email |
| POST | `/reset-password` | No | Reset password with token |

---

## Location Endpoints (Public)
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/governorates` | No | List all governorates |
| GET | `/governorates/{id}/cities` | No | List cities by governorate |
| GET | `/cities/{id}/neighborhoods` | No | List neighborhoods by city |

---

## Property Endpoints (Public)
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/properties` | No | Search & filter properties |
| GET | `/properties/{id}` | No | View single property with images |
| GET | `/properties/{id}/reviews` | No | View property reviews |
| GET | `/properties/{id}/whatsapp` | No | Get WhatsApp contact link |
| GET | `/users/{id}/reviews` | No | View user reviews |

### Search Filters (query params)
```
governorate_id, city_id, neighborhood_id,
type, min_price, max_price, rooms,
min_area, max_area, damage_status,
has_water, has_electricity, is_ready
```

### Property Types
```
apartment, villa, land, chalet, commercial, parking
```

### Damage Status Values
```
intact, partial, renovated
```

---

## Host Endpoints
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/host/properties` | Yes | List my properties |
| POST | `/host/properties` | Yes | Submit new property |
| GET | `/host/properties/{id}` | Yes | View my property |
| PUT | `/host/properties/{id}` | Yes | Edit property |
| DELETE | `/host/properties/{id}` | Yes | Archive property |
| PATCH | `/host/properties/{id}/availability` | Yes | Toggle availability |
| GET | `/host/bookings` | Yes | List booking requests |
| PATCH | `/host/bookings/{id}/accept` | Yes | Accept booking (optional discount) |
| PATCH | `/host/bookings/{id}/reject` | Yes | Reject booking |

### Submit Property Body
```json
{
    "title": "string (required)",
    "description": "string (optional)",
    "type": "apartment|villa|land|chalet|commercial|parking (required)",
    "governorate_id": "integer (required)",
    "city_id": "integer (required)",
    "neighborhood_id": "integer (optional)",
    "street": "string (optional)",
    "price": "decimal (required)",
    "area_m2": "decimal (optional)",
    "rooms": "integer (optional)",
    "damage_status": "intact|partial|renovated (required)",
    "has_water": "boolean (required)",
    "has_electricity": "boolean (required)",
    "is_ready": "boolean (required)"
}
```

### Accept Booking with Discount (optional)
```json
{
    "discounted_price": 450
}
```

>  Host must have complete profile before listing a property

---

## Property Images (Host only)
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/host/properties/{id}/images` | Yes | List property images |
| POST | `/host/properties/{id}/images` | Yes | Upload images (form-data) |
| PATCH | `/host/properties/{id}/images/{imageId}/main` | Yes | Set image as main |
| DELETE | `/host/properties/{id}/images/{imageId}` | Yes | Delete image |

### Image Upload Notes
- Body type must be `form-data` NOT raw JSON
- Key name must be `images[]`
- Allowed types: jpg, jpeg, png
- Max size: 2MB per image
- Max count: 10 images per property
- First uploaded image is automatically set as main

---

## Tenant Endpoints
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/tenant/bookings` | Yes | List my bookings |
| POST | `/tenant/bookings` | Yes | Submit booking request |
| GET | `/tenant/bookings/{id}` | Yes | View booking |
| PUT | `/tenant/bookings/{id}` | Yes | Edit pending booking |
| DELETE | `/tenant/bookings/{id}` | Yes | Cancel pending booking |
| GET | `/tenant/favorites` | Yes | List my favorites |
| POST | `/tenant/favorites` | Yes | Add property to favorites |
| DELETE | `/tenant/favorites/{propertyId}` | Yes | Remove from favorites |

### Submit Booking Body
```json
{
    "property_id": "integer (required)",
    "start_date": "date YYYY-MM-DD (required, future)",
    "end_date": "date YYYY-MM-DD (required, after start_date)"
}
```

>  Tenant must have complete profile before booking

---

## Contract Endpoints
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/contracts` | Yes | List my contracts (role-based) |
| GET | `/contracts/{id}` | Yes | View contract details |
| PATCH | `/contracts/{id}/cancel` | Yes | Cancel active contract |
| DELETE | `/contracts/{id}` | Yes (admin) | Archive inactive contract |
| GET | `/contracts/{id}/pdf` | Yes | Get PDF URL |
| GET | `/contracts/{id}/download` | Yes | Download PDF file |

---

## Review Endpoints
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| POST | `/reviews` | Yes | Submit review (after contract ends) |

### Submit Review Body
```json
{
    "contract_id": "integer (required)",
    "rating": "integer 1-5 (required)",
    "comment": "string (optional)"
}
```

>  Reviews only allowed after contract is expired or cancelled

---

## Notification Endpoints
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/notifications` | Yes | List my notifications |
| GET | `/notifications/unread-count` | Yes | Get unread count |
| PATCH | `/notifications/{id}/read` | Yes | Mark as read |
| PATCH | `/notifications/mark-all-read` | Yes | Mark all as read |

---

## Admin Endpoints
| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| GET | `/admin/properties` | Yes | List all properties |
| GET | `/admin/properties/pending` | Yes | List pending properties |
| PATCH | `/admin/properties/{id}/accept` | Yes | Accept property |
| PATCH | `/admin/properties/{id}/reject` | Yes | Reject property with reason |
| DELETE | `/admin/properties/{id}` | Yes | Archive property |
| GET | `/admin/bookings` | Yes | List all bookings (PII masked) |
| GET | `/admin/bookings/{id}` | Yes | View booking |
| DELETE | `/admin/bookings/stale` | Yes | Archive stale pending bookings (+48h) |

### Reject Property Body
```json
{
    "rejection_reason": "string (required)"
}
```

---

## Roles
| Role | Access |
|------|--------|
| `admin` | Full platform control |
| `host` | Manage own properties & bookings |
| `tenant` | Search, book, review, favorites |

---

## Status Flows

### Property Status
```
pending → accepted → available (public)
pending → rejected
accepted + edited essential fields → pending (re-review)
```

### Property Availability
```
not_available → available (after admin approval)
available → booked (after booking accepted)
booked → available (after contract ends/cancelled)
available ↔ not_available (host toggles manually)
```

### Booking Status
```
pending → accepted → contract created automatically
pending → rejected
pending → cancelled (by tenant)
```

### Contract Status
```
active → expired (automatic daily command)
active → cancelled (by tenant or host)
```

---

## Notification Types
| Type | Sent to | Trigger |
|------|---------|---------|
| `new_booking` | Host | Tenant submits booking |
| `booking_accepted` | Tenant | Host accepts booking |
| `booking_rejected` | Tenant | Host rejects booking |
| `booking_edited` | Host | Tenant edits booking |
| `booking_cancelled` | Host | Tenant cancels booking |
| `contract_activated` | Both parties | Contract created |
| `contract_cancelled` | Other party | Contract cancelled |
| `contract_expired` | Both parties | Contract expires automatically |
| `property_approved` | Host | Admin approves property |
| `property_rejected` | Host | Admin rejects property |
| `new_property_submitted` | Admin | Host submits new property |
| `property_modified` | Admin | Host modifies essential fields |
| `review_received` | Reviewee | Review submitted |
| `review_reminder` | Both parties | After contract ends/cancelled |

---

## Default Admin Credentials
```
Email: admin@hima.app
Password: password123
```

## Notes
- All responses are JSON
- Dates format: YYYY-MM-DD
- Soft delete used throughout (no permanent deletion)
- PDF contracts generated automatically on booking acceptance
- WhatsApp link requires host to have phone number