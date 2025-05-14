<?php
header("Content-type: text/plain");

// --- CONFIGURATION ---
$adminPhone = "+250788293785";  // Admin phone
$adminPIN   = "0000";           // Admin PIN

// --- USSD Inputs ---
$sessionId   = $_POST["sessionId"] ?? '';
$serviceCode = $_POST["serviceCode"] ?? '';
$phoneNumber = $_POST["phoneNumber"] ?? '';
$text        = $_POST["text"] ?? '';

// Validate phone number format
if (!empty($phoneNumber) && !preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber)) {
    echo "END Invalid phone number format. Must be in international format (e.g., +250788293785)";
    exit;
}

$steps = explode("*", $text);
$level = count($steps);
$response = "";

// --- MAIN MENU ---
if ($text == "") {
    $response  = "CON Welcome to 12GROUP Appointment System\n";
    $response .= "1. Book New Appointment\n";
    $response .= "2. View My Appointments\n";
    $response .= "3. Cancel Appointment\n";
    $response .= "4. Accept Appointment\n";
    $response .= "5. Admin Login";

// --- BOOK APPOINTMENT ---
} else if ($steps[0] == "1") {
    if ($level == 1) {
        $response = "CON Please enter your full name:";
    } else if ($level == 2) {
        // Validate name (only letters and spaces)
        if (!preg_match('/^[a-zA-Z\s]{2,50}$/', $steps[1])) {
            $response = "END Invalid name format. Please use only letters and spaces (2-50 characters).";
        } else {
            $response = "CON Select service type:\n";
            $response .= "1. Medical Consultation\n";
            $response .= "2. Legal Advice\n";
            $response .= "3. Business Coaching";
        }
    } else if ($level == 3) {
        // Validate service selection
        if (!in_array($steps[2], ['1', '2', '3'])) {
            $response = "END Invalid service selection. Please try again.";
        } else {
            $response = "CON Enter appointment date (YYYY-MM-DD):";
        }
    } else if ($level == 4) {
        $date = $steps[3];
        // Validate date format and future date
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            $response = "END Invalid date format. Please use YYYY-MM-DD format.";
        } else {
            $selectedDate = strtotime($date);
            $today = strtotime(date('Y-m-d'));
            
            if ($selectedDate < $today) {
                $response = "END Cannot book appointment in the past. Please select a future date.";
            } else {
                $response = "CON Enter appointment time (HH:MM):";
            }
        }
    } else if ($level == 5) {
        $name = $steps[1];
        $service = $steps[2];
        $date = $steps[3];
        $time = $steps[4];

        // Validate time format
        if (!preg_match("/^\d{2}:\d{2}$/", $time)) {
            $response = "END Invalid time format. Please use HH:MM format.";
        } else {
            // Validate date and time
            $appointmentDateTime = strtotime("$date $time");
            $currentDateTime = time();
            
            if ($appointmentDateTime === false) {
                $response = "END Invalid date or time. Please check your input.";
            } else if ($appointmentDateTime < $currentDateTime) {
                $response = "END Cannot book appointment in the past. Please select a future date and time.";
            } else {
                try {
                    include "db.php";
                    
                    // Check for existing appointment at the same time
                    $checkSql = "SELECT id FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status = 'pending'";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("ss", $date, $time);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    
                    if ($checkResult->num_rows > 0) {
                        $response = "END This time slot is already booked. Please choose another time.";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO appointments (phone, fullname, appointment_date, appointment_time) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $phoneNumber, $name, $date, $time);

                        if ($stmt->execute()) {
                            $appointmentId = $conn->insert_id;
                            $response = "END Appointment booked successfully!\n";
                            $response .= "Your appointment ID is: #$appointmentId\n";
                            $response .= "Name: $name\n";
                            $response .= "Service: " . getServiceName($service) . "\n";
                            $response .= "Date: $date\n";
                            $response .= "Time: $time";
                        } else {
                            $response = "END Failed to save appointment. Please try again.";
                        }
                        $stmt->close();
                    }
                    $checkStmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    $response = "END An error occurred. Please try again later.";
                }
            }
        }
    }

