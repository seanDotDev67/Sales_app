# Products and Orders

## Who can do what

| Action | Admin | Owner | Customer |
|---|---|---|---|
| See all products (`my_products.php`) | Yes | Only own | No |
| Add product | Yes, pick any owner | Yes, owner is themselves | No |
| Edit / delete product | Any | Only own | No |
| See orders (`admin_orders.php`) | All | Only those containing own products | No (uses `orders.php`) |
| Complete / cancel an order | Yes | No | No |

Owner scoping is done in SQL with `WHERE p.owner_id = <user id>`, so an owner never receives another owner's rows.

## Add a product

```mermaid
sequenceDiagram
    actor U as Owner or Admin
    participant M as my_products.php (Add modal)
    participant A as add_product.php
    participant FS as uploads/ folder
    participant DB as MySQL

    U->>M: fill name, price, stock, description, image, (owner if admin)
    M->>A: POST multipart/form-data
    A->>A: auth.php, require admin or owner
    opt admin picked an owner
        A->>DB: SELECT id FROM users WHERE id = ? AND role_id IN (1,3) AND status = 'approved'
        DB-->>A: row means owner is valid
    end
    A->>A: validate name, price >= 0, stock >= 0
    opt image selected
        A->>A: check size <= 2 MB and real MIME (jpg, png, webp)
        A->>FS: save as random 32-hex filename
    end
    A->>DB: INSERT INTO products (owner_id, name, description, price, stock, image) VALUES (?,?,?,?,?,?)
    DB-->>A: ok
    A-->>U: 302 to my_products.php with flash
```

## Edit a product (`edit_product.php`)

1. `SELECT id, owner_id, name, description, price, stock, image FROM products WHERE id = ?`
2. Owner check: an owner whose ID is not `owner_id` is redirected away.
3. On save: `UPDATE products SET owner_id, name, description, price, stock[, image] WHERE id = ?`

## Delete a product (`delete_product.php`)

```mermaid
flowchart TD
    A[POST id] --> B{Product exists<br/>and user allowed?}
    B -->|no| X[redirect with error]
    B -->|yes| C{Already in an order?<br/>order_details.prod_id}
    C -->|yes| W[Blocked: warning shown<br/>history must stay intact]
    C -->|no| D[BEGIN]
    D --> E[DELETE FROM cart_details WHERE prod_id = ?]
    E --> F[DELETE FROM products WHERE id = ?]
    F --> G[DELETE empty cart_master rows]
    G --> H[COMMIT and delete image file]
```

## Admin and owner order handling

```mermaid
sequenceDiagram
    actor AD as Admin
    participant O as admin_orders.php
    participant DB as MySQL

    AD->>O: GET
    O->>DB: SELECT om.order_id, om.status, om.created_at, om.total, u.username, u.f_name, u.l_name ... FROM order_master om JOIN users u ...
    DB-->>O: orders
    O->>DB: SELECT od.order_id, od.qty, od.price, p.name, o.username AS owner_name FROM order_details od JOIN products p ... LEFT JOIN users o ...
    DB-->>O: line items
    O-->>AD: order cards with Complete / Cancel buttons
    AD->>O: POST order_id, status = cancelled
    O->>DB: UPDATE order_master SET status = ? WHERE order_id = ? AND status = 'placed'
    DB-->>O: 1 row affected
    opt cancelled
        O->>DB: UPDATE products p JOIN order_details od ON od.prod_id = p.id SET p.stock = p.stock + od.qty WHERE od.order_id = ?
    end
    O-->>AD: redirect with flash
```

The `AND status = 'placed'` guard means an order can only change once. Double clicking Cancel cannot restore stock twice.

```mermaid
stateDiagram-v2
    [*] --> placed: checkout.php
    placed --> completed: Admin Complete
    placed --> cancelled: Admin Cancel (stock restored)
    completed --> [*]
    cancelled --> [*]
```

## Dashboard data (`index.php` for admin/owner)

| Card | Source |
|---|---|
| Users, Pending (admin) | `COUNT(*)` on `users`, and where `status = 'pending'` |
| Products | `COUNT(*)` on `products` (owner: where `owner_id = me`) |
| Orders, Revenue | `order_master` / `order_details` totals (owner: only lines of own products) |
| Recent orders | latest rows of `order_master` joined with `users` |
| Pending approvals | `users` where `status = 'pending'`, with Approve / Decline buttons |
| Low stock | `products` where `stock <= 5` |
