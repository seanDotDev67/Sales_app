# Cart and Checkout

A user has **one** `cart_master`, and it has **many** `cart_details`. When the last item leaves the cart, the `cart_master` row is deleted; the next add creates a new one with a new ID.

## Overview

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
    U -->|cart now empty| X[(DELETE empty cart_master)]
    G -->|"POST selected items"| H[checkout.php]
    H --> T[[Transaction]]
    T --> I[INSERT order_master]
    I --> J[INSERT order_details per item<br/>copies the price]
    J --> K[UPDATE products SET stock = stock - qty]
    K --> L[DELETE bought cart_details rows]
    L --> X2[(DELETE cart_master if empty)]
    X2 --> O[orders.php]
```

## 1. Add to cart (`add_to_cart.php`)

```mermaid
sequenceDiagram
    actor C as Customer
    participant A as add_to_cart.php
    participant DB as MySQL

    C->>A: POST product_id=5, quantity=2
    A->>DB: BEGIN
    A->>DB: SELECT id, name, stock FROM products WHERE id = 5 FOR UPDATE
    DB-->>A: (5, 'Cable Organizer', 9)
    A->>DB: SELECT status FROM users WHERE id = ?
    DB-->>A: 'approved'
    A->>DB: SELECT cart_id FROM cart_master WHERE user_id = ? ORDER BY cart_id DESC LIMIT 1
    alt no cart yet
        DB-->>A: no rows
        A->>DB: INSERT INTO cart_master (user_id) VALUES (?)
        DB-->>A: insert_id = 7
    else cart exists
        DB-->>A: cart_id = 7
    end
    A->>DB: SELECT cqty FROM cart_details WHERE cart_id = 7 AND prod_id = 5
    DB-->>A: 0 (or the quantity already in the cart)
    A->>A: in cart + new quantity must be <= stock
    A->>DB: INSERT INTO cart_details (cart_id, prod_id, cqty) VALUES (7,5,2) ON DUPLICATE KEY UPDATE cqty = cqty + VALUES(cqty)
    A->>DB: COMMIT
    A-->>C: 302 back with flash "added to cart"
```

`cart_details` has a unique key on (`cart_id`, `prod_id`), so adding the same product twice increases the quantity instead of creating a second row.

## 2. View cart (`cart.php`)

```mermaid
sequenceDiagram
    actor C as Customer
    participant P as cart.php
    participant DB as MySQL

    C->>P: GET
    P->>DB: SELECT cd.cart_detail_id, cd.prod_id, cd.cqty, p.name, p.price, p.stock, p.image, u.username AS owner_name FROM cart_master cm JOIN cart_details cd ... JOIN products p ... LEFT JOIN users u ... WHERE cm.user_id = ?
    DB-->>P: one row per cart item
    P-->>C: table with checkboxes, quantity forms, live total
```

Example result the page receives:

| cart_detail_id | prod_id | cqty | name | price | stock | owner_name |
|---|---|---|---|---|---|---|
| 12 | 5 | 2 | Cable Organizer | 5.00 | 9 | Sean |
| 13 | 6 | 1 | Pics | 123.00 | 20 | Sean |

## 3. Update or remove (`update_cart.php`)

| Action | SQL |
|---|---|
| Change quantity | `UPDATE cart_details SET cqty = ? WHERE cart_id = ? AND prod_id = ?` (after checking stock) |
| Remove item | `DELETE FROM cart_details WHERE cart_id = ? AND prod_id = ?` |
| Then, always | `DELETE FROM cart_master WHERE cart_id NOT IN (SELECT cart_id FROM cart_details)` |

## 4. Checkout (`checkout.php`)

Only the ticked items (`selected[]`) are bought. Everything runs in **one transaction**: if anything fails, nothing is saved.

```mermaid
sequenceDiagram
    actor C as Customer
    participant K as checkout.php
    participant DB as MySQL

    C->>K: POST selected[] = [5, 6]
    K->>DB: BEGIN
    K->>DB: SELECT cart_id FROM cart_master WHERE user_id = ?
    DB-->>K: cart_id = 7
    K->>DB: SELECT cd.prod_id, cd.cqty, p.name, p.price, p.stock FROM cart_details cd JOIN products p ... WHERE cd.cart_id = 7 AND cd.prod_id IN (5,6)
    DB-->>K: 2 rows (price and stock as of right now)
    K->>K: check qty <= stock, total = SUM(price * qty)
    K->>DB: INSERT INTO order_master (user_id, total) VALUES (?, 128.00)
    DB-->>K: order_id = 2
    loop each selected item
        K->>DB: INSERT INTO order_details (order_id, prod_id, qty, price) VALUES (2, ?, ?, ?)
        K->>DB: UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?
        DB-->>K: affected_rows (0 means stock changed, so roll back)
    end
    K->>DB: DELETE FROM cart_details WHERE cart_id = 7 AND prod_id IN (5,6)
    K->>DB: DELETE FROM cart_master WHERE cart_id NOT IN (SELECT cart_id FROM cart_details)
    K->>DB: COMMIT
    K-->>C: 302 to orders.php, flash "Order #2 placed. Total: $128.00"
```

### Before and after

```mermaid
flowchart LR
    subgraph Before
        CM1[cart_master 7] --> CD1[cart_details: product 5 x2]
        CM1 --> CD2[cart_details: product 6 x1]
        P1[products: stock 9 and 20]
    end
    subgraph After
        OM[order_master 2<br/>total 128.00, status placed] --> OD1[order_details: product 5 x2]
        OM --> OD2[order_details: product 6 x1]
        P2[products: stock 7 and 19]
        GONE[cart_master 7 deleted<br/>because it is empty]
    end
    Before ==> After
```

`order_details.price` stores the price **at the time of purchase**, so later price edits do not change old orders.

## 5. Order history (`orders.php`)

```mermaid
sequenceDiagram
    actor C as Customer
    participant O as orders.php
    participant DB as MySQL

    C->>O: GET
    O->>DB: SELECT order_id, total, status, created_at FROM order_master WHERE user_id = ? ORDER BY order_id DESC
    DB-->>O: the customer's orders
    O->>DB: SELECT od.order_id, od.qty, od.price, p.name, u.username AS owner_name FROM order_details od JOIN products p ... LEFT JOIN users u ...
    DB-->>O: line items
    O-->>C: orders with their items and status badge
```
