
STRING

Single Link
https://mshastra.com/sendurl.aspx?user=……..&pwd=........&senderid=........&mobileno=255..............&msgtext=TestSMS&CountryCode=255


Multiple Link
https://mshastra.com/sendurlcomma.aspx?user=……..&pwd=........&senderid=........&mobileno=255..............&msgtext=TestSMS&CountryCode=255


OPTION 1: WITH ONLY 9 LAST DIGIT

<?php

// Simulate getting a phone number from a form
$rawPhone = $_POST['phone'] ?? '0712001002'; // Replace this with actual form input

// Extract only digits
$cleanedPhone = preg_replace('/\D/', '', $rawPhone);

// Get the last 9 digits
$lastNine = substr($cleanedPhone, -9);

// Add country code prefix
$fullPhone = '255' . $lastNine;

$url = 'http://mshastra.com/sendsms_api_json.aspx';

$jsonData = array(
    array(
        "user" => "YOUR PROFILE ID", 
        "pwd" => "YOUR PASSWORD", 
        "number" => $fullPhone,
        "msg" => "testing form API",
        "sender" => "YOUR SENDER ID",
        "language" => "English"
    )
);

$jsonDataEncoded = json_encode($jsonData);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$result = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    echo 'Server Response: ' . $result;
}

curl_close($ch);
?>



OPTION 2: WITH FULL NUMBER

<?php

$url = 'http://mshastra.com/sendsms_api_json.aspx';


$jsonData = array(
    array(
        "user" => "YOUR PROFILE ID", 
        "pwd" => "YOUR PASSWORD", 
        "number" => "255XXXXXXXXX",
        "msg" => "testing form API",
        "sender" => "YOUR SENDER ID",
        "language" => "English"
    )
);


$jsonDataEncoded = json_encode($jsonData);


$ch = curl_init($url);


curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));


$result = curl_exec($ch);


if (curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    
    echo 'Server Response: ' . $result;
}


curl_close($ch);

?>