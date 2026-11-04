# Sales Manager – How the System Works

A PHP + MySQL (MySQLi, Bootstrap 5) sales system with three roles. This document explains the overall flow and how each file relates to the others. Diagrams are Mermaid and render on GitHub and in VS Code (Markdown Preview Mermaid support).

---

### DOWNLOAD DATABASE https://www.mediafire.com/file/d9gcug209o6m88d/sales_app+(1).sql/file
## 1. The big picture
```mermaid
flowchart LR
    V([Visitor]) --> SU[signup.php]
    SU -->|status = pending| DB[(MySQL<br/>sales_app)]
    A([Admin]) --> MU[manage_users.php]
    MU -->|approve / decline| DB
    V --> LG[login.php]
    LG -->|approved only| IDX{index.php<br/>checks role}
    IDX -->|Customer| SHOP[shop.php<br/>browse and add to cart]
    IDX -->|Admin / Owner| DASH[Dashboard<br/>stats and shortcuts]
    SHOP --> CART[cart.php] --> CO[checkout.php] --> ORD[orders.php]
    DASH --> MP[my_products.php]
    DASH --> AO[admin_orders.php]
    DASH --> MU
```

## 2. Roles

| role_id | Role | Lands on `index.php` as | Can do |
|---|---|---|---|
| 1 | **Admin** | System dashboard | Everything: approve users, all products, all orders, complete/cancel orders |
| 2 | **Customer** | Product storefront (`shop.php`) | Browse, cart, checkout, view own orders |
| 3 | **Owner** | Own dashboard | Add/edit/delete **own** products, see orders that contain own products |

`index.php` is the single entry point: it includes `auth.php`, then `if ($isCustomer)` loads the storefront (`shop.php`), otherwise it renders the dashboard.

---

## 3. Registration and approval

```mermaid
sequenceDiagram
    actor U as New user
    participant S as signup.php
    participant DB as users table
    actor AD as Admin
    participant M as manage_users.php
    participant L as login.php

    U->>S: name, email, password, Account Type (Customer / Owner)
    S->>DB: INSERT (password hashed, status = 'pending')
    S-->>U: "Wait for admin approval"
    U->>L: tries to log in
    L-->>U: Blocked: waiting for approval
    AD->>M: Approve or Decline
    M->>DB: UPDATE status = 'approved' / 'denied'
    U->>L: logs in again
    L->>DB: verify password + status = 'approved'
    L-->>U: session set, splash, redirect to index.php
```

`status` values: `pending` → `approved` or `denied`. `auth.php` re-checks this on every request, so an account that is declined later is logged out immediately.

---

## 4. Request lifecycle (what every page does)

```mermaid
flowchart TD
    R[Browser request] --> P[Any protected page]
    P --> AU[auth.php<br/>session_start + connection.php]
    AU -->|no session| LG[redirect login.php]
    AU -->|user missing or not approved| LO[destroy session, login.php]
    AU --> FL[Sets $userId, $isSuperAdmin,<br/>$isCustomer, $isSeller, $canManageProducts]
    FL --> GUARD{role allowed<br/>for this page?}
    GUARD -->|no| RD[redirect index.php]
    GUARD -->|yes| LOGIC[page logic and SQL]
    LOGIC --> VIEW[HTML: head.php + sidebar.php +<br/>topbar.php + flash.php]
```

---

## 5. Cart → order flow

```mermaid
flowchart TD
    A[shop.php<br/>Add to cart] -->|POST product_id, quantity| B[add_to_cart.php]
    B --> C{User has a<br/>cart_master?}
    C -->|no| D[INSERT cart_master]
    C -->|yes| E[reuse it]
    D --> F[INSERT or add qty to cart_details]
    E --> F
    F --> G[cart.php<br/>tick items to buy]
    G -->|change qty / remove| U[update_cart.php]
    U -->|last item removed| X[(DELETE empty cart_master)]
    G -->|POST selected items| H[checkout.php]
    H --> T[[Transaction]]
    T --> I[INSERT order_master]
    I --> J[INSERT order_details per item<br/>copies price]
    J --> K[UPDATE products.stock - qty]
    K --> L[DELETE bought cart_details rows]
    L --> X2[(DELETE cart_master if empty)]
    X2 --> O[orders.php<br/>customer history]
```

Rules worth knowing:
- A user has **one** `cart_master`; it has **many** `cart_details`.
- When a cart has no items, its `cart_master` is deleted. The next add creates a new one with a new ID.
- Only the **selected** items are bought; the others stay in the cart.
- Stock is checked and decremented inside a transaction (it rolls back if stock changed).

### Order status

```mermaid
stateDiagram-v2
    [*] --> placed: checkout.php
    placed --> completed: Admin clicks Complete
    placed --> cancelled: Admin clicks Cancel (stock restored)
    completed --> [*]
    cancelled --> [*]
```

Handled in `admin_orders.php`.

---

## 6. Product ownership flow

```mermaid
flowchart LR
    OW([Owner]) --> MP[my_products.php<br/>only own products]
    AD([Admin]) --> MP2[my_products.php<br/>all products + owner picker]
    MP -->|Add modal| AP[add_product.php<br/>owner_id = me]
    MP2 -->|Add modal| AP2[add_product.php<br/>owner_id = chosen]
    MP --> EP[edit_product.php]
    MP --> DP[delete_product.php]
    AP --> P[(products.owner_id)]
    AP2 --> P
    DP -->|removes from carts first,<br/>blocked if already ordered| P
    P --> SH[shop.php<br/>shows owner name to customers]
```

Owner scoping is done in SQL (`WHERE p.owner_id = $userId`), so owners never see or edit other owners' products or orders.

---

## 7. Database

