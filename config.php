<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'download_site');
define('DB_PORT', '3306');

// 管理员配置
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123'); // 首次登录后请修改

// 站点基础配置
define('SITE_URL', ''); // 留空则自动检测

// 连接数据库
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#e74c3c;background:#1a1a2e;min-height:100vh;"><h2>数据库连接失败</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>请检查 config.php 中的数据库配置。</p></div>');
        }
    }
    return $pdo;
}

// 获取站点设置
function getSetting($key, $default = '') {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// 设置站点配置
function setSetting($key, $value) {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?');
    $stmt->execute([$key, $value, $value]);
}

// Session 启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查管理员登录
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// 要求管理员登录
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin.php?page=login');
        exit;
    }
}
