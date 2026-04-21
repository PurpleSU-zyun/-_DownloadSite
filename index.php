<?php
require_once 'config.php';

$pdo        = getDB();
$siteTitle  = getSetting('site_title',    '文件下载中心');
$subTitle   = getSetting('site_subtitle', '安全、快速、便捷的文件下载服务');
$themeColor = getSetting('theme_color',   '#6c63ff');
$showCount  = getSetting('show_count',    '1');

// 搜索
$search = trim($_GET['q'] ?? '');
if ($search) {
    $stmt = $pdo->prepare('SELECT * FROM downloads WHERE enabled=1 AND (name LIKE ? OR description LIKE ?) ORDER BY sort_order ASC, id DESC');
    $stmt->execute(['%'.$search.'%', '%'.$search.'%']);
} else {
    $stmt = $pdo->query('SELECT * FROM downloads WHERE enabled=1 ORDER BY sort_order ASC, id DESC');
}
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($siteTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($subTitle) ?>">
<style>
:root{--primary:<?= htmlspecialchars($themeColor) ?>}
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;background:linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#1a0533 100%);font-family:'Segoe UI',system-ui,sans-serif;color:#e8e8f0;overflow-x:hidden}

/* 粒子背景 */
#particles{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0}

.wrap{position:relative;z-index:1;max-width:900px;margin:0 auto;padding:0 20px 60px}

/* Hero */
.hero{text-align:center;padding:72px 20px 48px}
.hero h1{font-size:clamp(1.8rem,5vw,3rem);font-weight:800;background:linear-gradient(135deg,#fff 30%,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1.2;margin-bottom:14px}
.hero p{font-size:1.1rem;color:rgba(255,255,255,.6);max-width:500px;margin:0 auto 36px}

/* Search */
.search-wrap{display:flex;max-width:480px;margin:0 auto;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:50px;overflow:hidden;backdrop-filter:blur(10px);transition:.3s}
.search-wrap:focus-within{border-color:var(--primary);box-shadow:0 0 0 3px rgba(108,99,255,.2)}
.search-wrap input{flex:1;background:none;border:none;padding:13px 20px;color:#fff;font-size:.95rem;outline:none}
.search-wrap input::placeholder{color:rgba(255,255,255,.4)}
.search-wrap button{background:var(--primary);border:none;padding:10px 22px;color:#fff;cursor:pointer;font-size:1rem;border-radius:0 50px 50px 0;transition:.2s}
.search-wrap button:hover{opacity:.9}

/* Count */
.result-count{text-align:center;color:rgba(255,255,255,.4);font-size:.85rem;margin:16px 0 28px}

/* Grid */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}

/* Card */
.dl-card{background:rgba(255,255,255,.06);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:22px 20px 18px;display:flex;flex-direction:column;gap:14px;transition:.3s;cursor:pointer;position:relative;overflow:hidden}
.dl-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--primary),#a855f7);opacity:0;transition:.3s}
.dl-card:hover{transform:translateY(-4px);border-color:rgba(108,99,255,.4);box-shadow:0 16px 40px rgba(0,0,0,.4)}
.dl-card:hover::before{opacity:1}
.dl-card-top{display:flex;align-items:flex-start;gap:14px}
.dl-icon{width:48px;height:48px;border-radius:12px;object-fit:contain;background:rgba(255,255,255,.1);padding:6px;flex-shrink:0}
.dl-icon-placeholder{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,rgba(108,99,255,.3),rgba(168,85,247,.2));display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
.dl-info{flex:1;min-width:0}
.dl-name{font-size:1rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dl-desc{font-size:.82rem;color:rgba(255,255,255,.5);margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.dl-meta{display:flex;gap:8px;flex-wrap:wrap}
.tag{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.75rem}
.tag-wait{background:rgba(59,130,246,.15);color:#7dd3fc;border:1px solid rgba(59,130,246,.25)}
.tag-lock{background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.25)}
.tag-count{background:rgba(34,197,94,.12);color:#86efac;border:1px solid rgba(34,197,94,.25)}
.dl-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;background:linear-gradient(135deg,var(--primary),#a855f7);border:none;border-radius:12px;color:#fff;font-size:.9rem;font-weight:600;cursor:pointer;transition:.3s;text-decoration:none}
.dl-btn:hover{opacity:.9;box-shadow:0 6px 20px rgba(108,99,255,.4)}

/* Empty */
.empty{text-align:center;padding:80px 20px;color:rgba(255,255,255,.4)}
.empty-icon{font-size:3rem;margin-bottom:16px}

/* Footer */
footer{text-align:center;padding:32px 20px;color:rgba(255,255,255,.3);font-size:.85rem}
footer a{color:rgba(167,139,250,.8);text-decoration:none;transition:.2s}
footer a:hover{color:#a78bfa}
</style>
</head>
<body>

<canvas id="particles"></canvas>

<div class="wrap">
    <div class="hero">
        <h1><?= htmlspecialchars($siteTitle) ?></h1>
        <p><?= htmlspecialchars($subTitle) ?></p>
        <form class="search-wrap" method="GET">
            <input type="text" name="q" placeholder="搜索文件名称..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">🔍</button>
        </form>
    </div>

    <p class="result-count">
        <?php if ($search): ?>
        搜索「<?= htmlspecialchars($search) ?>」找到 <strong style="color:#a78bfa"><?= count($items) ?></strong> 个结果 · <a href="index.php" style="color:rgba(255,255,255,.4)">清除</a>
        <?php else: ?>
        共 <strong style="color:#a78bfa"><?= count($items) ?></strong> 个可下载文件
        <?php endif; ?>
    </p>

    <?php if ($items): ?>
    <div class="grid">
    <?php foreach ($items as $item):
        $waitSec = $item['wait_time'] !== null ? intval($item['wait_time']) : intval(getSetting('wait_time','5'));
    ?>
    <div class="dl-card" onclick="location.href='download.php?id=<?= $item['id'] ?>'">
        <div class="dl-card-top">
            <?php if ($item['icon']): ?>
                <img src="<?= htmlspecialchars($item['icon']) ?>" class="dl-icon" onerror="this.outerHTML='<div class=\'dl-icon-placeholder\'>📄</div>'">
            <?php else: ?>
                <div class="dl-icon-placeholder">📄</div>
            <?php endif; ?>
            <div class="dl-info">
                <div class="dl-name"><?= htmlspecialchars($item['name']) ?></div>
                <?php if ($item['description']): ?><div class="dl-desc"><?= htmlspecialchars($item['description']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="dl-meta">
            <?php if ($waitSec > 0): ?><span class="tag tag-wait">⏱ <?= $waitSec ?>秒等待</span><?php else: ?><span class="tag tag-wait">⚡ 立即下载</span><?php endif; ?>
            <?php if ($item['password']): ?><span class="tag tag-lock">🔒 密码保护</span><?php endif; ?>
            <?php if ($showCount && $item['download_count'] > 0): ?><span class="tag tag-count">⬇ <?= number_format($item['download_count']) ?></span><?php endif; ?>
        </div>
        <a href="download.php?id=<?= $item['id'] ?>" class="dl-btn" onclick="event.stopPropagation()">
            ⬇ 下载文件
        </a>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty">
        <div class="empty-icon">📭</div>
        <p><?= $search ? '未找到匹配的文件' : '暂无可下载文件' ?></p>
    </div>
    <?php endif; ?>
</div>

<footer>
    <p>Powered by <a href="http://www.zyun.ink" target="_blank" rel="noopener">ZYUN</a></p>
</footer>

<script>
// 粒子背景
(function(){
    const c = document.getElementById('particles');
    const ctx = c.getContext('2d');
    let W, H, pts = [];
    function resize(){ W = c.width = innerWidth; H = c.height = innerHeight; }
    resize(); addEventListener('resize', resize);
    for(let i=0;i<60;i++) pts.push({x:Math.random()*9999,y:Math.random()*9999,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()*2+1,o:Math.random()*.5+.1});
    function draw(){
        ctx.clearRect(0,0,W,H);
        pts.forEach(p=>{
            p.x=(p.x+p.vx+W)%W; p.y=(p.y+p.vy+H)%H;
            ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
            ctx.fillStyle=`rgba(167,139,250,${p.o})`; ctx.fill();
        });
        // lines
        for(let i=0;i<pts.length;i++) for(let j=i+1;j<pts.length;j++){
            const dx=pts[i].x-pts[j].x, dy=pts[i].y-pts[j].y, d=Math.sqrt(dx*dx+dy*dy);
            if(d<100){ ctx.beginPath(); ctx.moveTo(pts[i].x,pts[i].y); ctx.lineTo(pts[j].x,pts[j].y); ctx.strokeStyle=`rgba(167,139,250,${.15*(1-d/100)})`; ctx.lineWidth=.5; ctx.stroke(); }
        }
        requestAnimationFrame(draw);
    }
    draw();
})();
</script>
</body>
</html>
