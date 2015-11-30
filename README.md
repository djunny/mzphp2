gitbook 帮助文档：

[https://djunny.gitbooks.io/mzphp/content/index.html](https://djunny.gitbooks.io/mzphp/content/index.html)


### 序

很久以来，楼主开发站点无数，一直希望能有一个枚，日开数站的 PHP 框架出现。

### mzphp 介绍


PHP 开发框架 mzphp，拥有特点：

- 性能，高性能极致加载、高效率编译和读取！
- 清晰，大量注释及实例，几分钟就上马进门！
- 小巧，整个框架 400k，几乎没有冗余代码！
- 奔放，支持 http 和 cli 双运行，php index.php {$control} {$action} 就能体验 PHP 在命令行下的奔放。（可用于后台常驻内存、爬虫等）
- 易用，优化过的 discuz 模板引擎，使模板调用更加简约（兼容 php 标签，支持static、block、{method()}等标签）
- 安全，简单又安全的取参数过程，有效防止 xss，封装的 SQL 防注入过程，确保系统安全。
- 扩展，丰富的库和插件：MySQL、PDO 引擎、scss 语法支持、css 压缩、css sprite 生成、js 合并、js 压缩等。（前后端开发相亲相爱不再分离）
- 调试，详细的 DEBUG、运行时间和SQL查询信息，只需在 URL 中加上固定参数，简单好用。

mzphp 做为一直追求高效、简单、快速开发的 PHP 框架，希望大家多多支持。

### mzphp 运行的环境

- 操作系统：windows、linux 
- PHP 环境：PHP 5.3+
- PHP 扩展：php-gd、php-mbstring、php-curl
- PHP 扩展（数据库）：php-mysql 或 php-pdo
- PHP 扩展（缓存）：php-memcached 或 php-redis


如果需要 php 在 cli (命令行) 下运行，而您的操作系统是 windows。

请在系统 -> 环境变量 -> PATH -> 添加你的 php.exe 所在路径


### 拉取 mzphp 代码

`
git clone git@git.oschina.net:mz/mzphp2.git
`

完后，可更新子项目例子：

`
	git submodule update
`

### 生成第一个项目

假我们需要建立的项目名称为：hello_world

请在项目目录建立一个hello_world(与 mzphp 目录同级)

复制 tools 目录下的 create_project.php 到　hello_world 目录。

提供两种方法生成项目结构：

- 第一种：打开浏览器访问 create_project.php ，生成
- 第二种：在命令行下 

```
cd hello_world

php create_project.php
```


访问生成的 index.php，可看到 mzphp Framework 字样，即访问成功.

### mzphp 目录文件

- cache         缓存支持（file、memcache、redis）
- core          核心方法
- db            数据库支持（mysql、pdo_mysql、pdo_sqlite）
- debug         调试支持
- helper        一些扩展库以及方法（log、misc、spider）
- plugin        插件目录
- mzphp.php     入口文件


### 项目目录文件

- conf         项目配置目录
- control      项目控制器目录
- core         默认 include 的库、基类、方法等
- data         项目数据保存目录
- static       所有静态文件（css/js/图片）存储目录
- view         项目模板目录
- index.php    入口文件

### 配置项目参数

打开项目 conf/conf.{env}.php 可按照注释编辑对应的参数。

{env}代表运行环境，由 $_SERVER['ENV'] 来控制 (默认为 debug)。

在 nginx 中，您可以通过添加：

`
fastcgi_param ENV 'online';
`

来切换线上、线下环境。


**附上一些小的参数修改**

- index.php 中，在定义 DEBUG 处，来设置启动 debug 的 url 参数。
- index.php 中，在定义 FRAMEWORK_PATH 处，可修改 mzphp 框架所处的路径


### 调试方法

index.php 中，你可以直接设置 **define('DEBUG', 1)** 永久打开 debug

也可以修改 **hello_world_debug** 这个字符串来告之项目当 url 中包含该字符时，自动开启 debug。

debug 开启时，右下角将出现一个浮动的运行时间展示，点击开来可以看到具体页面运行的信息。

**注意**

mzphp 框架为了减少磁盘 I/O，提高加载性能，会在项目第一次运行时，

- 生成的缓存文件在 data/runtime.php 中。
- 缓存的代码为： mzphp 框架下所有文件和项目 core 目录中所有 *.class.php 文件。
- 当开启 debug 后，所有文件均为动态加载，会跳过加载缓存 runtime.php。
- 当开启 debug 后，模板编译后的缓存文件不会加载。
- 当开启 debug 后，css 合并、js 合并等静态文件会重新生成。


### mzphp 控制器

在项目 control 目录中建立一个 user_control.class.php

同时，在该文件中定义一个 user_control 继承 base_control

```
<?php
!defined('FRAMEWORK_PATH') && exit('Accesss Deined');
class user_control extends base_control {
    public function on_index(){
        // define user
        $user = 'djunny';
        // assign variables
        VI::assign('user', $user);
        // default template path is view/user_index.htm
        $this->display();
    }
}
```
**$this->display** 调用显示模板，

可以自动识别为 view/user_index.htm,

即 {$control}_{$action}.htm

假如，view/user_index.htm 如下：
```
hello {$user}
```


使用 index.php?c=user-index 可访问到 user_control 的 on_index 方法，显示结果：

```
hello djunny
```


同时，使用命令行下：
```
php index.php user index 
```

也能在命令行下看到同样的结果

小建议：

- 在命令行下，你可以 core 中建立一个 cmd_control，在析构方法中做一些限制，例如判断是否 cmd 下运行（可以用 core::is_cmd() 方法）, user_control 继承 cmd_control，能有效的防止 control 被 http 请求到。
- 在命令行下，不建议使用 echo 来输出 log，可以使用帮助类 log::info($output) 来输入出 log。$output 可以为字符串、数字、数组、MAP。
- VI::assign 是传**引用**绑定变量、VI::assign_value 是**传值**绑定变量
- 如果调用 $this->display('user/index')，代表渲染 view/user/index.htm 模板文件。


### mzphp DAO 数据层

第一步：配置 conf 文件中的 db。

第二步：创建 user 表：
```
CREATE TABLE `user` (
  `id` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '',
  PRIMARY KEY (`uid`)
) DEFAULT CHARSET=utf8 COMMENT='user'
```


第三步，model 层有二种调用方法。

第一种，不定义 model，直接使用

```
<?php
!defined('FRAMEWORK_PATH') && exit('Accesss Deined');
class user_control extends base_control {
    public function on_index(){
        // SELECT * FROM user WHERE id =1
        $user = DB::T('user')->get(1);
        // ...
    }
}
```

此方法用于简单的 DAO 层调用。

第二种，在项目 model 目录中，新建一个 user.class.php 定义 user 继承自 base_model， 
```
<?php
!defined('FRAMEWORK_PATH') && exit('Accesss Deined');
class user extends base_model {
	function __construct() {
		parent::__construct('user', 'id');
	}
}
?>
```

在控制器中使用

```
<?php
!defined('FRAMEWORK_PATH') && exit('Accesss Deined');
class user_control extends base_control {
    public function on_index(){
        // SELECT * FROM user WHERE id =1
        $user = $this->user->get(1);
        // ...
    }
}
```

mzphp 中，控制器会自动加载并初始化对应的 model 层。

另外：

- mzphp 也支持原生查询 SQL，使用以下方法：


```
// add table prefix

$table = DB::table('user');

// build SQL

$sql = sprintf("SELECT * FROM %s WHERE id=%d", $table, 1);

// get query and return first row

$first_row = DB::query($sql, 1);

// get query 

$query = DB::query($sql);

while($val = DB::fetch($query)){

    log::info($val);


}

// select method

// get first row from user where id =1

$user = DB::select('user', array('id'=>1), ' id ASC', 0);

// get all user array

$user_list = DB::select('user');

// get user count

$user_count = DB::select('user', 0, 0, -2);

```


### mzphp 模板引擎 - 基础语法

1. <!--{code}-->                             默认注释语法 
2. {code}                                    嵌入属性语法
3. {$variable}                               输出变量
4. {CONSTANT}                                输出常量
5. {$array['index']}                         输出数组下标
6. {function(param1, param2, param3...)}     调用原生方法

模板中动态渲染内容，基本上由以上语法组成。

- 语法1 多用于 html 块级输出，例：
```
<!--{if 1==2}--><html></html><!--{/if}-->
```

- 语法2 多用于元素属性输出，例：
```
<form {if 1==2}method="post"{/if}>
```

- 语法6 调用原生方法，输出值必须为方法结果 return


### mzphp 模板引擎 - 子模板

**说明：**

加载一个子模板。(子模板最多支持三层嵌套)

**语法：**
```
<!--{template header.htm}-->
```

### mzphp 模板引擎 - 逻辑判断

**说明：**

if else 逻辑判断

**语法：**

```	
<!--{if $a == $b && $b == 1}-->
	a = b AND b = $b
<!--{elseif $b == 2 || $a == 3}-->
	b = $b OR a = $a
<!--{elseif $a == 1}-->
	a = 1
<!--{else}-->
	a != 1
<!--{/if}-->
```

### mzphp 模板引擎 - 循环

**说明：**

循环输出列表，可嵌套输出

**语法：**

```
<!--{loop $categories $index $cate}-->
	categories[$index]= {$cate['name']}
<!--{/loop}-->

```

### mzphp 模板引擎 - eval 标签
**说明：**

执行 php 代码。

**语法：**

```
<!--{eval 
$a = 1;
$b = array(
	'a' => 1,
	'b' => 2,
);
}-->

<!--{eval echo $my_var;}-->
<!--{eval $my_arr = array(1, 2, 3);}-->
<!--{eval print_r($my_arr);}-->
<!--{eval exit();}-->
```


### mzphp 模板引擎 - 方法调用标签

**说明：**

调用的方法并输出返回的内容。(方法必须以返回值)
	
**语法：**
```
{date('Y-m-d')}
{substr('123', 1)}
{print_r(array(1), 1)}
{spider::html2txt('<html></html>')}
```


### mzphp 模板引擎 - 静态文件

**语法：**
```
<!--{static source_file target_file is_compress}-->
```
**说明：**

source_file 文件后缀为 scss 或者  js 时，会调用对应的编译类对文件进行编译和生成，生成的文件路径：static/{$target_file}

is_compress 参数可以为 0 或 1(默认 1)，为 1 时，source_file 压缩，反之而不压缩。

在 debug 开启后，每次访问页面，都会生成一遍静态文件。在代码 push 的时候，需要产生的所有静态文件提交（线上不会再编译静态资源）
	
**规范**

- 建议编译后的缓存文件名都使用 _ 开头命名，这样后期方便清理。
- 编译后的 css 文件只能在原 css 目录（否则相对路径的图片可能加载失败）
- 编译后的 js 文件最好在原 js 目录（除非你的 js 文件没有对路径有依赖）

**静态文件寻找先后顺序**

1. static/
2. view/


**关于 css sprite**

当 source_file 以 * 结尾时, 识别为 css sprite 模式，程序将会对 source_file 目录中的所有文件进行合并，读取所有图片的 size ，生成对应的 scss 文件和合并的 png 文件：

1. target_file.scss （命名方式：.filename-png，文件名中_和.替换成-）
2. target_file.png（不需要重复的图片合并文件）
3. target_file-x.png （需要 x 轴重复的图片合并文件）
4. target_file-y.png（需要 y 轴重复的图片合并文件）

**实例**
```
<!--{static scss/png/* scss/sprite}-->
<!--{static scss/test.scss scss/_a.css}-->
<!--{static js/test1.js js/_a.js}-->
<!--{static js/test2.js js/_a.js 0}-->
<!--{static js/test3.js js/_a.js 1}-->
```

在以上例子中,系统做了以下处理：

1.先合并 scss/png/ 中所有图片为: scss/sprite.png，同时生成坐标 scss 文件：scss/sprite.scss

2.scss/test.scss 中调用 @import 'sprite'; 然后用 scss 语法来继承对应的 icon 或 图片即可，例（图片路径中.会变成-，更具体可以先成生一次后，打开生成后的sprite.scss看看）：


```
.find{
    @extend .loading-png;
}
```

3.开启合并文件 js/_a.js ，并将 js/test1.js 写入。

4.读取 js/test2.js，写入 js/_a.js 尾部。

5.读取 js/test3.js，并压缩，写入 js/_a.js

6.<!--{static scss/png/* scss/sprite}--> 替换成 空

7.<!--{static scss/test.scss scss/_a.css}--> 替换成 < link rel="stylesheet" href="scss/_a.css">

8.<!--{static js/test1.js js/_a.js}--> 替换成 < script src="js/_a.js"></ script>

9.<!--{static js/test2.js js/_a.js 0}--> <!--{static js/test3.js js/_a.js 1}--> 均替换成空



### mzphp 地址重写

地址重写规则：

**.htaccess**
```
Options 
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

#control + action
RewriteRule ^(\w+)(?:[/\-_\|\.,])(\w+)(?:[/\-_\|\.,])(.*)$ index.php\?c=$1-$2&rewrite=$3 [L]

#control 
RewriteRule ^(\w+)(?:[/\-_\|\.,])(.+)$ index.php\?c=$1&rewrite=$2 [L]
```

**apache httpd.ini**
```
[ISAPI_Rewrite]
RepeatLimit 32
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

#control + action
RewriteRule ^(\w+)(?:[/\-_\|\.,])(\w+)(?:[/\-_\|\.,])(.*)$ index.php\?c=$1-$2&rewrite=$3 [L]

#control 
RewriteRule ^(\w+)(?:[/\-_\|\.,])(.+)$ index.php\?c=$1&rewrite=$2 [L]
```
**nginx.conf**
```
#control + action
rewrite ^(\w+)(?:[/\-_\|\.,])(\w+)(?:[/\-_\|\.,])(.*)$ index.php?c=$1-$2&rewrite=$3 last;
#control 
rewrite ^(\w+)(?:[/\-_\|\.,])(.+)$ index.php?c=$1&rewrite=$2 last;
```

**iis 7**
```
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
    <rewrite>
       <rules>
			<rule name="mzphp_1" stopProcessing="true">
				<match url="(\w+)(?:[/\-_\|\.,])(\w+)(?:[/\-_\|\.,])(.*)$" />
				<action type="Rewrite" url="index.php?c={R:1}-{R:2}&rewrite={R:3}" appendQueryString="true" />
			</rule>
			<rule name="mzphp_2" stopProcessing="true">
				<match url="(\w+)(?:[/\-_\|\.,])(.+)$" />
				<action type="Rewrite" url="index.php?c={R:1}&rewrite={R:2}" appendQueryString="true" />
			</rule>
        </rules>
    </rewrite>
    </system.webServer>
</configuration>
```

### mzphp 自定义 URL

**1.非地址重写链接：**

```
index.php?c=control-action&var=...
```

**2.系统地址重写(该地址中的 / 分隔符和 [.html] 均可在conf文件中配置):**

```
/control/action/param1/param1value/param2/param2value...[.html]
```

conf/conf.[env].php 配置文件：

```
    //url rewrite params
	'rewrite_info' => array(
		'comma' => '/', // options: / \ - _  | . , 
		'ext' => '.html',// for example : .htm
	),
```

使用系统地址重写时，您可以使用url方法，来生成url:
```
function url($control, $action, $params = array()) ;
```
例：
```
echo url('index', 'index', array('id'=>1));
echo url('index-index', array('id'=>1));
echo url('index-index', 'id=1&time=2015');
```


**3.自定义地址重写：**
```
index.php?c=control-action&rewrite=param1/param1value...
```
例如，我们需要使用：**/help/123/** 来映射 **index.php?c=article-help&id=123**


可以在 urlrewrite 重写文件中配置（以 .htaccess 语法为例）：

```
RewriteRule ^(help)/(\d+)/?$ index.php\?c=article-$1&rewrite=id/$2 [L]
```

注：本例中 rewrite 参数传入的分割符（/），视 **rewrite_info** 中的 **comma** 而定。

****
****
****

更多实例敬请期待。