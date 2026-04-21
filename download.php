<?php
require_once 'config.php';

$pdo = getDB();
$id  = intval($_GET['id'] ?? 0);

// 获取下载项
$stmt = $pdo->prepare('SELECT * FROM downloads WHERE id=? AND enabled=1 LIMIT 1');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>404</title>
    <style>*{margin:0;padding:0;box-sizing:border-box}body{min-height:100vh;background:linear-gradient(135deg,#0f0c29,#302b63);display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;color:#fff}
    .box{text-align:center}.icon{font-size:4rem;margin-bottom:16px}.title{font-size:1.5rem;margin-bottom:8px}.sub{color:rgba(255,255,255,.5);margin-bottom:24px}
    a{display:inline-block;padding:10px 24px;background:rgba(108,99,255,.3);border:1px solid rgba(108,99,255,.5);border-radius:10px;color:#fff;text-decoration:none}</style>
    </head><body><div class="box"><div class="icon">📭</div><div class="title">文件不存在</div><div class="sub">该下载项未找到或已被禁用</div><a href="index.php">← 返回首页</a></div></body></html>
    <?php exit;
}

$siteTitle  = getSetting('site_title',    '文件下载中心');
$themeColor = getSetting('theme_color',   '#6c63ff');
$waitSec    = $item['wait_time'] !== null ? intval($item['wait_time']) : intval(getSetting('wait_time', '5'));
$hasPass    = !empty($item['password']);

// 密码验证
$passError    = '';
$passVerified = false;

// 检查 session 是否已验证
$sessionKey = 'dl_pass_' . $item['id'];
if ($hasPass && isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] === true) {
    $passVerified = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasPass) {
    $inputPass = trim($_POST['password'] ?? '');
    if ($inputPass === $item['password']) {
        $_SESSION[$sessionKey] = true;
        $passVerified = true;
    } else {
        $passError = '密码错误，请重试';
    }
}

// 增加下载计数（密码验证通过 或 无密码 时才在JS发起真实跳转，此处只记录进入下载页）
$showDownload = !$hasPass || $passVerified;

// 记录下载（通过fetch回调触发）
if (isset($_GET['count']) && $_GET['count'] == '1' && $showDownload) {
    $pdo->prepare('UPDATE downloads SET download_count=download_count+1 WHERE id=?')->execute([$id]);
    http_response_code(204);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($item['name']) ?> - <?= htmlspecialchars($siteTitle) ?></title>
<style>
:root{--primary:<?= htmlspecialchars($themeColor) ?>}
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;background:linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#1a0533 100%);font-family:'Segoe UI',system-ui,sans-serif;color:#e8e8f0;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
#particles{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0}
.container{position:relative;z-index:1;width:100%;max-width:520px}

/* 面包屑 */
.breadcrumb{display:flex;align-items:center;gap:8px;color:rgba(255,255,255,.4);font-size:.85rem;margin-bottom:20px}
.breadcrumb a{color:rgba(167,139,250,.8);text-decoration:none}
.breadcrumb a:hover{color:#a78bfa}

/* 卡片 */
.card{background:rgba(255,255,255,.07);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:36px 32px;text-align:center}

/* 图标 */
.file-icon-wrap{margin-bottom:20px}
.file-icon{width:72px;height:72px;border-radius:18px;object-fit:contain;background:rgba(255,255,255,.1);padding:8px}
.file-icon-placeholder{width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,rgba(108,99,255,.4),rgba(168,85,247,.3));display:inline-flex;align-items:center;justify-content:center;font-size:2rem}

/* 文字 */
.file-name{font-size:1.3rem;font-weight:700;color:#fff;margin-bottom:8px}
.file-desc{color:rgba(255,255,255,.5);font-size:.9rem;margin-bottom:24px;line-height:1.5}

/* 倒计时区域 */
.countdown-area{margin-bottom:24px}
.countdown-circle{position:relative;width:100px;height:100px;margin:0 auto 14px}
.countdown-circle svg{transform:rotate(-90deg)}
.countdown-track{fill:none;stroke:rgba(255,255,255,.1);stroke-width:6}
.countdown-prog{fill:none;stroke:var(--primary);stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset .5s linear;filter:drop-shadow(0 0 6px var(--primary))}
.countdown-num{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:2rem;font-weight:800;color:#fff}
.countdown-label{color:rgba(255,255,255,.5);font-size:.85rem}

/* 进度条 */
.progress-bar{height:4px;background:rgba(255,255,255,.1);border-radius:2px;overflow:hidden;margin-bottom:24px}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--primary),#a855f7);border-radius:2px;transition:width .5s linear;width:0}

/* 按钮 */
.btn-download{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:15px;background:linear-gradient(135deg,var(--primary),#a855f7);border:none;border-radius:14px;color:#fff;font-size:1.05rem;font-weight:700;cursor:pointer;transition:.3s;text-decoration:none}
.btn-download:not(:disabled):hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(108,99,255,.5)}
.btn-download:disabled{opacity:.4;cursor:not-allowed;transform:none}
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:10px;color:rgba(255,255,255,.6);text-decoration:none;font-size:.875rem;margin-top:14px;transition:.2s}
.btn-back:hover{background:rgba(255,255,255,.12);color:#fff}

/* 密码表单 */
.pass-form{text-align:left}
.pass-form .icon-big{text-align:center;font-size:2.5rem;margin-bottom:16px}
.pass-form h3{text-align:center;font-size:1.1rem;color:#fff;margin-bottom:6px}
.pass-form p{text-align:center;color:rgba(255,255,255,.5);font-size:.875rem;margin-bottom:24px}
.pass-input{width:100%;padding:13px 16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:12px;color:#fff;font-size:1rem;outline:none;transition:.3s;letter-spacing:.1em;text-align:center}
.pass-input:focus{border-color:var(--primary);background:rgba(108,99,255,.1);box-shadow:0 0 0 3px rgba(108,99,255,.2)}
.pass-error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:10px 14px;border-radius:8px;font-size:.875rem;margin-top:12px;text-align:center}
.btn-verify{display:block;width:100%;padding:13px;background:linear-gradient(135deg,var(--primary),#a855f7);border:none;border-radius:12px;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;margin-top:16px;transition:.3s}
.btn-verify:hover{opacity:.9;transform:translateY(-1px)}

/* Footer */
footer{position:relative;z-index:1;margin-top:32px;text-align:center;color:rgba(255,255,255,.3);font-size:.82rem}
footer a{color:rgba(167,139,250,.7);text-decoration:none}
footer a:hover{color:#a78bfa}
</style>
</head>
<body>

<canvas id="particles"></canvas>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">🏠 首页</a>
        <span>›</span>
        <span><?= htmlspecialchars(mb_substr($item['name'],0,30)) ?></span>
    </div>

    <div class="card">

    <?php if (!$showDownload): ?>
    <!-- 密码验证 -->
    <form method="POST" class="pass-form">
        <div class="icon-big">🔒</div>
        <h3>需要访问密码</h3>
        <p>「<?= htmlspecialchars($item['name']) ?>」已开启密码保护</p>
        <input type="password" name="password" class="pass-input" placeholder="请输入密码" autofocus autocomplete="off" required>
        <?php if ($passError): ?><div class="pass-error">⚠ <?= htmlspecialchars($passError) ?></div><?php endif; ?>
        <button type="submit" class="btn-verify">🔓 验证密码</button>
    </form>
    <a href="index.php" class="btn-back" style="display:flex;justify-content:center;margin-top:14px">← 返回首页</a>

    <?php else: ?>
    <!-- 下载页 -->
    <div class="file-icon-wrap">
        <?php if ($item['icon']): ?>
            <img src="<?= htmlspecialchars($item['icon']) ?>" class="file-icon" onerror="this.outerHTML='<div class=\'file-icon-placeholder\'>📄</div>'">
        <?php else: ?>
            <div class="file-icon-placeholder">📄</div>
        <?php endif; ?>
    </div>

    <div class="file-name"><?= htmlspecialchars($item['name']) ?></div>
    <?php if ($item['description']): ?><div class="file-desc"><?= htmlspecialchars($item['description']) ?></div><?php endif; ?>

    <?php if ($waitSec > 0): ?>
    <div class="countdown-area">
        <div class="countdown-circle">
            <?php
            $r   = 42;
            $circ = 2 * M_PI * $r;
            ?>
            <svg width="100" height="100" viewBox="0 0 100 100">
                <circle class="countdown-track" cx="50" cy="50" r="<?= $r ?>"/>
                <circle class="countdown-prog" cx="50" cy="50" r="<?= $r ?>"
                    stroke-dasharray="<?= $circ ?>"
                    stroke-dashoffset="0"
                    id="circProg"/>
            </svg>
            <div class="countdown-num" id="countNum"><?= $waitSec ?></div>
        </div>
        <div class="countdown-label">请等待倒计时结束后下载</div>
    </div>
    <div class="progress-bar"><div class="progress-fill" id="progFill"></div></div>
    <a href="<?= htmlspecialchars($item['url']) ?>" class="btn-download" id="dlBtn" disabled>
        ⏳ 请等待 <span id="dlBtnTxt"><?= $waitSec ?></span> 秒...
    </a>
    <?php else: ?>
    <a href="<?= htmlspecialchars($item['url']) ?>" class="btn-download" id="dlBtn">
        ⬇ 立即下载
    </a>
    <?php endif; ?>

    <a href="index.php" class="btn-back"><span>←</span> 返回首页</a>
    <?php endif; ?>

    </div>
</div>

<footer>Powered by <a href="http://www.zyun.ink" target="_blank" rel="noopener">ZYUN</a></footer>

<script>
// 粒子
(function(){
    const c=document.getElementById('particles'),ctx=c.getContext('2d');
    let W,H,pts=[];
    function resize(){W=c.width=innerWidth;H=c.height=innerHeight;}
    resize();addEventListener('resize',resize);
    for(let i=0;i<40;i++) pts.push({x:Math.random()*9999,y:Math.random()*9999,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()*2+1,o:Math.random()*.4+.1});
    function draw(){
        ctx.clearRect(0,0,W,H);
        pts.forEach(p=>{p.x=(p.x+p.vx+W)%W;p.y=(p.y+p.vy+H)%H;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(167,139,250,${p.o})`;ctx.fill();});
        requestAnimationFrame(draw);
    }
    draw();
})();

<?php if ($showDownload && $waitSec > 0): ?>
// 倒计时
(function(){
    const total = <?= $waitSec ?>;
    const circ  = <?= $circ ?>;
    const prog  = document.getElementById('circProg');
    const num   = document.getElementById('countNum');
    const txt   = document.getElementById('dlBtnTxt');
    const btn   = document.getElementById('dlBtn');
    const fill  = document.getElementById('progFill');
    let left = total;

    function tick(){
        if(left <= 0){
            prog.style.strokeDashoffset = circ;
            fill.style.width = '100%';
            num.textContent = '0';
            // 解锁按钮
            btn.removeAttribute('disabled');
            btn.innerHTML = '⬇ 下载文件';
            btn.addEventListener('click', onDownload, {once:true});
            // 计数
            fetch('?id=<?= $item['id'] ?>&count=1');
            return;
        }
        const done = total - left;
        const ratio = done / total;
        prog.style.strokeDashoffset = circ * (1 - ratio);
        fill.style.width = (ratio * 100) + '%';
        num.textContent = left;
        if(txt) txt.textContent = left;
        left--;
        setTimeout(tick, 1000);
    }
    setTimeout(tick, 1000);

    function onDownload(e){ /* 正常跳转 */ }
})();
<?php elseif ($showDownload && $waitSec == 0): ?>
// 立即可下载，记录计数
document.getElementById('dlBtn').addEventListener('click', function(){
    fetch('?id=<?= $item['id'] ?>&count=1');
}, {once:true});
<?php endif; ?>
</script>
</body>
</html>
