<?php
require 'vendor/autoload.php';

use AfricasTalking\SDK\AfricasTalking;

class SMS {
    protected $phone;
    protected $AT;
    function __construct() {
        // Initialize Africa's Talking gateway with correct credentials
        $this->AT = new AfricasTalking('sandbox', 'atsk_5972e3552f968f9d0d0a1fb57d0e779e7883b8f3f219d6c46f890c7195708a7164529b8e');
    }

    public function sendSMS($message, $recipients) {
        $sms = $this->AT->sms();

        try {
            // Don't include 'from' in sandbox
            $result = $sms->send([
                'to'      => $recipients,
                'message' => $message
            ]);
            
            // Log the result 
            error_log("SMS sent successfully to: " . $recipients);
            return true;
        } catch (Exception $e) {
            error_log("SMS Error: " . $e->getMessage());
            return false;
        }
    }
}

// Helper function for backward compatibility
function sendSMS($phone, $message) {
    $sms = new SMS();
    return $sms->sendSMS($message, $phone);
}

$phone="+250788293785";
$recipient="+250795020";
$message="Hello, this is a message from 12Group";

$sms= new SMS();
$sent = $sms->sendSMS($message,$recipient);
print_r($sent);

?>