<?php

function smtp_log(string $message): void {
    @file_put_contents(
        __DIR__ . DIRECTORY_SEPARATOR . 'smtp_error.log',
        date('Y-m-d H:i:s') . ' ' . $message . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function smtp_ca_file(): ?string {
    $candidates = [
        (string) ini_get('openssl.cafile'),
        (string) ini_get('curl.cainfo'),
        'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
        'C:\\xampp\\php\\extras\\ssl\\cacert.pem',
        'C:\\xampp\\perl\\vendor\\lib\\Mozilla\\CA\\cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ];
    foreach ($candidates as $file) {
        if ($file !== '' && @is_file($file)) {
            return $file;
        }
    }
    return null;
}

function smtp_read($fp): string {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtp_step($fp, string $label, string $command, array $okCodes, string $hint = ''): ?string {
    if ($command !== '') {
        fwrite($fp, $command . "\r\n");
    }
    $resp = smtp_read($fp);
    if (!in_array((int) substr($resp, 0, 3), $okCodes, true)) {
        $meta = stream_get_meta_data($fp);
        $why = trim(preg_replace('/\s+/', ' ', $resp));
        if ($why === '') {
            $why = !empty($meta['timed_out'])
                ? 'no reply (timed out) - network, firewall or antivirus is blocking the connection'
                : 'no reply (connection closed by something between this computer and Gmail)';
        }
        smtp_log("STEP {$label} FAILED: {$why}" . $hint);
        return null;
    }
    return $resp;
}

function smtp_attempt(array $cfg, int $port, string $mode, string $to, string $subject, string $body, string $replyTo): bool {
    $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $cfg['host'], 'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT];
    $ca = smtp_ca_file();
    if ($ca !== null) {
        $ssl['cafile'] = $ca;
    }
    $scheme = ($mode === 'ssl') ? 'ssl' : 'tcp';
    $fp = @stream_socket_client(
        $scheme . '://' . $cfg['host'] . ':' . $port,
        $errno, $errstr, 20, STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => $ssl])
    );
    if (!$fp) {
        smtp_log("PORT {$port}: CONNECTION FAILED - [{$errno}] {$errstr}"
               . ($ca === null ? ' (no certificate bundle found)' : ''));
        return false;
    }
    stream_set_timeout($fp, 20);

    $ok = false;
    do {
        if (smtp_step($fp, "PORT {$port} GREETING", '', [220]) === null) break;
        if (smtp_step($fp, "PORT {$port} EHLO", 'EHLO tutorconnect', [250]) === null) break;

        if ($mode === 'tls') {
            if (smtp_step($fp, "PORT {$port} STARTTLS", 'STARTTLS', [220]) === null) break;
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                smtp_log("PORT {$port}: could not start the secure (TLS) connection - certificate or antivirus problem");
                break;
            }
            if (smtp_step($fp, "PORT {$port} EHLO (secure)", 'EHLO tutorconnect', [250]) === null) break;
        }

        $loginHint = ' -> Gmail rejected the login. Use a 16-character App Password (2-Step Verification must be on).';
        if (smtp_step($fp, 'AUTH', 'AUTH LOGIN', [334]) === null) break;
        if (smtp_step($fp, 'USERNAME', base64_encode($cfg['username']), [334]) === null) break;
        if (smtp_step($fp, 'PASSWORD', base64_encode($cfg['password']), [235], $loginHint) === null) break;
        if (smtp_step($fp, 'MAIL FROM', 'MAIL FROM:<' . $cfg['from'] . '>', [250]) === null) break;
        if (smtp_step($fp, 'RCPT TO', 'RCPT TO:<' . $to . '>', [250, 251]) === null) break;
        if (smtp_step($fp, 'DATA', 'DATA', [354]) === null) break;

        $headers = 'Date: ' . date('r') . "\r\n"
                 . 'From: ' . mb_encode_mimeheader($cfg['from_name'], 'UTF-8') . ' <' . $cfg['from'] . ">\r\n"
                 . 'To: <' . $to . ">\r\n"
                 . ($replyTo !== '' ? 'Reply-To: <' . $replyTo . ">\r\n" : '')
                 . 'Subject: ' . $subject . "\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: 8bit\r\n";

        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body);

        fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
        $ok = smtp_step($fp, 'MESSAGE', '', [250]) !== null;
    } while (false);

    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return $ok;
}

function smtp_send_mail(string $to, string $subject, string $body, string $replyTo = ''): bool {
    $cfg = @include __DIR__ . DIRECTORY_SEPARATOR . 'smtp_config.php';
    if (!is_array($cfg) || strpos((string) $cfg['password'], 'PASTE_') === 0) {
        smtp_log('NOT CONFIGURED: smtp_config.php is missing or still has the placeholder password.');
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        smtp_log('Invalid recipient address: ' . $to);
        return false;
    }
    if (!extension_loaded('openssl')) {
        smtp_log('PHP OpenSSL extension is not loaded. Enable extension=openssl in php.ini and restart Apache.');
        return false;
    }

    $attempts = ((int) $cfg['port'] === 587)
        ? [[587, 'tls'], [465, 'ssl']]
        : [[465, 'ssl'], [587, 'tls']];

    foreach ($attempts as [$port, $mode]) {
        if (smtp_attempt($cfg, $port, $mode, $to, $subject, $body, $replyTo)) {
            smtp_log("SENT to {$to} via port {$port}");
            return true;
        }
    }
    return false;
}
