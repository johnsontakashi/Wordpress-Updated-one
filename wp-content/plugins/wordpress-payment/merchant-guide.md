# Monarch WooCommerce Payment Gateway - Merchant Guide

**Version:** 1.0.11
**Requires WordPress:** 5.0+
**Requires WooCommerce:** 5.0+
**Tested up to:** WordPress 6.4, WooCommerce 8.0

---

## Overview

The Monarch WooCommerce Payment Gateway enables your online store to accept ACH (Automated Clearing House) bank payments. ACH payments offer lower transaction fees compared to credit cards and provide a secure, direct bank-to-bank payment experience for your customers.

---

## Key Features

### Accept Bank Payments
- Direct bank transfers via ACH network
- Lower processing fees than credit cards
- Secure bank verification through industry-standard providers

### Two Ways to Connect Banks
Customers can link their bank accounts using either method:

| Method | Description | Best For |
|--------|-------------|----------|
| **Instant Verification** | Secure login through customer's bank portal | Customers who want instant setup |
| **Manual Entry** | Enter routing and account numbers directly | Customers who prefer manual input |

### Seamless Returning Customer Experience
- **First-time customers:** Complete a simple one-time registration
- **Returning customers:** See a "Welcome back!" message and click one button to authorize payment
- **No duplicate accounts:** Each customer has a single profile in the system

### Guest Checkout Support
- Customers can pay without creating a WordPress account
- Full ACH payment support for guest checkouts
- Order information stored with the transaction

### Test Mode
- Safely test the integration before going live
- Separate test environment
- Easy toggle between test and production modes

---

## Installation

### Requirements
- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL certificate (HTTPS required)
- Monarch API credentials

### Step-by-Step Installation

1. **Upload the Plugin**
   - Go to WordPress Admin > Plugins > Add New
   - Click "Upload Plugin"
   - Select the plugin ZIP file and click "Install Now"
   - Activate the plugin

2. **Configure Payment Settings**
   - Navigate to **WooCommerce > Settings > Payments**
   - Find "Monarch ACH Payment" and click **Manage**

3. **Enter Your Credentials**
   - API Key (provided by Monarch)
   - App ID (provided by Monarch)
   - Merchant Organization ID (provided by Monarch)
   - Partner Name (provided by Monarch)

4. **Enable Test Mode** (Recommended for initial setup)
   - Check "Enable Test Mode"
   - Save changes
   - Test a transaction to verify everything works

5. **Go Live**
   - Uncheck "Enable Test Mode"
   - Save changes
   - Your store is now accepting live ACH payments

---

## Configuration Options

| Setting | Description |
|---------|-------------|
| **Enable/Disable** | Turn the payment method on or off |
| **Title** | Payment method name shown to customers (e.g., "Pay with Bank Account") |
| **Description** | Description shown at checkout |
| **API Key** | Your Monarch API key |
| **App ID** | Your Monarch application ID |
| **Merchant Org ID** | Your merchant organization ID |
| **Partner Name** | Your partner name in the Monarch system |
| **Test Mode** | Enable for testing without processing real payments |

---

## Admin Dashboard

### Transactions Tab
View and manage all ACH transactions:
- Transaction ID and amount
- Payment status (Pending, Completed, Failed, etc.)
- Customer information
- Order details
- Click "View Details" for complete transaction information

### Customers Tab
View all customers who have used ACH payments:
- Customer name and email
- Account type (Registered user or Guest)
- Billing address
- Bank connection status
- Connection date

**Bank Status Indicators:**
| Status | Meaning |
|--------|---------|
| **Ready to Pay** | Customer has an active bank authorization |
| **Bank Linked** | Customer's bank is connected but needs to re-authorize for next purchase |

### Status Sync Tab
Manually sync transaction statuses with Monarch to ensure your records are up-to-date.

### Logs Tab
View system logs for troubleshooting:
- API communication logs
- Transaction processing details
- Error messages (if any)

---

## Customer Experience

### First-Time Purchase Flow

1. **Customer selects "Pay with Bank Account"** at checkout
2. **Enters required information:**
   - Phone number
   - Date of birth
   - Company name (optional)
