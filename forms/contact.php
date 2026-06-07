<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

// Marker für secrets.php (verhindert Direktaufruf via Browser)
define('CONTACT_APP', true);

// secrets.php liegt im Webroot (eine Ebene über /forms/)
$secretsPath = __DIR__ . '/../secrets.php';
if (!is_file($secretsPath)) {
    http_response_code(500);
    error_log('[contact.php] secrets.php nicht gefunden: ' . $secretsPath);
    exit('Server-Konfiguration fehlt.');
}
$cfg = require $secretsPath;

// === Konfiguration aus secrets.php ===
define('SMTP_HOST',         $cfg['SMTP_HOST']);
define('SMTP_PORT',         (int)$cfg['SMTP_PORT']);
define('SMTP_USER',         $cfg['SMTP_USER']);
define('SMTP_PASS',         $cfg['SMTP_PASS']);
define('MAIL_TO',           $cfg['MAIL_TO']);
define('TURNSTILE_SITEKEY', $cfg['TURNSTILE_SITEKEY']);
define('TURNSTILE_SECRET',  $cfg['TURNSTILE_SECRET']);

// Rate-Limit: max. Anfragen pro IP pro Zeitfenster (Sekunden)
const RATE_LIMIT_MAX    = 5;
const RATE_LIMIT_WINDOW = 60;
const RATE_LIMIT_DIR    = __DIR__ . '/.ratelimit';
// =================

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

/* ---------- Hilfsfunktionen ---------- */

function client_ip(): string {
    // Bei Reverse-Proxy ggf. HTTP_X_FORWARDED_FOR auswerten – nur, wenn vertrauenswürdig.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

function rate_limit_check(string $ip): bool {
    if (!is_dir(RATE_LIMIT_DIR)) {
        @mkdir(RATE_LIMIT_DIR, 0700, true);
    }
    $file = RATE_LIMIT_DIR . '/' . hash('sha256', $ip);
    $now  = time();
    $hits = [];

    $fp = @fopen($file, 'c+');
    if ($fp === false) {
        return false; // fail-closed
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            return false;
        }
        $raw = stream_get_contents($fp) ?: '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $hits = $decoded;
            }
        }
        $hits = array_values(array_filter(
            $hits,
            static fn($t) => is_int($t) && ($now - $t) < RATE_LIMIT_WINDOW
        ));

        if (count($hits) >= RATE_LIMIT_MAX) {
            return false;
        }
        $hits[] = $now;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($hits));
        fflush($fp);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    return true;
}

function verify_turnstile(string $token, string $ip): bool {
    if ($token === '') return false;

    $data = http_build_query([
        'secret'   => TURNSTILE_SECRET,
        'response' => $token,
        'remoteip' => $ip,
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => $data,
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ]);

    $resp = @file_get_contents(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        false,
        $ctx
    );
    if ($resp === false) return false;

    $json = json_decode($resp, true);
    return is_array($json) && !empty($json['success']);
}

/* ---------- Request-Verarbeitung ---------- */

// Debug-Logging (für Diagnose). In Produktion auf false setzen.
$DEBUG    = false;
$debugLog = __DIR__ . '/.ratelimit/maildebug.log';
if ($DEBUG && !is_dir(__DIR__ . '/.ratelimit')) {
    @mkdir(__DIR__ . '/.ratelimit', 0700, true);
}
$dlog = function (string $stage, array $extra = []) use ($DEBUG, $debugLog): void {
    if (!$DEBUG) return;
    @file_put_contents(
        $debugLog,
        '[' . date('c') . "] $stage " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND
    );
};
$dlog('REQUEST', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'ip'     => $_SERVER['REMOTE_ADDR']    ?? '',
    'len'    => $_SERVER['CONTENT_LENGTH'] ?? '',
    'keys'   => array_keys($_POST),
    'hp'     => [
        'website'    => $_POST['website']    ?? null,
        'url'        => $_POST['url']        ?? null,
        'company_hp' => $_POST['company_hp'] ?? null,
        'hp_time'    => $_POST['hp_time']    ?? null,
    ],
    'cf_token_len' => isset($_POST['cf-turnstile-response'])
        ? strlen($_POST['cf-turnstile-response']) : 0,
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Methode nicht erlaubt.');
}

