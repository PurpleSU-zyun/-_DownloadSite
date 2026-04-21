<?php
require_once 'config.php';

// ── 登录 / 登出 ──────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
    } else {
        $loginError = '用户名或密码错误';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php?page=login');
    exit;
}

$page = $_GET['page'] ?? 'dashboard';

// 登录页
if ($page === 'login' || !isAdminLoggedIn()) {
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>管理员登录</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);font-family:'Segoe UI',sans-serif;display:flex;align-items:center;justify-content:center}
.card{background:rgba(255,255,255,.08);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.15);border-radius:24px;padding:48px 40px;width:380px}
h2{color:#fff;text-align:center;margin-bottom:32px;font-size:1.5rem}
.logo{text-align:center;font-size:2.5rem;margin-bottom:16px}
label{display:block;color:rgba(255,255,255,.7);margin-bottom:6px;font-size:.875rem}
input[type=text],input[type=password]{width:100%;padding:12px 16px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:10px;color:#fff;font-size:1rem;margin-bottom:20px;outline:none;transition:.3s}
input:focus{border-color:#6c63ff;background:rgba(108,99,255,.15)}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#6c63ff,#a855f7);border:none;border-radius:12px;color:#fff;font-size:1rem;cursor:pointer;transition:.3s}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(108,99,255,.4)}
.error{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:.875rem}
</style>
</head>
<body>
<div class="card">
    <div class="logo">🔐</div>
    <h2>管理员登录</h2>
    <?php if (!empty($loginError)): ?><div class="error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <label>用户名</label>
        <input type="text" name="username" placeholder="admin" autocomplete="username" required>
        <label>密码</label>
        <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
        <button type="submit" class="btn">登录</button>
    </form>
</div>
</body>
</html>
    <?php exit; }

requireAdmin();

$pdo = getDB();
$msg = '';
$msgType = 'success';

// ── AJAX / POST 动作处理 ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_download') {
        $id       = intval($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $url      = trim($_POST['url'] ?? '');
        $icon     = trim($_POST['icon'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $wait     = $_POST['wait_time'] === '' ? null : intval($_POST['wait_time']);
        $sort     = intval($_POST['sort_order'] ?? 0);
        $enabled  = isset($_POST['enabled']) ? 1 : 0;

        if (!$name || !$url) {
            $msg = '名称和URL不能为空'; $msgType = 'error';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE downloads SET name=?,description=?,url=?,icon=?,password=?,wait_time=?,sort_order=?,enabled=? WHERE id=?');
                $stmt->execute([$name,$desc,$url,$icon ?: null,$password ?: null,$wait,$sort,$enabled,$id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO downloads (name,description,url,icon,password,wait_time,sort_order,enabled) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$name,$desc,$url,$icon ?: null,$password ?: null,$wait,$sort,$enabled]);
            }
            $msg = $id > 0 ? '更新成功' : '添加成功';
            header('Location: admin.php?page=downloads&msg=' . urlencode($msg));
            exit;
        }
    }

    if ($action === 'delete_download') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM downloads WHERE id=?')->execute([$id]);
        header('Location: admin.php?page=downloads&msg=' . urlencode('删除成功'));
        exit;
    }

    if ($action === 'toggle_enabled') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE downloads SET enabled = 1-enabled WHERE id=?')->execute([$id]);
        header('Location: admin.php?page=downloads');
        exit;
    }

    if ($action === 'save_settings') {
        $fields = ['site_title','site_subtitle','wait_time','theme_color','show_count'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) setSetting($f, trim($_POST[$f]));
        }
        $msg = '设置已保存';
        header('Location: admin.php?page=settings&msg=' . urlencode($msg));
        exit;
    }
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; }

// ── 数据加载 ────────────────────────────────────────────────────
$stats = [];
if ($page === 'dashboard') {
    $stats['total']    = $pdo->query('SELECT COUNT(*) FROM downloads')->fetchColumn();
    $stats['enabled']  = $pdo->query('SELECT COUNT(*) FROM downloads WHERE enabled=1')->fetchColumn();
    $stats['downloads']= $pdo->query('SELECT COALESCE(SUM(download_count),0) FROM downloads')->fetchColumn();
    $stats['protected']= $pdo->query('SELECT COUNT(*) FROM downloads WHERE password IS NOT NULL AND password != ""')->fetchColumn();
    $recent = $pdo->query('SELECT * FROM downloads ORDER BY created_at DESC LIMIT 5')->fetchAll();
}

