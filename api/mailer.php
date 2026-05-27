<?php
// api/mailer.php

// ==========================================
// PHPMailer Setup for InfinityFree
// ==========================================
// Since Composer (vendor/autoload.php) is hard to use on InfinityFree, 
// download PHPMailer from GitHub, extract it, put the 'src' folder in your root directory, 
// and rename it to 'PHPMailer'. Then use these requires:

require_once $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/Exception.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/PHPMailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendAdminInviteEmail($toEmail, $firstName, $verificationToken) {
    $mail = new PHPMailer(true);

    try {
        // ==========================================
        // SERVER SETTINGS
        // ==========================================
        // ⚠️ INFINITYFREE FIX: 
        // If the SMTP connection times out, comment out the SMTP block below, 
        // and uncomment the "$mail->isMail();" line to use InfinityFree's internal mailer.
        
        // --- Option A: Standard Gmail SMTP (Try this first) ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';           
        $mail->SMTPAuth   = true;                       
        $mail->Username   = 'team.novaira@gmail.com';     // Your Gmail Address
        $mail->Password   = 'qpunkumqposczzzn';        // Your 16-digit Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465;                        
        
        // --- Option B: InfinityFree Allowed Mailer (Fallback) ---
        // $mail->isMail(); 

        // ==========================================
        // RECIPIENTS
        // ==========================================
        $mail->setFrom('team.novaira@gmail.com', 'Novaira Global Administration');
        $mail->addAddress($toEmail, $firstName);

        // ==========================================
        // VERIFICATION URL
        // ==========================================
        // Pointing to your InfinityFree domain
        $verifyUrl = 'https://novaira.infinityfreeapp.com/admin/verify_admin.php?token=' . $verificationToken; 
        
        // ==========================================
        // HTML EMAIL TEMPLATE
        // ==========================================
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e9ecef; }
                .header { background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); padding: 35px 20px; text-align: center; color: #ffffff; }
                .header h1 { margin: 0; font-size: 24px; letter-spacing: 1px; font-weight: 800; }
                .content { padding: 35px; color: #333333; line-height: 1.6; }
                .content h2 { color: #1e293b; margin-top: 0; font-size: 20px; }
                .btn-container { text-align: center; margin: 35px 0 25px; }
                .btn { display: inline-block; padding: 14px 30px; background-color: #0d6efd; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-size: 14px; }
                .footer { text-align: center; padding: 25px; font-size: 12px; color: #6c757d; background-color: #f8f9fa; border-top: 1px solid #e9ecef; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>NOVAIRA GLOBAL</h1>
                </div>
                <div class='content'>
                    <h2>Welcome to the Team, $firstName!</h2>
                    <p>You have been invited to join the Novaira Global Administration team. Your account has been provisioned with <strong>Admin privileges</strong>.</p>
                    <p>To accept this invitation and activate your account, please click the verification button below to set up your secure password.</p>

                    <div class='btn-container'>
                        <a href='$verifyUrl' class='btn'>Verify Your Admin Account</a>
                    </div>
                    
                    <p style='font-size: 13px; color: #6c757d; text-align: center;'>If the button doesn't work, copy and paste this link into your browser:<br><br> <a href='$verifyUrl' style='color: #0d6efd; word-break: break-all;'>$verifyUrl</a></p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Novaira Global. All rights reserved.<br>
                    This link will expire in 24 hours.
                </div>
            </div>
        </body>
        </html>
        ";

        // --- Content Configuration ---
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Admin Account - Novaira Global';
        $mail->Body    = $htmlBody;
        $mail->AltBody = "Hello $firstName,\n\nYou have been invited as an Admin to Novaira Global.\n\nPlease verify your account and set your password by visiting the following link:\n$verifyUrl";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error for backend debugging
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>