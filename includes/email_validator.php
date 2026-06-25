<?php
/**
 * Email Validator — 5 layers of protection
 * Layer 1: Format check
 * Layer 2: Disposable domain blocklist (60+ services)
 * Layer 3: MX record DNS check
 * Layer 4: SMTP handshake (catches fake mailboxes on most servers)
 * Layer 5: Abstract API (catches fake Gmail/Yahoo/Outlook if key set)
 */

function validateEmail(string $email): array
{
    $email  = strtolower(trim($email));
    $domain = substr(strrchr($email, '@'), 1);
    $local  = substr($email, 0, strpos($email, '@'));

    // ── Layer 1: Format ────────────────────────────────
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return err('Invalid email format. Please check and try again.');
    }

    // ── Layer 2: Disposable / temp email domains ───────
    $blocked = [
        'mailinator.com','guerrillamail.com','guerrillamail.info','guerrillamail.biz',
        'guerrillamail.de','guerrillamail.net','guerrillamail.org','grr.la',
        'sharklasers.com','spam4.me','trashmail.com','trashmail.me','trashmail.net',
        'trashmail.io','trashmail.at','trashmail.xyz','dispostable.com',
        'mailnull.com','spamgourmet.com','spamgourmet.net','maildrop.cc',
        'throwam.com','fakeinbox.com','10minutemail.com','10minutemail.net',
        'tempr.email','discard.email','yopmail.com','yopmail.fr',
        'jetable.fr.nf','nospam.ze.tc','nomail.xl.cx','tempmail.com',
        'tempmail.net','tempmail.org','throwaway.email','temp-mail.org',
        'temp-mail.io','getnada.com','mailnesia.com','spamfree24.org',
        'spamhole.com','spamify.com','tempinbox.com','temporaryemail.net',
        'filzmail.com','mailexpire.com','spamfree.eu','spamobox.com',
        'getairmail.com','mailnew.com','mailscrap.com','spamevader.com',
        'boximail.com','crazymailing.com','dispostable.com','e4ward.com',
        'fakemail.net','hatespam.org','incognitomail.org','inoutmail.de',
        'lol.ovpn.to','mail-temp.com','mailzilla.org','mt2009.com',
        'no-spam.ws','nobulk.com','noclickemail.com','zetmail.com',
    ];

    if (in_array($domain, $blocked)) {
        return err('Temporary or disposable email addresses are not allowed. Please use your real email.');
    }

    // ── Layer 3: MX / DNS record check ─────────────────
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return err("The domain \"{$domain}\" does not exist or cannot receive emails.");
    }

    // ── Layer 4: SMTP handshake (catches many fake mailboxes) ─
    // Note: Gmail/Yahoo/Outlook accept all during SMTP and bounce later
    // but smaller domains this WILL catch fake addresses
    $smtpResult = smtpCheck($email, $domain);
    if ($smtpResult === 'invalid') {
        return err('This email address does not exist. Please use a real email.');
    }

    // ── Layer 5: Abstract API (most accurate — free 100/month) ─
    $apiKey = $_ENV['ABSTRACT_API_KEY'] ?? '';
    if ($apiKey && $apiKey !== 'your_abstract_api_key_here') {
        $apiResult = abstractApiCheck($email, $apiKey);
        if (!$apiResult['valid']) {
            return err($apiResult['reason']);
        }
    }

    return ['valid' => true, 'error' => ''];
}

// ── SMTP handshake check ───────────────────────────────
function smtpCheck(string $email, string $domain): string
{
    // Get MX records sorted by priority
    $mxHosts   = [];
    $mxWeights = [];
    if (!getmxrr($domain, $mxHosts, $mxWeights)) {
        return 'unknown'; // no MX = skip, Layer 3 already caught this
    }
    array_multisort($mxWeights, $mxHosts);

    // Try the highest-priority MX server
    foreach (array_slice($mxHosts, 0, 2) as $mx) {
        $socket = @fsockopen($mx, 25, $errno, $errstr, 8);
        if (!$socket) continue; // port 25 blocked by ISP → skip gracefully

        stream_set_timeout($socket, 8);

        $read = fgets($socket, 512); // greeting
        if (substr($read, 0, 3) !== '220') { fclose($socket); continue; }

        // EHLO
        fputs($socket, "EHLO quizapp.verify\r\n");
        while ($line = fgets($socket, 512)) {
            if (substr($line, 3, 1) === ' ') break; // end of multi-line
        }

        // MAIL FROM (neutral sender)
        fputs($socket, "MAIL FROM:<noreply@quizapp.verify>\r\n");
        $resp = fgets($socket, 512);
        if (substr($resp, 0, 3) !== '250') { fclose($socket); continue; }

        // RCPT TO — this is the actual check
        fputs($socket, "RCPT TO:<{$email}>\r\n");
        $resp = fgets($socket, 512);
        $code = (int)substr($resp, 0, 3);

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        // 250/251 = exists, 550/551/552/553/554 = doesn't exist
        if ($code === 250 || $code === 251) return 'valid';
        if ($code >= 550 && $code <= 559) return 'invalid';

        // Gmail/Outlook return 250 for everything (catch-all)
        // so 'unknown' means we can't tell — don't block
        return 'unknown';
    }

    return 'unknown'; // port 25 blocked — skip gracefully
}

// ── Abstract API check ────────────────────────────────
function abstractApiCheck(string $email, string $apiKey): array
{
    $url = "https://emailvalidation.abstractapi.com/v1/?api_key={$apiKey}&email=" . urlencode($email);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode !== 200) {
        return ['valid' => true, 'reason' => '']; // API down → let through
    }

    $data = json_decode($response, true);

    if ($data['is_disposable_email']['value'] ?? false) {
        return ['valid' => false, 'reason' => 'Disposable email addresses are not allowed.'];
    }

    if (!($data['is_mx_found']['value'] ?? true)) {
        return ['valid' => false, 'reason' => 'This email domain cannot receive emails.'];
    }

    $deliverability = $data['deliverability'] ?? 'UNKNOWN';
    if ($deliverability === 'UNDELIVERABLE') {
        return ['valid' => false, 'reason' => 'This email address does not exist. Please use your real email.'];
    }

    return ['valid' => true, 'reason' => ''];
}

function err(string $msg): array {
    return ['valid' => false, 'error' => $msg];
}