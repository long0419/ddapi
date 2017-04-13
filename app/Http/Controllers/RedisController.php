<?php

namespace App\Http\Controllers;

use App\MyClass\MyRedisCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Response;


class RedisController extends Controller
{
    /**
     * 获取用户信息
     */
    public function user()
    {
        //MyRedisCache::setUserCache();
        //Redis::command('DEL',['HOST_DATA_1']);
        //return Redis::hgetall('HOST_DATA_1');
        $hostid = md5(md5(1).md5('177'));
        var_dump(Redis::command('HGET',['HOST_DATA_1',$hostid]));
        echo date('Y-m-d H:i:s',1492060544);
        return Cache::get($hostid.'intake_redis');
    }

    /**
     * 获取node_host
     */
    public function nodeHost()
    {
        //MyRedisCache::setNodeHostCache();
        return Redis::hgetall('user_node_metric_node_088DBF7B54EFBA3CA599B3543C73EA1C');
    }

}