// Body-Größe begrenzen (Schutz vor Flooding)
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) {
    http_response_code(413);
    exit('Anfrage zu groß.');
}

$ip = client_ip();

// 1) Rate-Limiting
if (!rate_limit_check($ip)) {
    $dlog('STOP_RATE_LIMIT');
    http_response_code(429);
    header('Retry-After: ' . RATE_LIMIT_WINDOW);
    exit('Zu viele Anfragen. Bitte versuchen Sie es später erneut.');
}

// 2) Honeypot-Felder – müssen leer bleiben.
//    'website'/'url'/'company_hp' = klassische Honeypots,
//    'hp_time' = Zeit-Trap (zu schnell ausgefüllt = Bot).
if (!empty($_POST['website']) || !empty($_POST['url']) || !empty($_POST['company_hp'])) {
    $dlog('STOP_HONEYPOT_FIELD');
    http_response_code(204);
    exit;
}
$formLoaded = (int)($_POST['hp_time'] ?? 0);
if ($formLoaded <= 0 || (time() - $formLoaded) < 3) {
    $dlog('STOP_HP_TIME', ['hp_time' => $formLoaded, 'now' => time(), 'diff' => time() - $formLoaded]);
    http_response_code(204);
    exit;
}

// 3) Captcha (Cloudflare Turnstile) prüfen
$captchaToken = (string)($_POST['cf-turnstile-response'] ?? '');
if (!verify_turnstile($captchaToken, $ip)) {
    $dlog('STOP_CAPTCHA', ['token_len' => strlen($captchaToken)]);
    http_response_code(400);
    exit('Captcha-Prüfung fehlgeschlagen. Bitte laden Sie die Seite neu.');
}

