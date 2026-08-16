<?php

// Load SMTP configuration from environment variables when available.

define('SMTP_HOST', getenv('SMTP_HOST') !== false ? getenv('SMTP_HOST') : 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') !== false ? (int)getenv('SMTP_PORT') : 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') !== false ? getenv('SMTP_USERNAME') : 'your_mail@email.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') !== false ? getenv('SMTP_PASSWORD') : '16letterpassword');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') !== false ? getenv('SMTP_FROM_EMAIL') : 'your_mail@email.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') !== false ? getenv('SMTP_FROM_NAME') : 'Perfect Choice Inventory');

// When SMTP password is not configured, enable developer fallback (show codes locally)
define('MAIL_DEV_FALLBACK', empty(SMTP_PASSWORD));
