# Gmail App Password Setup Guide

## Issue: Email Delivery Still Failing

The most likely cause is **Gmail App Password** requirement. When you have 2-Factor Authentication enabled on Gmail, you cannot use your regular password for SMTP.

## Quick Fix Steps

### 1. Check if 2FA is Enabled
- Go to: https://myaccount.google.com/security
- Look for "2-Step Verification"
- If enabled, you MUST use an App Password

### 2. Generate App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select "Mail" for the app
3. Select "Other (Custom name)" and enter "CyberShield"
4. Click "Generate"
5. Copy the 16-character password (e.g., xxxx xxxx xxxx xxxx)

### 3. Update Configuration
Replace the current password in `includes/email_config.php`:

```php
$MAIL_PASSWORD = getenv('MAIL_PASSWORD') ?: 'YOUR_NEW_16_CHAR_APP_PASSWORD';
```

## Alternative: Try Different SMTP Settings

If App Password doesn't work, try these alternatives:

### Option 1: TLS on Port 587
```php
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = 587;
```

### Option 2: Less Secure Apps (Not Recommended)
1. Go to: https://myaccount.google.com/lesssecureapps
2. Turn ON "Allow less secure apps"
3. Use your regular Gmail password

## Testing
After updating password:
1. Access: http://localhost/security/detailed_email_test.php
2. Check for specific error messages
3. Monitor Apache error logs

## Common Error Messages

### "Authentication failed"
- Wrong password or need App Password
- 2FA is enabled but using regular password

### "Connection refused"
- Port 465/587 blocked by firewall
- Antivirus blocking SMTP

### "SSL certificate verify failed"
- XAMPP SSL configuration issue
- Try updating certificate bundle

## Debug Commands
Check Apache logs: `C:\xampp\apache\logs\error.log`
Check PHP logs: `C:\xampp\php\logs\php_error_log`
