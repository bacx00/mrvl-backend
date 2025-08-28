# 📧 Gmail SMTP Setup for MRVL Password Reset

## 🔑 Step 1: Generate Gmail App Password

### Why App Password?
Google requires App Passwords for SMTP access instead of your regular password for security. This is mandatory if you have 2FA enabled (which you should!).

### How to Generate:

1. **Go to Google Account Settings**
   - Visit: https://myaccount.google.com/security
   
2. **Enable 2-Step Verification** (if not already enabled)
   - Click on "2-Step Verification"
   - Follow the setup process

3. **Generate App Password**
   - Go to: https://myaccount.google.com/apppasswords
   - Or search for "App passwords" in your Google Account settings
   
4. **Create New App Password**
   - Select app: "Mail"
   - Select device: "Other (Custom name)"
   - Enter name: "MRVL Platform"
   - Click "Generate"
   
5. **Copy the 16-character password**
   - It will look like: `xxxx xxxx xxxx xxxx`
   - Remove spaces when using it
   - **SAVE IT!** You won't see it again

## 📝 Step 2: Update Environment Configuration

Edit `/var/www/mrvl-backend/.env` with your Gmail credentials:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your.email@gmail.com
MAIL_PASSWORD=yourapppasswordwithoutspaces
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your.email@gmail.com
MAIL_FROM_NAME="MRVL Tournament Platform"
```

### Example with real values:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=jhonny@gmail.com
MAIL_PASSWORD=abcd1234efgh5678
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=jhonny@gmail.com
MAIL_FROM_NAME="MRVL Tournament Platform"
```

## 🚀 Step 3: Apply Configuration

Run these commands to apply the changes:

```bash
cd /var/www/mrvl-backend

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Optional: Test email configuration
php artisan tinker
>>> Mail::raw('Test email from MRVL', function($message) {
...     $message->to('your-test-email@gmail.com')
...             ->subject('MRVL Email Test');
... });
>>> exit
```

## 🧪 Step 4: Test Forgot Password Flow

1. **Request Password Reset**
   ```bash
   curl -X POST https://staging.mrvl.net/api/auth/forgot-password \
     -H 'Content-Type: application/json' \
     -d '{"email": "jhonny@ar-mediia.com"}'
   ```

2. **Check Your Gmail**
   - You should receive an email within seconds
   - Check spam folder if not in inbox
   - Email will be from "MRVL Tournament Platform"

3. **Click Reset Link**
   - Link expires in 60 minutes
   - Opens password reset form
   - Enter new password

## ⚠️ Common Issues & Solutions

### Issue: "Authentication failed"
**Solution**: 
- Make sure you're using App Password, not regular password
- Remove any spaces from the app password
- Check that 2FA is enabled on your Google account

### Issue: "Connection refused"
**Solution**:
- Ensure port 587 is open on your server
- Try alternative port 465 with `MAIL_ENCRYPTION=ssl`

### Issue: "Emails going to spam"
**Solution**:
- Use same email for `MAIL_USERNAME` and `MAIL_FROM_ADDRESS`
- Add SPF/DKIM records if using custom domain
- Consider using Google Workspace for business email

### Issue: "Daily limit exceeded"
**Solution**:
- Gmail free accounts: 500 emails/day
- Consider upgrading to Google Workspace (2000 emails/day)
- Or use professional service (SendGrid, Mailgun, etc.)

## 🔒 Security Best Practices

1. **Never commit .env file to git**
2. **Use App Password, never regular password**
3. **Enable 2FA on Gmail account**
4. **Monitor sent emails in Gmail's Sent folder**
5. **Set up email alerts for suspicious activity**

## 📊 Gmail SMTP Limits

| Feature | Limit |
|---------|-------|
| Daily sending limit | 500 emails (free) / 2000 (Workspace) |
| Recipients per message | 500 |
| Recipients per day | 500 |
| Size per email | 25 MB |
| Connection timeout | 60 seconds |

## 🎯 Quick Setup Script

Save your credentials and run this script:

```bash
#!/bin/bash
# gmail_setup.sh

echo "🔧 Setting up Gmail for MRVL Platform"
echo "======================================"
echo ""
read -p "Enter your Gmail address: " gmail
read -sp "Enter your App Password (no spaces): " apppass
echo ""

# Update .env file
cd /var/www/mrvl-backend

# Backup current .env
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Update mail settings
sed -i "s/MAIL_MAILER=.*/MAIL_MAILER=smtp/" .env
sed -i "s/MAIL_HOST=.*/MAIL_HOST=smtp.gmail.com/" .env
sed -i "s/MAIL_PORT=.*/MAIL_PORT=587/" .env
sed -i "s/MAIL_USERNAME=.*/MAIL_USERNAME=$gmail/" .env
sed -i "s/MAIL_PASSWORD=.*/MAIL_PASSWORD=$apppass/" .env
sed -i "s/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
sed -i "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=$gmail/" .env

# Clear Laravel caches
php artisan config:clear
php artisan cache:clear

echo "✅ Gmail SMTP configured successfully!"
echo ""
echo "Test with: php artisan tinker"
echo ">>> Mail::raw('Test', fn(\$m) => \$m->to('$gmail')->subject('Test'));"
```

## ✅ You're Ready!

Once configured, your password reset emails will be sent through Gmail instantly. Users will receive professional HTML emails that work in all email clients.