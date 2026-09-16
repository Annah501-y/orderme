# OrderMe Backend Roadmap — Laravel

**Architecture:** React Frontend → REST API → Laravel Backend (Authentication / Business Logic / Authorization) → MySQL/PostgreSQL

---

## Phase 1 — Laravel Project Setup ✅ Completed
- I set up the Laravel project on PHP 8.2 with XAMPP, MySQL, and Postman for testing.
- I configured the environment file, database connection, API routes, controllers, models, migrations, form requests, and API resources.
- I later explored and configured PostgreSQL for the project environment.

## Phase 2 — Database Design ✅ Completed / Substantially Developed
- I designed the core marketplace schema: Users, Roles, Permissions, Categories, Products, Seller Profiles, Addresses, Cart, Cart Items, Orders, Order Items, Seller Orders, Payments, Reviews, Riders, Rider Invitations, and Deliveries.
- I modeled the key relationships — a User can have a Seller Profile, Addresses, Products, Orders, and a Rider Profile; an Order can have Order Items, Seller Orders, and Payments.

## Phase 3 — Authentication ✅ Completed
- I implemented authentication with Laravel Sanctum, including register, login, logout, logout-all, and a `/me` endpoint.
- I built the login flow to validate credentials, check account status, issue a Sanctum token, and return the user with their role.

## Phase 4 — Role & Permission System ✅ Completed
- I implemented Spatie Laravel Permission with four roles: Admin, Seller, Buyer, and Rider.
- I defined granular permissions across users, sellers, categories, products, cart, orders, payments, reviews, riders, and deliveries (e.g., `products.create`, `orders.manage`, `deliveries.assign`).
- I ensured authorization is enforced on the backend rather than relying solely on React route protection.

## Phase 5 — Category Management ✅ Completed / API Integration Underway
- I built full CRUD endpoints for categories (`GET/POST/PUT/DELETE /api/categories`).
- I am currently connecting this API to the frontend Navbar dropdown.

## Phase 6 — Product Management ✅ Completed / Being Finalized
- I built the product model tied to a Seller and Category, with fields for name, slug, description, image, pricing, discount, stock, and status.
- I implemented the final-price calculation from old price and discount.
- I built seller-facing APIs for product management.

## Phase 7 — Seller Management ✅ Completed
- I built seller registration, which creates a seller profile with store name, description, phone, and address.
- I implemented the admin approval workflow (Approve / Reject / Suspend) so sellers aren't treated as approved merchants until reviewed.

## Phase 8 — Address Management ✅ Completed
- I built a standalone address system supporting address line, district, city, region, country, coordinates, and a default-address flag.
- I designed it to be reusable for seller pickup locations, customer delivery locations, and future rider routing.

## Phase 9 — Cart 🔄 Backend Implemented / Frontend Integration Remains
- I implemented cart operations on the backend: add, update quantity, remove, view, and clear.
- I enforced that a cart always belongs to the authenticated buyer.
- I need to complete the frontend integration for these endpoints.

## Phase 10 — Multi-Seller Checkout ✅ Backend Architecture Completed
- I designed the system so a single customer order can span multiple sellers.
- I implemented one main Order that generates multiple SellerOrders, so each seller manages only their own portion.

## Phase 11 — Order Management ✅ Completed / Being Finalized
- I implemented the full order lifecycle: Pending → Confirmed → Processing → Ready for Delivery → Assigned → Picked Up → Out for Delivery → Delivered, plus cancellation.
- I scoped seller responsibility to stop at "Ready for Delivery," handing off to the delivery system from there.

## Phase 12 — Seller Order Management ✅ Completed
- I built seller-scoped order retrieval (`GET /seller/orders`) so a seller only ever sees their own orders.
- I enforced backend checks preventing one seller from modifying another seller's order.
- I allowed sellers to update status up to "Ready for Delivery."

## Phase 13 — Payment System ✅ Backend Architecture Implemented
- I designed the payment flow around a provider such as ClickPesa: checkout → create order → create payment → provider → payment result → status update.
- I decoupled payment status from order status, since a pending payment is not the same as a delivered order.

## Phase 14 — Review System ✅ Backend Permissions/API Structure Implemented
- I implemented review permissions (`reviews.view/create/update/delete`) tied to products and customers.
- I need to complete the frontend integration.

## Phase 15 — Rider Management 🔄 Currently Being Developed
- I implemented admin-only rider creation (riders do not self-register).
- On creation, Laravel generates the User, Rider profile, Rider role, and an invitation, with the account starting as inactive.

## Phase 16 — Rider Invitation System ✅ Implemented
- I built a `rider_invitations` table storing `user_id`, `token_hash`, `expires_at`, and `accepted_at`.
- I applied the security principle of never storing the plain invitation token — only its SHA-256 hash — while sending the plain token by email.