3. **Clicks "Connect Your Bank"**
4. **Chooses connection method:**
   - **Instant Verification:** Logs into bank portal securely
   - **Manual Entry:** Enters routing and account numbers
5. **Completes purchase** after bank is connected

### Returning Customer Flow

1. Customer selects "Pay with Bank Account"
2. Sees **"Welcome back!"** message
3. Clicks **"Continue with Bank"** button
4. Selects their previously-linked bank
5. Completes purchase

> **Note:** Returning customers do NOT need to re-enter their personal information or go through full registration again. They simply authorize the new payment with one click.

---

## Transaction Statuses

| Status | Description |
|--------|-------------|
| **Pending** | Payment initiated, awaiting processing |
| **Processing** | Payment is being processed by the ACH network |
| **Completed** | Payment successfully processed |
| **Failed** | Payment was declined or encountered an error |
| **Refunded** | Payment was refunded to customer |
| **Voided** | Payment was cancelled before processing |

---

## Security Features

### Bank Security
- Bank credentials are handled by PCI-compliant providers (Yodlee/Plaid)
- Your store never stores bank login credentials
- All data transmitted over encrypted HTTPS connections

### Payment Authorization
- Each transaction requires fresh authorization
- Payment tokens expire after each use (security by design)
- Prevents unauthorized repeat charges

### Customer Data
- Customer emails stored exactly as provided
- No data modification or obfuscation
- Standard WordPress security practices

---

## Frequently Asked Questions

### Why do returning customers need to click "Continue with Bank"?
This is a security feature. Each payment requires fresh authorization to prevent unauthorized charges. The customer simply clicks one button and selects their already-linked bank - they don't need to enter any information again.

### Can customers use guest checkout?
Yes, ACH payments fully support guest checkout. Customers don't need to create a WordPress account to pay with their bank.

### What happens if a customer wants to use a different bank?
They can click "Use a different bank account" on the checkout page to disconnect their current bank and connect a new one.

### How long do ACH payments take to process?
ACH payments typically take 2-5 business days to settle, depending on the banks involved. The transaction will show as "Processing" during this time.

### Are there any transaction limits?
Transaction limits are set by Monarch and may vary based on your merchant agreement. Contact Monarch support for specific limits.

---

## Troubleshooting

### "API Connection Failed"
- Verify your API Key, App ID, and Merchant Org ID are correct
- Ensure you're using the correct credentials for Test vs Production mode
- Check that your server can make outbound HTTPS requests

### Bank Popup Doesn't Open
- Customer may have a popup blocker enabled
- Instruct customer to allow popups from your site

### Transaction Shows as Failed
- Check the transaction details in the admin panel
- Review the Logs tab for specific error messages
- Common reasons: insufficient funds, invalid account, daily limits exceeded

### Customer Can't Connect Bank
- Try the alternative connection method (Instant vs Manual)
- Ensure customer is entering correct bank information
- Check if the bank is supported by the verification provider

---

## Getting Help

### Monarch Support
For API issues, credential questions, or transaction inquiries:
- Contact Monarch Technologies support
- Reference your Merchant Organization ID

### Plugin Issues
For WordPress/WooCommerce integration issues:
- Check the Logs tab for error details
- Verify WooCommerce and WordPress meet minimum version requirements
- Ensure SSL certificate is properly configured

---

## Best Practices

1. **Always test first** - Use test mode before accepting live payments
2. **Keep credentials secure** - Never share API keys publicly
3. **Monitor transactions** - Regularly check the Transactions tab
4. **Review logs** - Check logs if you encounter issues
5. **Update regularly** - Keep the plugin updated for security and features

---

## Quick Reference

### Minimum Requirements
- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- HTTPS/SSL enabled

### Required Credentials
- API Key
- App ID
- Merchant Organization ID
- Partner Name

### Test vs Production
| Mode | Purpose | Real Money? |
|------|---------|-------------|
| Test | Development & QA | No |
| Production | Live transactions | Yes |

---

*Monarch WooCommerce Payment Gateway v1.0.10*
