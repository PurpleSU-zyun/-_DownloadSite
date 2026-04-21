# -_DownloadSite
一个精美的文件下载站，可设置等待时间，下载密码。提供一个精美的管理面板，可设置链接，文件项目，以及设置安全设置。使用MySQL数据库
![示例图片1](https://github.com/PurpleSU-zyun/-_DownloadSite/blob/main/picture/1.png)
![示例图片2](https://github.com/PurpleSU-zyun/-_DownloadSite/blob/main/picture/2.png)
![示例图片3](https://github.com/PurpleSU-zyun/-_DownloadSite/blob/main/picture/3.png)
![示例图片4](https://github.com/PurpleSU-zyun/-_DownloadSite/blob/main/picture/4.png)


## 部署步骤

### 1. 创建数据库
在宝塔面板 → 数据库 → 添加数据库，记录：
- 数据库名
- 用户名
- 密码

### 2. 修改 config.php
```php
define('DB_HOST', 'localhost');
define('DB_USER', '你的数据库用户名');
define('DB_PASS', '你的数据库密码');
define('DB_NAME', '你的数据库名');

define('ADMIN_USER', 'admin');     // 后台用户名
define('ADMIN_PASS', 'admin123'); // 后台密码（请修改！）
```

### 3. 上传文件
将整个 `download-site/` 目录内的文件上传到网站根目录。

### 4. 运行安装
访问 `https://你的域名/install.php`，点击「开始安装」，
安装完成后**立即删除 install.php**。

### 5. 登录后台
访问 `https://你的域名/admin.php`
- 默认用户名：`admin`
- 默认密码：`admin123`
