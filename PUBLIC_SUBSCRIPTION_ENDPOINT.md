# Public Subscription Endpoint

This document explains how to use the new public subscription endpoint that allows unauthenticated users to create subscriptions.

## Problem Solved

The original `/subscription/subscribe` endpoint required authentication, which created a chicken-and-egg problem:
- Users needed to be authenticated to create a subscription
- But they couldn't authenticate without first having an account
- Customer data was provided but no user was created

## Solution

A new public endpoint `/subscription/subscribe/public` that:
1. Accepts customer data and creates a user account if needed
2. Automatically logs in the user
3. Creates the subscription
4. Redirects to the dashboard

## Endpoint Details

**URL:** `POST /subscription/subscribe/public`  
**Authentication:** None required  
**Route Name:** `subscription.store.public`

## Request Format

```json
{
  "plan_slug": "enterprise",
  "start_trial": true,
  "customer": {
    "first_name": "Riaan",
    "last_name": "Laubscher",
    "email": "loophole1@gmail.com",
    "password": "optional_password",
    "password_confirmation": "optional_password"
  },
  "payment_data": {
    "payment_method_id": "optional",
    "billing_address": {
      "line1": "optional",
      "city": "optional",
      "country": "optional"
    }
  }
}
```

## Required Fields

- `plan_slug`: Must be a valid, active plan slug
- `customer.first_name`: Customer's first name
- `customer.last_name`: Customer's last name  
- `customer.email`: Must be a valid email address and unique (not already registered)

## Optional Fields

- `start_trial`: Boolean, defaults to false
- `customer.password`: If not provided, a random password is generated
- `customer.password_confirmation`: Required if password is provided
- `payment_data`: Payment information (for future payment processing)

## Response Format

### Success (201 Created)

```json
{
  "message": "Subscription created successfully",
  "subscription": {
    "id": 123,
    "status": "trial",
    "plan_name": "Enterprise Plan"
  },
  "user": {
    "id": 456,
    "email": "loophole1@gmail.com",
    "name": "Riaan Laubscher"
  }
}
```

### Error (422 Unprocessable Entity)

```json
{
  "message": "Failed to create subscription",
  "error": "Validation error details"
}
```

## Validation Rules

1. **Plan Validation:**
   - Plan must exist and be active
   - If `start_trial` is true, plan must have trial period

2. **Customer Validation:**
   - Email must be unique (no existing user with same email)
   - If user exists but has active subscription, request fails
   - If user exists without active subscription, existing user is used

3. **Trial Validation:**
   - User cannot have already used trial for the same plan
   - Plan must support trial periods if `start_trial` is true

## Behavior

1. **New User:** Creates user account, logs them in, creates subscription
2. **Existing User (no subscription):** Uses existing user, logs them in, creates subscription  
3. **Existing User (has subscription):** Returns error
4. **Invalid Data:** Returns validation errors

## Usage Example

```javascript
// Frontend JavaScript example
const subscriptionData = {
  plan_slug: 'enterprise',
  start_trial: true,
  customer: {
    first_name: 'Riaan',
    last_name: 'Laubscher',
    email: 'loophole1@gmail.com'
  }
};

fetch('/subscription/subscribe/public', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
  },
  body: JSON.stringify(subscriptionData)
})
.then(response => response.json())
.then(data => {
  if (data.subscription) {
    // Success - redirect to dashboard or show success message
    window.location.href = '/subscription/dashboard';
  } else {
    // Handle errors
    console.error('Subscription creation failed:', data);
  }
});
```

## Security Considerations

1. **CSRF Protection:** Endpoint includes CSRF protection via web middleware
2. **Rate Limiting:** Consider adding rate limiting to prevent abuse
3. **Email Verification:** Users created this way should verify their email
4. **Password Security:** Auto-generated passwords are secure but users should change them

## Migration from Original Endpoint

To migrate from the original authenticated endpoint:

1. **Change the URL:** `/subscription/subscribe` → `/subscription/subscribe/public`
2. **Add customer data:** Include required customer fields
3. **Remove authentication:** No need to authenticate first
4. **Handle new responses:** Account for user creation in success response

## Testing

Run the test suite to verify the endpoint works correctly:

```bash
lando artisan test tests/Feature/Controllers/PublicSubscriptionTest.php
```

This endpoint solves the original issue where users couldn't create subscriptions because they weren't authenticated, while maintaining security and data integrity.
