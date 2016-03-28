<?php

/**
 *  Jwc class
 *  Created by Gump
 *  Update:2016-01-11 21:02:05
 */
require 'Snoopy.class.php';
require 'simple_html_dom.php';
require_once 'Classes/PHPExcel.php';

date_default_timezone_set("UTC");

class jwc{
    
    public $stuid;          //学号
    public $password;       //密码
    public $info_type;      //信息类型,考试表,成绩等等
    public $is_login;   
    public $openid;         //用户openid
    public $cookie_jar;
    public $login_cookie;   //登录后cookie
    public $year;           //学年
    public $term;           //学期
    public $baseUrl = "http://jwxt.jnu.edu.cn";     //教务处url
    private $model = array();
    public $login_cnt = 0;
    
    //进行参数设置,需要学号,密码,信息类型,openid,学年,学期
    function __construct($stuid,$password,$openid,$year,$term){

        $this->stuid = trim($stuid);

        $this->password = trim($password);

        $this->openid = trim($openid);
        
        $this->year = trim($year);
        
        $this->term = trim($term);

        if(empty($this->stuid) || empty($this->password) || empty($this->openid) || empty($this->year) || empty($this->term)){

            $this->throwError(403,'Incorrect parameter');

        }

        $this->cookie_jar = tempnam("temp", "cookie");

        //请自行建立字模，该处由于项目关系不提供，望见谅

        $this->model=array(
            ""
        );

        $this->is_login = false;

    }

    public function getValidateCodeResult(){ 

        $url = $this->baseUrl."/ValidateCode.aspx";

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, 0 );

        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt($ch,CURLOPT_COOKIEJAR, $this->cookie_jar );

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

        $contents = curl_exec($ch);

        curl_close($ch);

        $image = imagecreatefromstring($contents);

        $numpic = array();

        $count_head=0;

        $divide_head=array();

        $count_end=0;

        $divide_end=array();

        $temp="";

        $ymax=imagesy($image);

        $xmax=imagesx($image);

        for($x = 0; $x < $xmax; $x++){

            $ystring="";

            for($y = 0; $y < $ymax; $y++){

                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));

