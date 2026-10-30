# Request and Response Lifecycle

Every protected page follows the same path. Understanding this once explains the whole app.

## Page load (GET)

```mermaid
sequenceDiagram
    actor B as Browser
    participant P as Page (e.g. cart.php)
    participant A as auth.php
    participant DB as MySQL
    participant L as Layout includes

    B->>P: GET /Sales_app/cart.php (+ PHPSESSID cookie)
    P->>A: require_once 'auth.php'
    A->>A: session_start(), read $_SESSION['user_id']
    alt no session
        A-->>B: 302 redirect to login.php
    end
    A->>DB: SELECT role_id, username, status FROM users WHERE id = ?
    DB-->>A: one row (role_id=2, username='frank', status='approved')
    alt row missing or status != approved
        A-->>B: session destroyed, 302 to login.php
    end
    A-->>P: $userId, $isSuperAdmin, $isCustomer, $isSeller, $canManageProducts
    P->>P: role guard (wrong role = redirect to index.php)
    P->>DB: page-specific SELECT (prepared statement)
    DB-->>P: result rows
    P->>L: include head.php, sidebar.php, topbar.php, flash.php
    L->>DB: topbar.php: SELECT COALESCE(SUM(cqty),0) ... (cart badge)
    DB-->>L: number
    P-->>B: 200 HTML (rows rendered, values escaped)
```

Key point: the role is **re-read from the database on every request**. If an admin declines or changes a user, it takes effect on that user's next click.

## Form submit (POST, then redirect)

```mermaid
sequenceDiagram
    actor B as Browser
    participant X as Action file (e.g. add_to_cart.php)
    participant DB as MySQL
    participant F as $_SESSION['flash']
    participant P as Page (cart.php)

    B->>X: POST form fields
    X->>X: auth.php + validate input
    X->>DB: BEGIN, INSERT / UPDATE / DELETE (prepared)
    DB-->>X: affected rows / insert_id
    X->>DB: COMMIT
    X->>F: store success or error message
    X-->>B: 302 redirect to a page
    B->>P: GET that page
    P->>F: read and clear flash
    P-->>B: HTML with the alert
```

This is the **Post/Redirect/Get** pattern: refreshing the page never re-submits the form.

## Shared files in each request

| Step | File | Role |
|---|---|---|
| 1 | `connection.php` | Opens the MySQLi connection (`127.0.0.1:3307`, db `sales_app`) |
| 2 | `auth.php` | Session guard, user lookup, role flags |
| 3 | page file | Guard, queries, builds data |
| 4 | `head.php` | `<head>`, Bootstrap, `style.css` (versioned by file time) |
| 5 | `sidebar.php` | Role-based menu; includes `welcome_splash.php` |
| 6 | `topbar.php` | Title, breadcrumb, cart count |
| 7 | `flash.php` | One-time alert from the session |
