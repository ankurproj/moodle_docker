# reCAPTCHA Setup Guide

## What Was Added

The Application Status Check plugin now includes reCAPTCHA validation before the "Get Scheme" button to prevent automated abuse.

## Setup Instructions

### 1. Get reCAPTCHA Keys from Google

1. Go to [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Click **Create** or **+** button
3. Fill in the form:
   - **Label**: Your Moodle Site Name
   - **reCAPTCHA type**: Choose **reCAPTCHA v2** → "I'm not a robot" Checkbox
   - **Domains**: Add your domain (e.g., `yourdomain.com` or `localhost` for testing)
4. Accept the terms and click **Submit**
5. Copy both keys:
   - **Site Key** (public key)
   - **Secret Key** (private key)

### 2. Configure reCAPTCHA in Moodle

1. Log in to Moodle as administrator
2. Navigate to: **Site administration** → **Security** → **Site security settings**
3. Scroll down to the **reCAPTCHA** section
4. Paste your keys:
   - **reCAPTCHA site key**: Paste the Site Key (public key)
   - **reCAPTCHA secret key**: Paste the Secret Key (private key)
5. Click **Save changes**

### 3. Test the CAPTCHA

1. Log out or use an incognito/private browser window
2. Navigate to the Application Status Check page
3. You should now see the reCAPTCHA checkbox before the "Get Scheme" button
4. Complete the CAPTCHA and test the form submission

## Fallback Behavior

If reCAPTCHA keys are **not configured**:

- The form will work normally **without CAPTCHA**
- No errors will be shown
- The CAPTCHA element simply won't appear

This allows you to deploy the plugin before setting up reCAPTCHA if needed.

## Security Features

✅ CAPTCHA validation occurs **before** user lookup  
✅ Prevents automated bot submissions  
✅ Protects against brute force attacks  
✅ Server-side validation for security  
✅ Uses Google's reCAPTCHA v2 for reliability

## Troubleshooting

### CAPTCHA not showing?

- Check if reCAPTCHA keys are configured in Site administration
- Verify both public and private keys are entered correctly
- Ensure your domain is registered with Google reCAPTCHA

### CAPTCHA validation failing?

- Check server can access https://www.google.com/recaptcha/api/siteverify
- Verify PHP cURL extension is enabled
- Check firewall allows outbound HTTPS connections

### For local development:

- Use `localhost` or `127.0.0.1` as the domain in reCAPTCHA settings
- Consider using reCAPTCHA test keys for development (Google provides these)
