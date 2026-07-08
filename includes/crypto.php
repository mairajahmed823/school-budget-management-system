<?php
function encrypt_id($id) {
    $key = "my_secret_key";
    return base64_encode(openssl_encrypt($id, 'AES-128-ECB', $key));
}

function decrypt_id($encrypted_id) {
    $key = "my_secret_key";
    return openssl_decrypt(base64_decode($encrypted_id), 'AES-128-ECB', $key);
}