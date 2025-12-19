<?php
/**
 * Monarch Bank Callback Handler (Must-Use Plugin)
 *
 * This mu-plugin runs BEFORE any regular plugins and handles bank linking
 * callbacks from Monarch/Yodlee to prevent WordPress from returning a 404 error.
 *
 * Must-use plugins are loaded before regular plugins, making this the earliest
 * point we can intercept the request.
 */

// DEBUG: Log that this mu-plugin is loaded
error_log('MONARCH MU-PLUGIN: File loaded. REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? 'not set'));
error_log('MONARCH MU-PLUGIN: QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? 'not set'));

// Simple sanitization function
function monarch_mu_sanitize($str) {
    $str = strip_tags($str);
    $str = preg_replace('/[^a-zA-Z0-9_\-]/', '', $str);
    return $str;
}

// Simple JS escaping function
function monarch_mu_esc_js($str) {
    if (function_exists('esc_js')) {
        return esc_js($str);
    }
    $str = str_replace('\\', '\\\\', $str);
    $str = str_replace("'", "\\'", $str);
    $str = str_replace("\n", "\\n", $str);
    $str = str_replace("\r", "\\r", $str);
    return $str;
}

// Check for callback parameter
$monarch_mu_is_callback = false;
$monarch_mu_org_id = '';

// Method 1: Check $_GET directly
if (isset($_GET['monarch_bank_callback']) && $_GET['monarch_bank_callback'] === '1') {
    $monarch_mu_is_callback = true;
    $monarch_mu_org_id = isset($_GET['org_id']) ? monarch_mu_sanitize($_GET['org_id']) : '';
}

// Method 2: Parse REQUEST_URI
if (!$monarch_mu_is_callback && isset($_SERVER['REQUEST_URI'])) {
    if (strpos($_SERVER['REQUEST_URI'], 'monarch_bank_callback=1') !== false) {
        $monarch_mu_is_callback = true;
        if (preg_match('/org_id=([^&]+)/', $_SERVER['REQUEST_URI'], $matches)) {
            $monarch_mu_org_id = monarch_mu_sanitize(urldecode($matches[1]));
        }
    }
}

// Method 3: Check QUERY_STRING
if (!$monarch_mu_is_callback && isset($_SERVER['QUERY_STRING'])) {
    if (strpos($_SERVER['QUERY_STRING'], 'monarch_bank_callback=1') !== false) {
        $monarch_mu_is_callback = true;
        if (preg_match('/org_id=([^&]+)/', $_SERVER['QUERY_STRING'], $matches)) {
            $monarch_mu_org_id = monarch_mu_sanitize(urldecode($matches[1]));
        }
    }
}

// DEBUG: Log callback detection result
error_log('MONARCH MU-PLUGIN: is_callback=' . ($monarch_mu_is_callback ? 'true' : 'false') . ', org_id=' . $monarch_mu_org_id);

// If this is a callback, output the success page and exit
if ($monarch_mu_is_callback) {
    error_log('MONARCH MU-PLUGIN: *** CALLBACK DETECTED - Outputting success page ***');
    // Build URLs
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    if (function_exists('wc_get_checkout_url')) {
        $checkout_url = wc_get_checkout_url();
    } elseif (function_exists('home_url')) {
        $checkout_url = home_url('/checkout/');
    } else {
        $checkout_url = $protocol . $host . '/checkout/';
    }

    if (function_exists('admin_url')) {
        $ajax_url = admin_url('admin-ajax.php');
    } else {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $base_path = preg_replace('/\/checkout.*$|\?.*$/', '', $request_uri);
        $ajax_url = $protocol . $host . $base_path . '/wp-admin/admin-ajax.php';
    }

    // Generate nonce
    if (function_exists('wp_create_nonce')) {
        $nonce = wp_create_nonce('monarch_ach_nonce');
    } else {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $nonce = isset($_SESSION['monarch_callback_token']) ? $_SESSION['monarch_callback_token'] : '';
        if (empty($nonce)) {
            $nonce = bin2hex(random_bytes(16));
            $_SESSION['monarch_callback_token'] = $nonce;
        }
    }

    // Send headers
    if (!headers_sent()) {
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    // Check if we're in an iframe
    $in_iframe = isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe';

    // Output the success page
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>Bank Linked Successfully</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #f0fff4 0%, #e8f5e9 100%);
        }
        .success-container {
            text-align: center;
            padding: 40px 50px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            max-width: 480px;
            width: 100%;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 40px;
            color: white;
        }
        h1 {
            color: #1a1a1a;
            font-size: 26px;
            margin: 0 0 15px 0;
            font-weight: 600;
        }
        p {
            color: #666;
            font-size: 15px;
            margin: 0 0 10px 0;
            line-height: 1.5;
        }
        .confirm-btn {
            display: inline-block;
            background: #28a745;
            color: white;
            border: none;
            padding: 16px 36px;
            font-size: 17px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.2s ease;
            text-decoration: none;
            width: 100%;
            max-width: 320px;
        }
        .confirm-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }
        .note {
            font-size: 12px;
            color: #999;
            margin-top: 20px;
        }
        .open-window-btn {
            display: inline-block;
            background: #0073aa;
            color: white;
            border: none;
            padding: 16px 36px;
            font-size: 17px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.2s ease;
            text-decoration: none;
            width: 100%;
            max-width: 320px;
        }
        .open-window-btn:hover {
            background: #005a87;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 115, 170, 0.4);
        }
        .iframe-message {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1>Bank Linking Complete!</h1>
        <p>Your bank account has been successfully linked.</p>

        <div id="iframe-notice" class="iframe-message" style="display: none;">
            <strong>Almost done!</strong><br>
            Click the button below to open a new window and complete the verification.
        </div>

        <p id="main-instruction">Click the button below to verify and return to checkout.</p>

        <button type="button" id="open-window-btn" class="open-window-btn" style="display: none;">
            Open Verification Window
        </button>

        <button type="button" id="close-window-btn" class="confirm-btn" onclick="window.close(); setTimeout(function(){ window.location.href=checkoutUrl; }, 300);">
            Close Window
        </button>

        <p class="note">Click to close this window and return to checkout.</p>
    </div>

    <script>
        var orgId = '<?php echo monarch_mu_esc_js($monarch_mu_org_id); ?>';
        var ajaxUrl = '<?php echo monarch_mu_esc_js($ajax_url); ?>';
        var nonce = '<?php echo monarch_mu_esc_js($nonce); ?>';
        var checkoutUrl = '<?php echo monarch_mu_esc_js($checkout_url); ?>';
        var maxRetries = 5;
        var retryCount = 0;
        var retryDelay = 3000;

        // Notify opener window (the checkout page) that we've landed
        function notifyOpener(status, paytokenId) {
            try {
                // For popup window - use window.opener
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'MONARCH_BANK_CALLBACK',
                        status: status,
                        org_id: orgId,
                        paytoken_id: paytokenId || null
                    }, '*');
                    return true;
                }
                // For iframe - use window.parent
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'MONARCH_BANK_CALLBACK',
                        status: status,
                        org_id: orgId,
                        paytoken_id: paytokenId || null
                    }, '*');
                    return true;
                }
            } catch (e) {
                console.log('Could not notify opener/parent:', e);
            }
            return false;
        }

        // Notify immediately on page load
        notifyOpener('LANDED', null);

        // Check if we're inside an iframe
        var isInIframe = (window.self !== window.top);
        var currentUrl = window.location.href;

        if (isInIframe) {
            // We're in an iframe - AUTOMATICALLY open a new browser window
            document.getElementById('iframe-notice').style.display = 'block';
            document.getElementById('iframe-notice').innerHTML = '<strong>Opening verification window...</strong><br>Please wait while we open a new window.';
            document.getElementById('open-window-btn').style.display = 'inline-block';
            document.getElementById('close-window-btn').style.display = 'none';
            document.getElementById('main-instruction').textContent = 'A new window is opening...';

            // AUTOMATICALLY open new window after a short delay
            setTimeout(function() {
                var newWindow = window.open(currentUrl, 'MonarchBankSuccess', 'width=500,height=650,scrollbars=yes,resizable=yes');
                if (newWindow) {
                    // Update UI
                    document.getElementById('open-window-btn').textContent = 'Window Opened - Complete verification there';
                    document.getElementById('open-window-btn').disabled = true;
                    document.getElementById('iframe-notice').innerHTML = '<strong>Window opened!</strong><br>Please complete the verification in the new window that just opened.';
                    document.getElementById('main-instruction').textContent = 'Complete verification in the new window.';

                    // Notify parent that we opened a new window
                    notifyOpener('WINDOW_OPENED', null);
                } else {
                    // Popup was blocked - show manual button
                    document.getElementById('iframe-notice').innerHTML = '<strong>Popup blocked!</strong><br>Please click the button below to open the verification window.';
                    document.getElementById('open-window-btn').textContent = 'Click Here to Open Verification Window';
                    document.getElementById('open-window-btn').disabled = false;
                    document.getElementById('main-instruction').textContent = 'Click the button to continue.';
                }
            }, 500);

            // Handle manual "Open Window" button click (if popup was blocked)
            document.getElementById('open-window-btn').addEventListener('click', function() {
                // Open the same URL in a new window (not iframe)
                var newWindow = window.open(currentUrl, 'MonarchBankSuccess', 'width=500,height=650,scrollbars=yes,resizable=yes');
                if (newWindow) {
                    // Update UI
                    this.textContent = 'Window Opened - Complete verification there';
                    this.disabled = true;
                    document.getElementById('iframe-notice').innerHTML = '<strong>Window opened!</strong><br>Please complete the verification in the new window.';

                    // Notify parent that we opened a new window
                    notifyOpener('WINDOW_OPENED', null);
                } else {
                    alert('Please allow popups for this site to complete bank verification.');
                }
            });
        }

    </script>
</body>
</html>
    <?php
    exit;
}