// 4) Eingabe-Validierung
$name    = trim((string)($_POST['name']    ?? ''));
$emailIn = trim((string)($_POST['email']   ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

// Header-Injection verhindern (CRLF/Null entfernen) + HTML strippen
$name    = str_replace(["\r", "\n", "\0"], ' ', strip_tags($name));
$subject = str_replace(["\r", "\n", "\0"], ' ', strip_tags($subject));
$message = str_replace(["\r\n", "\r"], "\n", $message);

// UTF-8 absichern
foreach (['name', 'subject', 'message'] as $f) {
    if (!mb_check_encoding($$f, 'UTF-8')) {
        http_response_code(400);
        exit('Ungültige Zeichenkodierung.');
    }
}

$email = filter_var($emailIn, FILTER_VALIDATE_EMAIL);

$errors = [];
if (mb_strlen($name)    < 2   || mb_strlen($name)    > 100)  $errors[] = 'Name';
if (!$email             || mb_strlen($emailIn) > 254)        $errors[] = 'E-Mail';
if (mb_strlen($subject) < 2   || mb_strlen($subject) > 200)  $errors[] = 'Betreff';
if (mb_strlen($message) < 10  || mb_strlen($message) > 5000) $errors[] = 'Nachricht';

// Einfacher Spam-Score: zu viele Links = verdächtig
if (preg_match_all('~https?://~i', $message) > 5) {
    $errors[] = 'Nachricht (zu viele Links)';
}

if ($errors) {
    http_response_code(400);
    exit('Bitte prüfen Sie folgende Felder: ' . implode(', ', $errors));
}

/* ---------- Mail-Builder ---------- */

/**
 * Erzeugt ein HTML-Mail-Template mit Footer (Logo + Social Links + Impressum).
 * Logo + Icons werden per CID eingebettet (kein externes Hotlink).
 *
 * @param array<string,string> $iconCids  Map: Platzhalter => CID (z. B. ['{phoneIcon}' => 'icon-phone@...']).
 *                                        Fehlen Einträge, wird der Platzhalter durch Leerstring ersetzt.
 */
function build_mail_html(string $intro, string $bodyText, string $logoCid, array $iconCids = []): string {
    // Inhalt sicher escapen
    $intro = nl2br(htmlspecialchars($intro,    ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $body  = nl2br(htmlspecialchars($bodyText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $logoHtml = $logoCid !== ''
        ? '<img src="cid:' . htmlspecialchars($logoCid, ENT_QUOTES) . '" alt="Brückner Codecraft" '
        . 'style="max-width:100%;height:auto;">'
        : '';

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
    </head>
    <body
        style="margin:0;padding:0;background:#f4f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#222;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:24px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;
                        box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                        <tr>
                            <td colspan="5" style="padding:28px 32px 8px 32px;font-size:15px;line-height:1.55;">
                                {$intro}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" style="padding:8px 32px 24px 32px;font-size:14px;line-height:1.55;
                                background:#fafafa;border-top:1px solid #eee;border-bottom:1px solid #eee;">
                                {$body}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" style="padding: 5px 5px 5px 5px;">Mit freundlichen Grüßen</td>
                        </tr>
                        <tr>
                            <td rowspan="11" align="center" valign="top"
                                style="max-width:220px;padding:10px 10px 10px 10px;text-align:center;font-size:12px;line-height:1.5;color:#666;">
                                {$logoHtml}</td>
                            <td colspan="4" style="font-weight:600;color:#222;">Maik Brückner</td>
                        </tr>
                        <tr>
                            <td colspan="4" style="font-style:italic;color:#666;">CEO Brückner Codecraft</td>
                        </tr>
                        <tr>
                            <td colspan="4">&nbsp;</td>
                        </tr>
                        <tr>
                            <td width="24" style="max-width:16px;padding:5px 8px 5px 0;text-align:left;">{phoneIcon}</td>
                            <td colspan="3" style="font-weight:600;color:#222;">+49 171 6876940</td>
                        </tr>
                        <tr>
                            <td width="24" style="max-width:16px;padding:5px 8px 5px 0;text-align:left;">{mailIcon}</td>
                            <td colspan="3"><a href="mailto:info@bruckner-codecraft.com"
                                    style="font-weight:600;color:#0a66c2;text-decoration:none;">info@bruckner-codecraft.com</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4">&nbsp;</td>
                        </tr>
                        <tr>
                            <td colspan="4" style="font-weight:600;color:#222;">Brückner Codecraft</td>
                        </tr>
                        <tr>
                            <td colspan="4" style="color:#666;">Friesenstraße 10 <b>&middot;</b> 98529 Suhl</td>
                        </tr>
                        <tr>
                            <td width="24" style="max-width:16px;padding:5px 8px 5px 0;text-align:left;">{webIcon}</td>
                            <td colspan="3"><a href="https://bruckner-codecraft.com"
                                    style="font-weight:600;color:#0a66c2;text-decoration:none;">bruckner-codecraft.com</a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4">&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td align="center" valign="middle" style="max-width:28px;padding:12px 8px;">
                                <a href="https://github.com/mabrueckner" style="text-decoration:none;">{githubIcon}</a>
                            </td>
                            <td align="center" valign="middle" style="max-width:28px;padding:12px 8px;">
                                <a href="https://www.linkedin.com/in/maik-brückner" style="text-decoration:none;">{linkedinIcon}</a>
                            </td>
                            <td align="center" valign="middle" style="max-width:28px;padding:12px 8px;">
                                <a href="https://www.facebook.com/maik.brueckner" style="text-decoration:none;">{facebookIcon}</a>
                            </td>
                        </tr>
                        <tr>
                            <td width="24" style="max-width:16px;padding:5px 8px 5px 10px;text-align:left;">{lockIcon}</td>
                            <td colspan="4" style="font-size: medium;font-weight:600;color:#222;">Hinweis zum Datenschutz:</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td colspan="4" style="padding: 0px 5px 0px 0px;font-size: small;color:#222;">Diese E-Mail und etwaige Anhänge enthalten vertrauliche Informationen und sind ausschließlich für den/die Empfänger bestimmt. Sollten Sie diese Nachricht irrtümlich erhalten haben, informieren Sie bitte den Absender und löschen Sie die Nachricht.</td>
                        </tr>
                        <tr>
                            <td colspan="5">&nbsp;</td>
                        </tr>
                        <tr>
                            <td width="24" style="max-width:16px;padding:5px 8px 5px 10px;text-align:left;">{plantIcon}</td>
                            <td colspan="4" style="padding: 5px 5px 5px 0px;font-size: small;color:#222;">Bitte denken Sie an die Umwelt, bevor Sie diese E-Mail ausdrucken.</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
HTML;

    // Icon-Platzhalter einsetzen.
    // Inline-Icons: 16 px Anzeige (Datei 32–48 px für Retina).
    // Social-Icons: 28 px Anzeige (Datei 56–64 px für Retina).
    $inlineSize = 16;
    $socialSize = 28;

    $replacements = [];
    foreach (['phoneIcon','mailIcon','webIcon','lockIcon','plantIcon'] as $key) {
        $cid = $iconCids['{' . $key . '}'] ?? '';
        $replacements['{' . $key . '}'] = $cid !== ''
            ? '<img src="cid:' . htmlspecialchars($cid, ENT_QUOTES) . '" alt="" width="' . $inlineSize
              . '" height="' . $inlineSize . '" style="max-width:16px;height:auto;display:inline-block;vertical-align:middle;border:0;">'
            : '';
    }
    foreach (['githubIcon','linkedinIcon','facebookIcon'] as $key) {
        $cid = $iconCids['{' . $key . '}'] ?? '';
        $alt = ucfirst(str_replace('Icon', '', $key));
        $replacements['{' . $key . '}'] = $cid !== ''
            ? '<img src="cid:' . htmlspecialchars($cid, ENT_QUOTES) . '" alt="' . $alt . '" width="' . $socialSize
              . '" height="' . $socialSize . '" style="max-width:28px;height:auto;display:inline-block;vertical-align:middle;border:0;">'
            : $alt;
    }

    return strtr($html, $replacements);
}

/**
 * Plain-Text-Variante mit ASCII-Footer für Clients ohne HTML.
 */
function build_mail_text(string $intro, string $bodyText): string {
    $sep = str_repeat('-', 60);
    return $intro . "\n\n" . $bodyText . "\n\n" . $sep . "\n"
        . "Mit freundlichen Grüßen\n\n"
        . "Maik Brückner\n"
        . "CEO Brückner Codecraft\n\n"
        . "Tel.:  +49 171 6876940\n"
        . "Mail:  info@bruckner-codecraft.com\n\n"
        . "Brückner Codecraft\n"
        . "Friesenstraße 10, 98529 Suhl\n"
        . "Web:   https://bruckner-codecraft.com\n\n"
        . "GitHub:   https://github.com/mabrueckner\n"
        . "LinkedIn: https://www.linkedin.com/in/maik-brückner\n"
        . "Facebook: https://www.facebook.com/maik.brueckner\n"
        . $sep . "\n"
        . "Hinweis zum Datenschutz: Diese E-Mail und etwaige Anhänge enthalten\n"
        . "vertrauliche Informationen und sind ausschließlich für den/die\n"
        . "Empfänger bestimmt. Sollten Sie diese Nachricht irrtümlich erhalten\n"
        . "haben, informieren Sie bitte den Absender und löschen Sie die Nachricht.\n\n"
        . "Bitte denken Sie an die Umwelt, bevor Sie diese E-Mail ausdrucken.\n";
}

/**
 * Logo für CID-Embed suchen. PNG bevorzugt (beste Mail-Client-Kompatibilität),
 * SVG nur als Fallback (rendert nicht in allen Mail-Clients).
 * Erwartete Pfade relativ zu /forms/contact.php → also ../img/...
 */
function find_logo_path(): string {
    $candidates = [
        __DIR__ . '/../img/logo_mail.png',
        __DIR__ . '/../img/logo_h_de.png',
        __DIR__ . '/../img/logo_h_de.svg',
    ];
    foreach ($candidates as $p) {
        if (is_file($p) && is_readable($p)) return $p;
    }
    return '';
}

/**
 * Sucht ein Icon-PNG unter ../img/mail/<name>.png.
 * Liefert leeren String, wenn nicht vorhanden.
 */
function find_icon_path(string $name): string {
    $p = __DIR__ . '/../img/mail/' . $name . '.png';
    return (is_file($p) && is_readable($p)) ? $p : '';
}

/* ---------- Mailversand ---------- */

$logoPath = find_logo_path();
$logoCid  = $logoPath !== '' ? 'logo@bruckner-codecraft' : '';

// Icon-Konfiguration: Platzhalter => Dateiname (ohne Endung) im Ordner ../img/mail/
$iconFiles = [
    '{phoneIcon}'    => 'telephone-fill',
    '{mailIcon}'     => 'envelope-fill',
    '{webIcon}'      => 'globe2',
    '{lockIcon}'     => 'shield-lock-fill',
    '{plantIcon}'    => 'tree-fill',
    '{githubIcon}'   => 'github',
    '{linkedinIcon}' => 'linkedin',
    '{facebookIcon}' => 'facebook',
];

// 1) Admin-Mail an dich
$adminIntro = "Neue Kontaktanfrage";
$adminBody  =
    "Name:    $name\n" .
    "E-Mail:  $email\n" .
    "IP:      $ip\n" .
    "Zeit:    " . date('Y-m-d H:i:s') . "\n\n" .
    "Betreff:\n$subject\n\n" .
    "Nachricht:\n$message";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_PORT === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';

    if ($DEBUG) {
        $mail->SMTPDebug   = 2;
        $mail->Debugoutput = function ($str, $level) use ($debugLog) {
            @file_put_contents(
                $debugLog,
                '[' . date('c') . "] L$level $str\n",
                FILE_APPEND
            );
        };
    }

    if ($logoCid !== '') {
        $mail->addEmbeddedImage($logoPath, $logoCid, 'logo', 'base64',
            str_ends_with(strtolower($logoPath), '.svg') ? 'image/svg+xml' : 'image/png');
    }

    // Icons einbetten – nur die, die als Datei existieren.
    $iconCids = [];
    foreach ($iconFiles as $placeholder => $fileName) {
        $iconPath = find_icon_path($fileName);
        if ($iconPath === '') continue;
        $cid = $fileName . '@bruckner-codecraft';
        $mail->addEmbeddedImage($iconPath, $cid, $fileName . '.png', 'base64', 'image/png');
        $iconCids[$placeholder] = $cid;
    }

    $mail->setFrom(SMTP_USER, 'Kontaktformular');
    $mail->addAddress(MAIL_TO);
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Neue Kontaktanfrage: ' . mb_substr($subject, 0, 150);
    $mail->Body    = build_mail_html($adminIntro, $adminBody, $logoCid, $iconCids);
    $mail->AltBody = build_mail_text($adminIntro, $adminBody);

    $mail->send();
    if ($DEBUG) {
        @file_put_contents(
            $debugLog,
            '[' . date('c') . "] === ADMIN SEND OK, MessageID=" . $mail->getLastMessageID() . " ===\n\n",
            FILE_APPEND
        );
    }

    http_response_code(200);
    echo 'Ihre Nachricht wurde verschickt. Vielen Dank!';
} catch (Exception $e) {
    http_response_code(500);
    error_log('[contact.php] Mailfehler: ' . $mail->ErrorInfo);
    if ($DEBUG) {
        @file_put_contents(
            $debugLog,
            '[' . date('c') . "] === EXCEPTION: " . $mail->ErrorInfo . " ===\n\n",
            FILE_APPEND
        );
    }
    echo 'Es gab ein Problem beim Versenden Ihrer Nachricht. Bitte versuchen Sie es später erneut.';
}
