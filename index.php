<?php

class Order
{
    //声明静态属性
    private static $redis = null;
    private static $pdo = null;

    public static function Redis()
    {
        if (self::$redis == null) {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            self::$redis = $redis;
        }
        return self::$redis;
    }

    public static function mysql()
    {
        $dbhost = '127.0.0.1'; //数据库服务器
        $dbport = 3306; //端口
        $dbname = 'redis'; //数据库名称
        $dbuser = 'root'; //用户名
        $dbpass = 'root'; //密码


        // 连接
        try {
            $db = new PDO('mysql:host=' . $dbhost . ';port=' . $dbport . ';dbname=' . $dbname, $dbuser, $dbpass);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //设置错误模式
            $db->query('SET NAMES utf8;');
            self::$pdo = $db;
//            echo('连接数据库成功！');
        } catch (PDOException $e) {
            echo('连接数据库失败！');
            exit;
        }
        return self::$pdo;
    }

    // 抢购下单
    public function goodsOrder()
    {
        $redis = self::Redis();
        $db = self::mysql();
        $goodsId = 1;
        $sql = "select id,inventory,price from hw_goods where id=" . $goodsId;
        $stmt = $db->query($sql);
        //fetch从结果集中返回一行。该参数PDO::FETCH_ASSOC告诉PDO将结果作为关联数组返回。
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $redis = self::Redis();
        $count = $redis->rpop('num');//每次从num取出1 rpop移除最后一个元素
        if ($count == 0) {
            $this->log(0, 'no num redis');
            echo '已没有库存';
        } else {
            $this->doOrder($row, 1);
        }
    }

    // 下单更新库存
    public function doOrder($goods, $goodsNum)
    {
        $orderNo = $this->orderNo();
        $number = $goods['inventory'] - $goodsNum;
        if ($number < 0) {
            $this->log(0, '已没有库存');
            echo '已没有库存';
            return false;
        }
        $db = self::mysql();
        try {
            $db->beginTransaction(); //启动事务
            $sql = "INSERT INTO `hw_order` (user_id,order_sn,status,goods_id,o_num,price,created_at) VALUES (:user_id,:order_sn,:status,:goods_id,:o_num,:price,:created_at)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id' => rand(1, 500),
                ':order_sn' => $orderNo,
                ':status' => 1,
                ':goods_id' => $goods['id'],
                ':o_num' => $goodsNum,
                ':price' => $goods['price'] * 100,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
            $sql2 = "update hw_goods set inventory=inventory-" . $goodsNum . " where inventory>0 and id=" . $goods['id'];
            $res = $db->exec($sql2);

            $db->commit(); //提交事务

            $this->log(1, '下单和库存扣减成功');
        } catch (Exception $e) {

            $db->rollBack(); //回滚事务
            $this->log(0, '下单失败');
        }
    }

    // 生成订单号
    public function orderNo()
    {
        return date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }

    // 保存日志
    public function log($status, $msg)
    {
        $db = self::mysql();
        $sql = "INSERT INTO `hw_order_log` (status,msg,created_at) VALUES (:status,:msg,:created_at)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':msg' => $msg,
            ':status' => $status,
            ':created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

$order = new Order();
$order->goodsOrder();