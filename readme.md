### 华为NVR-for-laravel接入包

#### 1.安装到项目
```
composer require weicome/huaweinvr-for-laravel
```
#### 2.添加服务到config/app.php
在 providers 中添加
```
Wei\HuaweiNvr\HuaweiNvrServiceProvider::class
```
#### 3.发布配置
```
php arstran vendor:publish --provider="Wei\HuaweiNvr\HuaweiNvrServiceProvider"
```
#### 4. 修改配置文件的资料,在config/huaweinvr.php

```
'url' => env('HUAWEI_NVR_URL'),
'username' => env('HUAWEI_NVR_USERNAME'),
'password' => env('HUAWEI_NVR_PASSWORD'),
```
都是在环境文件中配置好，service配置是需要处理事件的类
```
'service'=>[
    \App\Services\HuaWei\EventService::class
]
```
事件处理类是自己编写逻辑的，

#### 5. 编写事件实现类，需要继承 Wei\HuaweiNvr\NVRInterface 类并实现handler方法
```
use Wei\HuaweiNvr\NVRInterface;
class Event implements NVRInterface{
    public function handler(array $event, array $channel, ?object $nvr = null)
    {
    }
}
```
变量$event,记录了告警事件$event['event'], 和图片查询事件$event['picture']
变量$channel,记录了管道数据 $channel['CH1'] => $channel['channel_name']
$nvr服务对象本身,在请求图片是传递，可以调用getPlaybackPicture($pic_info = null)方法

#### 6. 启动监听
```
$  php artisan nvr:listen
```
