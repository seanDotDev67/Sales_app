# Database

Database name: `sales_app` (MariaDB 10.4, port 3307).

## Relationships

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
        string f_name
        string l_name
        string username
        string email
        string password "hashed"
        int role_id FK
        enum status "pending, approved, denied"
        timestamp created_at
    }
    products {
        int id PK
        int owner_id FK
        string name
        string description
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
        timestamp created_at
    }
    order_details {
        int order_detail_id PK
        int order_id FK
        int prod_id FK
        int qty
        decimal price
    }
```

## Roles

| id | Name |
|---|---|
| 1 | Admin |
| 2 | Customer |
| 3 | Owner (stored as "Seller" in the table, shown as "Owner") |

## Which file touches which table

C = insert, R = read, U = update, D = delete.

| File | users | products | cart_master | cart_details | order_master | order_details |
|---|---|---|---|---|---|---|
| `auth.php` | R | | | | | |
| `login.php` | R, U (password upgrade) | | | | | |
| `signup.php` | C | | | | | |
| `manage_users.php` | R, U, D | | | | | |
| `index.php` (dashboard) | R | R | | | R | R |
| `shop.php` | R | R | | | | |
| `add_to_cart.php` | R | R | C, R | C, U, R | | |
| `cart.php` | R | R | R | R | | |
| `update_cart.php` | | R | R, D | U, D | | |
| `checkout.php` | | R, U (stock) | R, D | R, D | C | C |
| `orders.php` | R | R | | | R | R |
| `admin_orders.php` | R | U (restore stock) | | | R, U | R |
| `my_products.php` | R | R | | | | R |
| `add_product.php` | R | C | | | | |
| `edit_product.php` | R | R, U | | | | |
| `delete_product.php` | | R, D | D | D | | R |
| `topbar.php` | | | R | R | | |

## Foreign key behaviour

| Relation | On delete |
|---|---|
| `products.owner_id` to `users` | `SET NULL`: product stays, owner shown as unknown |
| `order_master.user_id` to `users` | `CASCADE`: deleting a user deletes their orders |
| `order_details.prod_id` to `products` | no cascade: an ordered product cannot be deleted |
| `cart_details.cart_id` to `cart_master` | cart items belong to one cart |

## Data lifecycle example

```mermaid
sequenceDiagram
    participant U as users
    participant CM as cart_master
    participant CD as cart_details
    participant P as products
    participant OM as order_master
    participant OD as order_details

    Note over U: frank (id 3) is approved
    CD->>CM: add item 1 creates cart_master #7
    CD->>CD: row (7, product 5, qty 2)
    Note over CM,CD: checkout selected items
    OM->>OM: INSERT order #2 total 128.00 status placed
    OD->>OD: INSERT (2, product 5, qty 2, price 5.00)
    P->>P: stock 9 becomes 7
    CD->>CD: DELETE row (7, product 5)
    CM->>CM: DELETE #7 (no items left)
    Note over CM: next add creates cart_master #8
```

Schema changes made during development are recorded in `migrate_order_master.sql` in the project root.