$editItem = null;
if ($page === 'edit' && isset($_GET['id'])) {
    $editItem = $pdo->prepare('SELECT * FROM downloads WHERE id=?');
    $editItem->execute([intval($_GET['id'])]);
    $editItem = $editItem->fetch();
}

$downloads = [];
if ($page === 'downloads') {
    $downloads = $pdo->query('SELECT * FROM downloads ORDER BY sort_order ASC, id DESC')->fetchAll();
}

$siteTitle    = getSetting('site_title', '文件下载中心');
$siteSubtitle = getSetting('site_subtitle', '安全、快速、便捷的文件下载服务');
$waitTime     = getSetting('wait_time', '5');
$themeColor   = getSetting('theme_color', '#6c63ff');
$showCount    = getSetting('show_count', '1');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>管理后台 - 文件下载站</title>
<style>
:root{--primary:<?= htmlspecialchars($themeColor) ?>;--primary-dark:#5a52e0;--bg:#0d0d1a;--sidebar:#111128;--card:rgba(255,255,255,.06);--border:rgba(255,255,255,.1);--text:#e8e8f0;--muted:rgba(255,255,255,.5)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;display:flex;min-height:100vh}
/* Sidebar */
.sidebar{width:240px;background:var(--sidebar);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh;z-index:100}
.sidebar-logo{padding:28px 24px 20px;border-bottom:1px solid var(--border)}
.sidebar-logo .logo-icon{font-size:1.8rem;margin-bottom:6px}
.sidebar-logo h1{font-size:1rem;color:#fff;font-weight:600}
.sidebar-logo p{font-size:.75rem;color:var(--muted);margin-top:2px}
.nav{flex:1;padding:16px 12px;overflow-y:auto}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:.9rem;transition:.2s;margin-bottom:4px}
.nav-item:hover,.nav-item.active{background:rgba(108,99,255,.2);color:#fff}
.nav-item.active{background:linear-gradient(135deg,rgba(108,99,255,.3),rgba(168,85,247,.2));color:#fff}
.nav-icon{font-size:1.1rem;width:22px;text-align:center}
.sidebar-footer{padding:16px;border-top:1px solid var(--border)}
.sidebar-footer a{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:.85rem;text-decoration:none;padding:8px 10px;border-radius:8px;transition:.2s}
.sidebar-footer a:hover{color:#fff;background:rgba(255,255,255,.05)}
/* Main */
.main{margin-left:240px;flex:1;padding:32px;min-height:100vh}
.page-header{margin-bottom:28px}
.page-header h2{font-size:1.5rem;font-weight:700;color:#fff}
.page-header p{color:var(--muted);font-size:.9rem;margin-top:4px}
/* Cards */
.card{background:var(--card);backdrop-filter:blur(10px);border:1px solid var(--border);border-radius:16px;padding:24px}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px 22px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--primary),#a855f7)}
.stat-num{font-size:2rem;font-weight:700;color:#fff;line-height:1}
.stat-label{color:var(--muted);font-size:.8rem;margin-top:6px}
.stat-icon{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:2rem;opacity:.2}
/* Table */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:12px 16px;font-size:.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)}
td{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.05);font-size:.9rem;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.03)}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:.875rem;cursor:pointer;border:none;text-decoration:none;transition:.2s;font-weight:500}
.btn-primary{background:linear-gradient(135deg,var(--primary),#a855f7);color:#fff}
.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
.btn-sm{padding:5px 12px;font-size:.8rem;border-radius:6px}
.btn-edit{background:rgba(59,130,246,.2);color:#60a5fa;border:1px solid rgba(59,130,246,.3)}
.btn-edit:hover{background:rgba(59,130,246,.3)}
.btn-delete{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.btn-delete:hover{background:rgba(239,68,68,.25)}
.btn-toggle{background:rgba(255,255,255,.1);color:var(--muted);border:1px solid var(--border)}
.btn-view{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3)}
/* Forms */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:20px}
.form-group.full{grid-column:1/-1}
label{display:block;color:rgba(255,255,255,.7);margin-bottom:7px;font-size:.875rem;font-weight:500}
input[type=text],input[type=number],input[type=url],input[type=password],textarea,select{width:100%;padding:11px 14px;background:rgba(255,255,255,.08);border:1px solid var(--border);border-radius:10px;color:#fff;font-size:.9rem;outline:none;transition:.3s;font-family:inherit}
input:focus,textarea:focus,select:focus{border-color:var(--primary);background:rgba(108,99,255,.1)}
textarea{resize:vertical;min-height:90px}
select option{background:#1a1a2e;color:#fff}
.hint{color:var(--muted);font-size:.78rem;margin-top:5px}
.toggle-wrap{display:flex;align-items:center;gap:12px}
input[type=checkbox]{width:18px;height:18px;accent-color:var(--primary);cursor:pointer}
/* Alert */
.alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.9rem;display:flex;align-items:center;gap:8px}
.alert-success{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#4ade80}
.alert-error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171}
/* Badge */
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:.75rem;font-weight:500}
.badge-on{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3)}
.badge-off{background:rgba(255,255,255,.08);color:var(--muted);border:1px solid var(--border)}
.badge-lock{background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.3)}
/* Icon preview */
.icon-preview{width:36px;height:36px;border-radius:8px;object-fit:contain;background:rgba(255,255,255,.1);padding:4px}
.icon-placeholder{width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.08);display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem}
/* Responsive */
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">📦</div>
        <h1>下载站管理</h1>
        <p>Download Manager</p>
    </div>
    <nav class="nav">
        <a href="admin.php" class="nav-item <?= $page==='dashboard'?'active':'' ?>"><span class="nav-icon">📊</span> 仪表盘</a>
        <a href="admin.php?page=downloads" class="nav-item <?= in_array($page,['downloads','edit','add'])?'active':'' ?>"><span class="nav-icon">📁</span> 下载管理</a>
        <a href="admin.php?page=settings" class="nav-item <?= $page==='settings'?'active':'' ?>"><span class="nav-icon">⚙️</span> 站点设置</a>
        <a href="index.php" class="nav-item" target="_blank"><span class="nav-icon">🌐</span> 前台预览</a>
    </nav>
    <div class="sidebar-footer">
        <a href="?logout=1">🚪 退出登录</a>
    </div>