## Phase 17 — Rider Email Invitation 🔄 Implemented / Being Stabilized
- I integrated Laravel email with Resend to send the activation link to new riders.
- I identified that the synchronous Resend request can hang in the current environment, and I need to move this to Laravel's database queue with a queue worker for production.

## Phase 18 — Rider Activation ✅ Implemented
- I built the public `POST /api/rider/activate` endpoint, which hashes the incoming token, validates it (existence, expiration, prior use, rider role), sets the password, activates the account, and marks the invitation accepted.

## Phase 19 — Delivery Management 🔶 Backend Architecture Planned
- I designed the Order → Delivery → Delivery Stops → Rider structure, allowing one order to produce multiple deliveries when sellers are geographically separated (e.g., separate deliveries per seller cluster with different riders).
- I need to build this out as the next major backend stage.

## Phase 20 — Intelligent Rider Assignment ⏳ Major Feature Still to Implement
- I need to build logic to group nearby sellers, identify available riders, and assign the closest one — e.g., grouping Sellers A and B in Mbezi under one rider while Seller C in Kariakoo gets a separate rider.
- I plan to use Google Maps/Routes for distance and routing data, while keeping the assignment business logic in Laravel.

## Phase 21 — Rider GPS ⏳ Still Needs Implementation
- I already designed the riders table with `latitude`, `longitude`, `is_available`, and `status`.
- I need to implement the flow where, after login and permission grant, the rider's GPS coordinates are sent to Laravel.

## Phase 22 — Delivery Tracking ⏳ Still Needs Implementation
- I need to build backend tracking of Rider → Delivery → Delivery Stops → Status, covering states such as Assigned, Accepted, Picking Up, Picked Up, Out for Delivery, and Delivered.

## Phase 23 — Delivery OTP ⏳ Still Needs Implementation
- I need to implement OTP-based delivery confirmation: generate an OTP, send it by SMS, and require the rider to submit it for Laravel to verify before marking the delivery complete — preventing a rider from self-confirming delivery.

## Phase 24 — Notifications ⏳ Still Needs Expansion
- I need to build notifications covering new orders, order updates, readiness for delivery, rider assignment/acceptance, pickup, out-for-delivery, OTP sent, and delivery confirmation.
- I plan to support email, SMS, and in-app channels.

## Phase 25 — Admin Delivery Management ⏳ Still Needs Implementation
- I need to give admins visibility and control over orders ready for delivery, available riders, active deliveries, assignments, rider locations, and delivery status, following the flow: find delivery groups → find available riders → assign rider → monitor.

## Phase 26 — Security & Validation 🔄 Ongoing / Final Stage
- I ensure the backend independently validates every claim from the frontend rather than trusting it (e.g., re-checking admin status server-side).
- I already use Form Requests, Sanctum, Spatie permissions, role checks, ownership checks, database transactions, and hashed invitation tokens.

## Phase 27 — API Testing 🔄 Ongoing
- I have used Postman extensively to test the flow from API through to database and JSON response.
- I have already tested authentication, seller profiles, products, seller orders, order status, rider creation, rider invitation, and rider activation.

## Phase 28 — Final Backend Testing ⏳ Pending
- I need to run a full end-to-end backend test: registration → login → add to cart → checkout → order creation → payment → seller order handling → ready for delivery → rider assignment → pickup → delivery → OTP verification → delivered → finalized records → review.

---

## Backend Status Summary

| Backend Area | Status |
|---|---|
| Laravel setup | Done |
| Database architecture | Done / expanding |
| Authentication | Done |
| Sanctum | Done |
| Roles & permissions | Done |
| Categories | Done |
| Products | Done |
| Seller profiles | Done |
| Addresses | Done |
| Cart | Backend done |
| Multi-seller orders | Done |
| Seller orders | Done |
| Payment architecture | Done / integration remaining |
| Reviews | API structure done |
| Rider management | In progress |
| Rider invitations | Done |
| Rider activation | Done |
| Email invitation | Working, queue improvement needed |
| Delivery model | Next major backend stage |
| Rider assignment | Planned |
| GPS | Planned |
| Delivery tracking | Planned |
| OTP delivery confirmation | Planned |
| Notifications | Planned |
| Final security testing | Pending |
| End-to-end testing | Pending |

---

## Roadmap From Today Onward

1. I need to finish the rider email/queue stabilization.
2. I need to build the Rider Dashboard API.
3. I need to build the delivery tables and models.
4. I need to build the delivery assignment API and rider availability logic.
5. I need to build the GPS location API and integrate Google Routes.
6. I need to implement automatic/semi-automatic rider assignment.
7. I need to build delivery tracking.
8. I need to implement SMS OTP generation and verification.
9. I need to finalize the Order → Delivered transition and notifications.
10. I need to build admin delivery management.
11. I need to finalize payment reconciliation and reviews.
12. I need to complete security testing, full Postman API testing, and end-to-end testing.

---

