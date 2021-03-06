<?php
/*** COPYRIGHT NOTICE *********************************************************
 *
 * Copyright 2009-2015 ProjeQtOr - Pascal BERNARD - support@projeqtor.org
 * Contributors : -
 *
 * This file is part of ProjeQtOr.
 * 
 * ProjeQtOr is free software: you can redistribute it and/or modify it under 
 * the terms of the GNU General Public License as published by the Free 
 * Software Foundation, either version 3 of the License, or (at your option) 
 * any later version.
 * 
 * ProjeQtOr is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for 
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * ProjeQtOr. If not, see <http://www.gnu.org/licenses/>.
 *
 * You can get complete code of ProjeQtOr, other resource, help and information
 * about contributors at http://www.projeqtor.org 
 *     
 *** DO NOT REMOVE THIS NOTICE ************************************************/

require_once "../tool/projeqtor.php";
scriptLog("adminFunctionalities.php");
if (array_key_exists('adminFunctionality', $_REQUEST)) {
	$adminFunctionality=$_REQUEST['adminFunctionality'];
}
if (! isset($adminFunctionality)) {
	echo "ERROR - functionality not defined";
	return;
}
if (securityGetAccessRightYesNo('menuAdmin','read')!='YES') {
  traceHack ( "admin functionality reached without access right" );
  exit ();
}

Sql::beginTransaction();
$nbDays=(array_key_exists('nbDays', $_REQUEST))?$_REQUEST['nbDays']:'';
if ($adminFunctionality=='sendAlert') {
	$result=sendAlert();
} else if ($adminFunctionality=='maintenance') {
	$result=maintenance();
} else if ($adminFunctionality=='updateReference') {
	$element=null;
	if (array_key_exists('element', $_REQUEST)) {
	  $element=$_REQUEST['element'];
	}
	if ($element=='*') {
		$element=null;
	}	else {
		if (intval($element)>0) {
			$elt=new Referencable($element);
			$element=$elt->name;
		}
	}
	$result=updateReference($element);
} else if ($adminFunctionality=='disconnectAll') {
  $audit=new Audit();
  $list=$audit->getSqlElementsFromCriteria(array("idle"=>"0"));
  $result="";
  foreach($list as $audit) {
  	if ($audit->sessionId!=session_id()) {
      $audit->requestDisconnection=1;     
  	} 
  	$res=$audit->save();
  	if ($result=="" or stripos($res,'id="lastOperationStatus" value="OK"')>0) {
  		$msgEnd=strpos($res,'<');
      $result=i18n('colRequestDisconnection').substr($res,$msgEnd);
  	}
  }
} else if ($adminFunctionality=='setApplicationStatusTo') { 
	$newStatus=$_REQUEST['newStatus'];
	$crit=array('idUser'=>null, 'idProject'=>null, 'parameterCode'=>'applicationStatus');
  $obj=SqlElement::getSingleSqlElementFromCriteria('Parameter', $crit);
  $obj->parameterValue=$newStatus;
  $result=$obj->save();
  $param=SqlElement::getSingleSqlElementFromCriteria('Parameter',array('idUser'=>null, 'idProject'=>null, 'parameterCode'=>'msgClosedApplication'));
  $param->parameterValue=$_REQUEST['msgClosedApplication'];
  $param->save();
  Parameter::clearGlobalParameters();
} else {
	$result="ERROR - functionality '$adminFunctionality' not defined";
}

// Message for result
displayLastOperationStatus($result);