</aside>

<main class="main">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= $msgType==='success'?'✓':'✗' ?> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php
// ━━ 仪表盘 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($page === 'dashboard'):
?>
<div class="page-header">
    <h2>📊 仪表盘</h2>
    <p>欢迎回来，概览当前站点状态</p>
</div>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">总下载项</div><div class="stat-icon">📦</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['enabled'] ?></div><div class="stat-label">已启用</div><div class="stat-icon">✅</div></div>
    <div class="stat-card"><div class="stat-num"><?= number_format($stats['downloads']) ?></div><div class="stat-label">总下载次数</div><div class="stat-icon">⬇️</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['protected'] ?></div><div class="stat-label">密码保护</div><div class="stat-icon">🔒</div></div>
</div>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h3 style="font-size:1rem;color:#fff">最近添加</h3>
        <a href="admin.php?page=add" class="btn btn-primary btn-sm">+ 添加新项目</a>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>名称</th><th>等待</th><th>密码</th><th>下载数</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
            <td><a href="admin.php?page=edit&id=<?= $r['id'] ?>" style="color:#a78bfa;text-decoration:none"><?= htmlspecialchars($r['name']) ?></a></td>
            <td><?= $r['wait_time'] !== null ? $r['wait_time'].'s' : '默认' ?></td>
            <td><?= $r['password'] ? '<span class="badge badge-lock">🔒 已设置</span>' : '<span style="color:var(--muted)">无</span>' ?></td>
            <td><?= number_format($r['download_count']) ?></td>
            <td><?= $r['enabled'] ? '<span class="badge badge-on">启用</span>' : '<span class="badge badge-off">禁用</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php
