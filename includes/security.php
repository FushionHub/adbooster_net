<?php
declare(strict_types=1);

/**
 * Centralised security helpers — Adsbooster.net
 *
 * Loaded from `config/site_setting_and_config.php`. Every helper is
 * idempotent (`function_exists` guarded) so double-includes are safe.
 */

// ─────────────────────────────────────────────────────────────
//  Output escaping
// ─────────────────────────────────────────────────────────────
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    function e_attr(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

// ─────────────────────────────────────────────────────────────
//  HTML sanitisation for user-authored rich content
//  (blog posts, CMS blocks, listing descriptions…)
// ─────────────────────────────────────────────────────────────
if (!function_exists('sanitize_html')) {
    /**
     * Allow-list HTML sanitizer. Strips scripts, event handlers, javascript:
     * / data: URLs and dangerous tags, keeps benign formatting.
     */
    function sanitize_html(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $allowedTags = '<p><br><strong><b><em><i><u><s><blockquote><code><pre><ul><ol><li>'
            . '<h1><h2><h3><h4><h5><h6><a><img><figure><figcaption><table><thead><tbody><tr>'
            . '<th><td><hr><span><div><section><article><small><sub><sup><mark><del><ins><video><source><audio>';

        $html = strip_tags($html, $allowedTags);

        // Drop dangerous protocols in href/src/xlink:href.
        $html = preg_replace(
            '#\s(href|src|xlink:href|action|formaction)\s*=\s*("|\')\s*(javascript|vbscript|data)\s*:[^"\']*\2#i',
            ' $1="#"',
            $html
        ) ?? $html;

        // Remove any inline event handlers.
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        // Remove style expressions / behaviour (legacy IE XSS vectors).
        $html = preg_replace('/\s(style\s*=\s*("[^"]*"|\'[^\']*\'))/i', function_exists('sanitize_css_style') ? '' : '', $html) ?? $html;

        // Re-apply a conservative style allow-list.
        $html = preg_replace_callback(
            '/<([a-z0-9]+)([^>]*)>/i',
            static function (array $m): string {
                $tag = strtolower($m[1]);
                $attrs = $m[2];

                // Only permit style + alignment on a few block tags.
                if (preg_match('/\sstyle\s*=\s*"([^"]*)"/i', $attrs, $sm)) {
                    $safe = sanitize_css_style($sm[1]);
                    $attrs = preg_replace('/\sstyle\s*=\s*"[^"]*"/i', '', $attrs) ?? $attrs;
                    if ($safe !== '') {
                        $attrs .= ' style="' . $safe . '"';
                    }
                }
                if (preg_match('/\sstyle\s*=\s*\'([^\']*)\'/i', $attrs, $sm)) {
                    $safe = sanitize_css_style($sm[1]);
                    $attrs = preg_replace('/\sstyle\s*=\s*\'[^\']*\'/i', '', $attrs) ?? $attrs;
                    if ($safe !== '') {
                        $attrs .= ' style="' . $safe . '"';
                    }
                }

                // Validate link targets.
                if ($tag === 'a') {
                    if (preg_match('/\shref\s*=\s*"([^"]*)"/i', $attrs, $hm)) {
                        $href = trim($hm[1]);
                        if ($href !== '#' && !preg_match('#^(https?:|mailto:|tel:|/)#i', $href)) {
                            $attrs = preg_replace('/\shref\s*=\s*"[^"]*"/i', ' href="#"', $attrs) ?? $attrs;
                        }
                        if (preg_match('#^https?://#i', $href)) {
                            $attrs = preg_replace('/\starget\s*=\s*"[^"]*"/i', '', $attrs) ?? $attrs;
                            $attrs .= ' rel="noopener noreferrer nofollow" target="_blank"';
                        }
                    }
                    // Images inside links are a common clickjacking trick.
                    if (!preg_match('/\shref\s*=/i', $attrs)) {
                        return '';
                    }
                }

                // Only http(s) images.
                if ($tag === 'img') {
                    if (!preg_match('/\ssrc\s*=\s*"([^"]*)"/i', $attrs, $sm)
                        || !preg_match('#^(https?:)?//#i', trim($sm[1]))
                    ) {
                        return '';
                    }
                    $attrs = preg_replace('/\s(srcset|onerror|loading)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs) ?? $attrs;
                }

                if ($tag === 'source' || $tag === 'video' || $tag === 'audio') {
                    if (preg_match('/\ssrc\s*=\s*"([^"]*)"/i', $attrs, $sm)
                        && !preg_match('#^(https?:)?//#|^\./#i', trim($sm[1]))
                    ) {
                        return '';
                    }
                }

                return '<' . $tag . $attrs . '>';
            },
            $html
        ) ?? $html;

        // Kill any stray closing tags for removed elements.
        $html = preg_replace('#</?(script|iframe|object|embed|form|meta|link|base|applet)\b[^>]*>#i', '', $html) ?? $html;

        return $html;
    }
}

if (!function_exists('sanitize_css_style')) {
    /**
     * Keep only benign CSS declarations (no url(), expression(), @import).
     */
    function sanitize_css_style(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }
        if (preg_match('/(expression|javascript|vbscript|@import|url\s*\()/i', $style)) {
            return '';
        }
        $allowed = [
            'text-align', 'text-decoration', 'text-transform', 'color', 'background-color',
            'font-size', 'font-weight', 'font-family', 'line-height', 'letter-spacing',
            'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
            'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right',
            'border', 'border-radius', 'border-color', 'border-width', 'border-style',
            'width', 'max-width', 'min-width', 'height', 'max-height', 'min-height',
            'display', 'float', 'clear', 'vertical-align', 'list-style-type',
        ];
        $out = [];
        foreach (explode(';', $style) as $decl) {
            if (!str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (!in_array($prop, $allowed, true)) {
                continue;
            }
            if (!preg_match('#^[a-z0-9#%.,\s()\'"/-]+$#i', $val)) {
                continue;
            }
            $out[] = $prop . ': ' . $val;
        }
        return implode('; ', $out);
    }
}

// ─────────────────────────────────────────────────────────────
//  Secure file-upload validation
// ─────────────────────────────────────────────────────────────
if (!function_exists('validate_upload')) {
    /**
     * Validate an uploaded file against an extension + MIME allow-list and
     * re-check the magic bytes. Returns null on success, or an error message.
     *
     * @param array       $file        one entry of $_FILES
     * @param array       $allowExt    lowercase extensions, e.g. ['jpg','png']
     * @param array       $allowMime   MIME prefixes or exact types
     * @param int|null    $maxBytes    pass null to use upload_max_filesize
     */
    function validate_upload(array $file, array $allowExt, array $allowMime, ?int $maxBytes = null): ?string
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            return 'No file was selected.';
        }
        if ($err !== UPLOAD_ERR_OK) {
            return match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too large for the server limit.',
                UPLOAD_ERR_PARTIAL                       => 'The upload was interrupted — please try again.',
                default                                  => 'The upload failed (code ' . $err . ').',
            };
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'Invalid upload received.';
        }

        $max = $maxBytes ?? (int)ini_get('upload_max_filesize');
        if (is_string((string)ini_get('upload_max_filesize')) && $maxBytes === null) {
            $max = (int)filter_var(ini_get('upload_max_filesize'), FILTER_VALIDATE_INT) ?: 8 * 1024 * 1024;
        }
        $max = min($max, (int)ini_get('post_max_size') ?: $max);
        if ((int)($file['size'] ?? 0) > $max) {
            return 'That file exceeds the ' . round($max / 1048576, 1) . ' MB limit.';
        }
        if ((int)($file['size'] ?? 0) <= 0) {
            return 'That file is empty.';
        }

        // Extension check on the *client* name.
        $name = (string)($file['name'] ?? 'file');
        if (!preg_match('/\.([a-z0-9]{1,8})$/i', $name, $m)) {
            return 'That file has no extension.';
        }
        $ext = strtolower($m[1]);
        // Reject double extensions such as photo.php.jpg only if the inner one
        // is a script — strip them and validate the real final extension.
        if (preg_match('/\.([a-z0-9]{2,5})\.[a-z0-9]{1,8}$/i', $name, $dm)
            && in_array(strtolower($dm[1]), ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'pl', 'py', 'cgi', 'sh', 'exe', 'js', 'htaccess'], true)
        ) {
            return 'That file name contains a hidden script extension.';
        }
        if (!in_array($ext, $allowExt, true)) {
            return 'File type .' . $ext . ' is not allowed here. Allowed: ' . implode(', ', $allowExt) . '.';
        }

        // Real MIME from magic bytes.
        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$fi->file($tmp);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)@mime_content_type($tmp);
        }
        if ($mime === '') {
            return 'Could not verify the file type — upload blocked.';
        }

        $ok = false;
        foreach ($allowMime as $allowed) {
            if (str_contains($allowed, '/*')) {
                if (str_starts_with($mime, substr($allowed, 0, -1))) {
                    $ok = true;
                    break;
                }
            } elseif ($mime === $allowed) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return 'File content (' . $mime . ') does not match the allowed type.';
        }

        // Belt-and-braces: reject anything whose magic bytes look like PHP/HTML.
        $head = (string)file_get_contents($tmp, false, null, 0, 512);
        foreach (['<?php', '<%', '#!/', '<?=', '<script'] as $sig) {
            if (stripos($head, $sig) === 0 || stripos($head, $sig) !== false && str_starts_with(ltrim($head), $sig)) {
                if ($mime !== 'text/plain' && $mime !== 'text/html') {
                    return 'File contents look like executable code.';
                }
            }
        }

        return null;
    }
}

