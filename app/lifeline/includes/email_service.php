<?php
/**
 * LifeLine Blood Network - Email Service
 * Provides email sending capabilities using PHP mail() or SMTP
 */

require_once __DIR__ . '/config.php';

class EmailService {
    private static array $config;
    
    /**
     * Initialize email configuration
     */
    public static function init(): void {
        self::$config = Config::getMailConfig();
    }
    
    /**
     * Check if email service is properly configured
     */
    public static function isConfigured(): bool {
        if (!isset(self::$config)) {
            self::init();
        }
        return !empty(self::$config['host']) && 
               !empty(self::$config['username']) && 
               !empty(self::$config['password']);
    }
    
    /**
     * Send an email
     */
    public static function send(string $to, string $subject, string $body, array $attachments = []): bool {
        if (!isset(self::$config)) {
            self::init();
        }
        
        // If SMTP is not configured, log the email for development
        if (!self::isConfigured()) {
            error_log("Email would be sent (SMTP not configured): To: $to, Subject: $subject");
            return true; // Return true to prevent errors in development
        }
        
        $headers = self::buildHeaders();
        $message = self::buildMessage($body);
        
        // Use PHPMailer if available, otherwise fall back to mail()
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return self::sendWithPHPMailer($to, $subject, $body, $attachments);
        }
        
