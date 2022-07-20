<?php
//创建事例链接redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

for ($i = 1; $i <= 100; $i++) {
    $redis->lpush('num', $i);
}

echo '塞入缓存成功！';







