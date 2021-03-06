<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2016/11/28
 * Time: 14:30
 */
namespace App\MyClass;

use App\Host;
use App\HostUser;
use App\Metric as MetricModel;
use App\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use DB;
use Log;

class MyRedisCache
{
    public static function initRedis()
    {
        $host = config('database.redis.default.host');
        $port = config('database.redis.default.port');
        $pass = config('database.redis.default.password');
        $redis = new \Redis();
        $redis->connect($host,$port);
        $redis->auth($pass);
        return $redis;
    }

    /**
     * 设置redis 缓存
     * @param $key
     * @param $value
     */
    public static function setRedisCache($key,$value)
    {
        $value = \GuzzleHttp\json_encode($value);
        //Redis::command('HSET', ['name', 5, 10]);
        Cache::put($key, $value,10);
    }

    /**
     * 获取 redis 缓存数据
     * @param $key
     * @return mixed
     */
    public static function getRedisCache($key)
    {
        $value = Cache::get($key);
        return \GuzzleHttp\json_decode($value);
    }

    /**
     * 获取metric 及 tags
     * @param $uid
     * @return array
     */
    public function metricCache($uid)
    {
        $redis = MyRedisCache::initRedis();
        $metric_key = 'search:metrics:'.$uid.':uid='.$uid;
        $metrics = $redis->hKeys($metric_key);
        if(empty($metrics)) return [];
        sort($metrics);
        $result = [];
        $pipe = $redis->multi(\Redis::PIPELINE);
        foreach($metrics as $key => $metric){
            $tag_key = "search:mts:".$uid.":".$metric;
            $pipe->hKeys($tag_key);
        }
        $replies = $pipe->exec();
        //$custom_tags = MyApi::getCustomTagsByHost($uid);
        $custom_tags = MyRedisCache::getCustomTags($uid);
        foreach($metrics as $key => $metric){
            $temp = new \stdClass();
            $temp->metric = $metric;
            $tags = $replies[$key];
            $tags_temp = [];
            foreach($tags as $val){
                $item = explode(",",$val);
                foreach($item as $value){
                    $arr = explode("=",$value);
                    if($arr[0] != "uid"){
                        array_push($tags_temp,$arr[0].':'.$arr[1]);
                    }
                    if($arr[0] === 'host' && !empty($arr[1])){
                        $key = $arr[1];
                        if(isset($custom_tags->$key)) $tags_temp = array_merge($tags_temp,$custom_tags->$key);
                    }
                }
            }
            //$tags_temp = array_merge($tags_temp,$custom_tags);
            $tags_temp = array_unique($tags_temp);
            sort($tags_temp);
            $temp->tags = $tags_temp;

            array_push($result,$temp);
        }

        return $result;
    }

    public function tagsCache($uid)
    {
        $redis = MyRedisCache::initRedis();
        $metric_key = 'search:metrics:'.$uid.':uid='.$uid;
        $metrics = $redis->hKeys($metric_key);
        if(empty($metrics)) return [];
        sort($metrics);
        $pipe = $redis->multi(\Redis::PIPELINE);
        foreach($metrics as $key => $metric){
            $tag_key = "search:mts:".$uid.":".$metric;
            $pipe->hKeys($tag_key);
        }
        $replies = $pipe->exec();
        //$custom_tags = MyApi::getCustomTagsByHost($uid);
        $custom_tags = MyRedisCache::getCustomTags($uid);
        $result = [];
        foreach($metrics as $key => $metric){
            $tags = $replies[$key];
            $tags_temp = [];
            foreach($tags as $val){
                $item = explode(",",$val);
                foreach($item as $value){
                    $arr = explode("=",$value);
                    if($arr[0] != "uid"){
                        array_push($tags_temp,$arr[0].':'.$arr[1]);
                    }
                    if($arr[0] === 'host' && !empty($arr[1])){
                        $key = $arr[1];
                        if(isset($custom_tags->$key)) $tags_temp = array_merge($tags_temp,$custom_tags->$key);
                    }
                }
            }
            $result = array_merge($result,$tags_temp);
        }
        //$result = array_merge($result,$custom_tags);
        $result = array_unique($result);
        sort($result);

        return $result;
    }

    public static function setCustomTags($metrics_in, $host, $uid)
    {
        $url = config('myconfig.tag_put_url') . '/api/host/tag?uid='.$uid.'&host='.$host;
        Log::info('tag_put_url = ' . $url);
        $agent = MyApi::getHostTagAgent($metrics_in);
        //$agent = 'host-cf-1,host-cf-2';
        if(empty($agent)) return;
        $data = json_encode([['@agent' => $agent]]);
        $res = MyApi::httpPost($url, $data, true);
        Log::info('put-host-tag === ' . $res);
    }

    public static function getCustomTags($uid)
    {
        $redis = MyRedisCache::initRedis();
        $key = 'search:hts:'.$uid.":*";
        $tags = $redis->keys($key);
        if(empty($tags)) return [];
        $pipe = $redis->multi(\Redis::PIPELINE);
        $hosts = [];
        foreach($tags as $key => $t_key){
            $arr = explode(':',$t_key);
            $hosts[$key] = end($arr);
            $pipe->hGetAll($t_key);
        }
        $replies = $pipe->exec();
        //$res = [];
        $res = new \stdClass();
        foreach($replies as $key => $item){
            $host = $hosts[$key];
            $res->$host = [];
            foreach($item as $tagk => $tagv){
                if($tagk == '@agent'){
                    $tagv_arr = explode(',',$tagv);
                    foreach($tagv_arr as $v){
                        array_push($res->$host,$v);
                    }
                }else{
                    array_push($res->$host,$tagk.':'.$tagv);
                }

            }
        }
        return $res;
    }

    public static function getMetricByService($slug,$uid,$type)
    {
        $metric_key = 'search:mts:'.$uid.':'.$slug.'.*';
        $metrics = Redis::keys($metric_key);
        $ret = new \stdClass();
        $ret->code = 0;
        $ret->message = 'success';
        $ret->result = [];

        if($type == 'show'){
            $res = new \stdClass();
            $x =0;$y=0;$w=3;$h=2;
            $data = [];
            foreach($metrics as $key => $item){
                $chartid = $key + 1;
                array_push($data,["{$chartid}",$x,$y,$w,$h]);
                if($x >= 9){
                    $x = 0;
                    $y += $h;
                }else{
                    $x += $w;
                }
            }
            $res->name = $slug;
            $res->type = 'system';
            $res->owner = ['id'=>null,'email' => 'test@apmsys.com','name' => '路人甲'];
            $res->order  = json_encode($data);
            $ret->result = $res;
        }
        if($type == 'chart'){
            foreach($metrics as $key => $item){
                $chartid = $key + 1;
                $res = new \stdClass();
                $res->metrics = [];
                $res->type = "timeseries";
                $res->id = $chartid;
                $arr = explode(':',$item);
                $metric = $arr[3];
                $res->name = $metric;
                $m = new \stdClass();
                $m->metric = $metric;
                $m->tags = ["scope"];
                $m->rate = false;
                $m->aggregator = "avg";
                $m->by = null;
                $m->type = "line";
                array_push($res->metrics,$m);
                array_push($ret->result,$res);
            }
        }

        return $ret;
    }

}