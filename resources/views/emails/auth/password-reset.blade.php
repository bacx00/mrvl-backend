<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Password Reset - MRVL Tournament Platform</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles for all email clients */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        
        /* Client-specific styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #ffffff;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .tagline {
            color: #fed7d7;
            font-size: 16px;
            margin: 0;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
            margin: 0 0 20px 0;
        }
        
        .message {
            font-size: 16px;
            color: #4a5568;
            margin: 0 0 30px 0;
            line-height: 1.8;
        }
        
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: #ffffff !important;
            text-decoration: none !important;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(220, 38, 38, 0.25);
            transition: all 0.3s ease;
        }
        
        .reset-button:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(220, 38, 38, 0.35);
        }
        
        .alternative-link {
            background-color: #f7fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .alternative-link p {
            color: #718096;
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        
        .alternative-link a {
            color: #dc2626;
            word-break: break-all;
            text-decoration: none;
        }
        
        .security-info {
            background-color: #fef5e7;
            border-left: 4px solid #f6ad55;
            padding: 20px;
            margin: 30px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .security-info h3 {
            color: #c05621;
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 10px 0;
        }
        
        .security-info p {
            color: #9c4221;
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        
        .security-info ul {
            color: #9c4221;
            font-size: 14px;
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .footer {
            background-color: #2d3748;
            padding: 30px;
            text-align: center;
        }
        
        .footer p {
            color: #a0aec0;
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        
        .footer a {
            color: #fed7d7;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-container {
                background-color: #1a202c;
            }
            .content {
                background-color: #1a202c;
            }
            .greeting {
                color: #f7fafc;
            }
            .message {
                color: #e2e8f0;
            }
        }
        
        /* Mobile responsiveness */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .header, .content, .footer {
                padding: 25px 20px !important;
            }
            .logo {
                font-size: 28px;
            }
            .greeting {
                font-size: 20px;
            }
            .reset-button {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box;
            }
        }
        
        /* Outlook-specific fixes */
        <!--[if mso]>
        .reset-button {
            background-color: #dc2626 !important;
        }
        <![endif]-->
    </style>
</head>
<body>
    <!--[if mso]>
    <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
            <td align="center" valign="top">
    <![endif]-->
    
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <a href="{{ config('app.frontend_url', config('app.url')) }}" class="logo">MRVL</a>
            <p class="tagline">Marvel Rivals Tournament Platform</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <h1 class="greeting">Password Reset Request</h1>
            
            <p class="message">
                Hi there,<br><br>
                
                We received a request to reset your password for your MRVL account. If you made this request, 
                click the button below to reset your password. If you didn't request this, you can safely ignore this email.
            </p>
            
            <div class="button-container">
                <a href="{{ $url }}" class="reset-button">
                    Reset My Password
                </a>
            </div>
            
            <div class="alternative-link">
                <p><strong>Button not working?</strong> Copy and paste this link into your browser:</p>
                <a href="{{ $url }}">{{ $url }}</a>
            </div>
            
            <div class="security-info">
                <h3>🔒 Security Information</h3>
                <p><strong>This link will expire in 60 minutes</strong> for your security.</p>
                <ul>
                    <li>Only use this link if you requested a password reset</li>
                    <li>Never share this link with anyone</li>
                    <li>If you didn't request this, please ignore this email</li>
                    <li>Your current password remains unchanged until you complete the reset</li>
                </ul>
            </div>
            
            <p class="message">
                <strong>Need help?</strong> If you're having trouble with your account or didn't request this reset, 
                please contact our support team at <a href="mailto:support@mrvl.net" style="color: #dc2626;">support@mrvl.net</a>.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <strong>MRVL Tournament Platform</strong><br>
                The ultimate destination for Marvel Rivals competitive gaming
            </p>
            <p>
                <a href="{{ config('app.frontend_url', config('app.url')) }}">Visit Platform</a> |
                <a href="{{ config('app.frontend_url', config('app.url')) }}/terms">Terms</a> |
                <a href="{{ config('app.frontend_url', config('app.url')) }}/privacy">Privacy</a>
            </p>
            <p style="font-size: 12px; margin-top: 20px;">
                This email was sent to {{ $user->email ?? 'your email address' }}.<br>
                If you no longer wish to receive these emails, you can unsubscribe from your account settings.
            </p>
        </div>
    </div>
    
    <!--[if mso]>
            </td>
        </tr>
    </table>
    <![endif]-->
</body>
</html>