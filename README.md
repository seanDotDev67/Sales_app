# 🛒 Sales App

> **A simple PHP-based Sales Management System** for managing products, shopping carts, checkout, and user authentication.

Built with **PHP + MySQL** and designed to run locally using **XAMPP/WAMP**.

---

## ✨ Features

| Feature | Description |
|---|---|
| 🔐 **Authentication** | User registration, login, and logout |
| 📦 **Product Management** | Add and edit product information |
| 🛒 **Shopping Cart** | Add products and manage quantities |
| 💳 **Checkout** | Process items in the shopping cart |
| 🖼️ **Image Uploads** | Upload and display product images |
| 🗄️ **MySQL Database** | Persistent storage for users, products, and sales |

---

## 🛠️ Tech Stack

| Technology | Purpose |
|---|---|
| 🐘 **PHP** | Backend / application logic |
| 🗄️ **MySQL** | Database |
| 🌐 **HTML / CSS** | User interface |
| ⚡ **JavaScript** | Client-side interactions |
| 🚀 **Apache** | Local web server |
| 🔧 **Git** | Version control |
| 🖥️ **XAMPP / WAMP** | Local development environment |

---

## 📋 Prerequisites

Before running the project, make sure you have:

- [x] **Git**
- [x] **XAMPP** or **WAMP**
- [x] **PHP**
- [x] **MySQL**
- [x] A modern web browser

---

# 🚀 Getting Started

Follow the steps below to run the project locally.

## 1️⃣ Clone the Repository

### XAMPP

Open Command Prompt or Terminal:

```bash
cd C:/xampp/htdocs

git clone https://github.com/seanDotDev67/Sales_app.git

cd Sales_app
```

### WAMP

Clone the repository inside your WAMP `www` directory:

```bash
cd C:/wamp64/www

git clone https://github.com/seanDotDev67/Sales_app.git

cd Sales_app
```

---

## 2️⃣ Download the Database

Download the project's SQL database:

**📥 Database:**  
https://www.mediafire.com/file/pcuga5rsildcz5u/sales_project+(1).sql/file

Save the downloaded file as:

```text
sales_project.sql
```

You can place it inside the project directory or keep it in your Downloads folder.

---

# 🗄️ 3️⃣ Setup the Database

### Start XAMPP

Open the **XAMPP Control Panel** and start:

```text
Apache   🟢
MySQL    🟢
```

Then open:

```text
http://localhost/phpmyadmin
```

### Create the Database

1. Click **New** in the left sidebar.
2. Enter:

```text
sales_project
```

3. Click **Create**.

### Import the SQL File

1. Select `sales_project` from the left sidebar.
2. Open the **Import** tab.
3. Click **Choose File**.
4. Select:

```text
sales_project.sql
```

5. Scroll down.
6. Click **Import** / **Go**.

If successful, the project's tables should appear under the `sales_project` database.

---

# 🔌 4️⃣ Configure Database Connection

Open:

```text
connection.php
```

Make sure the database configuration matches your local MySQL setup.

Example:

```php
<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "sales_project";

$conn = mysqli_connect(
    $host,
    $user,
    $password,
    $database
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
```

> ⚠️ **Note:** If your MySQL server uses a different username, password, or port, update the configuration accordingly.

---

# 🌐 5️⃣ Run the Application

Once Apache and MySQL are running, open:

```text
http://localhost/Sales_app/index.php
```

🎉 **The Sales App should now be running locally!**

---

# 📁 Project Structure

```text
Sales_app/
│
├── 📂 uploads/
│   └── Product image uploads
│
├── 📄 add_product.php
├── 📄 add_to_cart.php
├── 📄 cart.php
├── 📄 checkout.php
├── 📄 connection.php
├── 📄 edit_product.php
├── 📄 index.php
├── 📄 login.php
├── 📄 logout.php
├── 📄 signup.php
└── 📄 update_cart.php
```

### 📌 Important Files

| File | Purpose |
|---|---|
| `index.php` | Main page / dashboard |
| `login.php` | User login |
| `signup.php` | User registration |
| `logout.php` | Ends the user session |
| `add_product.php` | Adds new products |
| `edit_product.php` | Updates product information |
| `add_to_cart.php` | Adds products to cart |
| `cart.php` | Displays shopping cart |
| `update_cart.php` | Updates cart quantities |
| `checkout.php` | Handles checkout |
| `connection.php` | Database connection |
| `uploads/` | Stores uploaded product images |

---

# 🔄 Application Flow

```text
          ┌──────────────┐
          │    Login     │
          │ / Sign Up    │
          └──────┬───────┘
                 │
                 ▼
        ┌─────────────────┐
        │    Dashboard    │
        └────────┬────────┘
                 │
        ┌────────┴────────┐
        ▼                 ▼
 ┌─────────────┐   ┌─────────────┐
 │   Products  │   │ Shopping    │
 │ Management  │   │    Cart     │
 └──────┬──────┘   └──────┬──────┘
        │                  │
        │                  ▼
        │           ┌─────────────┐
        │           │  Checkout   │
        │           └──────┬──────┘
        │                  │
        └──────────┬───────┘
                   ▼
             ┌─────────────┐
             │   MySQL DB  │
             └─────────────┘
```

---

# 🧪 Local Development

When developing locally, make sure:

```text
Apache  → Running
MySQL   → Running
Database → sales_project
Project → C:/xampp/htdocs/Sales_app
```

Then access:

```text
http://localhost/Sales_app/
```

---

# 🐛 Troubleshooting

### ❌ Database Connection Failed

Check:

- MySQL is running.
- Database name is `sales_project`.
- Username/password in `connection.php` are correct.
- Your MySQL port matches your local configuration.

---

### ❌ Page Not Found

Make sure the project is inside:

```text
C:/xampp/htdocs/
```

Then verify:

```text
C:/xampp/htdocs/Sales_app/
```

Access it through:

```text
http://localhost/Sales_app/
```

---

### ❌ Product Images Not Showing

Make sure the following directory exists:

```text
Sales_app/uploads/
```

Also make sure Apache/PHP has permission to write files into the directory.

---

# 🤝 Contributing

Contributions and improvements are welcome.

If you're working with a team, create a separate branch instead of directly modifying `main`:

```bash
git checkout -b feature/your-feature
```

After making your changes:

```bash
git add .
git commit -m "Add your feature"
git push origin feature/your-feature
```

Then open a **Pull Request** on GitHub.

### Suggested Branches

```text
main
│
├── feature/product-management
├── feature/shopping-cart
├── feature/checkout
└── fix/login-validation
```

> 💡 Keep `main` stable and use feature branches for development.

---

# 📌 Project Status

🟢 **Active Development**

This project is intended for learning and demonstrating fundamental concepts in:

- PHP development
- MySQL database integration
- CRUD operations
- Session-based authentication
- Shopping cart systems
- Basic sales workflows
- Git/GitHub collaboration

---

## 👨‍💻 Author

**Sean**

GitHub:  
https://github.com/seanDotDev67

---

## 📄 License

This project is intended for **educational purposes**.

---

<p align="center">
  Built with ❤️ using PHP & MySQL
</p>