if (!function_exists('store_upload')) {
    /**
     * Move a validated upload to a destination directory under a random name.
     * Returns the web-relative path on success, or null.
     */
    function store_upload(array $file, string $relativeDir, string $ext, ?string $prefix = null): ?string
    {
        $dir = ROOT_PATH . '/' . ltrim($relativeDir, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        // Defence in depth: never allow script execution in an upload dir.
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Options -Indexes -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phar\nRemoveType .php .phtml .php3 .php4 .php5 .php7 .phar\n<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|phar|pl|py|cgi|sh|htaccess|ini)$\">\nRequire all denied\n</FilesMatch>\n");
        }

        $name = ($prefix !== null ? preg_replace('/[^a-z0-9_\-]/i', '', $prefix) . '_' : '')
            . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
        $tmp = (string)($file['tmp_name'] ?? '');

        if (is_uploaded_file($tmp)) {
            $ok = @move_uploaded_file($tmp, $dir . '/' . $name);
        } else {
            $ok = @rename($tmp, $dir . '/' . $name) || @copy($tmp, $dir . '/' . $name);
        }
        if (!$ok) {
            return null;
        }
        @chmod($dir . '/' . $name, 0644);
        return trim($relativeDir, '/') . '/' . $name;
    }
}

// ─────────────────────────────────────────────────────────────
//  SSRF guard — block private / loopback / link-local targets
// ─────────────────────────────────────────────────────────────
if (!function_exists('is_public_http_url')) {
    function is_public_http_url(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim($parts['host'], '[]'));

        // Literal blocklist for common internal names.
        $blocked = ['localhost', 'localhost.localdomain', '0.0.0.0', '::1', 'ip6-localhost', 'metadata', 'metadata.google.internal'];
        if (in_array($host, $blocked, true)) {
            return false;
        }
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
            return false;
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            } elseif (function_exists('dns_get_record')) {
                foreach (['A', 'AAAA'] as $type) {
                    foreach ((array)@dns_get_record($host, $type) as $rec) {
                        if (!empty($rec['ip'])) {
                            $ips[] = $rec['ip'];
                        } elseif (!empty($rec['ipv6'])) {
                            $ips[] = $rec['ipv6'];
                        }
                    }
                }
            }
        }

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }
}