                if($rgb['red'] == 211 && $rgb['green'] == 211 && $rgb['blue'] == 211){

                	$numpic[$x][$y] = 0;

                	// echo '0';

                	$ystring .= '0';

                }else if($rgb['red'] == 105 && $rgb['green'] == 105 && $rgb['blue'] == 105){

                	if($y >= 1 && $y < $ymax - 1){

                		$rgb_right = imagecolorsforindex($image, imagecolorat($image, $x, $y + 1));

                		//如果右边不是灰色或者干扰线，应该是红色

                		if(!($rgb['red'] == 211 && $rgb['green'] == 211 && $rgb['blue'] == 211) && !($rgb['red'] == 105 && $rgb['green'] == 105 && $rgb['blue'] == 105)){

                			//判断左边颜色

                			if($numpic[$x][$y - 1] == 1){

                				$numpic[$x][$y] = 1;

                				$ystring .= '1';

                				// echo '0';

                			}else{

                				$numpic[$x][$y] = 0; 

                				$ystring .= '0';

                				// echo '0';

                			}

                		}else{

                			$numpic[$x][$y] = 0; 

                			$ystring .= '0';

                			// echo '0';

                		}

                	}else{

                		$numpic[$x][$y] = 0; 

                		$ystring .= '0';

                		// echo '0';

                	}

                }else{

                	$numpic[$x][$y] = 1;

                	// echo '1';

                	$ystring .= '1';
                    
                }

            }

            //字符粘连切分有待改进

            if ($ystring != "00000000000000000000" && $temp == "00000000000000000000") {

                $divide_head[$count_head]=$x;

                $count_head++;

            }

            if ($ystring == "00000000000000000000" && $temp != "00000000000000000000" && $x != 0) {

                $divide_end[$count_end]=$x;

                $count_end++;

            }

            $temp=$ystring;

        }

        $restring="";

        $result=array();

        if ($count_head == 4 && $count_end == 4) {

            for ($i = 0; $i < 4 ; $i++) {

                $xverify = "";

                for ($j = 0; $j < $divide_end[$i] - $divide_head[$i]; $j++) { 

                    $xverify.="0";

                }

                $temp_str = "";

                for($y = 0; $y < $ymax; $y++){

                    $xstring="";

                    for($x = $divide_head[$i]; $x < $divide_end[$i]; $x++){

                        $xstring.=$numpic[$x][$y];

                    }

                    if ($xstring != $xverify) {

                        $temp_str .= $xstring;

                    }

                }

                $result[] = $temp_str;

            }

        }

        return $result;

    }
    
    /*
    *	校验帐号密码信息
    */
    function validate(){

        $this->login();

        if($this->is_login){

            header('Content-type: application/json');

            $data['code'] = 201;

            $data['msg'] = 'validate success';

            echo json_encode($data);

            unlink($this->login_cookie);

            unlink($this->cookie_jar );

        }

    }
    
    /*
     *	登录
     */
    function login(){

        $Snoopy = new Snoopy;

        $loginUrl = $this->baseUrl."/login.aspx";

        $Snoopy->fetchform($loginUrl);

        $html = new simple_html_dom;

        $html->load($Snoopy->results);

        $input = $html->find('input');

        $hiddenInput = array(

            '__VIEWSTATE',

            '__EVENTVALIDATION',

            'btnLogin',

            '__VIEWSTATEGENERATOR'

        );

        $postData = array();

        foreach($input as $k){

            if(in_array($k->attr['name'],$hiddenInput)){

                $postData[$k->attr['name']] = $k->attr['value'];

            }

        }

        $postData['txtYHBS'] = $this->stuid;

        $postData['txtYHMM'] = $this->password;

        $this->login_cookie = tempnam("./temp", "login_cookie");

        $count = 0;

        $postUrl = $this->baseUrl."/Login.aspx";

        while(1 && $count<=50){

            $postData['txtFJM'] = $this->getCode();

            $count++;

            $ch = curl_init($postUrl);

            curl_setopt ( $ch, CURLOPT_POST, 1 );

	        curl_setopt ( $ch ,CURLOPT_HTTPHEADER, array('Expect:'));	

            curl_setopt ( $ch, CURLOPT_HEADER, 0);

            curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

            curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );

            curl_setopt ($ch, CURLOPT_REFERER, $this->baseUrl );

            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_jar );

            curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->login_cookie );

            $contents = curl_exec($ch);

            $info = curl_getinfo($ch);

            curl_close($ch);

            if($info['http_code'] == 302){

                $this->is_login = true;

                break;

            }

        }

        if($count>50){

            $this->throwError(404,'login failed');

            unlink($this->login_cookie);

            unlink($this->cookie_jar );

        }else{

            return $this->is_login;

        }

    }
    
    /*
     * 处理课程信息
     */
    function handleExamLesson($contents){
        
        $html = new simple_html_dom();
        
        $html->load($contents);

        $tr = $html->find('table[id=GVZHCJ] tr');

        $term = $this->term == 1 ? "上" : "下";

        $word = $this->year.'-'.(string)((int)  $this->year+1)."学年".  $term."学期";

        $flag = 0;	//标记是否到达位置

        $attr = array('id','name','type','score','credit','point');

        $data = array();

        $data['data'] = array();

        $final = "";

        foreach ($tr as $k) {

            $td = $k->find("td");

            $i = 0;

            $eachLesson = array();

            foreach ($td as $key) {

                $text = strip_tags(trim($key->plaintext));

                if(preg_match('/^本学期平均学分绩点/', $text)){

                    $flag = 0;

                    $term = $text;

                    break;

                }

                if(preg_match('/^最终的平均学分绩点/', $text)){

                    $final = $text;

                    break;

                    }


                if($flag && $text != '&nbsp;' && $text != ''){

                    $eachLesson[$attr[$i]] = $text;

                    $i++;

                }

                if($text == $word){

                    $flag = 1;

                }

            }
            
            $data['final'] = $final;
            
            $data['term'] = $term;

            if(!empty($eachLesson)){

                $data['data'][] = $eachLesson;

            }

        } 
        
        return $data;
        
    }
    
    
    /*
     *	获取最好成绩
     *  不再区分主修和双学位成绩
     *  Update 2015年7月17日 17:07:08
     */
    function getHistoryScore(){

        $this->login();

        $postData = array();

        $url = $this->baseUrl."/Secure/Cjgl/Cjgl_Cjcx_WdCj.aspx";

        $contents = $this->getRequset($url);

        $html = new simple_html_dom;

        $html->load($contents);

        $input = $html->find('form[id=Form1] input');

        $hiddenInput = array(
            '__EVENTARGUMENT',
            '__VIEWSTATE',
            '__VIEWSTATEGENERATOR',
            '__EVENTVALIDATION',
            'txtXH',
            'txtXM',
            'txtYXZY'
        );

        $postData['__EVENTTARGET'] = "lbtnQuery";

        foreach($input as $k){

            if(@in_array($k->attr['name'],$hiddenInput)){

                $postData[$k->attr['name']] = $k->attr['value'];

            }

        }
        
        $postData['rbtnListLBXX'] = '%D7%EE%BA%C3%B3%C9%BC%A8%C1%D0%B1%ED';

        $referUrl = $this->baseUrl."/Secure/Cjgl/Cjgl_Cjcx_WdCj.aspx";

        $type_options = $html->find('select[id=ddlXXLB] option');
        
        $data = array();
        
        $data['data'] = array();

        //主修成绩

        $type = $type_options[0]->attr['value'];

        $postData['ddlXXLB'] = $type;
        
        $contents = $this->postRequest($url, $postData, $referUrl);

        $contents = mb_convert_encoding($contents,'utf-8','UTF-8,GBK,GB2312,BIG5');
 
        $data['data']['main_score'] = $this->handleExamLesson($contents);
                
        //双学位

        $type = $type_options[2]->attr['value'];

        $postData['ddlXXLB'] = $type; 

        $contents = $this->postRequest($url, $postData, $referUrl);

        $contents = mb_convert_encoding($contents,'utf-8','UTF-8,GBK,GB2312,BIG5');
        
        $data['data']['double_score'] = $this->handleExamLesson($contents);
        
        if(empty($data['data']['main_score']['data']) && empty($data['data']['double_score']['data'])){
            
            $data['code'] = 300;

            $data['msg'] = "No info";

        }else{

            $data['code'] = 200;

            $data['msg'] = "success";

        }

        header('Content-type: application/json');

        echo json_encode($data);

        unlink($this->login_cookie);

        unlink($this->cookie_jar);

    }
    
    /*
     *	获取考试表
     */
    function getExam(){

        $this->login();

        $postData = array();

        $url = $this->baseUrl."/Secure/PaiKeXuanKe/wfrm_xk_StudentKcb.aspx";

        $contents = $this->getRequset($url);

        $html = new simple_html_dom();

        $html->load($contents);

        $input = $html->find('form[id=Form1] input');

        $hiddenInput = array(
            '__EVENTTARGET',
            '__EVENTARGUMENT',
            '__LASTFOCUS',
            '__VIEWSTATE',
            '__VIEWSTATEGENERATOR',
            '__EVENTVALIDATION',
            'dlstNdxq0',
            'btnNewExpKsb',
        );

        foreach($input as $k){

            if(@in_array($k->attr['name'],$hiddenInput)){

                $postData[$k->attr['name']] = $k->attr['value'];

            }

        }

        $postData['dlstXndZ0'] = $this->year.'-'.(string)((int)  $this->year+1);

        $postData['dlstXndZ'] = $postData['dlstXndZ0'];

        $xq_options = $html->find('select[id=dlstNdxq] option');

        switch ($this->term) {

            case '1':

                $xq = $xq_options[1]->attr['value'];

                break;

            case '2':

                $xq = $xq_options[2]->attr['value'];

                break;

            default:

                $this->throwError(406,'Incorrect term parameter');

                break;

        }

        $referUrl = $this->baseUrl."/Secure/PaiKeXuanKe/wfrm_xk_StudentKcb.aspx";

        $postData['dlstNdxq'] = $xq;

        $contents = $this->postRequest($url, $postData, $referUrl);

        $contents = mb_convert_encoding($contents,'utf-8','UTF-8,GBK,GB2312,BIG5');

        $url = $this->baseUrl."/Secure/TeachingPlan/wfrm_Prt_Report.aspx";

        $contents = $this->getRequset($url);

        $html->load($contents);

        $js = $html->find('script');

        //进入正则把url匹配出来

        $regex = "/Reserved(.*?)OpType=/";

        $temp = array();

        preg_match($regex, $js[23]->innertext,$temp);

        $url = $this->baseUrl."/".$temp[0].'Export&FileName=Rpt_Student_Ksb&ContentDisposition=AlwaysAttachment&Format=Excel';

        $contents = $this->getRequset($url);

        $xlsName = $this->openid.rand(0,100000);

        $xls = fopen("./xlstemp/".$xlsName.".xls", "w");

        fwrite($xls, $contents);

        fclose($xls);

        $filePath = "./xlstemp/".$xlsName.".xls";

        $PHPReader = new PHPExcel_Reader_Excel2007(); 

        if(!$PHPReader->canRead($filePath)){ 

            $PHPReader = new PHPExcel_Reader_Excel5(); 

            if(!$PHPReader->canRead($filePath)){ 

                $this->throwError(407,'excel not found');

            } 

        } 

        $excel_data = array();

        $weekArr = array("周一","周二","周三","周四","周五","周六","周日");

        $PHPExcel = $PHPReader->load($filePath);

        $currentSheet = $PHPExcel->getSheet(0);

        $allColumn = $currentSheet->getHighestColumn();

        $allRow = $currentSheet->getHighestRow();

        for($rowIndex=1;$rowIndex<=$allRow;$rowIndex++){

            for($colIndex='A';$colIndex<=$allColumn;$colIndex++){

                $addr = $colIndex.$rowIndex;

                $cell = $currentSheet->getCell($addr)->getValue();

                if($cell instanceof PHPExcel_RichText){    //富文本转换字符串

                    $cell = trim($cell->__toString());

                }

                if($colIndex == 'A' && !in_array($cell, $weekArr)){

                    break;

                }

                if($cell != '' && $colIndex != 'A'){

                    $this->handleExam($excel_data, $cell);

                }

            }

        }
        
        $html->clear();
        
        $postData = array();
        
        $url = $this->baseUrl."/Secure/PaiKeXuanKe/wfrm_xk_StudentKcb.aspx";

	$contents = $this->getRequset($url);
        
        $html->load($contents);

        $input = $html->find("form[id=Form1] input");

        $hiddenInput = array(
            '__EVENTARGUMENT',
            '__LASTFOCUS',
            '__VIEWSTATE',
            '__VIEWSTATEGENERATOR',
            '__EVENTVALIDATION',
        );

        $postData['dlstXndZ0'] = $this->year.'-'.(string)((int)$this->year+1);

        $postData['dlstXndZ'] = $this->year.'-'.(string)((int)$this->year+1);

        $xq0_ptions = $html->find('select[id=dlstNdxq0] option');

        switch ($this->term) {

            case '1':

                $xq0 = $xq0_ptions[0]->attr['value'];

                break;

            case '2':

                $xq0 = $xq0_ptions[1]->attr['value'];

                break;

            default:

                $this->throwError(406,'Incorrect term parameter');

                break;
        }

        $xq_options = $html->find('select[id=dlstNdxq] option');

        $xq = $xq_options[1]->attr['value'];

        $postData['dlstNdxq0'] = $xq0;

        $postData['dlstNdxq'] = $xq;

        foreach($input as $k){

            if(@in_array($k->attr['name'],$hiddenInput)){

                $postData[$k->attr['name']] = $k->attr['value'];

            }

        }

        $postData['__EVENTTARGET'] = "dlstNdxq0";

        $referUrl = "http://202.116.0.176/Secure/PaiKeXuanKe/wfrm_xk_StudentKcb.aspx";

        $contents = $this->postRequest($url,$postData,$referUrl);

        $contents = mb_convert_encoding($contents,'utf-8','UTF-8,GBK,GB2312,BIG5');

        $html->clear();

        $html->load($contents);

        $tr = $html->find("table[id=dgrdZwb] tbody tr");

        $seat_data = array();

        $tdName = array("code","name","location","date","time","column","row","stuid","stuname");

        for ($i = 1; $i < (count($tr)-1); $i++) {

                $td = $tr[$i]->find('td');

                $j = 0;

                $lesson = array();

                foreach ($td as $k) {

                        $lesson[$tdName[$j]] = trim($k->plaintext);

                        $j++;

                }

                $seat_data[] = $lesson;

        }
        
        $insert_flag = 0;   //标识两个数组都含有这门课程
        
        $data = array();
        
        $data['data'] = array();
        
        foreach($excel_data as $excel){
            
            foreach($seat_data as $seat){
                
                if($seat['name'] == $excel['name']){
                    
                    $insert_flag = 1;      //两个数组都含有该课程信息,进行合并
                    
                    $exam = array();
                    
                    $exam['name'] = $seat['name'];
                    
                    $exam['location'] = $seat['location'];
                    
                    $exam['time'] = $seat['date']." ".$seat['time'];
                    
                    $exam['seat'] = "第".$seat['column']."列 第".$seat['row']."行";
                    
                    $data['data'][] = $exam;
                    
                }
                
            }
            
            if($insert_flag == 0){
                
                $exam = array();
                
                $exam['name'] = $excel['name'];
                
                $exam['location'] = $excel['location'];
                
                $exam['time'] = $excel['time'];
                
                $exam['seat'] = "暂无";
                
                $data['data'][] = $exam;
                
            }
            
            $insert_flag = 0;
            
        }

        if(empty($data['data'])){

            $this->throwError(300,'No info');

        }

        $data['code'] = '200';

        $data['msg'] = 'success';

        $data = json_encode($data);

        unlink("./xlstemp/".$xlsName.".xls");

        header('Content-type: application/json');

        echo $data;

        unlink($this->login_cookie);

        unlink($this->cookie_jar );

    }
    
    /*
    *	处理Excel每个格子的数据
    */
    function handleExam(&$exam, $cell){

         $temp = array();

         $cell = str_replace(array("\r\n", "\r", "\n"), " ", $cell);

         preg_match_all('/考试时间：(.*?)\(\d{8}\)/', $cell, $temp);

         $temp = $temp[1];

         foreach($temp as $k){

            $temp1 = array();	

            $attr = array();	//用于存放每门课程的每一项属性

            preg_match('/(.*?)\s+(.*?)\s+(.*?)\s+课程：(.*)/', $k, $temp1);

            $attr['time'] = $temp1[1].' '.$temp1[2];

            $attr['location'] = $temp1[3];

            $attr['name'] = $temp1[4];

            $time = strtotime($temp1[1]);

            $flag = 0;	//用于标记元素是否已经插入序列

            if(count($exam) > 0){

                for($i=0; $i<count($exam); $i++){

                    $timeTemp = explode(" ", $exam[$i]['time']);

                    if($time < strtotime($timeTemp[0])){

                        $end = count($exam);

                        for($j = $end; $j > $i; $j--){

                            $exam[$j] = $exam[$j-1];

                        }

                        $exam[$i] = $attr;

                        $flag = 1;

                        break;

                    }

                }

                if(!$flag){

                    array_push($exam, $attr);

                }

            }else{

                //如果是第一个元素

                array_push($exam, $attr);

            }

        }

    }
    
    /*
     *	获取课表
     */
    function getClass(){

        $this->login();

        // echo "login success";

        $url = $this->baseUrl."/Secure/PaiKeXuanKe/wfrm_XK_MainCX.aspx";

        $contents = $this->getRequset($url);

        // echo $contents;

        $html = new simple_html_dom();

        $html->load($contents);

        $input = $html->find('form[id=Form1] input');

        $hiddenInput = array(
            '__EVENTTARGET',
            '__EVENTARGUMENT',
            '__VIEWSTATE',
            '__VIEWSTATEGENERATOR',
            '__EVENTVALIDATION',
            'bthSearch',
        );

        $postData = array();

        foreach($input as $k){

            if(@in_array($k->attr['name'],$hiddenInput)){

                $postData[$k->attr['name']] = $k->attr['value'];

            }

        }

        $xq_ptions = $html->find('select[id=dlstNdxq] option');

        switch ($this->term) {

            case '1':

                $xq = $xq_ptions[1]->attr['value'];

                break;

            case '2':

                $xq = $xq_ptions[2]->attr['value'];

                break;

            default:

                $this->throwError(406,'Incorrect term parameter');

                break;

        }

        $postData['dlstNdxq'] = $xq;

        $postData['dlstXndZ'] = $this->year.'-'.(string)((int)$this->year+1);

        $referUrl = $this->baseUrl."/Secure/PaiKeXuanKe/wfrm_XK_MainCX.aspx";

        $contents = $this->postRequest($url,$postData,$referUrl);

        $contents = mb_convert_encoding($contents,'utf-8','UTF-8,GBK,GB2312,BIG5');

        $html = new simple_html_dom();

        $html->load($contents);

        $tr = $html->find('table[id=dgrdXk] tr');

        $tr = array_splice($tr,1);

        $allClass = array('data' => array());

        $eachClass = array();

        $eachAttrName = array('classCode','id','name','score','type1','type','time','teacher','position','more','status','exam');

        foreach ($tr as $k) {

            $td = $k->find('td');

            $i = 0;

            $classAttr = array();

            foreach ($td as $k) {

                if ($eachAttrName[$i] == 'time') {

                    $classAttr['time'] = $this->getTimeArray(trim($k->plaintext),trim($td[8]->plaintext));

                } else {

                    if(trim($k->plaintext) == '&nbsp;'){

                        $classAttr[$eachAttrName[$i]] = '无';

                    }else{

                        $classAttr[$eachAttrName[$i]] = trim($k->plaintext);

                    }

                }

                $i++;

            }

            $eachClass[] = $classAttr;

        }

        if(empty($eachClass)){

            $this->throwError('300','No info');

        }

        $allClass['data'] = $eachClass;

        $allClass['msg'] = 'success';

        $allClass['code'] = '200';

        header('Content-type: application/json');

        $result = json_encode($allClass);

        echo $result;

        unlink($this->login_cookie);

        unlink($this->cookie_jar );

    }
    
    /*
     *	处理课表时间，返回time节点
     */
    function getTimeArray($time, $address){

        $regex_1 = '/①(.*)②(.*)/';

        $regex_2 = '/①(.*);(.*)/';

        $regex_3 = '/①(.*)/';

        $regex_4 = '/①(.*)②(.*)③(.*)/';

        $return_time = array();

        $temp = array();

        $address_temp = array();

        if(preg_match($regex_1, $time, $temp)){

            preg_match('/①(.*)②(.*)/', $address, $address_temp);

            for($i = 1; $i <= 2; $i++){

                if(strpos($temp[$i],";")){

                    //有的课程存在用分号隔开的情况

                    $timeSpiceArr = array();

                    $timeSpice = explode(";", $temp[$i]);

                    foreach ($timeSpice as $k) {

                            $return_time[] = $this->handleTime($k,$this->handleAddress($address_temp[$i]));

                    }

                }else{

                    $return_time[] = $this->handleTime($temp[$i],$this->handleAddress($address_temp[$i]));

                }

            }

        }else if(preg_match($regex_2, $time, $temp)){

            for($i = 1; $i <= 2; $i++){

                if(strpos($temp[$i],";")){

                    //有的课程存在用分号隔开的情况

                    $timeSpiceArr = array();

                    $timeSpice = explode(";", $temp[$i]);

                    foreach ($timeSpice as $k) {

                        $return_time[] = $this->handleTime($k,$this->handleAddress($address));

                    }

                }else{

                    $return_time[] = $this->handleTime($temp[$i],$this->handleAddress($address));

                }

            }

        }else if(preg_match($regex_3, $time, $temp)){

            if(strpos($temp[1], ";")){

                $timeSpiceArr = array();

                $timeSpice = explode(";", $temp[$i]);

                foreach ($timeSpice as $k) {

                    $return_time[] = $this->handleTime($k,$this->handleAddress($address));

                }

            }else{

                $return_time[] = $this->handleTime($temp[1],$this->handleAddress($address));

            }

        }else if(preg_match($regex_4, $time, $temp)){

            preg_match('/①(.*)②(.*)③(.*)/', $address, $address_temp);

            for($i = 1; $i <= 3; $i++){

                if(strpos($temp[$i],";")){

                    //有的课程存在用分号隔开的情况

                    $timeSpiceArr = array();

                    $timeSpice = explode(";", $temp[$i]);

                    foreach ($timeSpice as $k) {

                        $return_time[] = $this->handleTime($k,$this->handleAddress($address_temp[$i]));

                    }

                }else{

                    $return_time[] = $this->handleTime($temp[$i],$this->handleAddress($address_temp[$i]));

                }

            }

        }

        return $return_time;

    }
    
    /*
    *	处理课表课室地址，返回课室地址
    */
    function handleAddress($address){

        $address = str_replace("①", "", $address);

        $address = str_replace("②", "", $address);

        $address = str_replace("③", "", $address);

        $address = str_replace(",", "", $address);

        return $address;

    }
    
    /*
    *	处理课程的时间，单双周，开始时间、结束时间
    */
    function handleTime($time,$address){

        //需要判断单双周，周几，第几节，从第几周开始课程
        //先判断第几周开始，单双周，周几，第几节
        //1月3日更新，把周期改成20周而不是16周

        $temp = array();

        if(preg_match('/\((.*?)-(.*?)\)/', $time, $temp)){

            //存在信息表示从第几周开始

            $start = $temp[1];

            $end = $temp[2];

            if($end >= 16){

                $end = 20;

            }

        }else{

            $start = 1;

            $end = 20;

        }

        if(preg_match('/双周/', $time)){

            //如果是双周

            $week = "01010101010101010101";

            $time = str_replace("双周", "", $time);

        }else if(preg_match('/单周/', $time)){

            $week = "10101010101010101010";

            if(preg_match('/单周/', $time)){

                    $time = str_replace("单周", "", $time);

            }

        }else{	

            $week = "11111111111111111111";

        }

        //处理未开始的课程

        for($i = 0; $i < $start-1; $i++){

            $week[$i] = '0';

        }

        for($i = $end; $i<20; $i++){

            $week[$i] = '0';

        }

        $week2num = array(
            '一' => '1',
            '二' => '2',
            '三' => '3',
            '四' => '4',
            '五' => '5',
            '六' => '6',
            '日' => '7',
        );

        if(preg_match('/周(.*?) -/', $time, $temp)){

            $weekday = $week2num[$temp[1]];

        }

        if(preg_match('/- (.*)节/', $time, $temp)){

            $node = $temp[1];

        }

        $timeArray = array();

        $timeArray['node'] = $node;

        $timeArray['week'] = $week;

        $timeArray['weekday'] = $weekday;

        $timeArray['position'] = $address;

        return $timeArray;

    }
    
    /*
     * 	用于登录后的get请求
     */
    function getRequset($url){

        $ch = curl_init($url);
 
	//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

        curl_setopt($ch, CURLOPT_HEADER, 0 );

        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:','X-FORWARDED-FOR:172.16.18.47', 'CLIENT-IP:172.16.18.47'));

        curl_setopt($ch,CURLOPT_COOKIEFILE, $this->login_cookie );

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

        $contents = curl_exec($ch);

        curl_close($ch);

        return $contents;
        
    }
    
    /*
     *	用于登录后的post请求
     */
    function postRequest($url,$postData,$referUrl){

        $ch = curl_init($url);

        curl_setopt ( $ch, CURLOPT_POST, 1 );

	//curl_setopt ( $ch ,CURLOPT_HTTPHEADER, array('Expect:'));

        curl_setopt ( $ch, CURLOPT_HEADER, 0);

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:','X-FORWARDED-FOR:172.16.18.47', 'CLIENT-IP:172.16.18.47'));

        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );

        curl_setopt ($ch, CURLOPT_REFERER, $referUrl );

        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->login_cookie );

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36' );

        $contents = curl_exec($ch);

        return $contents;

    }
    
    /*
     *	处理png格式的验证码
     */
    function handlePng($imgfile){

        $handle = imagecreatefrompng($imgfile);

        $w = imagesx($handle);

        $h = imagesy($handle);

        $newImg = imagecreatetruecolor($w, $h);

        $black = imagecolorallocate($newImg, 0, 0, 0);

        $white = imagecolorallocate($newImg, 255, 255, 255);

        $size = getimagesize($imgfile);

        $data = array();

        for($x = 0; $x < $size[0]; ++$x){

                //先从上到下扫描

                for($y = 0; $y < $size[1]; ++$y){

                    $rgb = imagecolorat($handle,$x,$y);

                    $rgbArray = imagecolorsforindex($handle, $rgb);

                    if($rgbArray['red'] < 240){

                        imagesetpixel($newImg, $x, $y, $white);

                        //算法修正干扰线部分，只修复上下受到的干扰

                        if($y >= 1 && $y < $size[1] - 1){

                            //获取上下两个点的像素值

                            $above_rgb = imagecolorat($handle,$x,$y-1);

                            $bellow_rgb = imagecolorat($handle,$x,$y+1);

                            $above_rgbArray = imagecolorsforindex($handle, $above_rgb);

                            $bellow_rgbArray = imagecolorsforindex($handle, $bellow_rgb);

                            if($above_rgbArray['red'] > 240 && $bellow_rgbArray['red'] > 240){

                                //如果都是红色比较浓的，就修复这点

                                imagesetpixel($newImg, $x, $y, $black);

                            }

                        }

                    }else{

                        imagesetpixel($newImg, $x, $y, $black);

                    }

                }

        }

        //至此已经读完所有原图的像素点，现进行二值图的生成

        imagepng($newImg, $imgfile);

        imagedestroy($newImg);

        return true;

    }
    
    /*
     *  获取验证码
     */
    function getCode(){

        $result = array();

        $get_code_cnt = 0;

        while(count($result) != 4 && $get_code_cnt < 10){

            $result = $this->getValidateCodeResult();

            $get_code_cnt++;

            // dump($result,false);

        }

        $max_percent = 70;

        $temp_key = "";

        $string = "";

        for ($t = 0; $t < 4; $t++) { 

            foreach ($this->model as $key => $value) {
            
                similar_text($result[$t],$value,$percent);
        
                if ($percent > 70) {

                    if($percent > $max_percent){

                        $temp_key = $key;

                        $max_percent = $percent;

                    }
            
                }
        
            }   

            $string .= $temp_key; 

            $max_percent = 70;

            $temp_key = "";

        }

        return $string;

    }
    
    /*
     *	用于抛出异常
     */
    function throwError($code,$msg){

        $data['code'] = $code;

        $data['msg'] = $msg;

        $data = json_encode($data);

        header('Content-type: application/json');

        echo $data;

        exit;

    }
   
}

function dump($var,$exit=true){

    echo "<pre>";

    print_r($var);

    echo "</pre>";

    if($exit){

        exit;

    }
    
}

