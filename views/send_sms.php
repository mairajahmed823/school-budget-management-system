<?php

if (isset($_POST['mobile']) && isset($_POST['message'])) {

    $api_key = "923161060259-4ff52dfd-d558-487b-8781-d51eb1ed7f43"; // yahan apni key lagao

    $mobile = $_POST['mobile'];
    $message = $_POST['message'];
    $sender = "SendPK"; // sender id

    $post = "sender=" . urlencode($sender) .
        "&mobile=" . urlencode($mobile) .
        "&message=" . urlencode($message);

    $url = "https://sendpk.com/api/sms.php?api_key=" . $api_key;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // 👇 ye 2 lines add karo
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);

    if ($result === false) {
        echo "Error: " . curl_error($ch);
    } else {
        echo "SMS Response: " . $result;
    }

    curl_close($ch);
}
?>

<form method="POST" action="send_sms.php">
    <input type="text" name="mobile" placeholder="923xxxxxxxxx" required><br><br>

    <textarea name="message" placeholder="Enter Message" required></textarea><br><br>

    <button type="submit">Send SMS</button>
</form>