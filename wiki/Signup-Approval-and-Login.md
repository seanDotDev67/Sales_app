# Signup, Approval and Login

## States of an account

```mermaid
stateDiagram-v2
    [*] --> pending: signup.php
    pending --> approved: Admin clicks Approve
    pending --> denied: Admin clicks Decline
    approved --> denied: Admin declines later
    denied --> approved: Admin approves again
    approved --> [*]: Admin deletes user
```

## 1. Signup

```mermaid
sequenceDiagram
    actor U as Visitor
    participant S as signup.php
    participant DB as users table

    U->>S: f_name, l_name, email, username, password, account_type
    S->>S: validate, map account_type (customer=2, owner=3)
    S->>S: password_hash(password)
    S->>DB: INSERT INTO users (f_name,l_name,email,username,password,role_id,status) VALUES (?,?,?,?,?,?,'pending')
    DB-->>S: ok (or duplicate username/email error)
    S-->>U: redirect to login.php with notice "wait for admin approval"
```

## 2. Admin approval

```mermaid
sequenceDiagram
    actor AD as Admin
    participant M as manage_users.php
    participant DB as users table

    AD->>M: GET page
    M->>DB: SELECT id,f_name,l_name,email,username,role_id,status,created_at FROM users
    DB-->>M: all accounts
    M-->>AD: table with Approve / Decline / Change role / Delete
    AD->>M: POST action=update_status, user_id, status
    M->>DB: UPDATE users SET status = ? WHERE id = ?
    DB-->>M: 1 row affected
    M-->>AD: redirect back with flash message
```

Other actions on the same file: `change_role` runs `UPDATE users SET role_id = ?`, and `delete_user` runs `DELETE FROM users WHERE id = ?` (an admin cannot delete themselves).

## 3. Login

```mermaid
sequenceDiagram
    actor U as User
    participant L as login.php
    participant DB as users table
    participant I as index.php

    U->>L: POST username + password
    L->>DB: SELECT id, username, password, role_id, status FROM users WHERE username = ?
    DB-->>L: row or nothing
    alt no row / wrong password
        L-->>U: "Invalid username or password."
    else status = pending
        L-->>U: "Waiting for administrator approval."
    else status = denied
        L-->>U: "Your account request was declined."
    else approved
        opt legacy plain-text password
            L->>DB: UPDATE users SET password = hash WHERE id = ?
        end
        L->>L: session_regenerate_id(true)
        L->>L: set user_id, username, role_id, show_splash
        L-->>U: 302 to index.php
        U->>I: GET index.php
        I-->>U: Customer: storefront, Admin/Owner: dashboard, plus one-time splash
    end
```

## Who sees what after login

```mermaid
flowchart TD
    IDX[index.php] --> R{role_id}
    R -->|2 Customer| SH[require shop.php<br/>product catalog]
    R -->|1 Admin| AD[Dashboard: all users, orders, revenue,<br/>pending approvals, low stock]
    R -->|3 Owner| OW[Dashboard: own products,<br/>orders containing own products]
```

## Logout

`logout.php` empties `$_SESSION`, expires the cookie, destroys the session and redirects to `login.php`.
