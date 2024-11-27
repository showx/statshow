<!DOCTYPE html>
<html>
<head>
    <title>HTTP请求监控统计</title>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .chart-container {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 30px; }
        .filters {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        select, button {
            padding: 8px 15px;
            margin-right: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>HTTP请求监控统计</h1>
        
        <div class="filters">
            <select id="timeRange">
                <option value="300">最近5分钟</option>
                <option value="900">最近15分钟</option>
                <option value="1800">最近30分钟</option>
                <option value="3600" selected>最近1小时</option>
                <option value="86400">最近24小时</option>
            </select>
            <button onclick="refreshStats()">刷新数据</button>
        </div>

        <div class="chart-container">
            <div id="uriStats" style="height:400px;"></div>
        </div>
        
        <div class="chart-container">
            <div id="ipStats" style="height:400px;"></div>
        </div>
        
        <div class="chart-container">
            <div id="slowRequests" style="height:400px;"></div>
        </div>
    </div>

    <script>
        const charts = {
            uri: echarts.init(document.getElementById('uriStats')),
            ip: echarts.init(document.getElementById('ipStats')),
            slow: echarts.init(document.getElementById('slowRequests'))
        };

        function refreshStats() {
            const timeRange = document.getElementById('timeRange').value;
            const baseUrl = '{{webpath}}/api';
            
            // 获取URI统计
            fetch(`${baseUrl}/uri?timeRange=${timeRange}`)
                .then(res => res.json())
                .then(data => updateUriChart(data));
                
            // 获取IP统计
            fetch(`${baseUrl}/ip?timeRange=${timeRange}`)
                .then(res => res.json())
                .then(data => updateIpChart(data));
                
            // 获取慢请求统计
            fetch(`${baseUrl}/slow?timeRange=${timeRange}`)
                .then(res => res.json())
                .then(data => updateSlowChart(data));
        }

        // 初始加载
        refreshStats();
        
        // 自动刷新
        setInterval(refreshStats, 60000);
        
        // 窗口大小改变时重绘图表
        window.addEventListener('resize', () => {
            Object.values(charts).forEach(chart => chart.resize());
        });
    </script>
</body>
</html> 