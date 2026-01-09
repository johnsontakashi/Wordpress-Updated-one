# Monarch WooCommerce Payment Gateway - Developer Guide

**Version:** 1.0.13
**Requires WordPress:** 5.0+
**Requires WooCommerce:** 5.0+
**Tested up to:** WordPress 6.4, WooCommerce 8.0

---

## Overview

The Monarch WooCommerce Payment Gateway is a secure ACH (Automated Clearing House) payment solution that integrates Monarch's payment processing API with WooCommerce stores. This plugin enables merchants to accept direct bank payments from customers, offering a cost-effective alternative to credit card processing.

---

## Key Features

### 1. ACH Bank Payments
- Direct bank-to-bank transfers via ACH network
- Lower transaction fees compared to credit cards
- Secure bank linking through Yodlee/Plaid integration

### 2. Dual Bank Connection Methods
Customers can connect their bank accounts in two ways:

| Method | Description | Use Case |
|--------|-------------|----------|
| **Instant Bank Verification** | Connects via Yodlee/Plaid secure interface | Customers who prefer instant verification through their bank's login |
| **Manual Bank Entry** | Direct entry of routing and account numbers | Customers who prefer to enter bank details manually |

### 3. Returning Customer Experience
The plugin intelligently handles returning customers:

- **First Purchase:** Customer completes full registration (phone, DOB, company name) and links their bank
- **Return Purchase:** Customer sees "Welcome back!" message and simply clicks "Continue with Bank" to authorize payment
- **No Duplicate Records:** The plugin stores the customer's `org_id` permanently - no duplicate purchaser records are created in Monarch

### 4. Guest Checkout Support
- Full support for guest (non-logged-in) customers
- Guest customer data stored in WooCommerce session and order meta
- Seamless checkout experience without requiring account creation

### 5. Test Mode
- Built-in test mode for development and staging environments
- Separate API endpoints for test vs production
- Easy toggle in WooCommerce payment settings

---

## Technical Architecture

### Data Storage

#### User Meta (Registered Customers)
| Meta Key | Description | Persistence |
|----------|-------------|-------------|
| `_monarch_org_id` | Customer's Monarch organization ID | Permanent |
| `_monarch_paytoken_id` | Payment authorization token | Cleared after each transaction |
| `_monarch_org_api_key` | Customer's API key (if applicable) | Permanent |
| `_monarch_org_app_id` | Customer's App ID (if applicable) | Permanent |
| `_monarch_connected_date` | Date bank was first linked | Permanent |

#### Order Meta (All Orders)
| Meta Key | Description |
|----------|-------------|
| `_monarch_org_id` | Organization ID used for this order |
| `_monarch_paytoken_id` | PayToken used for this transaction |
| `_monarch_transaction_id` | Monarch transaction reference ID |

#### Custom Database Table
The plugin creates a `{prefix}_monarch_ach_transactions` table for transaction logging:

```sql
CREATE TABLE wp_monarch_ach_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    transaction_id VARCHAR(255),
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(50),
    monarch_org_id VARCHAR(255),
    paytoken_id VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### API Integration Flow

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   WooCommerce   │────▶│  Monarch Plugin │────▶│   Monarch API   │
│    Checkout     │     │                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘
                               │
                               ▼
                        ┌─────────────────┐
                        │  Yodlee/Plaid   │
                        │  Bank Linking   │
                        └─────────────────┘
```

#### New Customer Flow
1. Customer enters phone, DOB, company name
2. Plugin calls `POST /organization` to create purchaser in Monarch
3. Customer links bank via Yodlee/Plaid popup OR manual entry
4. Plugin retrieves `paytoken_id` via `GET /getlatestpaytoken/{org_id}`
5. Plugin processes payment via `POST /transaction/sale`
6. PayToken is cleared (expires after use per Monarch security)

#### Returning Customer Flow
1. Plugin detects existing `org_id` in user meta
2. Customer clicks "Continue with Bank"
3. Plugin calls `GET /organization/{org_id}` to get bank linking URL
4. Customer selects their already-linked bank
5. New `paytoken_id` is generated
6. Payment proceeds as normal

