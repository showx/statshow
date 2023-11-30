# statshow
statshow一个简易的API监控服务器，statstics监控项目使用

# 使用

```PHP
require dirname(__FILE__).'/vendor/autoload.php';

$server = new \statshow\StatShow('0.0.0.0', 8081);
$server->start();
```

# 所需组件
1. redis
2. php
   （gd扩展，event扩展）
3. libevent

# todolist
highcharts图表的展示



# 其它
第三方比较好用的数据检索

## 腾讯云日志服务
https://cloud.tencent.com/document/product/614/47044

## 检索分析日志
https://cloud.tencent.com/document/product/614/56447
https://cloud.tencent.com/document/product/614/47044


### 查看访问量比较多的uri
* | select uri,histogram (cast (__TIMESTAMP__ as timestamp) ,interval 1 minute) as analytic_time, count (*) as log_count group by uri order by analytic_time limit 1000

* | select uri, count (*) as log_count group by uri order by log_count desc limit 1000

### 查看访问量比较多的ip
* | select remote_addr, count (*) as log_count group by remote_addr order by log_count desc limit 1000

* | select remote_addr,ip_to_city(remote_addr),uri, count (*) as log_count group by remote_addr,uri order by log_count desc

* | select uri,remote_addr,count (*) as log_count group by uri,remote_addr order by log_count desc

### 耗时uri
* | select uri,remote_addr,bytes_sent,request_time,upstream_response_time where upstream_response_time>0.5 order by upstream_response_time desc
* | select uri,remote_addr,request,http_referer,upstream_response_time where upstream_response_time>0.5 order by upstream_response_time desc