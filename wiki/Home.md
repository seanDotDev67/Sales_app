# Sales Manager Wiki

A PHP + MySQL (MySQLi, Bootstrap 5) sales system with three roles. This wiki shows **how a browser request travels through the PHP files, what SQL runs, and what the database sends back**.

## Pages

| Page | What you will learn |
|---|---|
| [Request and Response Lifecycle](Request-and-Response-Lifecycle) | What happens on every single page load |
| [Signup, Approval and Login](Signup-Approval-and-Login) | Registration, admin approval, session creation |
| [Cart and Checkout](Cart-and-Checkout) | Cart master/details, buying, stock, order creation |
| [Products and Orders](Products-and-Orders) | Owner/admin product management and order handling |
| [Database](Database) | Tables, relations, and which file reads or writes each table |

## Roles at a glance

| role_id | Role | `index.php` shows |
|---|---|---|
| 1 | Admin | System dashboard |
| 2 | Customer | Product storefront (`shop.php`) |
| 3 | Owner | Own dashboard (own products and orders only) |

## The whole system in one picture

```mermaid
flowchart LR
    V([Visitor]) --> SU[signup.php]
    SU -->|status = pending| DB[(MySQL<br/>sales_app)]
    A([Admin]) --> MU[manage_users.php]
    MU -->|approve / decline| DB
    V --> LG[login.php]
    LG -->|approved only| IDX{index.php<br/>checks role}
    IDX -->|Customer| SHOP[shop.php]
    IDX -->|Admin / Owner| DASH[Dashboard]
    SHOP --> CART[cart.php] --> CO[checkout.php] --> ORD[orders.php]
    DASH --> MP[my_products.php]
    DASH --> AO[admin_orders.php]
    DASH --> MU
```

## File map: who includes whom

```mermaid
flowchart TD
    subgraph Core
        CONN[connection.php]
        AUTH[auth.php]
    end
    subgraph Shared UI
        HEAD[head.php]
        SIDE[sidebar.php]
        SPL[welcome_splash.php]
        TOP[topbar.php]
        FLASH[flash.php]
        CSS[style.css]
    end
    subgraph Pages
        IDX[index.php]
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
    Pages --> AUTH
    Actions --> AUTH
    IDX -->|customer| SHOP
    Pages --> HEAD & SIDE & TOP & FLASH
    SIDE --> SPL
    HEAD --> CSS
    SHOP -->|form| ATC
    CARTP -->|form| UPC
    CARTP -->|form| CHK
    MYP -->|form| ADDP
    MYP -->|form| DELP
    MYP --> EDITP
```

## Conventions

- Pages set `$pageTitle` / `$pageCrumb`, then include the shared layout files.
- Messages are stored in `$_SESSION['flash']` and shown once by `flash.php`.
- Writes happen in POST-only action files that redirect back afterwards (Post/Redirect/Get).
- Queries use prepared statements; output is escaped with `htmlspecialchars`.

## Running locally

1. Start **Apache** and **MySQL** in XAMPP (MySQL on port **3307**, or edit `connection.php`).
2. Create the `sales_app` database and apply `migrate_order_master.sql` if your schema is older.
3. Open `http://localhost/Sales_app/login.php`.
