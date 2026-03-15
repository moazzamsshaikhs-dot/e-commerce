# 🛒 ShopEase Pro - Complete Setup Guide
## E-Commerce Project - Fixed & Ready

---

## ✅ ERRORS FIXED

### Root Level Fixes
| File | Fix Applied |
|------|-------------|
| `includes/config.php` | ✅ Added `redirectToDashboard()` function |
| `includes/config.php` | ✅ Real PHPMailer OTP email implementation |
| `includes/config.php` | ✅ Added `logout` case in `sendSecurityAlert()` |
| `includes/config.php` | ✅ Added all CSS color aliases (`--primary`, `--success` etc.) |
| `includes/auth-check.php` | ✅ Fixed session timeout (last_activity based) |
| `includes/header.php` | ✅ Added all CSS root color aliases |
| `assets/css/style.css` | ✅ Added `--primary`, `--success` etc. aliases (matches admin colors) |
| `login.php` | ✅ Removed duplicate `redirectToDashboard()` function |
| `logout.php` | ✅ Fixed: session_destroy + session_start before success message |
| `index.php` | ✅ Fixed: Uses `includes/config.php` instead of raw `session_start()` |
| `index.php` | ✅ Fixed: Uncommented CSS root variables |
| `submit_review.php` | ✅ Fixed: INSERT includes `title` column |
| `product-details.php` | ✅ Fixed: Redirects use SITE_URL |
| `.htaccess` | ✅ Created with security headers |

### Admin Level Fixes
| File | Fix Applied |
|------|-------------|
| `admin/includes/config.php` | ✅ Fixed `redirectToDashboard()` to use SITE_URL |
| `admin/includes/header.php` | ✅ Added all CSS root color aliases |
| `admin/includes/auth-check.php` | ✅ Rewritten - proper session timeout |
| `admin/dashboard.php` | ✅ Fixed redirect to use SITE_URL |

### User Level Fixes
| File | Fix Applied |
|------|-------------|
| `user/dashboard.php` | ✅ Fixed typo: `'vender'` → `'vendor'` |

### Database
| File | Contents |
|------|----------|
| `database/ecommerce_db.sql` | Main database (4947 lines, 80+ tables) |
| `database/fix_missing_tables.sql` | Additional vendor tables |
| `database/complete_fixes.sql` | ✅ NEW: All ALTER TABLE fixes + missing tables |

---

## 🚀 XAMPP SETUP STEPS

### Step 1: Place Files
```
C:\xampp\htdocs\e-commerce\
```
Copy entire project folder here.

### Step 2: Database Setup
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Create database: **`ecommerce_db`**
3. Import in this order:
   - `database/ecommerce_db.sql` (main database)
   - `database/fix_missing_tables.sql` (vendor tables)
   - `database/complete_fixes.sql` (column fixes)

### Step 3: Configure Email (OTP)
In `includes/config.php`, update:
```php
define('SMTP_USER', 'your-gmail@gmail.com');
define('SMTP_PASS', 'your-gmail-app-password');
```
> **Note:** Use Gmail App Password (not your regular password)
> Gmail → Account → Security → 2FA → App Passwords

### Step 4: Install Composer Dependencies
```bash
cd C:\xampp\htdocs\e-commerce
composer install
```
This installs: PHPMailer, Stripe SDK, DomPDF, PayPal SDK

### Step 5: Create Admin Account
Run in phpMyAdmin:
```sql
INSERT INTO users (username, email, password, full_name, user_type, email_verified, account_status)
VALUES (
  'admin',
  'admin@shopeasepro.com',
  '$2y$10$YourHashedPasswordHere',
  'Admin User',
  'admin',
  1,
  'active'
);
```
Or use PHP to generate hash: `echo password_hash('Admin@123', PASSWORD_BCRYPT);`