        return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send email using PHPMailer (if available)
     */
    private static function sendWithPHPMailer(string $to, string $subject, string $body, array $attachments = []): bool {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = self::$config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = self::$config['username'];
            $mail->Password = self::$config['password'];
            $mail->SMTPSecure = self::$config['encryption'];
            $mail->Port = self::$config['port'];
            
            // Recipients
            $mail->setFrom(self::$config['from_address'], self::$config['from_name']);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            // Attachments
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build email headers for mail() function
     */
    private static function buildHeaders(): string {
        $from = self::$config['from_address'];
        $fromName = self::$config['from_name'];
        
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        return $headers;
    }
    
    /**
     * Build HTML email message
     */
    private static function buildMessage(string $body): string {
        return "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>" .
               self::getEmailHeader() .
               $body .
               self::getEmailFooter() .
               "</body></html>";
    }
    
    /**
     * Get email header HTML
     */
    private static function getEmailHeader(): string {
        $appName = Config::get('APP_NAME', 'LifeLine Blood Network');
        return "
            <div style='background: #b91c1c; color: white; padding: 20px; text-align: center;'>
                <h1 style='margin: 0; font-size: 24px;'>{$appName}</h1>
            </div>
            <div style='padding: 20px; background: #f9fafb;'>
        ";
    }
    
    /**
     * Get email footer HTML
     */
    private static function getEmailFooter(): string {
        $appName = Config::get('APP_NAME', 'LifeLine Blood Network');
        $year = date('Y');
        return "
            </div>
            <div style='background: #1f2937; color: #9ca3af; padding: 20px; text-align: center; font-size: 12px;'>
                <p>&copy; {$year} {$appName}. All rights reserved.</p>
                <p>This is an automated message. Please do not reply directly to this email.</p>
            </div>
        ";
    }
    
    /**
     * Send welcome email to new donor
     */
    public static function sendDonorWelcome(string $email, string $name): bool {
        $subject = "Welcome to LifeLine Blood Network!";
        $body = "
            <h2>Welcome, {$name}!</h2>
            <p>Thank you for registering as a blood donor with LifeLine Blood Network. Your willingness to donate blood can save lives in your community.</p>
            
            <h3>What's Next?</h3>
            <ul>
                <li>Keep your profile updated with your current availability</li>
                <li>Update your last donation date after each donation</li>
                <li>You'll receive email notifications when hospitals need blood matching your type</li>
            </ul>
            
            <p><strong>Remember:</strong> You can donate whole blood every 56 days. Please ensure you're well-rested and hydrated before donating.</p>
            
            <a href='" . Config::get('APP_URL') . "/donor/dashboard.php' style='display: inline-block; background: #b91c1c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Go to Dashboard</a>
        ";
        
        return self::send($email, $subject, $body);
    }
    
    /**
     * Send welcome email to new hospital
     */
    public static function sendHospitalWelcome(string $email, string $hospitalName): bool {
        $subject = "Welcome to LifeLine Blood Network!";
        $body = "
            <h2>Welcome, {$hospitalName}!</h2>
            <p>Thank you for registering your hospital with LifeLine Blood Network. You now have access to our network of voluntary blood donors.</p>
            
            <h3>What's Next?</h3>
            <ul>
                <li>Create blood requests when you need donors</li>
                <li>Our system will automatically find compatible donors in your area</li>
                <li>Contact donors directly through their provided contact information</li>
            </ul>
            
            <p><strong>Important:</strong> Please verify donor availability and health status before scheduling donations. Always follow proper blood banking protocols.</p>
            
            <a href='" . Config::get('APP_URL') . "/hospital/dashboard.php' style='display: inline-block; background: #b91c1c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Go to Dashboard</a>
        ";
        
        return self::send($email, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordReset(string $email, string $resetLink): bool {
        $subject = "Password Reset Request";
        $body = "
            <h2>Password Reset Request</h2>
            <p>We received a request to reset your password for your LifeLine Blood Network account.</p>
            
            <p>If you made this request, click the button below to reset your password:</p>
            
            <a href='{$resetLink}' style='display: inline-block; background: #b91c1c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0;'>Reset Password</a>
            
            <p>Or copy and paste this link into your browser:</p>
            <p style='background: #f3f4f6; padding: 10px; word-break: break-all;'>{$resetLink}</p>
            
            <p><strong>This link will expire in 24 hours.</strong></p>
            
            <p>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
        ";
        
        return self::send($email, $subject, $body);
    }
    
    /**
     * Send blood request notification to compatible donors
     */
    public static function sendBloodRequestNotification(string $email, string $donorName, array $request): bool {
        $subject = "URGENT: Blood Donation Needed - " . $request['patient_blood_type'];
        $urgencyColor = $request['urgency'] === 'critical' ? '#991b1b' : ($request['urgency'] === 'urgent' ? '#92400e' : '#1e40af');
        $urgencyBg = $request['urgency'] === 'critical' ? '#fee2e2' : ($request['urgency'] === 'urgent' ? '#fef3c7' : '#dbeafe');
        
        $body = "
            <h2>Urgent Blood Request</h2>
            <p>Hi {$donorName},</p>
            <p>A hospital needs blood donors with your blood type <strong>{$request['patient_blood_type']}</strong>.</p>
            
            <div style='background: {$urgencyBg}; border-left: 4px solid {$urgencyColor}; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: {$urgencyColor}; font-weight: bold;'>Urgency: " . ucfirst($request['urgency']) . "</p>
                <p style='margin: 5px 0 0 0;'>Hospital: {$request['hospital_name']}</p>
                <p style='margin: 5px 0 0 0;'>Location: {$request['city']}, {$request['state']}</p>
                <p style='margin: 5px 0 0 0;'>Units Needed: {$request['units_needed']}</p>
                <p style='margin: 5px 0 0 0;'>Required By: " . ($request['required_date'] ?: 'ASAP') . "</p>
            </div>
            
            <a href='" . Config::get('APP_URL') . "/view_request.php?id={$request['id']}' style='display: inline-block; background: #b91c1c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 20px;'>View Request Details</a>
            
            <p style='margin-top: 20px; font-size: 12px; color: #6b7280;'>You received this email because your blood type matches this request. <a href='" . Config::get('APP_URL') . "/donor/edit_profile.php'>Update your preferences</a></p>
        ";
        
        return self::send($email, $subject, $body);
    }
}

// Auto-initialize
EmailService::init();