// ━━ 下载列表 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
elseif ($page === 'downloads'):
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start">
    <div><h2>📁 下载管理</h2><p>管理所有可下载文件项目</p></div>
    <a href="admin.php?page=add" class="btn btn-primary">+ 添加项目</a>
</div>
<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>图标</th><th>名称</th><th>等待时间</th><th>密码</th><th>下载数</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($downloads as $d): ?>
        <tr>
            <td><?php if ($d['icon']): ?><img src="<?= htmlspecialchars($d['icon']) ?>" class="icon-preview" onerror="this.style.display='none'"><?php else: ?><span class="icon-placeholder">📄</span><?php endif; ?></td>
            <td>
                <div style="font-weight:500;color:#fff"><?= htmlspecialchars($d['name']) ?></div>
                <?php if ($d['description']): ?><div style="font-size:.78rem;color:var(--muted);margin-top:2px"><?= htmlspecialchars(mb_substr($d['description'],0,50)) ?>...</div><?php endif; ?>
            </td>
            <td><?= $d['wait_time'] !== null ? $d['wait_time'].'<small style="color:var(--muted)"> 秒</small>' : '<span style="color:var(--muted)">默认('.getSetting('wait_time','5').'s)</span>' ?></td>
            <td><?= $d['password'] ? '<span class="badge badge-lock">🔒 已设置</span>' : '<span style="color:var(--muted)">无</span>' ?></td>
            <td><?= number_format($d['download_count']) ?></td>
            <td><?= $d['enabled'] ? '<span class="badge badge-on">启用</span>' : '<span class="badge badge-off">禁用</span>' ?></td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="admin.php?page=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-edit">✏️ 编辑</a>
                    <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_enabled"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button type="submit" class="btn btn-sm btn-toggle"><?= $d['enabled']?'⏸ 禁用':'▶ 启用' ?></button></form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('确认删除「<?= htmlspecialchars($d['name']) ?>」？')"><input type="hidden" name="action" value="delete_download"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button type="submit" class="btn btn-sm btn-delete">🗑 删除</button></form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$downloads): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">暂无数据，<a href="admin.php?page=add" style="color:#a78bfa">点击添加</a></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php
// ━━ 添加/编辑 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
elseif ($page === 'add' || $page === 'edit'):
    $isEdit = $page === 'edit' && $editItem;
    $item = $isEdit ? $editItem : ['id'=>0,'name'=>'','description'=>'','url'=>'','icon'=>'','password'=>'','wait_time'=>'','sort_order'=>0,'enabled'=>1];
?>
<div class="page-header">
    <h2><?= $isEdit?'✏️ 编辑':'➕ 添加' ?> 下载项目</h2>
    <p><?= $isEdit?'修改现有':'新建一个' ?>可下载文件条目</p>
