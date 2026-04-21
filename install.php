<?php
require_once 'config.php';

$messages = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();

        // 创建 downloads 表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `downloads` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL COMMENT '文件名称',
            `description` TEXT DEFAULT NULL COMMENT '文件描述',
            `url` TEXT NOT NULL COMMENT '下载URL',
            `icon` VARCHAR(500) DEFAULT NULL COMMENT '图标URL',
            `password` VARCHAR(255) DEFAULT NULL COMMENT '密码（空为不加密）',
            `wait_time` INT DEFAULT NULL COMMENT '等待秒数（NULL则使用全局设置）',
            `sort_order` INT DEFAULT 0 COMMENT '排序',
            `enabled` TINYINT(1) DEFAULT 1 COMMENT '是否启用',
            `download_count` INT DEFAULT 0 COMMENT '下载次数',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='下载项目表'");
        $messages[] = ['type' => 'success', 'text' => '✓ downloads 表创建成功'];

        // 创建 settings 表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `key` VARCHAR(100) PRIMARY KEY COMMENT '配置键',
            `value` TEXT DEFAULT NULL COMMENT '配置值',
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点设置表'");
        $messages[] = ['type' => 'success', 'text' => '✓ settings 表创建成功'];

        // 插入默认设置
        $defaults = [
            'site_title'    => '文件下载中心',
            'site_subtitle' => '安全、快速、便捷的文件下载服务',
            'wait_time'     => '5',
            'theme_color'   => '#6c63ff',
            'show_count'    => '1',
            'footer_text'   => 'ZYUN 提供支持',
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES (?, ?)");
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $messages[] = ['type' => 'success', 'text' => '✓ 默认设置写入成功'];

        // 插入示例数据
        $pdo->exec("INSERT IGNORE INTO `downloads` (`id`, `name`, `description`, `url`, `icon`, `wait_time`, `enabled`) VALUES
            (1, 'WordPress 最新版', '全球最流行的开源建站系统', 'https://wordpress.org/latest.zip', 'https://s.w.org/style/images/about-hero-image.png', NULL, 1),
            (2, 'VS Code 安装包', '微软出品的轻量级代码编辑器', 'https://code.visualstudio.com/sha/download?build=stable&os=win32-x64-user', NULL, 3, 1)
        ");
        $messages[] = ['type' => 'success', 'text' => '✓ 示例数据插入成功'];

        $success = true;
    } catch (PDOException $e) {
        $messages[] = ['type' => 'error', 'text' => '✗ 错误：' . htmlspecialchars($e->getMessage())];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>安装向导 - 文件下载站</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);font-family:'Segoe UI',sans-serif;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:rgba(255,255,255,.08);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.15);border-radius:20px;padding:48px;max-width:560px;width:100%}
h1{color:#fff;font-size:1.8rem;margin-bottom:8px}
.sub{color:rgba(255,255,255,.6);margin-bottom:32px;font-size:.95rem}
.msg{padding:12px 16px;border-radius:10px;margin-bottom:10px;font-size:.9rem}
.msg.success{background:rgba(46,213,115,.15);border:1px solid rgba(46,213,115,.3);color:#2ed573}
.msg.error{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c}
.btn{display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#6c63ff,#a855f7);color:#fff;border:none;border-radius:12px;font-size:1rem;cursor:pointer;text-decoration:none;transition:.3s;width:100%;text-align:center}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(108,99,255,.4)}
.btn-secondary{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2)}
.warn{background:rgba(255,193,7,.1);border:1px solid rgba(255,193,7,.3);color:#ffc107;padding:14px;border-radius:10px;margin-bottom:24px;font-size:.9rem}
.links{display:flex;gap:12px;margin-top:20px}
.links a{flex:1}
</style>
</head>
<body>
<div class="card">
    <h1>🚀 安装向导</h1>
    <p class="sub">文件下载站 - 初始化数据库</p>

    <?php if (!$success): ?>
    <div class="warn">⚠️ 请确认已在 <code>config.php</code> 中填写正确的数据库信息，且数据库 <strong><?= htmlspecialchars(DB_NAME) ?></strong> 已存在。</div>
    <form method="POST">
        <button type="submit" class="btn">▶ 开始安装</button>
    </form>
    <?php endif; ?>

    <?php foreach ($messages as $m): ?>
        <div class="msg <?= $m['type'] ?>"><?= $m['text'] ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
    <div class="msg success" style="font-size:1rem;padding:16px">🎉 安装完成！请删除此文件后再使用。</div>
    <div class="links">
        <a href="index.php" class="btn btn-secondary">🌐 访问首页</a>
        <a href="admin.php" class="btn">🔐 进入后台</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
