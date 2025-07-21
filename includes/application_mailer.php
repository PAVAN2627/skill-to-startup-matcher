<?php
// Application notification email system
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendApplicationStatusEmail($student_email, $student_name, $startup_name, $job_title, $new_status, $notes = '') {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer not available - cannot send email notification");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP configuration (using existing settings)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->Port       = 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = 'pavanmalith3@gmail.com';
        $mail->Password   = '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // Email settings
        $mail->setFrom('pavanmalith3@gmail.com', 'Skill to Startup Matcher');
        $mail->addAddress($student_email, $student_name);
        
        // Email content based on status
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        
        $subject = '';
        $message_body = '';
        $emoji = '';
        $color = '';
        
        switch($new_status) {
            case 'accepted':
                $subject = "🎉 Application Accepted - $startup_name";
                $emoji = "🎉";
                $color = "#28a745";
                $status_text = "ACCEPTED";
                $message = "Congratulations! Your application has been accepted.";
                $next_steps = "
                    <div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
                        <h4 style='color: #155724; margin: 0 0 10px 0;'>🚀 Next Steps:</h4>
                        <ul style='color: #155724; margin: 0;'>
                            <li>Check your email for further instructions from $startup_name</li>
                            <li>Prepare for potential interviews or onboarding</li>
                            <li>Keep your contact information updated</li>
                            <li>The startup team will contact you soon with more details</li>
                        </ul>
                    </div>
                ";
                break;
                
            case 'interviewed':
                $subject = "📞 Interview Scheduled - $startup_name";
                $emoji = "📞";
                $color = "#17a2b8";
                $status_text = "INTERVIEW SCHEDULED";
                $message = "Great news! You've been selected for an interview.";
                $next_steps = "
                    <div style='background: #d1ecf1; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8;'>
                        <h4 style='color: #0c5460; margin: 0 0 10px 0;'>📅 Interview Preparation:</h4>
                        <ul style='color: #0c5460; margin: 0;'>
                            <li>The startup team will contact you with interview details</li>
                            <li>Review your application and prepare your portfolio</li>
                            <li>Research more about $startup_name</li>
                            <li>Prepare questions about the role and company</li>
                        </ul>
                    </div>
                ";
                break;
                
            case 'rejected':
                $subject = "Application Update - $startup_name";
                $emoji = "📝";
                $color = "#dc3545";
                $status_text = "NOT SELECTED";
                $message = "Thank you for your interest in $startup_name.";
                $next_steps = "
                    <div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;'>
                        <h4 style='color: #721c24; margin: 0 0 10px 0;'>🔄 Keep Going:</h4>
                        <ul style='color: #721c24; margin: 0;'>
                            <li>This decision doesn't reflect your abilities</li>
                            <li>Continue exploring other opportunities</li>
                            <li>Use this experience to improve your applications</li>
                            <li>Stay connected with our platform for new opportunities</li>
                        </ul>
                    </div>
                ";
                break;
                
            default:
                $subject = "Application Status Update - $startup_name";
                $emoji = "📋";
                $color = "#ffc107";
                $status_text = strtoupper($new_status);
                $message = "Your application status has been updated.";
                $next_steps = "
                    <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                        <h4 style='color: #856404; margin: 0 0 10px 0;'>📋 Status Update:</h4>
                        <p style='color: #856404; margin: 0;'>Your application is being reviewed. We'll update you as the process continues.</p>
                    </div>
                ";
        }
        
        $mail->Subject = $subject;
        
        // Create comprehensive email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Application Status Update</title>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.1);'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 28px; font-weight: 300;'>$emoji Skill to Startup Matcher</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Application Status Update</p>
                </div>
                
                <!-- Content -->
                <div style='padding: 40px 30px;'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <div style='background: $color; color: white; padding: 12px 24px; border-radius: 25px; display: inline-block; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>
                            $status_text
                        </div>
                    </div>
                    
                    <h2 style='color: #333; margin: 0 0 20px 0; font-size: 24px; text-align: center;'>
                        Hello, " . htmlspecialchars($student_name) . "! 👋
                    </h2>
                    
                    <p style='font-size: 16px; margin: 20px 0; text-align: center;'>
                        $message
                    </p>
                    
                    <!-- Application Details -->
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 30px 0;'>
                        <h3 style='margin: 0 0 15px 0; color: #333; font-size: 18px;'>📋 Application Details</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; color: #666;'>🏢 Startup:</td>
                                <td style='padding: 8px 0;'>" . htmlspecialchars($startup_name) . "</td>
                            </tr>";
                            
        if ($job_title) {
            $mail->Body .= "
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; color: #666;'>💼 Position:</td>
                                <td style='padding: 8px 0;'>" . htmlspecialchars($job_title) . "</td>
                            </tr>";
        }
        
        $mail->Body .= "
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; color: #666;'>📅 Updated:</td>
                                <td style='padding: 8px 0;'>" . date('F j, Y \a\t g:i A') . "</td>
                            </tr>
                        </table>
                    </div>";
                    
        // Add notes if provided
        if (!empty($notes)) {
            $mail->Body .= "
                    <div style='background: #e3f2fd; padding: 20px; border-radius: 10px; margin: 30px 0; border-left: 4px solid #2196f3;'>
                        <h4 style='margin: 0 0 10px 0; color: #1976d2;'>💬 Message from " . htmlspecialchars($startup_name) . ":</h4>
                        <p style='margin: 0; color: #1976d2; font-style: italic;'>\"" . nl2br(htmlspecialchars($notes)) . "\"</p>
                    </div>";
        }
        
        $mail->Body .= $next_steps;
        
        $mail->Body .= "
                    <!-- CTA Button -->
                    <div style='text-align: center; margin: 40px 0;'>
                        <a href='http://localhost/skill-to-startup-matcher/student/dashboard.php' 
                           style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                  color: white; 
                                  padding: 15px 30px; 
                                  text-decoration: none; 
                                  border-radius: 25px; 
                                  font-weight: bold;
                                  display: inline-block;
                                  text-transform: uppercase;
                                  letter-spacing: 1px;
                                  font-size: 14px;'>
                            🚀 View Dashboard
                        </a>
                    </div>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 40px 0;'>
                    
                    <p style='font-size: 14px; color: #666; text-align: center; margin: 20px 0;'>
                        💡 <strong>Need help?</strong> Contact us or visit your dashboard for more opportunities.
                    </p>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #eee;'>
                    <p style='margin: 0; font-size: 14px; color: #666;'>
                        This email was sent by <strong>Skill to Startup Matcher</strong><br>
                        Connecting talented students with innovative startups
                    </p>
                    <div style='margin-top: 20px;'>
                        <a href='http://localhost/skill-to-startup-matcher' style='color: #667eea; text-decoration: none; margin: 0 10px;'>🌐 Website</a>
                        <a href='mailto:pavanmalith3@gmail.com' style='color: #667eea; text-decoration: none; margin: 0 10px;'>📧 Support</a>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        // Plain text version
        $mail->AltBody = "
Application Status Update - Skill to Startup Matcher

Hello $student_name,

Your application status has been updated to: " . strtoupper($new_status) . "

Startup: $startup_name" . 
        ($job_title ? "\nPosition: $job_title" : "") . "
Updated: " . date('F j, Y \a\t g:i A') . 
        ($notes ? "\n\nMessage from startup:\n$notes" : "") . "

Visit your dashboard for more details: http://localhost/skill-to-startup-matcher/student/dashboard.php

Best regards,
Skill to Startup Matcher Team
        ";
        
        $mail->send();
        error_log("Application status email sent successfully to $student_email");
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send application status email: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to log email attempts (for debugging)
function logEmailAttempt($student_email, $status, $success) {
    $log_entry = date('Y-m-d H:i:s') . " - Email to $student_email for status '$status' - " . 
                 ($success ? 'SUCCESS' : 'FAILED') . "\n";
    
    $log_file = __DIR__ . '/../logs/email_notifications.log';
    
    // Create logs directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Function specifically for acceptance emails (main request)
function sendApplicationAcceptanceEmail($student_email, $student_name, $startup_name, $job_title = null, $notes = '') {
    $success = sendApplicationStatusEmail($student_email, $student_name, $startup_name, $job_title, 'accepted', $notes);
    logEmailAttempt($student_email, 'accepted', $success);
    return $success;
}

// Function for any status change email
function sendApplicationUpdateEmail($student_email, $student_name, $startup_name, $job_title, $status, $notes = '') {
    $success = sendApplicationStatusEmail($student_email, $student_name, $startup_name, $job_title, $status, $notes);
    logEmailAttempt($student_email, $status, $success);
    return $success;
}
?>