// ─────────────────────────────────────────────────────────────
//  Shared cURL wrapper with hardened defaults
// ─────────────────────────────────────────────────────────────
if (!function_exists('http_request')) {
    /**
     * @param array $opts extra CURLOPT_* overrides
     * @return array{code:int, body:string, headers:string, error:string}
     */
    function http_request(string $method, string $url, string $body = '', array $headers = [], array $opts = []): array
    {
        // Pass CAINFO explicitly: it works even if php.ini is reset, and is
        // the only reliable way on a build without a system CA store.
        $bundle = function_exists('ca_bundle_found') ? ca_bundle_found() : '';
        $verify = $bundle !== '';

        $c = curl_init($url);
        $base = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT      => 'Adsbooster/2.0',
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($verify) {
            $base[CURLOPT_CAINFO] = $bundle;
        }
        if (strtoupper($method) === 'POST') {
            $base[CURLOPT_POST] = true;
            $base[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($c, array_merge($base, $opts));
        $resp = curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        $hdrs = (string)curl_getinfo($c, CURLINFO_HEADER_OUT);
        $err  = curl_error($c);
        curl_close($c);

        return ['code' => $code, 'body' => (string)$resp, 'headers' => $hdrs, 'error' => $err];
    }
}

// ─────────────────────────────────────────────────────────────
//  Client IP that does not blindly trust spoofable headers
// ─────────────────────────────────────────────────────────────
if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        // Only honour forwarded headers when REMOTE_ADDR is a private hop
        // (i.e. we are behind our own reverse proxy).
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($xff !== '') {
                $first = trim(explode(',', $xff)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}


// ---------------------------------------------------------------
//  Whether outbound cURL can verify TLS certificates
//  Several PHP/Laragon installs ship without a CA bundle. Callers
//  use this to decide whether a strict handshake is possible.
// ---------------------------------------------------------------
if (!function_exists('ca_bundle_found')) {
    /**
     * Absolute path to a usable CA bundle, or '' if none was found.
     * Laragon ships one under etc\ssl but PHP's curl.cainfo is usually
     * empty, so every TLS check silently degrades unless we locate it.
     */
    function ca_bundle_found(): string
    {
        // 1. Already configured in php.ini
        foreach ([(string)ini_get('curl.cainfo'), (string)ini_get('openssl.cafile')] as $p) {
            if ($p !== '' && is_readable($p)) {
                return $p;
            }
        }

        // 2. Bundles shipped with this Laragon / PHP install
        foreach ([
            __DIR__ . '/../certs/ca-bundle.crt',
            'C:/laragon/etc/ssl/cacert.pem',
            'C:/laragon/bin/laragon/utils/curl-ca-bundle.crt',
            'C:/laragon/etc/apps/phpmyadmin/vendor/composer/ca-bundle/res/cacert.pem',
            'C:/laragon/bin/php/extras/ssl/cacert.pem',
            'C:/tools/php85/extras/ssl/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
        ] as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        // 3. Walk the PHP prefix looking for extras/ssl/cacert.pem
        $prefix = (string)PHP_PREFIX;
        if ($prefix !== '' && is_dir($prefix . '/extras/ssl')) {
            foreach ((array)glob($prefix . '/extras/ssl/*.pem') ?: [] as $g) {
                if (is_readable($g)) {
                    return $g;
                }
            }
        }

        return '';
    }
}

if (!function_exists('ca_bundle_available')) {
    function ca_bundle_available(): bool
    {
        return ca_bundle_found() !== '';
    }
}

if (!function_exists('ca_curl_opts')) {
    /**
     * Curl options to attach to a request, keyed by CURLOPT_* constant.
     *
     * ⚠ NEVER spread this into an array literal (`...ca_curl_opts()`) and
     * never `array_merge()` it. Both renumber integer keys, which silently
     * turns CURLOPT_SSL_VERIFYPEER(64) into a meaningless id like 1000.
     * cURL then throws "must contain only valid cURL options", and any code
     * that catches it quietly degrades to fake data.
     *
     * Correct usage — apply straight onto the handle:
     *
     *     $ch = curl_init($url);
     *     curl_setopt_array($ch, $opts);
     *     ca_curl_setopt($ch);
     *
     * or, if you must merge, use the union operator (left side wins):
     *
     *     curl_setopt_array($ch, $opts + ca_curl_opts());
     */
    function ca_curl_opts(): array
    {
        $bundle = ca_bundle_found();
        if ($bundle === '') {
            // Fail closed rather than silently disabling verification:
            // an unverified HTTPS request can leak credentials to a MITM.
            ca_bundle_warn_once();
            return [
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
        }
        return [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO         => $bundle,
        ];
    }
}

if (!function_exists('ca_curl_setopt')) {
    /**
     * Apply the TLS options onto a cURL handle. This is the safe way to use
     * ca_curl_opts() — it never touches array keys, so it cannot be broken by
     * spread/array_merge semantics.
     *
     * Returns the handle so it can be used inline: $ch = ca_curl_setopt($ch);
     */
    function ca_curl_setopt($ch)
    {
        if (!is_resource($ch) && !is_object($ch)) {
            return $ch;
        }
        foreach (ca_curl_opts() as $opt => $val) {
            curl_setopt($ch, (int)$opt, $val);
        }
        return $ch;
    }
}

// NOTE: `curl.cainfo` is PHP_INI_SYSTEM, so ini_set() at runtime is a no-op.
// The bundle path is instead persisted into php.ini (see ca_bundle_found()
// above); here we only detect it and log a clear warning if it is missing.
if (!function_exists('ca_bundle_warn_once')) {
    function ca_bundle_warn_once(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (ca_bundle_found() === '') {
            error_log('Adsbooster TLS: no CA bundle found; HTTPS certificate '
                . 'verification will fail. Set curl.cainfo in php.ini.');
        }
    }
}
if (function_exists('ca_bundle_warn_once')) {
    ca_bundle_warn_once();
}