// --- VIEW APPOINTMENTS ---
} else if ($steps[0] == "2") {
    try {
        include "db.php";
        $sql = "SELECT * FROM appointments WHERE phone = ? ORDER BY appointment_date DESC, appointment_time DESC LIMIT 3";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $response = "END Your Recent Appointments:\n";
            while ($row = $result->fetch_assoc()) {
                $response .= "\nID: #{$row['id']}\n";
                $response .= "Name: {$row['fullname']}\n";
                $response .= "Date: {$row['appointment_date']}\n";
                $response .= "Time: {$row['appointment_time']}\n";
                $response .= "Status: {$row['status']}\n";
            }
        } else {
            $response = "END No appointments found.";
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $response = "END An error occurred while fetching appointments. Please try again later.";
    }

// --- CANCEL APPOINTMENT ---
} else if ($steps[0] == "3") {
    if ($level == 1) {
        try {
            include "db.php";
            $sql = "SELECT id, appointment_date, appointment_time FROM appointments WHERE phone = ? AND status = 'pending' ORDER BY appointment_date DESC, appointment_time DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $phoneNumber);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response = "CON Select appointment to cancel:\n";
                while ($row = $result->fetch_assoc()) {
                    $response .= "{$row['id']}. {$row['appointment_date']} at {$row['appointment_time']}\n";
                }
            } else {
                $response = "END No pending appointments found.";
            }

            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $response = "END An error occurred while fetching appointments. Please try again later.";
        }
    } else if ($level == 2) {
        $appointmentId = $steps[1];
        
        // Validate appointment ID
        if (!is_numeric($appointmentId)) {
            $response = "END Invalid appointment ID.";
        } else {
            try {
                include "db.php";
                
                // Get appointment details
                $sql = "SELECT * FROM appointments WHERE id = ? AND phone = ? AND status = 'pending'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $appointmentId, $phoneNumber);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $appointment = $result->fetch_assoc();
                    
                    // Update status
                    $updateSql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("i", $appointmentId);
                    
                    if ($updateStmt->execute()) {
                        $response = "END Appointment cancelled successfully.";
                    } else {
                        $response = "END Failed to cancel appointment. Please try again.";
                    }
                    
                    $updateStmt->close();
                } else {
                    $response = "END Invalid appointment ID or appointment already cancelled.";
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $response = "END An error occurred while cancelling the appointment. Please try again later.";
            }
        }
    }

// --- ACCEPT APPOINTMENT ---
} else if ($steps[0] == "4") {
    if ($level == 1) {
        try {
            include "db.php";
            $sql = "SELECT id, appointment_date, appointment_time FROM appointments WHERE phone = ? AND status = 'confirmed' ORDER BY appointment_date DESC, appointment_time DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $phoneNumber);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response = "CON Select appointment to accept:\n";
                while ($row = $result->fetch_assoc()) {
                    $response .= "{$row['id']}. {$row['appointment_date']} at {$row['appointment_time']}\n";
                }
            } else {
                $response = "END No confirmed appointments found to accept.";
            }

            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $response = "END An error occurred while fetching appointments. Please try again later.";
        }
    } else if ($level == 2) {
        $appointmentId = $steps[1];
        
        // Validate appointment ID
        if (!is_numeric($appointmentId)) {
            $response = "END Invalid appointment ID.";
        } else {
            try {
                include "db.php";
                
                // Get appointment details
                $sql = "SELECT * FROM appointments WHERE id = ? AND phone = ? AND status = 'confirmed'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $appointmentId, $phoneNumber);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $appointment = $result->fetch_assoc();
                    
                    // Update status to accepted
                    $updateSql = "UPDATE appointments SET status = 'accepted' WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("i", $appointmentId);
                    
                    if ($updateStmt->execute()) {
                        $response = "END Appointment accepted successfully!\n";
                        $response .= "ID: #{$appointment['id']}\n";
                        $response .= "Date: {$appointment['appointment_date']}\n";
                        $response .= "Time: {$appointment['appointment_time']}\n";
                        $response .= "Thank you for confirming your appointment!";
                    } else {
                        $response = "END Failed to accept appointment. Please try again.";
                    }
                    
                    $updateStmt->close();
                } else {
                    $response = "END Invalid appointment ID or appointment not confirmed.";
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $response = "END An error occurred while accepting the appointment. Please try again later.";
            }
        }
    }

