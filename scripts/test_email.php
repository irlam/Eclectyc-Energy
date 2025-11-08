#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/test_email.php
 * Test email functionality and configuration
 * Usage: php scripts/test_email.php [recipient@example.com] [--method=phpmailer|mail]
 */

require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$recipient = $argv[1] ?? $_ENV['ADMIN_EMAIL'] ?? null;
$method = 'phpmailer'; // Default to PHPMailer

// Parse arguments
foreach ($argv as $arg) {
    if (strpos($arg, '--method=') === 0) {
        $method = substr($arg, 9);
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║          Eclectyc Energy - Email Test Utility               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Check configuration
echo "📧 Email Configuration Check\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

$mailHost = $_ENV['MAIL_HOST'] ?? null;
$mailPort = $_ENV['MAIL_PORT'] ?? 587;
$mailUsername = $_ENV['MAIL_USERNAME'] ?? null;
$mailPassword = $_ENV['MAIL_PASSWORD'] ?? null;
$mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
$mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@eclectyc.energy';
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'Eclectyc Energy';

$configStatus = [];
$configStatus[] = ['MAIL_HOST', $mailHost, !empty($mailHost)];
$configStatus[] = ['MAIL_PORT', $mailPort, !empty($mailPort)];
$configStatus[] = ['MAIL_USERNAME', $mailUsername ? '(set)' : '(not set)', !empty($mailUsername)];
$configStatus[] = ['MAIL_PASSWORD', $mailPassword ? '(set)' : '(not set)', !empty($mailPassword)];
$configStatus[] = ['MAIL_ENCRYPTION', $mailEncryption, true];
$configStatus[] = ['MAIL_FROM_ADDRESS', $mailFrom, !empty($mailFrom)];
$configStatus[] = ['ADMIN_EMAIL', $recipient ?: '(not set)', !empty($recipient)];

foreach ($configStatus as list($key, $value, $ok)) {
    $icon = $ok ? '✅' : '❌';
    echo "  $icon $key: $value\n";
}

echo "\n";

if (!$mailHost) {
    echo "❌ Error: MAIL_HOST is not configured in .env\n";
    echo "\n";
    echo "To configure email, add these to your .env file:\n";
    echo "  MAIL_HOST=smtp.example.com\n";
    echo "  MAIL_PORT=587\n";
    echo "  MAIL_USERNAME=your-username\n";
    echo "  MAIL_PASSWORD=your-password\n";
    echo "  MAIL_ENCRYPTION=tls\n";
    echo "  MAIL_FROM_ADDRESS=noreply@eclectyc.energy\n";
    echo "  ADMIN_EMAIL=admin@eclectyc.energy\n";
    echo "\n";
    exit(1);
}

if (!$recipient) {
    echo "❌ Error: No recipient specified\n";
    echo "\n";
    echo "Usage: php scripts/test_email.php recipient@example.com\n";
    echo "   Or: Set ADMIN_EMAIL in .env file\n";
    echo "\n";
    exit(1);
}

echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

// Test email
$subject = '[Eclectyc Energy] Email Test - ' . date('Y-m-d H:i:s');
$message = "This is a test email from Eclectyc Energy platform.\n\n";
$message .= "If you received this email, your email configuration is working correctly!\n\n";
$message .= "Configuration Details:\n";
$message .= "  - Method: $method\n";
$message .= "  - SMTP Host: $mailHost:$mailPort\n";
$message .= "  - Encryption: $mailEncryption\n";
$message .= "  - From: $mailFromName <$mailFrom>\n";
$message .= "  - Sent at: " . date('Y-m-d H:i:s') . "\n";

echo "📤 Sending test email...\n";
echo "   To: $recipient\n";
echo "   Method: $method\n";
echo "\n";

$success = false;
$error = null;

try {
    if ($method === 'phpmailer') {
        $success = sendWithPHPMailer($recipient, $subject, $message, $error);
    } else {
        $success = sendWithMailFunction($recipient, $subject, $message, $error);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $success = false;
}

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";

if ($success) {
    echo "✅ Email sent successfully!\n";
    echo "\n";
    echo "   Check your inbox at: $recipient\n";
    echo "   Subject: $subject\n";
    echo "\n";
    echo "Note: It may take a few minutes to arrive. Check spam folder if not received.\n";
} else {
    echo "❌ Email sending failed!\n";
    echo "\n";
    if ($error) {
        echo "   Error: $error\n";
    }
    echo "\n";
    echo "Troubleshooting:\n";
    echo "  1. Verify SMTP credentials are correct\n";
    echo "  2. Check if SMTP server is accessible\n";
    echo "  3. Verify firewall allows outbound connections on port $mailPort\n";
    echo "  4. Try using telnet to test SMTP: telnet $mailHost $mailPort\n";
    echo "  5. Check error logs for more details\n";
}

echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

exit($success ? 0 : 1);

/**
 * Send email using PHPMailer
 */
function sendWithPHPMailer(string $to, string $subject, string $message, ?string &$error): bool
{
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
        $mail->SMTPAuth = !empty($_ENV['MAIL_USERNAME']);
        
        if ($mail->SMTPAuth) {
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
        }
        
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        
        // Enable verbose debug output (optional, comment out for production)
        // $mail->SMTPDebug = 2;
        
        // Recipients
        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@eclectyc.energy',
            $_ENV['MAIL_FROM_NAME'] ?? 'Eclectyc Energy'
        );
        $mail->addAddress($to);
        $mail->addReplyTo(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@eclectyc.energy',
            $_ENV['MAIL_FROM_NAME'] ?? 'Eclectyc Energy'
        );
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        $error = $e->getMessage();
        return false;
    }
}

/**
 * Send email using PHP mail() function
 */
function sendWithMailFunction(string $to, string $subject, string $message, ?string &$error): bool
{
    $headers = [
        'From: ' . ($_ENV['MAIL_FROM_NAME'] ?? 'Eclectyc Energy') . ' <' . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@eclectyc.energy') . '>',
        'Reply-To: ' . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@eclectyc.energy'),
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8',
    ];
    
    $result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    if (!$result) {
        $error = 'mail() function returned false. Check PHP mail configuration.';
    }
    
    return $result;
}
