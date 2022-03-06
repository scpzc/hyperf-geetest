极验验证码 v3.0 扩展包

## 安装

``` bash
$ composer require scpzc/hyperf-geetest -vvv
```

## 使用
0. 发布配置

```php
php bin/hyperf.php vendor:publish scpzc/hyperf-geetest
```

1. 生成极验验证码对象

``` php
// $config 参数见下方[配置项]
$geetest = new Geetest($config);
```

2. 在模板中引入 [jquery.min.js](https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js) 和 [gt.js](http://static.geetest.com/static/tools/gt.js) ,在需要使用验证码的地方增加下述代码渲染

    > gt.js 建议放在本地,防止极验验证码服务器宕机影响自己的站点
    
``` php
<?= $geetest->view(); ?>
```
3. 在 `captchaUrl` 路由指定的操作中,获取验证码参数

```php
echo $geetest->captcha();
```
4. 随表单提交时,服务端校验验证码

```php
// 校验结果为 true 或 false
$geetest->validate($params['geetest_challenge'], $params['geetest_validate'], $params['geetest_seccode']);
```

## 配置项

| 配置项  | 说明  | 选项  | 默认值  |
| ------------ | ------------ | ------------ | ------------ |
| width | 按钮宽度  | 单位可以是 px, %, em, rem, pt  | 100%|
| lang | 语言，极验验证码免费版不支持多国语言  | zh-cn, en, zh-tw, ja, ko, th  | zh-cn  |
| product  | 验证码展示方式  | popup, float  | popup  |
| geetestID  | 极验验证码ID  |   |   |
| geetestKey  | 极验验证码KEY  |   |   |
| clientFailAlert  | 客户端失败提示语  |   | 请完成验证码  |
| serverFailAlert  | 服务端失败提示语  |   | 验证码校验失败  |
| captchaUrl  | 获取验证码初始化参数路由  |   |   |