</div>
<div class="card">
<form method="POST">
    <input type="hidden" name="action" value="save_download">
    <input type="hidden" name="id" value="<?= $item['id'] ?>">
    <div class="form-grid">
        <div class="form-group full">
            <label>文件名称 *</label>
            <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" placeholder="例：Visual Studio Code 安装包" required>
        </div>
        <div class="form-group full">
            <label>文件描述</label>
            <textarea name="description" placeholder="简短描述这个文件的用途..."><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group full">
            <label>下载 URL *</label>
            <input type="url" name="url" value="<?= htmlspecialchars($item['url']) ?>" placeholder="https://example.com/file.zip" required>
            <p class="hint">直链地址，点击下载后将跳转到此URL</p>
        </div>
        <div class="form-group full">
            <label>图标 URL</label>
            <input type="url" name="icon" value="<?= htmlspecialchars($item['icon'] ?? '') ?>" placeholder="https://example.com/icon.png" id="iconInput">
            <p class="hint">留空使用默认图标；支持任意图片URL</p>
            <div id="iconPreviewWrap" style="margin-top:10px;<?= $item['icon']?'':'display:none' ?>">
                <img id="iconPreview" src="<?= htmlspecialchars($item['icon'] ?? '') ?>" style="width:48px;height:48px;border-radius:8px;object-fit:contain;background:rgba(255,255,255,.1);padding:4px">
            </div>
        </div>
        <div class="form-group">
            <label>访问密码</label>
            <input type="text" name="password" value="<?= htmlspecialchars($item['password'] ?? '') ?>" placeholder="留空则无密码保护">
            <p class="hint">设置后用户需输入正确密码才能下载</p>
        </div>
        <div class="form-group">
            <label>等待时间（秒）</label>
            <input type="number" name="wait_time" value="<?= htmlspecialchars($item['wait_time'] ?? '') ?>" placeholder="留空使用全局设置（<?= getSetting('wait_time','5') ?>秒）" min="0" max="60">
            <p class="hint">0 = 立即下载；留空使用全局默认值</p>
        </div>
        <div class="form-group">
            <label>排序权重</label>
            <input type="number" name="sort_order" value="<?= intval($item['sort_order']) ?>" placeholder="0">
            <p class="hint">数字越小越靠前</p>
        </div>
        <div class="form-group">
            <label>启用状态</label>
            <div class="toggle-wrap" style="margin-top:8px">
                <input type="checkbox" name="enabled" id="enabled" <?= $item['enabled']?'checked':'' ?>>
                <label for="enabled" style="margin-bottom:0;cursor:pointer">显示在前台</label>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:12px;margin-top:8px">
        <button type="submit" class="btn btn-primary"><?= $isEdit?'💾 保存修改':'➕ 添加项目' ?></button>
        <a href="admin.php?page=downloads" class="btn btn-toggle">取消</a>
    </div>
</form>
</div>

<?php
// ━━ 站点设置 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
elseif ($page === 'settings'):
?>
<div class="page-header">
    <h2>⚙️ 站点设置</h2>
    <p>配置前台下载页面的展示内容</p>
</div>
<div class="card">
<form method="POST">
    <input type="hidden" name="action" value="save_settings">
    <div class="form-grid">
        <div class="form-group full">
            <label>下载页标题</label>
            <input type="text" name="site_title" value="<?= htmlspecialchars($siteTitle) ?>" placeholder="文件下载中心">
        </div>
        <div class="form-group full">
            <label>下载页副标题</label>
            <input type="text" name="site_subtitle" value="<?= htmlspecialchars($siteSubtitle) ?>" placeholder="安全、快速、便捷的文件下载服务">
        </div>
        <div class="form-group">
            <label>全局等待时间（秒）</label>
            <input type="number" name="wait_time" value="<?= htmlspecialchars($waitTime) ?>" min="0" max="60" placeholder="5">
            <p class="hint">各下载项未单独设置时使用此值；0 = 立即下载</p>
        </div>
        <div class="form-group">
            <label>主题颜色</label>
            <div style="display:flex;gap:10px;align-items:center">
                <input type="color" name="theme_color" value="<?= htmlspecialchars($themeColor) ?>" style="width:50px;height:42px;padding:2px;cursor:pointer;border-radius:8px">
                <input type="text" id="colorText" value="<?= htmlspecialchars($themeColor) ?>" style="flex:1" oninput="document.querySelector('[name=theme_color]').value=this.value">
            </div>
        </div>
        <div class="form-group">
            <label>显示下载次数</label>
            <div class="toggle-wrap" style="margin-top:8px">
                <input type="checkbox" name="show_count" id="show_count" value="1" <?= $showCount?'checked':'' ?>>
                <label for="show_count" style="margin-bottom:0;cursor:pointer">在前台展示下载次数</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">💾 保存设置</button>
</form>
</div>
<?php endif; ?>

</main>

<script>
// 图标预览
const iconInput = document.getElementById('iconInput');
if (iconInput) {
    iconInput.addEventListener('input', function(){
        const wrap = document.getElementById('iconPreviewWrap');
        const img = document.getElementById('iconPreview');
        if (this.value) {
            wrap.style.display = '';
            img.src = this.value;
        } else {
            wrap.style.display = 'none';
        }
    });
}
// 颜色同步
const colorPicker = document.querySelector('[name=theme_color]');
if (colorPicker) {
    colorPicker.addEventListener('input', function(){
        const t = document.getElementById('colorText');
        if(t) t.value = this.value;
    });
}
</script>
</body>
</html>
