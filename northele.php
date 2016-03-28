<?php
date_default_timezone_set('Asia/Shanghai');
/*

 *	Created By Gump

 *	2015年3月14日 21:34:28

 */
class North_Ele{

	private $getTokenUrl = 'http://202.116.1.249:9518/j/getcry.php?id=';
    private $loginUrl = "http://10.136.2.5/jnuweb/WebService/JNUService.asmx/Login";
    private $balanceUrl = "http://10.136.2.5/jnuweb/WebService/JNUService.asmx/GetAccountBalance";
    private $dataUrl = 'http://10.136.2.5/jnuweb/WebService/JNUService.asmx/GetCustomerMetricalData';
    private $paymentRecordUrl = 'http://10.136.2.5/jnuweb/WebService/JNUService.asmx/GetPaymentRecord';
    private $userInfoUrl = "http://10.136.2.5/jnuweb/WebService/JNUService.asmx/GetUserInfo";
    private $billCostUrl = "http://10.136.2.5/jnuweb/WebService/JNUService.asmx/GetBillCost";

    private $userId;
    private $cookieJar;
    private $token;
    private $dateTime;
    private $room;

    public $balance = 0;  					//余额
    public $eleCost = 0;					//当前使用电量，这里数据更新比每月电量快
    public $eleRest = 0;  					//剩余补贴电量
    public $eleCharge = 0;					//电费
    public $eleCostPerMonth = array();		//每月用电量
    public $waterCostPerMonth = array();	//每月用水量
    public $eleCostPerDay = array();		//每天用电量
    public $paymentRecord = array();		//充值记录

    public function __construct($room){

    	$this->cookieJar = tempnam("./temp", "loginCookie");

    	$this->room = trim(strtolower($room));

    	$this->login();

    }

