# Trendy Store - XAMPP MySQL Backend Version

This version keeps the original Trendy Store UI and adds a PHP + MySQL backend.

## Project structure

```text
TrendyStore/
├── index.html
├── login.html
├── profile.html
├── addtocard.html
├── buynow.html
├── wishlist.html
├── Contactus.html
├── InquiryMenu.html
├── database.sql
├── js/
│   └── backend.js
├── api/
│   ├── db.php
│   ├── test.php
│   ├── register.php
│   ├── me.php
│   ├── logout.php
│   ├── products.php
│   ├── cart.php
│   ├── wishlist.php
│   ├── profile.php
│   ├── order.php
│   ├── contact.php
│   └── inquiry.php
└── img/
    └── original project images
```

## 1. Put the project in XAMPP

Extract this folder to:

`C:\xampp\htdocs\TrendyStore`

Start Apache and MySQL from XAMPP.

Then open:

`http://localhost/TrendyStore/`

Do not open the HTML files by double-clicking them.

## 2. Create the database

Open:

`http://localhost/phpmyadmin`

Go to **SQL**, paste the complete contents of `database.sql`, and click **Go**.

The SQL creates:

- `trendy_store`
- `users`
- `products`
- `wishlist`
- `cart`
- `orders`
- `order_items`
- `contact_messages`
- `inquiries`

It also inserts the products that were present in the uploaded Trendy Store project.

## 3. Check the PHP/MySQL connection

Open:

`http://localhost/TrendyStore/api/test.php`

You should get a JSON response saying:

`Database connection successful.`

## 4. Default XAMPP database settings

`api/db.php` uses:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `trendy_store`
- Username: `root`
- Password: empty

If your XAMPP MySQL uses another port, change `$port` in `api/db.php`.

## 5. Registration

The existing `login.html` is the registration page in the uploaded project.

It now sends registration data to:

`api/register.php`

Passwords are stored with PHP `password_hash()` and are not stored in browser localStorage.

## 6. Login/session behavior

The PHP session is used to identify the logged-in user.

The browser calls:

`api/me.php`

to check the current session.

Logout calls:

`api/logout.php`

## 7. Products

Products are loaded from MySQL through:

`api/products.php`

The browser no longer needs the hard-coded product list for displaying the store.

## 8. Cart and wishlist

Cart:

`api/cart.php`

Wishlist:

`api/wishlist.php`

Both are associated with the logged-in user's database ID.

## 9. Orders

Checkout sends order information to:

`api/order.php`

The PHP backend calculates the final product prices from the database instead of trusting prices sent by the browser.

Card number and CVV are not stored in MySQL.

## 10. Contact and inquiry

Contact messages are stored by:

`api/contact.php`

Inquiries are stored by:

`api/inquiry.php`

## Important

If MySQL still shows "shutdown unexpectedly", fix that XAMPP MySQL/InnoDB problem first. The PHP files cannot connect until MySQL is running.