---

## Installation

### Requirements
- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL certificate (HTTPS required for production)
- Monarch API credentials (API Key, App ID, Merchant Org ID)

### Installation Steps

1. Upload the `wordpress-payment` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Navigate to **WooCommerce > Settings > Payments**
4. Click **Manage** on "Monarch ACH Payment"
5. Configure your API credentials:
   - API Key
   - App ID
   - Merchant Organization ID
   - Partner Name
6. Enable Test Mode for initial testing
7. Save changes

### File Structure

```
wordpress-payment/
├── woocommerce-monarch-ach.php      # Main plugin file
├── includes/
│   ├── class-wc-monarch-ach-gateway.php   # Payment gateway class
│   ├── class-wc-monarch-admin.php         # Admin interface
│   ├── class-monarch-api.php              # API wrapper
│   └── class-monarch-logger.php           # Logging utility
├── assets/
│   ├── css/
│   │   ├── monarch-ach.css          # Frontend styles
│   │   └── monarch-admin.css        # Admin styles
│   └── js/
│       ├── monarch-ach.js           # Frontend JavaScript
│       └── monarch-admin.js         # Admin JavaScript
└── developer-guide.md               # This file
```

---

## Configuration Options

### Gateway Settings

| Setting | Description | Required |
|---------|-------------|----------|
| Enable/Disable | Toggle payment method on/off | Yes |
| Title | Payment method name shown at checkout | Yes |
| Description | Description shown at checkout | No |
| API Key | Your Monarch API key | Yes |
| App ID | Your Monarch application ID | Yes |
| Merchant Org ID | Your merchant organization ID | Yes |
| Partner Name | Your partner name in Monarch system | Yes |
| Test Mode | Enable for development/staging | No |

### API Endpoints

| Environment | Base URL |
|-------------|----------|
| Test/Development | `https://devapi.monarch.is/v1` |
| Production | `https://api.monarch.is/v1` |

---

## Admin Features

### Transactions Tab
- View all ACH transactions
- Filter by status, date, amount
- Click "View Details" for full transaction information
- Pagination for large transaction volumes

### Customers Tab
- View all customers who have used Monarch ACH
- Shows both registered users and guest customers
- Displays:
  - Customer name and email
  - Account type (Registered/Guest)
  - Billing address
  - Monarch Org ID
  - Bank status (Ready to Pay / Bank Linked)
  - Connected date

### Status Sync Tab
- Manually sync transaction statuses with Monarch API
- Useful for reconciliation

### Logs Tab
- View API request/response logs
- Debug transaction issues
- Monitor API health

---

## Security Considerations

### PayToken Expiration
Per Monarch's security design, payment tokens (`paytoken_id`) expire after each transaction. This prevents:
- Unauthorized repeat charges
- Token theft and reuse
- Accidental duplicate payments

The customer's bank connection (`org_id`) remains permanent - only the authorization token expires.

### Data Handling
- All sensitive data transmitted over HTTPS
- Bank credentials never stored locally
- Bank linking handled by Yodlee/Plaid (PCI-compliant)
- WordPress nonces used for all AJAX requests
- Capability checks on all admin functions

### Email Privacy
- Customer emails are stored exactly as provided
- No email modification or obfuscation
- Real email addresses used for Monarch registration

---

## Hooks and Filters

### Actions

```php
// Fired after successful Monarch payment
do_action('monarch_ach_payment_complete', $order_id, $transaction_id);

// Fired when bank is connected
do_action('monarch_ach_bank_connected', $customer_id, $org_id);

// Fired when bank is disconnected
do_action('monarch_ach_bank_disconnected', $customer_id);
```

### Filters

```php
// Modify transaction data before API call
$transaction_data = apply_filters('monarch_ach_transaction_data', $transaction_data, $order);

// Modify customer data before organization creation
$customer_data = apply_filters('monarch_ach_customer_data', $customer_data, $user_id);
```

---

## Troubleshooting

### Common Issues

