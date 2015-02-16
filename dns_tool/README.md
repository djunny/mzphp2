说明：

利用本工具可以批量添加 dnspod、dnsla、dnsdun 域名。

使用：
1、conf 目录下：

重命名 xxx.php.default 为 xxx.php
(xxx 为对应的 dns 解析服务商名称)

修改文件内容，对应 api 请求参数。

2、将您的新域名按格式写至 conf/domains.txt 文件中：
dns服务商	主域名|子域名1|子域名2...
dnspod	abc123123123.cn|*.abc123123123.cn

3、将您需要绑定的域名写至 conf/ips.txt 文件中（每行一个，可重复）。
（注意，域名文件domains.txt和ips.txt文件的行数需要一致）

4、打开命令行, 切换到 dns_tool 目录，运行：
	php index.php start
(如果需要域名随机绑定)