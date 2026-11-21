<?php
/**
 * Simple TOTP implementation for PHP
 * Handles Base32 decoding and TOTP verification
 */
class TOTP {
    /**
     * Verify a TOTP code against a secret
     * @param string $secret Base32 encoded secret
     * @param string $code 6-digit code to verify
     * @param int $discrepancy Allowed time drift (1 = 30 seconds)
     * @return bool
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate TOTP code for a given secret and time slice
     */
    public static function getCode($secret, $timeSlice) {
        $secretKey = self::base32Decode($secret);

        // Pack time into binary string
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        
        // HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Dynamic truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        
        $value = unpack('N', $hashPart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Helper to decode Base32 strings
     */
    private static function base32Decode($base32) {
        $base32 = strtoupper($base32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $decoded = '';
        $buffer = 0;
        $bufferSize = 0;

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $value = strpos($alphabet, $char);
            if ($value === false) continue;

            $buffer = ($buffer << 5) | $value;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $decoded .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }

        return $decoded;
    }
}
?>