// --- ADMIN LOGIN ---
} else if ($steps[0] == "5") {
    if ($level == 1) {
        $response = "CON Enter Admin PIN:";
    } else if ($level == 2) {
        $pin = $steps[1];

        if ($phoneNumber === $adminPhone && $pin === $adminPIN) {
            $response = "CON Admin Menu:\n";
            $response .= "1. View Pending Appointments\n";
            $response .= "2. Approve Appointment\n";
        } else {
            $response = "END Access denied. Invalid PIN or phone.";
        }
    } else if ($level == 3) {
        $adminOption = $steps[2];
        
        if ($adminOption == "1") {
            try {
                include "db.php";
                $sql = "SELECT * FROM appointments WHERE status = 'pending' ORDER BY appointment_date ASC, appointment_time ASC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    $response = "END Pending Appointments:\n";
                    while ($row = $result->fetch_assoc()) {
                        $response .= "\nID: #{$row['id']}\n";
                        $response .= "Client: {$row['fullname']}\n";
                        $response .= "Phone: {$row['phone']}\n";
                        $response .= "Date: {$row['appointment_date']}\n";
                        $response .= "Time: {$row['appointment_time']}\n";
                    }
                } else {
                    $response = "END No pending appointments.";
                }

                $conn->close();
            } catch (Exception $e) {
                $response = "END An error occurred while fetching appointments. Please try again later.";
            }
        } else if ($adminOption == "2") {
            try {
                include "db.php";
                $sql = "SELECT id, fullname, phone, appointment_date, appointment_time FROM appointments WHERE status = 'pending' ORDER BY appointment_date ASC, appointment_time ASC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    $response = "CON Select appointment to approve:\n";
                    while ($row = $result->fetch_assoc()) {
                        $response .= "{$row['id']}. {$row['fullname']} - {$row['appointment_date']} at {$row['appointment_time']}\n";
                    }
                } else {
                    $response = "END No pending appointments to approve.";
                }

                $conn->close();
            } catch (Exception $e) {
                $response = "END An error occurred while fetching appointments. Please try again later.";
            }
        } else {
            $response = "END Invalid option selected.";
        }
    } else if ($level == 4) {
        $appointmentId = $steps[3];
        
        // Validate appointment ID
        if (!is_numeric($appointmentId)) {
            $response = "END Invalid appointment ID.";
        } else {
            try {
                include "db.php";
                
                // Get appointment details
                $sql = "SELECT * FROM appointments WHERE id = ? AND status = 'pending'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $appointmentId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $appointment = $result->fetch_assoc();
                    
                    // Update status to confirmed
                    $updateSql = "UPDATE appointments SET status = 'confirmed' WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("i", $appointmentId);
                    
                    if ($updateStmt->execute()) {
                        $response = "END Appointment #$appointmentId has been approved.\n";
                        $response .= "Client: {$appointment['fullname']}\n";
                        $response .= "Date: {$appointment['appointment_date']}\n";
                        $response .= "Time: {$appointment['appointment_time']}";
                    } else {
                        $response = "END Failed to approve appointment. Please try again.";
                    }
                    
                    $updateStmt->close();
                } else {
                    $response = "END Invalid appointment ID or appointment already processed.";
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $response = "END An error occurred while approving the appointment. Please try again later.";
            }
        }
    }

// --- INVALID INPUT ---
} else {
    $response = "END Invalid option. Please try again.";
}

// Helper function to get service name
function getServiceName($serviceCode) {
    $services = [
        '1' => 'Medical Consultation',
        '2' => 'Legal Advice',
        '3' => 'Business Coaching'
    ];
    return $services[$serviceCode] ?? 'Unknown Service'; 
}

echo $response;
