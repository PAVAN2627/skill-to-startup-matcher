<?php
// PHPMailer email functionality for XAMPP
// Using working email configuration from your other project

// Include PHPMailer (you need to install it via Composer or download manually)
// For Composer: composer require phpmailer/phpmailer
// Or download from: https://github.com/PHPMailer/PHPMailer

// Uncomment these lines if you have PHPMailer installed:
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOTPEmail($to, $otp, $name) {
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendOTPWithPHPMailer($to, $otp, $name);
    } else {
        // Fallback to logging for development
        return sendOTPWithLogging($to, $otp, $name);
    }
}

function sendOTPWithPHPMailer($to, $otp, $name) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // SMTP server configuration (using your working settings)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->Port       = 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = 'pavanmalith3@gmail.com';   // Your SMTP username
        $mail->Password   = '';      // Your SMTP password
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        // Message settings
        $mail->setFrom('pavanmalith3@gmail.com', 'Skill to Startup Matcher');
        $mail->addAddress($to, $name);
        
        // Email content
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - OTP Code';
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #333;'>Email Verification</h2>
                    <p>Hello $name,</p>
                    <p>Thank you for registering with Skill to Startup Matcher. Please use the following OTP to verify your email address:</p>
                    <div style='background: #f4f4f4; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                        <h1 style='color: #007bff; font-size: 36px; margin: 0; letter-spacing: 8px;'>$otp</h1>
                    </div>
                    <p>This OTP is valid for 10 minutes.</p>
                    <p>If you didn't request this verification, please ignore this email.</p>
                    <hr style='margin: 30px 0;'>
                    <p style='color: #666; font-size: 12px;'>
                        This is an automated email from Skill to Startup Matcher. Please do not reply to this email.
                    </p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Hi $name,\n\nYour OTP code is: $otp\nIt expires in 10 minutes.\n\n— Skill to Startup Matcher";

        $mail->send();
        
        // Also log for development tracking
        $log_message = "[" . date('Y-m-d H:i:s') . "] OTP sent via EMAIL to $name ($to): $otp\n";
        file_put_contents(__DIR__ . '/../logs/otp_log.txt', $log_message, FILE_APPEND | LOCK_EX);
        
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        
        // Fallback to logging if email fails
        return sendOTPWithLogging($to, $otp, $name);
    }
}

function sendOTPWithLogging($to, $otp, $name) {
    // For development when PHPMailer is not available
    $log_message = "[" . date('Y-m-d H:i:s') . "] OTP for $name ($to): $otp\n";
    file_put_contents(__DIR__ . '/../logs/otp_log.txt', $log_message, FILE_APPEND | LOCK_EX);
    
    error_log("OTP sent to $to: $otp (logged only - PHPMailer not available)");
    return true;
}

function sendApprovalEmail($to, $orgName, $approved) {
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendApprovalWithPHPMailer($to, $orgName, $approved);
    } else {
        // Fallback to logging for development
        return sendApprovalWithLogging($to, $orgName, $approved);
    }
}

function sendApprovalWithPHPMailer($to, $orgName, $approved) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // SMTP server configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->Port       = 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = 'pavanmalith3@gmail.com';
        $mail->Password   = 'qsqa drxj xflr ergx';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        // Message settings
        $mail->setFrom('pavanmalith3@gmail.com', 'Skill to Startup Matcher');
        $mail->addAddress($to, $orgName);
        
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        
        if ($approved) {
            $mail->Subject = 'Startup Account Approved! 🎉';
            $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #28a745;'>🎉 Account Approved!</h2>
                        <p>Hello $orgName,</p>
                        <p>Congratulations! Your startup account has been approved by our admin team.</p>
                        <p>You can now login and access all features of the Skill to Startup Matcher platform:</p>
                        <ul>
                            <li>Browse qualified student candidates</li>
                            <li>Review applications from interested students</li>
                            <li>Access our AI-powered matching system</li>
                        </ul>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='#' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                                Login Now
                            </a>
                        </div>
                        <p>Welcome to the Skill to Startup Matcher community!</p>
                    </div>
                </body>
                </html>
            ";
        } else {
            $mail->Subject = 'Startup Account Application Update';
            $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #dc3545;'>Account Application Update</h2>
                        <p>Hello $orgName,</p>
                        <p>Thank you for your interest in Skill to Startup Matcher.</p>
                        <p>After careful review, we are unable to approve your startup account at this time.</p>
                        <p>If you have questions or would like to reapply, please contact our support team.</p>
                        <hr style='margin: 30px 0;'>
                        <p>Best regards,<br>The Skill to Startup Matcher Team</p>
                    </div>
                </body>
                </html>
            ";
        }
        
        $mail->AltBody = $approved 
            ? "Hi $orgName,\n\nYour startup account has been approved! You can now login and access all features.\n\n— Skill to Startup Matcher"
            : "Hi $orgName,\n\nWe are unable to approve your startup account at this time. Please contact support for more information.\n\n— Skill to Startup Matcher";

        $mail->send();
        
        // Log the action
        $status = $approved ? 'APPROVED' : 'REJECTED';
        $log_message = "[" . date('Y-m-d H:i:s') . "] Approval email sent to $orgName ($to): $status\n";
        file_put_contents(__DIR__ . '/../logs/otp_log.txt', $log_message, FILE_APPEND | LOCK_EX);
        
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return sendApprovalWithLogging($to, $orgName, $approved);
    }
}

function sendApprovalWithLogging($to, $orgName, $approved) {
    $status = $approved ? 'APPROVED' : 'REJECTED';
    $log_message = "[" . date('Y-m-d H:i:s') . "] Approval email for $orgName ($to): $status (logged only)\n";
    file_put_contents(__DIR__ . '/../logs/otp_log.txt', $log_message, FILE_APPEND | LOCK_EX);
    
    error_log("Approval email to $to: $status (logged only - PHPMailer not available)");
    return true;
}
?>
