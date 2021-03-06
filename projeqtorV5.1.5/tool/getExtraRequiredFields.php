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

/** ============================================================================
 * 
 */
require_once "../tool/projeqtor.php";

$objectClass=null;
if (isset($_REQUEST['className'])) {
  $objectClass=$_REQUEST['className'];
}
$objectId=null;
if (isset($_REQUEST['id'])) {
  $objectId=$_REQUEST['id'];
}
if ($objectClass===null or $objectId===null) {
  throwError('className and/or id not found in REQUEST');
}

$obj=new $objectClass($objectId);

$type=null;
$typeName='id'.$objectClass.'Type';
if (isset($_REQUEST[$typeName])) {
	$type=$_REQUEST[$typeName];
}
$status=null;
if (isset($_REQUEST['idStatus'])) {
  $status=$_REQUEST['idStatus'];
}
$planningMode=null;
$pmName=$objectClass.'PlanningElement_id'.$objectClass.'PlanningMode';
if (isset($_REQUEST[$pmName])) {
  $planningMode=$_REQUEST[$pmName];
}

$result=$obj->getExtraRequiredFields($type,$status,$planningMode);

$peName=$objectClass.'PlanningElement';
if (property_exists($obj, $peName)) {
  $pe=$obj->$peName;
  $resultPe=$pe->getExtraRequiredFields($type,$status,$planningMode);
  foreach ($resultPe as $key=>$val) {
    $result[$peName.'_'.$key]=$val;
  }
}


$arrayDefault=array('description'=>'optional', 'result'=>'optional', 'idResource'=>'optional',
   $peName.'_validatedStartDate'=>'optional', $peName.'_validatedEndDate'=>'optional', $peName.'_validatedDuration'=>'optional');
foreach ($arrayDefault as $key=>$val) {
  if (property_exists($obj,$key) and $obj->isAttributeSetToField($key,'required')) {
    $arrayDefault[$key]='required';
  }
}
$result=array_merge($arrayDefault,$result);

echo json_encode($result);