# E-Commerce Project - Error Fixes TODO

## Task: Fix all errors in e-commerce project and complete the web application

### Step 1: Fix Path Errors in Root Files ✅ COMPLETED
- [x] Fix `add-to-cart.php` - Already correct path
- [x] Fix `add-to-wishlist.php` - Already correct path
- [x] Fix `contact.php` - Already fixed in previous session
- [x] Fix `category.php` - Already fixed in previous session
- [x] Fix `submit_review.php` - Fixed path to includes/auth-check.php
- [x] Fix `product-details.php` - Fixed paths to includes/header.php and includes/footer.php

### Step 2: Fix Syntax Errors ✅ COMPLETED
- [x] Fix `get_errors.php` - Added missing opening PHP tag

### Step 3: Database Fixes (Need to run in phpMyAdmin)
- [ ] Run `fix_collations.php` to fix collation issues
- [ ] Run `fix_missing_tables.sql` to create missing tables

### Step 4: Test and Verify
- [ ] Test all pages load correctly
- [ ] Verify database connections work
- [ ] Test payment gateway integration

## Summary of Fixes Made:
1. **submit_review.php**: Changed `admin/includes/config.php` → `includes/config.php` and `admin/includes/auth-check.php` → `includes/auth-check.php`
2. **product-details.php**: Changed `admin/includes/header.php` → `includes/header.php` and `admin/includes/footer.php` → `includes/footer.php`
3. **get_errors.php**: Added missing opening PHP tag `<?php`

## Database Files Ready:
- `database/fix_missing_tables.sql` - Contains SQL to create all missing tables (vendor_settings, vendor_earnings, vendor_withdrawal_requests, vendors_payment_methods, vendor_bank_accounts, vendor_paypal_accounts, vendor_stripe_accounts, vendor_mobile_accounts, vendor_cards, wishlist, vendor_commissions, vendor_category_commissions, vendor_documents)
- `fix_collations.php` - Script to fix database collations to utf8mb4_unicode_ci
