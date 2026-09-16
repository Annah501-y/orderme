# OrderMe

OrderMe is a multi-vendor e-commerce and delivery platform that connects customers with sellers through integrated order, payment, and delivery management.

This repository contains the **Laravel REST API backend** for OrderMe. It powers the separate React frontend and handles authentication, authorization, product management, seller management, shopping carts, multi-seller orders, payments, rider management, and delivery operations.

---

## Table of Contents

- [Project Overview](#project-overview)
- [Technology Stack](#technology-stack)
- [Architecture](#architecture)
- [User Roles](#user-roles)
- [Database Structure](#database-structure)
- [Authentication](#authentication)
- [Authorization](#authorization)
- [Core Modules](#core-modules)
- [Rider & Delivery System](#rider--delivery-system)
- [API Architecture](#api-architecture)
- [Validation & Data Integrity](#validation--data-integrity)
- [Installation](#installation)
- [Environment Configuration](#environment-configuration)
- [Running on a Local Network](#running-on-a-local-network)
- [Queue Processing](#queue-processing)
- [Useful Laravel Commands](#useful-laravel-commands)
- [Development Status](#development-status)
- [Planned Delivery Workflow](#planned-delivery-workflow)
- [Security Considerations](#security-considerations)
- [Future Improvements](#future-improvements)
- [Project Goal](#project-goal)

---

## Project Overview

OrderMe is designed as a multi-vendor marketplace where:

- Buyers can purchase products from different sellers in a single checkout.
- Sellers can manage their stores, products, stock, and orders.
- Administrators manage users, sellers, products, orders, riders, and deliveries.
- Riders receive delivery assignments and manage deliveries.
- One customer order can be split into multiple seller orders and multiple deliveries when sellers are geographically separated.
- Customer deliveries are confirmed using OTP verification.
- Rider location is used for delivery assignment and tracking.

---

## Technology Stack

**Backend**
- Laravel
- PHP 8.2+
- Laravel Sanctum
- Spatie Laravel Permission
- REST API
- MySQL / PostgreSQL

**Development & Testing**
- XAMPP
- Composer
- Postman
- Git / GitHub

**External Services**
| Service | Purpose |
|---|---|
| Resend | Email invitations |
| ClickPesa | Payment processing |
| SMS provider | Delivery OTP |
| Google Maps / Routes | Distance and route calculations |

---

## Architecture

OrderMe follows a separated frontend/backend architecture. The Laravel backend does not render the application UI — it exposes JSON APIs consumed by the React frontend.

```
                  ORDERME SYSTEM

                React Frontend
                       │
                       │  HTTP / JSON
                       ▼
               Laravel REST API
                       │
          ┌────────────┼────────────┐
          ▼            ▼            ▼
     Authentication  Business    Authorization
                     Logic
                       │
                       ▼
                    Database
                       │
              MySQL / PostgreSQL
```

---

## User Roles

OrderMe currently supports four roles.

### Buyer
Register · Log in · Browse products & categories · Manage cart · Create orders · Make payments · View orders · Manage wishlist · Review products · Confirm delivery

### Seller
Register · Create seller profile · Manage store information · Add/update products · Manage stock · View & process orders · Mark orders ready for delivery

### Admin
Manage users, sellers, categories, products, orders, riders, and deliveries · Approve, reject, or suspend sellers

### Rider
Activate account via invitation · Log in · View assigned deliveries · Accept assignments · Update delivery status · Share location · Complete deliveries via OTP verification

---

## Database Structure

Main entities:

```
users, roles, permissions, categories, products, seller_profiles,
addresses, cart, cart_items, orders, order_items, seller_orders,
payments, reviews, riders, rider_invitations, deliveries, delivery_stops
```

**User relationships**

```
User
├── Seller Profile
├── Addresses
├── Products
├── Orders
└── Rider Profile
```

**Product relationships**

```
Product
├── Seller
└── Category
```

---

## Authentication

Authentication is handled with **Laravel Sanctum**.

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Register a new user |
| POST | `/api/login` | Authenticate and receive a token |
| POST | `/api/logout` | Revoke the current token |
| POST | `/api/logout-all` | Revoke all tokens for the user |
| GET | `/api/me` | Get the authenticated user |

**Flow**

```
Client → Login → Laravel validates credentials → Check account status
       → Create Sanctum token → Return authenticated user
```

Inactive accounts cannot log in.

---

## Authorization

Authorization is handled with **Spatie Laravel Permission** and is enforced entirely on the backend — frontend route restrictions are never treated as sufficient.

**Roles:** `admin` · `seller` · `buyer` · `rider`

**Representative permissions:**

```
users.view / users.update / users.delete
sellers.view / sellers.approve / sellers.reject / sellers.suspend
categories.view / categories.create / categories.update / categories.delete
products.view / products.create / products.update / products.delete
products.manage-stock / products.activate
cart.view / cart.manage
orders.view / orders.create / orders.update / orders.manage
payments.view / payments.create / payments.manage
reviews.view / reviews.create / reviews.update / reviews.delete
riders.view / riders.create / riders.update / riders.delete / riders.activate
deliveries.view / deliveries.assign / deliveries.update / deliveries.manage
```

---

## Core Modules

### Seller Management
```
Seller Registration → Seller Profile → Store Address → Admin Review → Approve / Reject / Suspend
```
Seller profiles store `store_name`, `store_description`, `phone`, and `address`. A store address is required before approval.

### Address Management
Addresses are stored independently of user accounts (`address_line`, `district`, `city`, `region`, `country`, `latitude`, `longitude`, `is_default`) and are reused for seller pickup locations, customer delivery addresses, and rider routing.

### Category Management
Categories are managed entirely on the backend rather than hardcoded in the frontend:
```
GET    /api/categories
POST   /api/categories
PUT    /api/categories/{category}
DELETE /api/categories/{category}
```

### Product Management
Products store `category_id`, `seller_id`, `name`, `slug`, `description`, `image`, `old_price`, `discount`, `price`, `stock_quantity`, and `is_active`. Final price is derived from old price and discount. Sellers manage their own catalog; admins have platform-level control.

### Shopping Cart
Cart operations (view, add, update quantity, remove, clear) are scoped to the authenticated buyer:
```
Buyer → Cart → Cart Items → Products
```

### Multi-Seller Orders
A buyer can purchase from several sellers in a single checkout. Laravel creates **one main Order** plus **multiple Seller Orders**, so each seller manages only their portion:
```
Order #1024
├── Seller A → Product 1, Product 2
├── Seller B → Product 3
└── Seller C → Product 4
```

### Order Lifecycle
```
Pending → Confirmed → Processing → Ready for Delivery
        → Assigned → Picked Up → Out for Delivery → Delivered
```
Orders may also be cancelled. Seller responsibility ends at **Ready for Delivery**, after which the delivery system takes over. Each seller can only view and update their own seller order, up to Ready for Delivery.

### Payment System
Designed to integrate with **ClickPesa**:
```
Checkout → Create Order → Create Payment → Payment Provider → Payment Result → Update Payment
```
Payment status is tracked independently from order status.

### Review System
Customers can review products after purchase, governed by `reviews.view/create/update/delete` permissions.

---

## Rider & Delivery System

### Rider Management
Riders are created by administrators only — there is no public rider registration.
```
Admin Dashboard → Create Rider → User Account → Rider Profile → Rider Role → Invitation
```
New rider accounts start as `is_active = false`.

### Rider Profile
Stores `user_id`, `latitude`, `longitude`, `is_available`, and `status` (`offline` / `available` / `busy`), reserved for GPS-based assignment and tracking.

### Rider Invitation System
Invitations are stored in `rider_invitations` (`user_id`, `token_hash`, `expires_at`, `accepted_at`). The plain token is never stored — only its SHA-256 hash — and is delivered to the rider by email.

### Rider Activation
```
POST /api/rider/activate
```
```
Invitation Token → Hash Token → Find Invitation → Check Token → Check Expiration
                 → Check Accepted Status → Verify Rider Role → Set Password
                 → Activate Account → Mark Invitation Accepted
```

### Email Invitations
Sent via **Resend**:
```
Admin creates rider → Laravel generates token → Invitation email
    → Rider clicks activation link → React activation page → Laravel activation API
```
Intended to run through Laravel queues for reliable async delivery.

### Delivery Management
A single order can generate multiple deliveries when sellers are geographically separated:
```
Order #1024
├── Delivery 1 → Seller A Pickup, Seller B Pickup, Rider 1
└── Delivery 2 → Seller C Pickup, Rider 2
```

### Rider Assignment (Planned)
Assignment logic will weigh seller pickup locations, customer destination, rider availability/location, and route distance — grouping nearby pickups under a single rider where possible. Google Maps/Routes supplies distance and routing data; Laravel owns the assignment logic.

### Rider GPS
```
Rider Login → Location Permission → GPS Coordinates → Laravel API → Rider Location
```

### Delivery Tracking
```
Assigned → Accepted → Picking Up → Picked Up → Out for Delivery → Delivered
```

### Delivery OTP
```
Order Out for Delivery → Generate OTP → Send OTP to Customer → Rider Arrives
    → Customer Provides OTP → Rider Submits OTP → Laravel Verifies OTP → Delivery Confirmed
```

---

## API Architecture

REST-style structure returning JSON responses:

```
/api
├── auth
├── categories
├── products
├── cart
├── orders
├── payments
├── wishlist
├── reviews
├── seller
├── admin
├── rider
└── deliveries
```

Standard response shape:

```json
{
    "success": true,
    "message": "Request completed successfully.",
    "data": {}
}
```

---

## Validation & Data Integrity

**Form Requests** keep validation out of controllers, e.g. `StoreRiderRequest`, `ActivateRiderRequest`, `StoreSellerProfileRequest`, `StoreProductRequest`, `UpdateOrderStatusRequest`.

**Database transactions** protect multi-step operations — e.g. rider creation wraps user creation, role assignment, rider profile creation, and invitation creation so a failure never leaves a partial record.

---

## Queue Processing

Used for queued operations such as invitation emails:

```bash
php artisan make:queue-table
php artisan migrate
php artisan queue:work
```

Keep the queue worker running while testing queued email operations.

---

## Useful Laravel Commands

| Command | Purpose |
|---|---|
| `php artisan config:clear` | Clear cached configuration |
| `php artisan optimize:clear` | Clear application caches |
| `php artisan migrate` | Run migrations |
| `php artisan make:controller ControllerName` | Create a controller |
| `php artisan make:model ModelName -m` | Create a model with migration |
| `php artisan make:request RequestName` | Create a Form Request |
| `php artisan serve` | Run the development server |
| `php artisan queue:work` | Start the queue worker |

---

## Development Status

**Completed**
- Laravel project setup & database configuration
- REST API architecture
- Sanctum authentication, registration & login
- Role-based authorization (Buyer, Seller, Admin, Rider) with Spatie permissions
- Category, product, seller profile, and address management
- Cart backend
- Multi-seller order architecture & seller order management
- Order status management
- Payment architecture
- Review permissions/API structure
- Rider creation, profile, invitation system & activation
- Email invitation integration
- API testing with Postman

**In Progress**
- Rider email queue
- Delivery management & assignment
- Rider availability & GPS
- Delivery tracking
- Google Maps/Routes integration
- SMS OTP
- Payment provider completion
- Notifications
- Final review integration
- End-to-end testing

---

## Planned Delivery Workflow

```
Buyer → Checkout → Order Created → Payment → Seller Orders Created
      → Sellers Process Orders → Ready for Delivery → Delivery Assignment
      → Rider Assigned → Rider Accepts → Pickup → Out for Delivery
      → Customer → OTP Verification → Delivered → Review
```

---

## Security Considerations

The backend enforces security independently of the frontend:

- Laravel Sanctum authentication
- Role- and permission-based authorization
- Form Request validation
- Password hashing
- Hashed invitation tokens with expiration and single-use acceptance
- Database transactions for multi-step operations
- Seller order ownership checks
- Inactive account protection

Frontend restrictions alone are never treated as sufficient authorization.

---

## Future Improvements

- Advanced delivery assignment algorithm
- Google Routes API integration
- Real-time rider tracking
- SMS notification service
- Delivery OTP (full rollout)
- Payment webhook handling
- Automated notifications
- Advanced reporting & admin analytics
- Seller earnings and commission calculation
- Delivery performance analytics
- Caching & API rate limiting
- Production deployment
- Automated test suite

---

## Project Goal

OrderMe aims to be a scalable multi-vendor marketplace combining:

**E-commerce + Multi-Seller Ordering + Payments + Delivery Management + Rider Tracking**

The Laravel backend provides the central business logic and API layer connecting customers, sellers, administrators, riders, payments, orders, and deliveries.

```
React Frontend → Laravel REST API → Database → External Services
```

OrderMe is currently under active development, with the backend and frontend maintained as separate applications communicating over REST APIs.