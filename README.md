# JNU API
---
### 1.教务处接口
##### 验证码识别部分请看Verifycode.md
	用法：http://xxx.com/info.php?stuid=123&password=abc&type=xxx&openid=kkk[&year=2014[&term=1]]
	
	stuid:学号
	password:教务处密码
	type:查询类型，包括class(课表),exam(考试表),score(成绩),validate(验证账号)
	openid:kkk
	year:选填，年份
	term:选填，学期
	
课表返回：
	
	{
    	"data": [
       		{
            	"classCode": "班号",
            	"id": "课程编号",
            	"name": "课程名",
            	"score": "学分",
            	"type1": "修学类型",
            	"type": "所属类别",
            	"time": [
                	{
                    	"node": "第几节",
                    	"week": "0表示当周没课，1表示当周有课",
                    	"weekday": "星期几",
                    	"position": "课室地点"
                	},
                	{
                    	"node": "第几节",
                    	"week": "0表示当周没课，1表示当周有课",
                    	"weekday": "星期几",
                    	"position": "课室地点"
                	}
            	],
            	"teacher": "教师名字",
            	"position": "该周上课地点",
            	"more": "备注",
            	"status": "课程阶段",
            	"exam": "考试时间"
        	}
    	],
    	"msg": "success",
    	"code": "200"
	}
	
考试表返回：

	{
    	"data": [
        	{
            	"name": "课程名",
            	"location": "考试地点",
            	"time": "考试时间",
            	"seat": "第i列 第j行"
        	}
    	],
    	"code": "200",
    	"msg": "success"
	}

成绩返回：

	{
    	"data": {
        	"main_score": {
            	"data": [
                	{
                    	"id": "课程编号",
                    	"name": "课程名",
                    	"type": "修学类型",
                    	"score": "分数",
                    	"credit": "学分",
                    	"point": "该成绩绩点"
                	},
                	{
                    	"id": "课程编号",
                    	"name": "课程名",
                    	"type": "修学类型",
                    	"score": "分数",
                    	"credit": "学分",
                    	"point": "该成绩绩点"
                	}
            	],
            	"final": "最终的平均学分绩点:x.xx",
            	"term": "本学期平均学分绩点:x.xx"
        	},
        	"double_score": {
            	"data": [],
            	"final": "最终的平均学分绩点:0",
            	"term": "下"
        	}
    	},
    	"code": 200,
    	"msg": "success"
	}
	
验证账号信息返回：
	
	{
    	"code": 201,
    	"msg": "validate success"
	}
	
账号密码错误返回：

	{"code":404,"msg":"login failed"}
	
找不到信息返回：

	{"code":"300","msg":"No info"}
	
参数不合法：

	{"code":403,"msg":"Incorrect parameter"}


### 2.南校区电费查询
#### 依赖casper运行，请先安装casper并配置好环境变量
##### Notice: 把cryjs.php、aes.js放在与northele.php同一个目录下
	南校区电费网站基本整个使用js来构建，加密使用了CryptoJS的AES方法
	casper作用为渲染cryjs.php并把经过CryptoJS加密过的密文输出到文件供notrhele.php读取
	
返回：

	{
    	"data": {
        	"balance": 余额,
        	"eleCost": 已用度数,
        	"eleRest": 剩余免费度数,
        	"eleCharge":电费,
        	"eleCostPerMonth": [
            	{
                	"month": "2016-03",
                	"cost": 48.63
            	}
        	],
        	"eleCostPerDay": [
            	{
                	"day": "2016-03-27",
                	"cost": 1.25
            	}
        	],
        	"paymentRecord": [
            	{
                	"time": "2016-03-22 17:57:12",
                	"pay": 20
            	}
        	]
    	},
    	"code": 201,
    	"msg": "success"
	}