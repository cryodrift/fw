<?php

//declare(strict_types=1);

namespace cryodrift\fw;

/**
 * base for the Matrics project
 *
 */
class Crypt
{

    private static string $base32_alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encryptPw(string $data, string $password): string
    {
        // Generate a salt
        $salt = openssl_random_pseudo_bytes(8);

        // Derive a key from the password and salt using PBKDF2
        $key = hash_pbkdf2('sha256', $password, $salt, 1000, 32, true);

        // Encrypt the string using AES-256-CBC
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encryptedString = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

        // Combine the salt, IV, and encrypted string
        return base64_encode($salt . $iv . $encryptedString);
    }

    public static function decryptPw(string $data, string $password): string
    {
        // Decode the base64 encoded encrypted string
        $data = base64_decode($data);

        // Extract the salt, IV, and encrypted string
        $salt = substr($data, 0, 8);
        $iv = substr($data, 8, openssl_cipher_iv_length('aes-256-cbc'));
        $encryptedString = substr($data, 8 + openssl_cipher_iv_length('aes-256-cbc'));

        // Derive the key from the password and salt using PBKDF2
        $key = hash_pbkdf2('sha256', $password, $salt, 1000, 32, true);

        // Decrypt the string using AES-256-CBC
        return openssl_decrypt($encryptedString, 'aes-256-cbc', $key, 0, $iv);
    }


    public static function base32_encode(string $data): string
    {
        $output = '';
        $buffer = 0;
        $bits_left = 0;

        foreach (str_split($data) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits_left += 8;

            while ($bits_left >= 5) {
                $bits_left -= 5;
                $output .= self::$base32_alphabet[($buffer >> $bits_left) & 0x1F];
                $buffer &= (1 << $bits_left) - 1;
            }
        }

        if ($bits_left > 0) {
            $output .= self::$base32_alphabet[($buffer << (5 - $bits_left)) & 0x1F];
        }

        return $output;
    }


    public static function base32_decode(string $data): string
    {
        $data = strtoupper($data);
        $binary = '';

        $padding = substr_count($data, '=');
        $data = rtrim($data, '=');

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(strpos(self::$base32_alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $tmp = bin2hex(substr($binary, 0, strlen($binary) - 8 * $padding));
        return pack('H*', $tmp);
    }

    public static function generateSSHKeys(string $password, string $comment): array
    {
// 1) Generate an RSA key pair (2048 bits, typically secure enough)
        $config = [
          "private_key_bits" => 2048,
          "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);

        if (!$res) {
            die("Error: Could not generate RSA key pair.");
        }

        // 2) Export the private key in PEM format (PKCS#1 or PKCS#8), protected by a passphrase if desired
        openssl_pkey_export($res, $prikey, $password);

        // 3) Extract RSA details
        $details = openssl_pkey_get_details($res);
        $rawMod = $details['rsa']['n'];  // Modulus (binary)
        $rawExp = $details['rsa']['e'];  // Exponent (binary)

        // 4) Build the OpenSSH-formatted public key
        $pubkey = self::buildOpenSSHRsaPublicKey($rawExp, $rawMod, $comment);

        if ($prikey && $pubkey) {
            return ['pri' => $prikey, 'pub' => $pubkey];
        }
        return [];
    }


    public static function decrypt(string $data, string $key, bool $private = true): string
    {
        if ($private) {
            openssl_private_decrypt($data, $out, $key);
        } else {
            openssl_public_decrypt($data, $out, $key);
        }
        return $out;
    }

    public static function encrypt(string $data, string $key, bool $public = true): string
    {
        if ($public) {
            openssl_public_encrypt($data, $out, $key);
        } else {
            openssl_private_encrypt($data, $out, $key);
        }
        return $out;
    }

    /**
     * Build an OpenSSH-style RSA public key string:
     *   ssh-rsa <base64_payload> [optional_comment]
     */
    private static function buildOpenSSHRsaPublicKey(string $rawExp, string $rawMod, string $comment): string
    {
        // 1) Convert the raw exponent/modulus from binary to big integers via GMP
        $bigExp = gmp_import($rawExp);
        $bigMod = gmp_import($rawMod);

        // 2) Pack them into OpenSSH’s binary structure:
        //    string "ssh-rsa" | string exponent | string modulus
        //    each string is a 4-byte length followed by that many bytes
        $algoBytes = self::sshString("ssh-rsa");
        $expBytes = self::sshString(self::bignumToBinary($bigExp));
        $modBytes = self::sshString(self::bignumToBinary($bigMod));

        // 3) Combine all
        $publicKeyPayload = $algoBytes . $expBytes . $modBytes;

        // 4) Convert to base64
        $publicKeyBase64 = base64_encode($publicKeyPayload);

        // 5) Final line: ssh-rsa AAAAB3Nz... comment
        return "ssh-rsa " . $publicKeyBase64 . " " . $comment;
    }

    /**
     * Convert a GMP integer to binary (big-endian) for OpenSSH.
     * Requires PHP 7.3+ (for gmp_export). If you need older PHP, you'll
     * have to write a custom big-endian exporter.
     */
    private static function bignumToBinary(\GMP $value): string
    {
        // gmp_export defaults to big-endian
        return gmp_export($value);
    }

    /**
     * Helper: Encode a string for the SSH wire format
     * [4-byte length][string bytes]
     */
    private static function sshString(string $data): string
    {
        return pack("N", strlen($data)) . $data;
    }

    public static function getRandomUuid(): string
    {
        return base64_encode(openssl_random_pseudo_bytes(32));
    }

}