| Issue | Possible Cause | Solution |
|-------|----------------|----------|
| "API connection failed" | Invalid credentials | Verify API Key, App ID, and Merchant Org ID |
| Bank popup doesn't open | Popup blocker | Instruct customer to allow popups |
| "PayToken not found" | Token expired | Customer needs to re-authorize via "Continue with Bank" |
| Transaction fails | Insufficient funds / Invalid account | Check Monarch dashboard for detailed error |

### Debug Mode
Enable WooCommerce logging to capture detailed API interactions:

1. Go to **WooCommerce > Status > Logs**
2. Look for `monarch-ach-*` log files
3. Review API requests and responses

### Testing Checklist
- [ ] Test mode enabled and working
- [ ] New customer registration flow
- [ ] Returning customer flow
- [ ] Guest checkout flow
- [ ] Manual bank entry
- [ ] Instant bank verification
- [ ] Transaction appears in admin
- [ ] Order status updates correctly
- [ ] Refund process (if applicable)

---

## Support

For API-related questions, contact Monarch Technologies support.

For plugin-specific issues, check the logs and transaction details in the WordPress admin.

---

## Changelog

### Version 1.0.13
- **CRITICAL FIX: Rewrote `/getUserByEmail` API handling completely**
- API method now makes direct HTTP request instead of using generic `make_request()`
- Returns explicit `user_exists` flag (true/false) for unambiguous detection
- Handles ALL HTTP status codes properly:
  - 404 = User does NOT exist (proceed to create)
  - 200 = User EXISTS (use existing org_id)
  - Other codes = Check response body for orgId
- Added `extract_org_id_from_response()` helper in API class
- Logs RAW HTTP response body for debugging
- Gateway code simplified to use `user_exists` flag

### Version 1.0.12
- **FIXED: "Email address already in use" error** - Completely rewrote org_id extraction logic
- Now searches for orgId in ALL possible response locations using 3 methods:
  - Method 1: Check standard `data` array with multiple key names (orgId, org_id, organizationId)
  - Method 2: Check `response` array including nested `user` and `organization` objects
  - Method 3: Deep recursive search through entire API response
- Added `find_org_id_recursive()` helper function for thorough orgId discovery
- Both instant verification and manual entry flows now use identical extraction logic
- WooCommerce detection now supports WordPress Multisite installations

### Version 1.0.11
- Added comprehensive debug logging for `/getUserByEmail` API response
- Logs full API response structure, success/error status, and data type analysis
- Added clear "EMAIL LOOKUP START/END" markers in logs for easier debugging
- Logs exactly why user was/wasn't found (success=false vs empty data)
- Helps diagnose why "email already exists" error might still occur
- Same logging added to both instant verification and manual entry flows

### Version 1.0.10
- Fixed ALL customer data fields to use real-time form input
- First name, last name, and email now prioritize form data over WordPress user meta
- Completely eliminates cached data issues for logged-in users
- Added comprehensive debug logging for all customer data fields

### Version 1.0.9
- Fixed email caching issue - now uses real-time billing email from form
- For logged-in users, form email takes priority over WordPress account email
- Added debug logging to track email source (form vs WordPress user)
- Prevents "email already exists" error caused by cached/old email addresses

### Version 1.0.8
- Added email lookup before organization creation (prevents "email already exists" error)
- Uses `/getUserByEmail` endpoint to check for existing users
- If user exists, retrieves existing org_id instead of creating duplicate
- Follows Monarch's 4-step embedded bank linking flow exactly
- Applied same fix to manual bank entry flow

### Version 1.0.7
- Fixed email handling - uses customer's actual email
- Improved returning customer flow with "Continue with Bank" button
- Enhanced Customers tab showing both registered and guest users
- Added billing address display in admin
- Improved bank status indicators
- Better error handling and logging

### Version 1.0.6
- Added manual bank entry option
- Guest checkout support
- Transaction details modal in admin

### Version 1.0.5
- Initial release with core ACH functionality
- Yodlee/Plaid bank linking integration
- Admin transaction management
