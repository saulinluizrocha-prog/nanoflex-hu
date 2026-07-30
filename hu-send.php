<?php
error_reporting(0);
session_start();

function getClientIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function normalizePhone($phone) {
    // Remove all non-numeric characters except +
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Normalize format
    if (strpos($phone, '+36') === 0) {
        // already +36
    } elseif (strpos($phone, '0036') === 0) {
        $phone = '+36' . substr($phone, 4);
    } elseif (strpos($phone, '36') === 0 && strlen($phone) >= 10) {
        $phone = '+' . $phone;
    } elseif (strpos($phone, '06') === 0) {
        $phone = '+36' . substr($phone, 2);
    } elseif (strpos($phone, '0') === 0) {
        $phone = '+36' . substr($phone, 1);
    } elseif (strpos($phone, '+') !== 0) {
        $phone = '+36' . $phone;
    }
    
    return $phone;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $phone_raw = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    if (empty($name) || empty($phone_raw)) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . (strpos($_SERVER['HTTP_REFERER'], '?') !== false ? '&' : '?') . 'error=empty');
        exit;
    }
    
    $phone = normalizePhone($phone_raw);
    
    if (!preg_match('/^\+36[0-9]{8,9}$/', $phone)) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . (strpos($_SERVER['HTTP_REFERER'], '?') !== false ? '&' : '?') . 'error=phone');
        exit;
    }

    // Capture URL params or POST data
    $sub_id = $_POST['sub_id'] ?? $_GET['sub_id'] ?? '';
    $sub_id_1 = $_POST['sub_id_1'] ?? $_GET['sub_id_1'] ?? '';
    $sub_id_2 = $_POST['sub_id_2'] ?? $_GET['sub_id_2'] ?? '';
    $sub_id_3 = $_POST['sub_id_3'] ?? $_GET['sub_id_3'] ?? '';
    $sub_id_4 = $_POST['sub_id_4'] ?? $_GET['sub_id_4'] ?? '';
    $sub_id_5 = $_POST['sub_id_5'] ?? $_GET['sub_id_5'] ?? '';
    $gclid = $_POST['gclid'] ?? $_GET['gclid'] ?? '';
    
    $utm_source = $_POST['utm_source'] ?? $_GET['utm_source'] ?? '';
    $utm_medium = $_POST['utm_medium'] ?? $_GET['utm_medium'] ?? '';
    $utm_campaign = $_POST['utm_campaign'] ?? $_GET['utm_campaign'] ?? '';
    $utm_term = $_POST['utm_term'] ?? $_GET['utm_term'] ?? '';
    $utm_content = $_POST['utm_content'] ?? $_GET['utm_content'] ?? '';

    // Include the Terra API connector
    require_once 'api.php';
    
    $connector = new CApiConnector(
        'c66289394c2a6e8515c8e8b382fba719', // api_key
        14858,                              // offer_id
        75329,                              // user_id
        'https://t-api.org'                 // api_domain
    );

    $leadData = [
        'name' => $name,
        'phone' => $phone,
        'country' => 'HU',
        'ip' => getClientIP(),
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'tz' => 2,
        'stream_id' => '409913',
        'sub_id' => $sub_id,
        'sub_id_1' => $sub_id_1,
        'sub_id_2' => $sub_id_2,
        'sub_id_3' => $sub_id_3,
        'sub_id_4' => $sub_id_4,
        'sub_id_5' => $sub_id_5,
        'gclid' => $gclid,
        'utm_source' => $utm_source,
        'utm_medium' => $utm_medium,
        'utm_campaign' => $utm_campaign,
        'utm_term' => $utm_term,
        'utm_content' => $utm_content,
    ];

    $response = $connector->create($leadData);
    
    if (isset($response['id'])) {
        header('Location: hu-success.html?id=' . urlencode($response['id']));
    } else {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . (strpos($_SERVER['HTTP_REFERER'], '?') !== false ? '&' : '?') . 'error=api');
    }
    exit;
} else {
    header('Location: /');
    exit;
}
?>
