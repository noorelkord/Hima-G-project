# Hima 

Hima is a rental property platform that connects **tenants**, **hosts**, and **admins** through a decoupled web application: a Laravel REST API backend and a vanilla HTML/CSS/JS frontend. Tenants can browse and book properties, hosts can list and manage properties, and admins moderate listings and bookings. Digital rental contracts are generated automatically once a booking is confirmed.

**Live app:** [hima-g-project.vercel.app](https://hima-g-project.vercel.app)

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
  - [Backend Setup](#backend-setup)
  - [Frontend Setup](#frontend-setup)
- [API Overview](#api-overview)
- [Deployment](#deployment)
- [Contributors](#contributors)

## Features

- **Authentication & Roles** — Registration, login, email verification, and password reset via Laravel Sanctum, with tenant/host/admin roles managed through Spatie Permission.
- **Property Management** — Hosts create, update, and manage listings, including multiple images per property and availability toggling. New listings require admin approval before going live.
- **Location Hierarchy** — Properties are organized by governorate → city → neighborhood for structured browsing and filtering.
- **Booking Lifecycle** — Tenants request bookings with cost calculation; hosts accept or reject requests; admins can view and archive stale bookings.
- **Digital Contracts** — Confirmed bookings automatically generate a PDF rental contract (via mPDF), downloadable by both tenant and host, with scheduled auto-expiry.
- **Reviews** — Tenants can leave reviews on properties and hosts within review windows tied to completed bookings.
- **Notifications** — In-app notifications with unread counts and mark-as-read/mark-all-as-read actions.
- **Favorites** — Tenants can save properties to a favorites list.
- **WhatsApp Contact Links** — Quick generation of WhatsApp links for direct tenant-host contact.
- **Admin Dashboard** — Property approval/rejection, booking oversight, and contract monitoring.

## Tech Stack

**Backend**
- PHP 8.2, Laravel 12
- Laravel Sanctum (authentication)
- Spatie Laravel Permission (roles & permissions)
- mPDF (contract PDF generation)
- MySQL (production) / SQLite (local default)
- AWS S3 via Flysystem (property image storage in production)

**Frontend**
- Vanilla HTML, CSS, and JavaScript (no framework)
- Consumes the backend as a JSON REST API

**Deployment**
- Backend: Render (Docker)
- Frontend: Vercel

## Project Structure

```
Hima-G-project/
├── backend/          # Laravel REST API
│   ├── app/
│   │   ├── Http/Controllers/   # Auth, Property, Booking, Contract, Review, Notification, Admin/Host/Tenant controllers
│   │   └── Models/             # User, Property, PropertyImage, Booking, Contract, Review, Notification, Favorite, Governorate, City, Neighborhood, ReviewWindow
│   ├── routes/api.php          # API route definitions
│   ├── database/               # Migrations, seeders, factories
│   └── API_DOCUMENTATION.md    # Detailed endpoint documentation
└── frontend/          # Vanilla JS/HTML/CSS client
    ├── index.html, properties.html, property-details.html, booking-form.html, ...
    ├── dashboard-host.html, dashboard-tenant.html, admin-dashboard.html, ...
    ├── css/
    └── js/
```

## Getting Started

### Backend Setup

**Requirements:** PHP 8.2+, Composer, Node.js (for asset tooling)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database in `.env` (SQLite is used by default; set `DB_CONNECTION`, `DB_DATABASE`, etc. for MySQL). Then run:

```bash
php artisan migrate
php artisan serve
```

The API will be available at `http://127.0.0.1:8000`.

For local property image storage, the default filesystem disk works out of the box. In production, configure the S3 disk (see `backend/README.md` for the required `FILESYSTEM_DISK`, `AWS_*` environment variables).

### Frontend Setup

The frontend is static HTML/CSS/JS and can be served with any static file server:

```bash
cd frontend
python3 server.py
```

Update the API base URL in the frontend JS config to point at your running backend (`http://127.0.0.1:8000` for local development).

## API Overview

The backend exposes a versioned-free REST API under `/api`, including:

| Area | Example Endpoints |
|---|---|
| Auth | `POST /register`, `POST /login`, `POST /admin/login`, `POST /forgot-password` |
| Properties | `GET /properties`, `GET /properties/{id}`, `GET /properties/{id}/whatsapp` |
| Locations | `GET /governorates`, `GET /governorates/{id}/cities`, `GET /cities/{id}/neighborhoods` |
| Host | `GET/POST /host/properties`, `PATCH /host/bookings/{id}/accept` |
| Tenant | `POST /tenant/bookings/calculate`, `POST /tenant/bookings`, `GET /tenant/favorites` |
| Admin | `GET /admin/properties/pending`, `PATCH /admin/properties/{id}/accept` |
| Contracts | `GET /contracts/{id}/pdf`, `GET /contracts/{id}/download` |
| Reviews | `POST /reviews`, `GET /properties/{id}/reviews` |
| Notifications | `GET /notifications`, `PATCH /notifications/mark-all-read` |

Full endpoint details are documented in [`backend/API_DOCUMENTATION.md`](backend/API_DOCUMENTATION.md).

## Deployment

- **Backend** is containerized (see `backend/Dockerfile`) and deployed on Render, using S3 for persistent property image storage.
- **Frontend** is deployed on Vercel at [hima-g-project.vercel.app](https://hima-g-project.vercel.app).