    private function login(){

    	$postData = array(

    		'user' => $this->room,

            'password' => "2ay/7lGoIrXLc9KeacM7sg==",

    	);

    	$postData = json_encode($postData);

    	$header = array(

    		'Content-Type: application/json',

    	);

    	$ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->loginUrl);

        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch,  CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookieJar );

        curl_setopt ($ch, CURLOPT_REFERER, 'http://10.136.2.5/jnuweb/' );

        $contents = curl_exec($ch);

        curl_close($ch);

        $loginJson = json_decode($contents);

        if(!$loginJson->d->Success){

        	$this->throwError(404,"宿舍号不正确");

        }else{

        	$this->userId = $loginJson->d->ResultList[0]->customerId;

        	$this->initToken();				//初始化获取Token值

        	$this->initBalance();			//余额查询


			$this->initBillCost();			//用于当前电量费用查询

			$this->initCostPerMonth();		//每月电费查询

			$this->initConstPerDay();		//每天电费查询

			$this->initPaymentRecord();		//充值记录

        }

    }

    private function initBillCost(){

    	$thisYear = date("Y");

    	$thisMonth = date("m");

    	$postData = array(

    		'endDate' => $thisYear.'-'.($thisMonth+1).'-01',

            'energyType' => 0,

            'startDate' => $thisYear.'-'.$thisMonth.'-01',

    	);

    	$postData = json_encode($postData);

    	$header = array(

    		'Content-Type: application/json',

    		'X-Requested-With:XMLHttpRequest',

    		'Token:'.$this->token,

    		'DateTime:'.$this->dateTime

    	);

    	$ch = curl_init($this->billCostUrl);

		curl_setopt ( $ch, CURLOPT_POST, 1 );

		curl_setopt ( $ch, CURLOPT_HEADER, 0);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );

		curl_setopt($ch,CURLOPT_COOKIEFILE, $this->cookieJar );

		curl_setopt ($ch, CURLOPT_REFERER, 'http://10.136.2.5/jnuweb/' );

		$contents = curl_exec($ch);

		curl_close($ch);

		$contentsJson = json_decode($contents);

		$eleCostValues = $contentsJson->d->ResultList[0]->energyCostDetails[0]->billItemValues[0];

		$this->eleCost = $eleCostValues->energyValue;

		$this->eleRest = 32-$this->eleCost;

		if($this->eleRest<0){

			$this->eleRest = 0;

		}

		$this->eleCharge = $eleCostValues->chargeValue;

    }

    private function initToken(){

    	$jsName = time().rand(0,100000);

    	$k['userID'] = (int)$this->userId;

		$k['tokenTime'] = $this->dateTime = date("Y-m-d H:i:s",time()+(8*60*60));

		$k = urlencode(json_encode($k));

		$contents = "
			var casper = require('casper').create();
			var url = \"http://xxx.com/cryjs.php?k=$k\";
			casper.start(url,function(){
				this.wait(100,function(){});
				console.log(this.getPageContent());
			});
			casper.run();
			";

		$fopen = fopen($jsName.".js", "w");

		fwrite($fopen, $contents);

		fclose($fopen);

		$command = "casperjs.exe ".$jsName.".js > ".$jsName.".txt";

		$shell = exec($command,$out);

		unlink($jsName.'.js');

		$txt = file_get_contents($jsName.".txt"); 

		preg_match('/body\>(.*)/', $txt,$temp);

		$this->token = str_replace("\r","%0A",$temp[1]);

		if(!unlink($jsName.'.txt'))
		{
			$command = "del ".$jsName.".txt";
			$shell = exec($command,$out);
		}

		if(empty($this->token)){

			$this->throwError(300,'No Token info');

		}

    }

    private function initBalance(){

    	$header = array(

    		'Content-Type: application/json',

    		'X-Requested-With:XMLHttpRequest',

    		'Content-Length: 0',

    		'Token:'.$this->token,

    		'DateTime:'.$this->dateTime

    	);

    	$ch = curl_init($this->balanceUrl);

		curl_setopt ( $ch, CURLOPT_POST, 1 );

		curl_setopt ( $ch, CURLOPT_HEADER, 0);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

		curl_setopt ( $ch, CURLOPT_POSTFIELDS, '' );

		curl_setopt($ch,CURLOPT_COOKIEFILE, $this->cookieJar );

		curl_setopt ($ch, CURLOPT_REFERER, 'http://10.136.2.5/jnuweb/' );

		$contents = curl_exec($ch);

		curl_close($ch);

		$contentsJson = json_decode($contents);

		if($contentsJson->d->success){

			$this->balance = $contentsJson->d->balance;

		}

    }

    private function throwError($code,$msg){

    	$data['code'] = $code;

		$data['msg'] = $msg;

		$data = json_encode($data);

		header('Content-type: application/json');

		echo $data;

		exit;

    }

    private function initCostPerMonth(){

    	$thisYear = date("Y");

    	$furtherYear = $thisYear + 1;

    	$postData = array(

    		'endDate' => $furtherYear.'-01-01',

            'energyType' => 0,

            'interval' => 3,

            'startDate' => $thisYear.'-01-01',

    	);

    	$postData = json_encode($postData);

    	$header = array(

    		'X-Requested-With:XMLHttpRequest',

            'Content-Type:application/json; charset=UTF-8',

            'Token:'.$this->token,

            'DateTime:'.$this->dateTime,

    	);

    	$ch = curl_init($this->dataUrl);

		curl_setopt ( $ch, CURLOPT_POST, 1 );

		curl_setopt ( $ch, CURLOPT_HEADER, 0);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );

		curl_setopt($ch,CURLOPT_COOKIEFILE, $this->cookieJar );

		curl_setopt ($ch, CURLOPT_REFERER, 'http://10.136.2.5/jnuweb/' );

		$contents = curl_exec($ch);

		curl_close($ch);

		$contentsJson = json_decode($contents);

		$eleMonthDatas = $contentsJson->d->ResultList[0]->datas;

		$eleMonthDatas = array_reverse($eleMonthDatas);

		$j = 1;

		foreach($eleMonthDatas as $k){

			$temp = array();

			$temp['month'] = date("Y-m",$k->recordTime/1000);

			$temp['cost'] = $k->dataValue;

			$this->eleCostPerMonth[] = $temp;

			$j++;

			if($j > 4){

				break;

			}

		}

    }

    private function initConstPerDay(){

    	$thisYear = date("Y");

    	$thisMonth = date("m");

    	$postData = array(

    		'endDate' => $thisYear.'-'.($thisMonth+1).'-01',

            'energyType' => 0,

            'interval' => 1,

            'startDate' => $thisYear.'-'.$thisMonth.'-01',

    	);

    	$postData = json_encode($postData);

    	$header = array(

    		'X-Requested-With:XMLHttpRequest',

            'Content-Type:application/json; charset=UTF-8',

            'Token:'.$this->token,

            'DateTime:'.$this->dateTime,

    	);

    	$ch = curl_init($this->dataUrl);

		curl_setopt ( $ch, CURLOPT_POST, 1 );

		curl_setopt ( $ch, CURLOPT_HEADER, 0);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );

		curl_setopt($ch,CURLOPT_COOKIEFILE, $this->cookieJar );

		curl_setopt ($ch, CURLOPT_REFERER, 'http://10.136.2.5/jnuweb/' );

		$contents = curl_exec($ch);

		curl_close($ch);

		$contentsJson = json_decode($contents);

		$eleDayDatas = $contentsJson->d->ResultList[0]->datas;

		$eleDayDatas = array_reverse($eleDayDatas);

		$j=1;

		foreach($eleDayDatas as $k){

			$temp = array();

			$temp['day'] = date("Y-m-d",$k->recordTime/1000);

			$temp['cost'] = $k->dataValue;

			$this->eleCostPerDay[] = $temp;

			$j++;

			if($j > 4){

				break;

			}

		}

    }

    private function initPaymentRecord(){

    	$postData = array(

    		'recordCount' => 100,

            'startIdx' => 0,

    	);

    	$postData = json_encode($postData);

    	$header = array(

    		'X-Requested-With:XMLHttpRequest',

            'Content-Type:application/json; charset=UTF-8',

            'Token:'.$this->token,

            'DateTime:'.$this->dateTime,

    	);

    	$ch = curl_init($this->paymentRecordUrl.'?_dc='.$this->Millisecond());

		curl_setopt ( $ch, CURLOPT_POST, 1 );

		curl_setopt ( $ch, CURLOPT_HEADER, 0);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );

		curl_setopt($ch,CURLOPT_COOKIEFILE, $this->cookieJar );

		curl_setopt ($ch, CURLOPT_REFERER, 'http://10.136.2.5/jnuweb/' );

		$contents = curl_exec($ch);

		curl_close($ch);

		$contentsJson = json_decode($contents);

		if(!$contentsJson->d->Success){

			$this->throwError(300,'No payment info');

		}

		$paymentResultList = $contentsJson->d->ResultList;

		$i = 0;
		foreach($paymentResultList as $k){

			$temp = array();

			if($k->paymentType == '充值'){
				if($i < 3)
					$i++;
				else
					break;

				$temp['time'] = date('Y-m-d H:i:s',$k->logTime/1000);

				$temp['pay'] = $k->dataValue;

				$this->paymentRecord[] = $temp;

			}

		}

    }

    public function getDatas(){

    	$data['data'] = array();

    	$data['code'] = 201;

    	$data['msg'] = 'success';

    	$temp['balance'] = $this->balance;

    	$temp['eleCost'] = $this->eleCost;

    	$temp['eleRest'] = $this->eleRest;

    	$temp['eleCharge'] = $this->eleCharge;

    	$temp['eleCostPerMonth'] = $this->eleCostPerMonth;

    	$temp['eleCostPerDay'] = $this->eleCostPerDay;

    	$temp['paymentRecord'] = $this->paymentRecord;

    	$data['data'] = $temp;

    	header('Content-type: application/json');

    	echo json_encode($data);

    }

    //返回毫秒数
    private function Millisecond(){

        list($s1, $s2) = explode(' ', microtime());

        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);

    }

}
if(!empty($_GET['room'])){

	$ele = new North_Ele($_GET['room']);

	$ele->getDatas();

}else{

	header('Content-type: application/json');

	$data['code'] = 500;

	$data['msg'] = "参数不正确";

	echo json_encode($data);

}

?>