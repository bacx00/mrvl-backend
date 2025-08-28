#!/bin/bash

# MRVL Gmail Setup Script
# This script configures Gmail SMTP for password reset emails

echo ""
echo "📧 ========================================="
echo "   MRVL GMAIL SMTP CONFIGURATION"
echo "==========================================="
echo ""
echo "This script will configure Gmail for sending"
echo "password reset emails from your MRVL platform."
echo ""
echo "⚠️  REQUIREMENTS:"
echo "   1. Gmail account with 2FA enabled"
echo "   2. App Password (not regular password)"
echo ""
echo "📱 GET APP PASSWORD:"
echo "   1. Go to: https://myaccount.google.com/apppasswords"
echo "   2. Create new app password for 'Mail'"
echo "   3. Copy the 16-character code"
echo ""
read -p "Press Enter when you have your App Password ready..."
echo ""

# Get Gmail credentials
read -p "📧 Enter your Gmail address: " GMAIL_ADDRESS
if [[ ! "$GMAIL_ADDRESS" =~ ^[a-zA-Z0-9._%+-]+@gmail\.com$ ]]; then
    echo "❌ Invalid Gmail address. Must end with @gmail.com"
    exit 1
fi

echo "🔑 Enter your App Password (paste and press Enter)"
echo "   Note: Spaces will be automatically removed"
read -sp "   App Password: " APP_PASSWORD_RAW
echo ""

# Remove spaces from app password
APP_PASSWORD=$(echo "$APP_PASSWORD_RAW" | tr -d ' ')

# Validate app password length (should be 16 characters without spaces)
if [ ${#APP_PASSWORD} -ne 16 ]; then
    echo "❌ App Password should be 16 characters (excluding spaces)"
    echo "   You entered: ${#APP_PASSWORD} characters"
    exit 1
fi

echo ""
echo "📝 Configuration Summary:"
echo "   Email: $GMAIL_ADDRESS"
echo "   Password: ${APP_PASSWORD:0:4}****${APP_PASSWORD:12:4}"
echo ""
read -p "Is this correct? (y/n): " CONFIRM

if [[ $CONFIRM != "y" && $CONFIRM != "Y" ]]; then
    echo "❌ Setup cancelled"
    exit 1
fi

# Backup current .env
echo ""
echo "🔄 Backing up current configuration..."
cd /var/www/mrvl-backend
BACKUP_FILE=".env.backup.$(date +%Y%m%d_%H%M%S)"
cp .env "$BACKUP_FILE"
echo "✅ Backup saved to: $BACKUP_FILE"

# Update .env file
echo "📝 Updating email configuration..."
sed -i "s/^MAIL_MAILER=.*/MAIL_MAILER=smtp/" .env
sed -i "s/^MAIL_HOST=.*/MAIL_HOST=smtp.gmail.com/" .env
sed -i "s/^MAIL_PORT=.*/MAIL_PORT=587/" .env
sed -i "s|^MAIL_USERNAME=.*|MAIL_USERNAME=$GMAIL_ADDRESS|" .env
sed -i "s|^MAIL_PASSWORD=.*|MAIL_PASSWORD=$APP_PASSWORD|" .env
sed -i "s/^MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
sed -i "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=\"$GMAIL_ADDRESS\"|" .env
sed -i "s|^MAIL_FROM_NAME=.*|MAIL_FROM_NAME=\"MRVL Tournament Platform\"|" .env

# Clear Laravel caches
echo "🧹 Clearing Laravel caches..."
php artisan config:clear > /dev/null 2>&1
php artisan cache:clear > /dev/null 2>&1
php artisan view:clear > /dev/null 2>&1

echo "✅ Configuration updated successfully!"
echo ""

# Test email sending
echo "🧪 Would you like to send a test email? (y/n)"
read -p "   Choice: " TEST_EMAIL

if [[ $TEST_EMAIL == "y" || $TEST_EMAIL == "Y" ]]; then
    read -p "   Enter recipient email for test: " TEST_RECIPIENT
    
    echo "   Sending test email..."
    
    # Create PHP test script
    cat > test_gmail.php << 'EOF'
<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$recipient = $argv[1] ?? 'test@example.com';

try {
    Mail::raw('This is a test email from MRVL Tournament Platform. If you received this, your Gmail SMTP is configured correctly!', function($message) use ($recipient) {
        $message->to($recipient)
                ->subject('MRVL Email Test - ' . date('Y-m-d H:i:s'));
    });
    echo "✅ Test email sent successfully to: $recipient\n";
    echo "   Check your inbox (and spam folder)!\n";
} catch (\Exception $e) {
    echo "❌ Failed to send test email\n";
    echo "   Error: " . $e->getMessage() . "\n";
}
EOF
    
    php test_gmail.php "$TEST_RECIPIENT"
    rm test_gmail.php
fi

echo ""
echo "========================================="
echo "✅ GMAIL SETUP COMPLETE!"
echo "========================================="
echo ""
echo "📋 What's configured:"
echo "   • SMTP Server: smtp.gmail.com:587"
echo "   • Encryption: TLS"
echo "   • From: $GMAIL_ADDRESS"
echo "   • Daily limit: 500 emails"
echo ""
echo "🔐 Test Password Reset Flow:"
echo "   1. Go to: https://staging.mrvl.net"
echo "   2. Click Login → Forgot Password"
echo "   3. Enter your email"
echo "   4. Check Gmail for reset link"
echo ""
echo "📁 Configuration saved in:"
echo "   /var/www/mrvl-backend/.env"
echo ""
echo "🔄 To restore previous settings:"
echo "   cp $BACKUP_FILE .env"
echo "   php artisan config:clear"
echo ""
echo "Need help? Check: /var/www/mrvl-backend/gmail_setup_guide.md"
echo ""