<?php
// call_student.php
session_start();
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['email']) || !isset($_SESSION['email_password'])) {
    $_SESSION['error_message'] = "You must be logged in to send call notifications.";
    header("Location: managescheduled.php");
    exit();
}

// Get counselor details from session
$counselorEmail = $_SESSION['email'];
$counselorName = $_SESSION['username'];
$emailPassword = $_SESSION['email_password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['call_student'])) {
    $appointmentId = $_POST['appointment_id'];
    
    // Get appointment details
    $stmt = $conn->prepare("SELECT * FROM student_appointment WHERE studentId = ?");
    $stmt->bind_param("s", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    if ($appointment) {
        // Debug: Log appointment data
        error_log("Appointment data: " . print_r($appointment, true));
        
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $counselorEmail;
            $mail->Password = $emailPassword; // Use stored Gmail app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom($counselorEmail, $counselorName);
            $mail->addAddress($appointment['studentEmail'], $appointment['studentName']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'PENTING: Kehadiran Wajib untuk Sesi Kaunseling - MyCounsel';

            // Prepare issue categories with their types
            $issueCategories = array();
            
            // Debug each field before checking
            error_log("academicIssues value: " . $appointment['academicIssues']);
            error_log("mentalHealth value: " . $appointment['mentalHealth']);
            error_log("volunteer value: " . $appointment['volunteer']);
            error_log("referred value: " . $appointment['referred']);
            
            // Check for any non-zero, non-null value
            if (!empty($appointment['academicIssues'])) {
                $issueType = !empty($appointment['academicIssueType']) ? $appointment['academicIssueType'] : 'Tidak dinyatakan';
                $issueCategories[] = "Isu Akademik: " . $issueType;
            }
            
            if (!empty($appointment['mentalHealth'])) {
                $issueCategories[] = "Kesihatan Mental";
            }
            
            if (!empty($appointment['volunteer'])) {
                $issueType = !empty($appointment['volunteerType']) ? $appointment['volunteerType'] : 'Tidak dinyatakan';
                $issueCategories[] = "Sukarelawan: " . $issueType;
            }
            
            if (!empty($appointment['referred'])) {
                $issueType = !empty($appointment['referralSource']) ? $appointment['referralSource'] : 'Tidak dinyatakan';
                $issueCategories[] = "Dirujuk: " . $issueType;
            }

            // Ensure there's always at least one category
            if (empty($issueCategories)) {
                $issueCategories[] = "Kaunseling Umum";
            }

            // Debug categories
            error_log("Issue categories: " . print_r($issueCategories, true));
            
            $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointment['appointmentDateTime']));

            // Start building email with more formal language
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #d9534f; text-align: center; padding: 10px; background-color: #f9f9f9; border-radius: 5px;'>NOTIS RASMI: KEHADIRAN WAJIB SESI KAUNSELING</h2>
                    
                    <p>Saudara/Saudari <strong>{$appointment['studentName']}</strong>,</p>
                    
                    <p>Ini adalah notis rasmi mengenai temujanji kaunseling anda yang telah dijadualkan. Kehadiran anda adalah <strong>wajib</strong> untuk sesi berikut:</p>
                    
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;'>
                        <p style='font-size: 1.2em; font-weight: bold;'>{$formattedDateTime}</p>
                    </div>
                    
                    <div style='background-color: #f9f2f2; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p style='font-weight: bold; margin-bottom: 10px;'>Butiran Sesi:</p>
                        <ul style='margin-left: 20px;'>
            ";
            
            // Add each category as a list item
            foreach ($issueCategories as $issue) {
                $mail->Body .= "<li style='margin-bottom: 5px;'>{$issue}</li>";
            }
            
            // Continue with the rest of the email in formal language
            $mail->Body .= "
                        </ul>
                    </div>
                    
                    <p>Dimaklumkan bahawa kehadiran anda adalah <strong>diwajibkan</strong> untuk sesi penting ini. Kehadiran adalah komponen penting dalam proses kaunseling dan diperlukan untuk menangani perkara-perkara yang dinyatakan di atas.</p>
                    
                    <p>Sekiranya anda tidak dapat hadir ke temujanji yang dijadualkan ini, anda perlu menghubungi Pejabat Kaunseling secepat mungkin untuk membuat pengaturan alternatif. Sila ambil perhatian bahawa kegagalan untuk hadir tanpa pemberitahuan terlebih dahulu mungkin memberi implikasi terhadap kedudukan akademik dan/atau status pendaftaran anda.</p>
                    
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p style='margin-bottom: 5px;'><strong>Maklumat Temujanji:</strong></p>
                        <p style='margin-bottom: 5px;'><strong>Tarikh dan Masa:</strong> {$formattedDateTime}</p>
                        <p style='margin-bottom: 5px;'><strong>Lokasi:</strong> Bilik Kaunseling Individu, Pejabat HEPS</p>
                        <p style='margin-bottom: 0;'><strong>Maklumat Perhubungan:</strong> {$counselorEmail}</p>
                    </div>
                    
                    <p>Sila hadir 5 minit lebih awal dari masa temujanji yang ditetapkan. Sekiranya anda mempunyai sebarang pertanyaan atau memerlukan maklumat lanjut, sila hubungi Pejabat Kaunseling.</p>
                    
                    <p style='margin-top: 20px;'>Yang benar,<br><strong>{$counselorName}</strong><br>Kaunselor Pelajar Kolej Poly-Tech Mara Alor Setar<br>MyCounsel</p>
                </div>
            ";

            // Create plain text version with issue categories
            $plainTextIssues = "Butiran Sesi:\n";
            foreach ($issueCategories as $issue) {
                $plainTextIssues .= "- " . $issue . "\n";
            }
            
            $mail->AltBody = "NOTIS RASMI: KEHADIRAN WAJIB SESI KAUNSELING\n\n" .
                            "Saudara/Saudari {$appointment['studentName']},\n\n" .
                            "Ini adalah notis rasmi mengenai temujanji kaunseling anda yang telah dijadualkan. Kehadiran anda adalah wajib untuk sesi berikut:\n\n" .
                            "Tarikh dan Masa: {$formattedDateTime}\n\n" .
                            $plainTextIssues . "\n" .
                            "Dimaklumkan bahawa kehadiran anda adalah diwajibkan untuk sesi penting ini. Kehadiran adalah komponen penting dalam proses kaunseling dan diperlukan untuk menangani perkara-perkara yang dinyatakan di atas.\n\n" .
                            "Sekiranya anda tidak dapat hadir ke temujanji yang dijadualkan ini, anda perlu menghubungi Pejabat Kaunseling secepat mungkin untuk membuat pengaturan alternatif. Sila ambil perhatian bahawa kegagalan untuk hadir tanpa pemberitahuan terlebih dahulu mungkin memberi implikasi terhadap kedudukan akademik dan/atau status pendaftaran anda.\n\n" .
                            "Maklumat Temujanji:\n" .
                            "Tarikh dan Masa: {$formattedDateTime}\n" .
                            "Lokasi: Bilik Kaunseling Individu, Pejabat HEPS\n" .
                            "Maklumat Perhubungan: {$counselorEmail}\n\n" .
                            "Sila hadir 5 minit lebih awal dari masa temujanji yang ditetapkan. Sekiranya anda mempunyai sebarang pertanyaan atau memerlukan maklumat lanjut, sila hubungi Pejabat Kaunseling.\n\n" .
                            "Yang benar,\n{$counselorName}\nKaunselor Pelajar Kolej Poly-Tech Mara Alor Setar\nMyCounsel";

            $mail->send();
            $_SESSION['success_message'] = "Call to attend notification sent to  " . $appointment['studentEmail'];
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Failed to send call notification: " . $mail->ErrorInfo;
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    } else {
        $_SESSION['error_message'] = "Appointment not found.";
    }

    // Set a flag in the session that this appointment has been called
    $_SESSION['called_appointment_' . $appointmentId] = true;
    
    // Redirect back to the manage scheduled page
    header("Location: managescheduled.php?called=" . $appointmentId);
    exit();
}
?>