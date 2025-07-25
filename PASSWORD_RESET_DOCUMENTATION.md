# Password Reset Implementation Documentation

## Overview
The password reset functionality has been fully implemented in the MRVL backend with email support and frontend URL integration.

## Configuration

### 1. Email Configuration (.env file)
You need to update the following settings in your `.env` file with your actual email service credentials:

```env
# Frontend URL (where users will be redirected)
APP_FRONTEND_URL=https://staging.mrvl.net

# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com      # Replace with your SMTP host
MAIL_PORT=587                  # Replace with your SMTP port
MAIL_USERNAME=your_email@gmail.com    # Your email address
MAIL_PASSWORD=your_app_password       # Your email app password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@mrvl.net"
MAIL_FROM_NAME="MRVL Tournament Platform"
```

### 2. Gmail Setup (if using Gmail)
1. Enable 2-factor authentication on your Gmail account
2. Generate an App Password:
   - Go to https://myaccount.google.com/apppasswords
   - Select "Mail" and your device
   - Copy the generated password
   - Use this as MAIL_PASSWORD in .env

### 3. Other Email Providers
- **SendGrid**: MAIL_HOST=smtp.sendgrid.net, MAIL_PORT=587
- **Mailgun**: MAIL_HOST=smtp.mailgun.org, MAIL_PORT=587
- **Amazon SES**: MAIL_HOST=email-smtp.region.amazonaws.com, MAIL_PORT=587

## API Endpoints

### 1. Request Password Reset
**Endpoint**: `POST /api/auth/forgot-password`
**Body**:
```json
{
    "email": "user@example.com"
}
```
**Response**:
```json
{
    "success": true,
    "message": "Password reset link sent to your email address"
}
```

### 2. Reset Password
**Endpoint**: `POST /api/auth/reset-password`
**Body**:
```json
{
    "token": "reset-token-from-email",
    "email": "user@example.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```
**Response**:
```json
{
    "success": true,
    "message": "Your password has been reset successfully"
}
```

## Frontend Implementation Requirements

### 1. Forgot Password Page
Create a page where users can request a password reset:

```jsx
// Example React component
function ForgotPassword() {
    const [email, setEmail] = useState('');
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        const response = await fetch('https://staging.mrvl.net/api/auth/forgot-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email })
        });
        const data = await response.json();
        // Handle response
    };
    
    return (
        <form onSubmit={handleSubmit}>
            <input 
                type="email" 
                value={email} 
                onChange={(e) => setEmail(e.target.value)}
                placeholder="Enter your email"
                required 
            />
            <button type="submit">Send Reset Link</button>
        </form>
    );
}
```

### 2. Reset Password Page (at /reset-password route)
This page should extract the token and email from URL parameters:

```jsx
// Example React component
function ResetPassword() {
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    
    // Extract token and email from URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const email = urlParams.get('email');
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        const response = await fetch('https://staging.mrvl.net/api/auth/reset-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                token,
                email,
                password,
                password_confirmation: passwordConfirmation
            })
        });
        const data = await response.json();
        if (data.success) {
            // Redirect to login page
            window.location.href = '/login';
        }
    };
    
    return (
        <form onSubmit={handleSubmit}>
            <input 
                type="password" 
                value={password} 
                onChange={(e) => setPassword(e.target.value)}
                placeholder="New Password"
                minLength="8"
                required 
            />
            <input 
                type="password" 
                value={passwordConfirmation} 
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                placeholder="Confirm New Password"
                minLength="8"
                required 
            />
            <button type="submit">Reset Password</button>
        </form>
    );
}
```

## Email Template
The email sent to users will contain:
- Subject: "Reset Your MRVL Password"
- A personalized greeting
- A reset button that links to: `https://staging.mrvl.net/reset-password?token=TOKEN&email=EMAIL`
- Expiration notice (60 minutes)
- Security notice

## Testing

### 1. Test with cURL
```bash
# Request password reset
curl -X POST https://staging.mrvl.net/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"test@example.com"}'

# Reset password (after receiving token)
curl -X POST https://staging.mrvl.net/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "token": "your-reset-token",
    "email": "test@example.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }'
```

### 2. Check Email Logs
If using `MAIL_MAILER=log` for testing, check the email content in:
```bash
tail -f /var/www/mrvl-backend/storage/logs/laravel.log
```

## Security Considerations
1. Tokens expire after 60 minutes
2. Each token can only be used once
3. Passwords must be at least 8 characters
4. Old tokens are invalidated when a new reset is requested
5. Email validation ensures only registered users can reset passwords

## Troubleshooting

### Email not sending
1. Check .env mail configuration
2. Clear config cache: `php artisan config:clear`
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify SMTP credentials

### Token invalid error
1. Ensure token hasn't expired (60 minute limit)
2. Check that email matches the token
3. Verify token hasn't been used already

### Frontend redirect issues
1. Verify APP_FRONTEND_URL in .env
2. Ensure /reset-password route exists in frontend
3. Check URL parameter extraction in frontend

## Complete Flow
1. User clicks "Forgot Password" on login page
2. User enters email and submits
3. Backend sends email with reset link
4. User clicks link in email
5. User is redirected to frontend reset page with token
6. User enters new password
7. Frontend sends reset request to backend
8. Password is updated
9. User can login with new password