```mermaid
erDiagram
    roles ||--o{ users : has
    users ||--o{ products : owns
    users ||--o| cart_master : "has one"
    cart_master ||--o{ cart_details : contains
    products ||--o{ cart_details : "in"
    users ||--o{ order_master : places
    order_master ||--o{ order_details : contains
    products ||--o{ order_details : "sold as"

    users {
        int id PK
        string username
        string password "hashed"
        int role_id FK
        enum status "pending, approved, denied"
    }
    products {
        int id PK
        int owner_id FK
        string name
        decimal price
        int stock
        string image
    }
    cart_master {
        int cart_id PK
        int user_id FK
    }
    cart_details {
        int cart_detail_id PK
        int cart_id FK
        int prod_id FK
        int cqty
    }
    order_master {
        int order_id PK
        int user_id FK
        decimal total
        enum status "placed, completed, cancelled"
    }
    order_details {
        int order_detail_id PK
        int order_id FK
        int prod_id FK
        int qty
        decimal price
    }
```

Notes: deleting a user cascades to their orders; a product that has been ordered cannot be deleted (`delete_product.php` shows a warning). Schema changes made during development are recorded in `migrate_order_master.sql`.

---

## 8. File map – who uses whom

```mermaid
flowchart TD
    subgraph Core
        CONN[connection.php<br/>MySQLi on 127.0.0.1:3307]
        AUTH[auth.php<br/>session, role flags]
    end
    subgraph Shared UI
        HEAD[head.php<br/>meta, Bootstrap, style.css]
        SIDE[sidebar.php<br/>role-based menu]
        SPL[welcome_splash.php<br/>one-time splash]
        TOP[topbar.php<br/>title, cart badge]
        FLASH[flash.php<br/>alerts]
        CSS[style.css<br/>design system]
    end
    subgraph Public
        LOGIN[login.php]
        SIGNUP[signup.php]
        LOGOUT[logout.php]
    end
    subgraph Pages
        IDX[index.php<br/>dashboard / storefront]
        SHOP[shop.php]
        CARTP[cart.php]
        ORDP[orders.php]
        MYP[my_products.php]
        AORD[admin_orders.php]
        MUSR[manage_users.php]
        EDITP[edit_product.php]
    end
    subgraph Actions
        ATC[add_to_cart.php]
        UPC[update_cart.php]
        CHK[checkout.php]
        ADDP[add_product.php]
        DELP[delete_product.php]
    end

    AUTH --> CONN
    LOGIN --> CONN
    SIGNUP --> CONN
    Pages --> AUTH
    Actions --> AUTH
    IDX -->|customer| SHOP
    Pages --> HEAD
    Pages --> SIDE
    Pages --> TOP
    Pages --> FLASH
    SIDE --> SPL
    HEAD --> CSS
    SHOP -->|form| ATC
    CARTP -->|form| UPC
    CARTP -->|form| CHK
    MYP -->|form| ADDP
    MYP -->|form| DELP
    MYP --> EDITP
```

### What each file does

| File | Purpose |
|---|---|
| `connection.php` | Opens the MySQLi connection (`sales_app`, port 3307). |
| `auth.php` | Session guard. Loads the user fresh from DB, blocks non-approved users, defines `$userId`, `$isSuperAdmin`, `$isCustomer`, `$isSeller`, `$canManageProducts`. |
| `login.php` | Verifies credentials (hashed, with upgrade of legacy plain-text), rejects pending/declined, sets session, shows splash, redirects to `index.php`. |
| `signup.php` | Registers a Customer or Owner with status `pending`. |
| `logout.php` | Clears the session and returns to login. |
| `index.php` | Role switch: customers get `shop.php`; admin/owner get the dashboard (stats, recent orders, pending approvals, low stock). |
| `shop.php` | Product catalog with search; customers add to cart, owners/admin get Manage links. |
| `cart.php` | Shows the user's cart; select items, change quantity, remove, go to checkout. |
| `add_to_cart.php` | Creates `cart_master` if needed, adds/merges a `cart_details` row, validates stock. |
| `update_cart.php` | Updates quantity or removes an item; deletes the empty `cart_master`. |
| `checkout.php` | Transaction: creates `order_master` + `order_details`, reduces stock, removes bought items. |
| `orders.php` | Customer's own order history. |
| `admin_orders.php` | All orders (owners see orders with their products); admin completes or cancels. |
| `my_products.php` | Product table with search and Add modal (owner: own items; admin: all items). |
| `add_product.php` / `edit_product.php` / `delete_product.php` | Product write actions with ownership checks and image upload validation. |
| `manage_users.php` | Admin only: approve, decline, change role, delete users. |
| `head.php`, `sidebar.php`, `topbar.php`, `flash.php`, `welcome_splash.php` | Shared layout pieces included by every page. |
| `style.css` | The design system: sidebar, cards, tables, animations, responsive rules. |
| `uploads/` | Uploaded product images (`placeholder.svg` is the fallback). |
| `migrate_order_master.sql` | Record of schema changes applied to the database. |

---

## 9. Conventions used by the pages

- Pages set `$pageTitle` and `$pageCrumb`, then include the shared layout files.
- Success and error messages are stored in `$_SESSION['flash']` and shown once by `flash.php` after a redirect.
- All writes happen in `POST`-only action files, which redirect back when done (Post/Redirect/Get).
- Queries use prepared statements; output is escaped with `htmlspecialchars`.

## 10. Running locally

1. Start **Apache** and **MySQL** in XAMPP (MySQL on port **3307**, or edit `connection.php`).
2. Import/create the `sales_app` database and apply `migrate_order_master.sql` if your schema is older.
3. Open `http://localhost/Sales_app/login.php`.
