<?php
/** Class Crypt
 *
 * Encrypt and decrypt functions using OpenSSL
 *
 * Encryption key and initialization vector are set in the Session, using a pseudo-random string of 256 bits for the key,
 * and AES-256 cipher for the iv
 *
 * For confirmation emails and reset password requests, encryptWithKey and decryptWithkey are used with a predefined key and iv,
 * as it will not decrypt if the user logs in again (new key and IV are generated)
 *
 */

class Crypt
{
    public static function encrypt($string) {
        // Set key with a pseudo-random string of 32 bytes (256 bits), and an initialization vector (128 bits) if it doesn't exist in session
        if(!Session::keyExists('encKey') || !Session::keyExists('iv')) {
            Session::set('encKey', base64_encode(openssl_random_pseudo_bytes(32)));
            Session::set('iv', openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc')));
        }

        // Get key and iv from session
        $key = Session::get('encKey');
        $iv  = Session::get('iv');

        // Remove base64 encoding from the key
        $encryptionKey = base64_decode($key);
        // Encrypt the data using AES 256 encryption in CBC mode using the key and iv.
        $encryptedString = openssl_encrypt($string, 'aes-256-cbc', $encryptionKey, 0, $iv);
        return base64_encode($encryptedString);
    }


    public static function decrypt($string) {
        if(!Session::keyExists('encKey') || !Session::keyExists('iv')) {
            throw new exception("Encryption failed - key and/or iv doesn't exist.");
        }
        $key = Session::get('encKey');
        $iv  = Session::get('iv');

        // Remove the base64 encoding from our key and encrypted string
        $encryptionKey   = base64_decode($key);
        $encryptedString = base64_decode($string);
        return openssl_decrypt($encryptedString, 'aes-256-cbc', $encryptionKey, 0, $iv);
    }


    // Used for email verification on account registration and reset password
    // Key and iv cannot be randomly generated - it will change every time a new session starts
    public static function encryptWithKey($string, $key, $iv="qwertyasdfghzxcv") {
        if(strlen($key) !== 32) {
            Throw new Exception("Encryption key must be 32 characters long.");
        }
        if(strlen($iv) !== 16) {
            Throw new Exception("Encryption iv must be 16 characters long.");
        }

        $encryptedString = openssl_encrypt($string, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encryptedString);
    }


    public static function decryptWithKey($string, $key, $iv="qwertyasdfghzxcv") {
        if(strlen($key) !== 32) {
            Throw new Exception("Encryption key must be 32 characters long.");
        }
        if(strlen($iv) !== 16) {
            Throw new Exception("Encryption iv must be 16 characters long.");
        }

        $string = base64_decode($string);
        return openssl_decrypt($string, 'aes-256-cbc', $key, 0, $iv);

    }
}