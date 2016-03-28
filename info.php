<?php

require 'jwc.php';

@$stuid = $_GET['stuid'];

@$password = $_GET['password'];

@$info_type = $_GET['type'];

@$openid = $_GET['openid'];

if(!empty($_GET['year'])){

    $year = $_GET['year'];

}else{

    $year = 2014;

}

if(!empty($_GET['term'])){

    $term = $_GET['term'];

}else{

    $term = 2;

}

$jwc = new jwc($stuid,$password,$openid,$year,$term);

switch ($info_type) {

    case 'class':

        $jwc->getClass();

        break;

    case 'exam':

        $jwc->getExam();

        break;

    case 'score':

        //成绩

        $jwc->getHistoryScore();

        break;

    case 'validate':

        $jwc->validate();

        break;

    default:

        $jwc->throwError(500,'Incorrect access');

        break;
		
}

?>