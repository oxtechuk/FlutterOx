# Doken Ox Pro - API Documentation

Base URL: `/wp-json/mvapp/v1`

## Authentication (JWT)

### Login
- **Endpoint**: `POST /auth/login`
- **Body**:
  ```json
  {
    "username": "user",
    "password": "password"
  }
  ```
- **Response**: `{ token, user_email, user_nicename, user_display_name }`

### Register
- **Endpoint**: `POST /auth/register`
- **Body**:
  ```json
  {
    "username": "user",
    "email": "user@example.com",
    "password": "password"
  }
  ```

### Refresh Token
- **Endpoint**: `POST /auth/refresh`
- **Headers**: `Authorization: Bearer <token>`

---

## Home API

### Get Home Data
- **Endpoint**: `GET /home`
- **Parameters**:
  - `location`: (string, optional) User location for personalization
  - `lang`: `en` | `ar` (default: en)
- **Response**:
  ```json
  {
    "user": { "name": "...", "greeting": "..." },
    "categories": [...],
    "offers": [...],
    "featured_sellers": [...],
    "new_collection": [...],
    "all_items": [...]
  }
  ```

---

## Search API

### Search
- **Endpoint**: `GET /search`
- **Parameters**:
  - `q`: Search query (string)
  - `category_id`: (int, optional)
  - `type`: `all` | `products` | `vendors` (default: all)
  - `page`: (int, default: 1)
- **Response**:
  ```json
  {
    "products": [...],
    "vendors": [...],
    "categories": [...]
  }
  ```

---

## Product API

### Get Product Detail
- **Endpoint**: `GET /products/{id}`
- **Response**:
  ```json
  {
    "id": 123,
    "name": "...",
    "attributes": [...],
    "in_stock": true,
    "vendor": {...},
    ...
  }

### Submit Product Review (Requires Auth)
- **Endpoint**: `POST /products/{id}/review`
- **Body**:
  ```json
  {
    "rating": 5,
    "comment": "Great product!"
  }
  ```
- **Response**: `{ "success": true, "message": "Review submitted..." }`

---

## Cart API (Requires Auth)

### Get Cart
- **Endpoint**: `GET /cart`

### Add to Cart
- **Endpoint**: `POST /cart/add`
- **Body**:
  ```json
  {
    "product_id": 123,
    "quantity": 1,
    "variation_id": 0
  }
  ```

### Update Item
- **Endpoint**: `POST /cart/update`
- **Body**: `{ "key": "...", "quantity": 2 }`

### Remove Item
- **Endpoint**: `POST /cart/remove`
- **Body**: `{ "key": "..." }`

---

## Checkout & Orders API (Requires Auth)

### Create Order (Checkout)
- **Endpoint**: `POST /checkout`
- **Body**:
  ```json
  {
    "billing": { "first_name": "...", "email": "...", ... },
    "shipping": { ... },
    "payment_method": "cod"
  }
  ```

### List Orders
- **Endpoint**: `GET /orders`
- **Parameters**: `page`, `status`

### Order Detail
- **Endpoint**: `GET /orders/{id}`

---

## Profile & Settings API (Requires Auth)

### Update Addresses
- **Endpoint**: `POST /profile/addresses`
- **Body**: `{ "billing": {...}, "shipping": {...} }`

### Get Addresses
- **Endpoint**: `GET /profile/addresses`

### Change Password
- **Endpoint**: `PUT /profile/password`
- **Body**: `{ "current_password": "...", "new_password": "..." }`

### Get Settings
- **Endpoint**: `GET /settings`
- **Response**: `{ "languages": [...], "currency": "..." }`

---

## Vendors API

### List Vendors
- **Endpoint**: `GET /vendors`
- **Parameters**: `page`, `search`

### Vendor Detail
- **Endpoint**: `GET /vendors/{id}`

### Vendor Products
- **Endpoint**: `GET /vendors/{id}/products`