### Step 6: Test URLs
| URL | Description |
|-----|-------------|
| `http://localhost/e-commerce/` | Homepage |
| `http://localhost/e-commerce/login.php` | Login |
| `http://localhost/e-commerce/signup.php` | Signup + OTP |
| `http://localhost/e-commerce/admin/dashboard.php` | Admin Panel |
| `http://localhost/e-commerce/admin/vendors/dashboard.php` | Vendor Panel |
| `http://localhost/e-commerce/user/dashboard.php` | User Panel |

---

## 🎨 COLOR SYSTEM

All pages now use unified CSS variables from `admin/products/products.php`:

```css
:root {
    --primary: #4361ee;      /* Main Blue */
    --success: #06d6a0;      /* Green */
    --warning: #ffb703;      /* Yellow */
    --danger:  #ef476f;      /* Red */
    --info:    #4cc9f0;      /* Light Blue */
    --dark:    #2b2d42;      /* Dark */
    --light:   #f8f9fa;      /* Light Gray */
}
```

---

## 📧 OTP EMAIL FLOW

```
User Signup → OTP Generated → PHPMailer sends email → verify-otp.php → Auto Login
```

OTP is also logged to PHP error log during development.

---

## 🔐 LOGIN FLOW

```
login.php → DB check → email_verified? → No? → send OTP → verify-otp.php
                                        → Yes? → Set Session → redirectToDashboard()
```

Redirects:
- `admin` → `admin/dashboard.php`
- `vendor` → `admin/vendors/dashboard.php`  
- `user` → `user/dashboard.php`

---

## 📂 COMPLETE FILE STRUCTURE

```
e-commerce/
├── index.php               ← Homepage (landing page)
├── login.php               ← Login
├── signup.php              ← Register + OTP trigger
├── logout.php              ← Logout
├── verify-otp.php          ← OTP verification
├── forgot-password.php     ← Password reset
├── product-details.php     ← Product page
├── category.php            ← Category listing
├── contact.php             ← Contact page
├── add-to-cart.php         ← Cart handler
├── add-to-wishlist.php     ← Wishlist handler
├── submit_review.php       ← Review handler
├── .htaccess               ← Security rules
├── composer.json           ← PHP dependencies
├── includes/
│   ├── config.php          ← Main config (DB, functions)
│   ├── header.php          ← Frontend header + navbar
│   ├── footer.php          ← Frontend footer + JS
│   ├── auth-check.php      ← Login required check
│   ├── admin-access-check  ← Admin only check
│   ├── vendor-access-check ← Vendor only check
│   └── payments/           ← Payment gateway classes
├── admin/
│   ├── dashboard.php       ← Admin dashboard
│   ├── products/           ← Product management
│   ├── orders/             ← Order management
│   ├── users/              ← User management
│   ├── vendors/            ← Vendor management
│   └── includes/           ← Admin config/header/footer
├── user/
│   ├── dashboard.php       ← User dashboard
│   ├── cart/               ← Shopping cart
│   ├── checkout/           ← Checkout
│   ├── orders/             ← Order history
│   └── wishlist/           ← Wishlist
├── assets/
│   ├── css/style.css       ← Global styles
│   ├── css/dashboard.css   ← Dashboard styles
│   └── js/                 ← JavaScript files
├── payment/
│   └── index.php           ← Payment gateway (Stripe/PayPal/JazzCash/EasyPaisa)
└── database/
    ├── ecommerce_db.sql    ← Main database
    ├── fix_missing_tables.sql
    └── complete_fixes.sql  ← ✅ NEW: Run this after import
```

---

## ⚠️ KNOWN ISSUES TO MONITOR

1. **Google/Facebook Social Login** - Buttons exist in login.php but not connected yet (needs OAuth keys)
2. **Payment Gateways** - Need real API keys in `includes/payment-config.php`
3. **File Uploads** - `assets/uploads/` folder must be writable (`chmod 755`)
4. **Stripe** - Set key in `api/create-payment-intent.php`

---

*Setup Guide generated automatically - ShopEase Pro v1.0*