function sendAlert(){
  $alertSendTo=(array_key_exists('alertSendTo', $_REQUEST))?$_REQUEST['alertSendTo']:'';
  $alertSendDate=(array_key_exists('alertSendDate', $_REQUEST))?$_REQUEST['alertSendDate']:'';
  $alertSendTime=(array_key_exists('alertSendTime', $_REQUEST))?$_REQUEST['alertSendTime']:'';
  $alertSendType=(array_key_exists('alertSendType', $_REQUEST))?$_REQUEST['alertSendType']:'';
  $alertSendTitle=(array_key_exists('alertSendTitle', $_REQUEST))?$_REQUEST['alertSendTitle']:'';
  $alertSendMessage=(array_key_exists('alertSendMessage', $_REQUEST))?$_REQUEST['alertSendMessage']:'';
  $ctrl="";
  if (! trim($alertSendTitle)) {
    $ctrl.= i18n("messageMandatory", array(i18n('colTitle'))).'<br/>';
  }
  if (! trim($alertSendMessage)) {
   $ctrl.=i18n("messageMandatory", array(i18n('colMessage'))).'<br/>';
  }
  if ($ctrl) {
  	$returnValue= $ctrl;
    $returnValue .= '<input type="hidden" id="lastOperation" value="control" />';
    $returnValue .= '<input type="hidden" id="lastOperationStatus" value="ERROR" />';
    return $returnValue;
  }
  $lstUser=array();
  if ($alertSendTo=='*') {
    $lstUser=SqlList::getList('User');
  } else if ($alertSendTo=='connect'){
    $audit=new Audit();
    $lst=$audit->getSqlElementsFromCriteria(array('idle'=>'0'));
    foreach($lst as $audit) {
      $lstUser[$audit->idUser]='';
    }
  } else {
 	  $lstUser[$alertSendTo]='';
  }
  //Sql::beginTransaction();
  foreach ($lstUser as $id=>$name) {
 	  $alert=new Alert();
 	  $alert->idUser=$id;
    $alert->alertType=$alertSendType;
    $alert->alertInitialDateTime=$alertSendDate . " " . substr($alertSendTime,1);
    $alert->alertDateTime=$alertSendDate . " " . substr($alertSendTime,1);
    $alert->title=ucfirst(i18n($alertSendType)) . ' - ' . $alertSendTitle;
    $alert->message=$alertSendMessage;  
    $alert->save();
  }
  $returnValue= i18n('sentAlertTo',array(count($lstUser)));
  $returnValue .= '<input type="hidden" id="lastOperation" value="insert" />';
  $returnValue .= '<input type="hidden" id="lastOperationStatus" value="OK" />';
  //Sql::commitTransaction();
  return $returnValue;
}

function maintenance() {
	$operation=(array_key_exists('operation', $_REQUEST))?$_REQUEST['operation']:'';
	$item=(array_key_exists('item', $_REQUEST))?$_REQUEST['item']:'';
	$nbDays=(array_key_exists('nbDays', $_REQUEST))?$_REQUEST['nbDays']:'0';
	$ctrl="";
  if (! trim($operation) or ($operation!='delete' and $operation!='close' and $operation!='read')) {
    $ctrl.='ERROR<br/>';
  }
  if (! trim($item) or ($item!='Alert' and $item!='Mail' and $item!='Audit' and $item!="Logfile")) {
    $ctrl.='ERROR<br/>';
  }
  if ( trim($nbDays)=='' or (intval($nbDays)=='0' and $nbDays!='0')) {
    $ctrl.= i18n("messageMandatory", array(i18n('days'))) .'<br/>';
  }
  //echo '|'.$operation.'|'.$item.'|'.intval($nbDays).'|';
  if ($ctrl) {
    $returnValue= $ctrl;
    $returnValue .= '<input type="hidden" id="lastOperation" value="control" />';
    $returnValue .= '<input type="hidden" id="lastOperationStatus" value="ERROR" />';
    return $returnValue;
  }
  $targetDate=addDaysToDate(date('Y-m-d'), (-1)*$nbDays ) . ' ' . date('H:i');
  $obj=new $item();
  $clauseWhere="1=0";
  if ($item=="Alert") {
  	$clauseWhere="alertInitialDateTime<'" . $targetDate . "'"; 
  } else if ($item=="Mail") {
  	$clauseWhere="mailDateTime<'" . $targetDate . "'";
  } else if ($item=="Audit") {
    $clauseWhere="disconnectionDateTime<'" . $targetDate . "'";
  } else if ($item=="Logfile") {
    $clauseWhere=$targetDate;
  }
  if ($operation=="close") {
  	if ($item=="Alert") {
  	  $obj->read($clauseWhere);
  	}
    return $obj->close($clauseWhere);
  } else if ($operation=="delete") {
    return $obj->purge($clauseWhere);
  } else if ($operation=="read" and $item=="Alert") {
    $clauseWhere="readFlag=0 and idUser=".getSessionUser()->id;
    return $obj->read($clauseWhere);
  }
}

function updateReference($element) {
	$arrayElements=array();
	if ($element) {
		$arrayElements[]=ucfirst($element);
	} else {
		$list=SqlList::getListNotTranslated('Referencable');
		foreach ($list as $ref) {		
			$arrayElements[]=$ref;
		}
	}
	foreach ($arrayElements as $elt) {
		$obj=new $elt();
		$request="update " . $obj->getDatabaseTableName() . " set reference=null";
		SqlDirectElement::execute($request); 
		$lst=$obj->getSqlElementsFromCriteria(null, false);
	  foreach ($lst as $object) {
		  $object->setReference(true);
		}
	}
	$element=(!$element)?'all':$element;
	$returnValue=i18n('updatedReference',array(i18n($element)));	
	$returnValue .= '<input type="hidden" id="lastOperation" value="update" />';
  $returnValue .= '<input type="hidden" id="lastOperationStatus" value="OK" />';
  return $returnValue;
}