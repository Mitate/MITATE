<?php
if($_GET['username']!="" && $_GET['password']!="" && $_GET['deviceid']!="" && $_GET['time']!="" && $_GET['networktype']!="" && $_GET['latitude']!="" && $_GET['longitude']!="" && $_GET['batterypower']!="" && $_GET['signalstrength']!="" && $_GET['networkcarrier'] !="" && $_GET['devicemodelname'] != "")
{
	libxml_use_internal_errors(true); 
$xml = simplexml_load_file("config.xml");
$dbhostname = $xml->databaseConnection->serverAddress;
$dbusername = $xml->databaseConnection->user;
$dbpassword = $xml->databaseConnection->password;
$dbschemaname = $xml->databaseConnection->name;
$passwordEncryptionKey = $xml->database->passwordEncryptionKey;
    $dbconnection = mysql_connect($dbhostname, $dbusername, $dbpassword);
    if (!$dbconnection)	{
        die('Could not connect: ' . mysql_error());
    }
    mysql_select_db($dbschemaname, $dbconnection);
    $encrypted_password = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($passwordEncryptionKey), base64_decode($_GET[password]), MCRYPT_MODE_CBC, md5(md5($passwordEncryptionKey))));
	$loginresultset = mysql_query("SELECT count(*) as status FROM userinfo where username = '$_GET[username]' and password = '$encrypted_password' and status = 1");
    if ($loginresultset) {
        $loginresultrow = mysql_fetch_assoc($loginresultset);
        if ($loginresultrow['status'] == "1") {
            $pendingtestset = mysql_query("SELECT
			trf.sourceip as sourceip, trf.destinationip as destinationip, trf.bytes as bytes, trf.transferid as transferid,
			trs.transactionid as transactionid, trf.type as type, trf.packetdelay, trf.explicit, substring(replace(replace(content,'\t',''), '\n\r', '\n'),1) content, trf.noofpackets, trf.portnumber, trf.contenttype, trf.response, trf.delay as transferdelay
			from criteria cri, transfer trf, transactions trs, trans_criteria_link tcl, trans_transfer_link ttl, experiment exp, userdevice usrdvc
			where cri.criteriaid = tcl.criteriaid
			and trs.transactionid = tcl.transactionid
			and trs.count > 0
			and (find_in_set('$_GET[deviceid]', cast(REPLACE(cri.deviceid, ' ', '') as char)) > 0 or cri.deviceid = 'client')
			and trf.transferid = ttl.transferid
			and ttl.transactionid = trs.transactionid
			and trs.transactionid not in (select transactionid from transaction_fetched where deviceid = '$_GET[deviceid]')
			and '$_GET[time]' between SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 5), ';', -1) and SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 6), ';', -1)
			and SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 4), ';', -1) = '$_GET[networktype]'
			and SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 7), ';', -1) <= $_GET[batterypower]
			and SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 8), ';', -1) <= $_GET[signalstrength]
			and (SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 9), ';', -1) = '$_GET[networkcarrier]' or SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 9), ';', -1) = 'allNetworkCarriers')
			and (SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 10), ';', -1) = '$_GET[devicemodelname]' or SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 10), ';', -1) = 'allDeviceModelNames')
			and ((6378.137 * ACos((Cos(cast($_GET[latitude] as decimal)*(22/(180*7)))) * (Cos(cast(SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 1), ';', -1) as decimal)*(22/(180*7)))) * (Cos((cast(SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 2), ';', -1) as decimal) - cast($_GET[longitude] as decimal))*(22/(180*7)))) + Sin(cast($_GET[latitude] as decimal)*(22/(180*7))) * (Sin(cast(SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 1), ';', -1) as decimal)*(22/(180*7)))))) <= (cast(SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 3), ';', -1) as decimal)*(22/(180*7))) 
			or (6378.137 * ACos((Cos(cast($_GET[latitude] as decimal)*(22/(180*7)))) * (Cos(cast(SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 1), ';', -1) as decimal)*(22/(180*7)))) * (Cos((cast(SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 2), ';', -1) as decimal) - cast($_GET[longitude] as decimal))*(22/(180*7)))) + Sin(cast($_GET[latitude] as decimal)*(22/(180*7))) * (Sin(cast(SUBSTRING_INDEX(SUBSTRING_INDEX(cri.specification, ';', 1), ';', -1) as decimal)*(22/(180*7)))))) = 0 )
			and usrdvc.username = '$_GET[username]'
			and usrdvc.deviceid = $_GET[deviceid]
			and $_GET[batterypower] >= usrdvc.minbatterypower 
			and exp.experiment_id = trs.experiment_id
			and (exp.experiment_id in (
			select exp1.experiment_id from experiment exp1
			inner join experiment exp2 on exp1.experiment_id >= exp2.experiment_id
			where exp1.username != '$_GET[username]'
			group by exp1.experiment_id, exp1.wifidata
			having SUM(exp2.wifidata) < (
			select available_wifi_credits from usercredits where username = '$_GET[username]'
			) and SUM(exp2.wifidata) > 0
			union select exp1.experiment_id
			from experiment exp1
			inner join experiment exp2 on exp1.experiment_id >= exp2.experiment_id
			where exp1.username != '$_GET[username]'
			group by exp1.experiment_id, exp1.cellulardata
			having SUM(exp2.cellulardata) < (
			select available_cellular_credits from usercredits where username = '$_GET[username]'
			) and SUM(exp2.cellulardata) > 0
			)
			or exp.experiment_id in (
			select exp1.experiment_id from experiment exp1 where username = '$_GET[username]'
			))
			order by trs.transactionid, ttl.orderno");
            $output="";
			$i = 0 ;
			$transaction_id_array = "";
            while($pendingtestrow=mysql_fetch_assoc($pendingtestset))
     		{
				$output[]= $pendingtestrow;
				if($output[$i][contenttype] == "HEX") {
					if(strlen($output[$i][content]) % 2 != 0)
						$output[$i][content] = $output[$i][content] . "0";
				}
				$output_transaction_id = $output[$i][transactionid];
				$transaction_id_array[$i] = $output_transaction_id;
				$i = $i + 1;
			}
            if($output) {
				$temp_count = count(array_unique($transaction_id_array));
				print(json_encode($output));
				$temp_check_for_null_val = 0;
				$credits_to_contribute = 0;
				$final_transaction_id_array = array_unique($transaction_id_array);
				while($temp_count > 0 && $temp_check_for_null_val < count($transaction_id_array)) {
					$transaction_count_reduce = $final_transaction_id_array[$temp_check_for_null_val];
					if($transaction_count_reduce != '') {
						$sql_store_deviceid ="INSERT INTO transaction_fetched (transactionid, deviceid) VALUES($transaction_count_reduce, '$_GET[deviceid]')";
						if (!mysql_query($sql_store_deviceid, $dbconnection)) {die('Error: ' . mysql_error());}
						mysql_query("update transactions set count = count - 1 where transactionid = $transaction_count_reduce", $dbconnection);
						$temp_count = $temp_count - 1;
						$get_distinct_experiment_ids = mysql_query("select distinct exp.experiment_id, exp.cellulardata, exp.wifidata 
						from experiment exp, transactions tran 
						where tran.experiment_id = exp.experiment_id
						and tran.experiment_id = $transaction_count_reduce
						and exp.username != '$_GET[username]'");
						$get_distinct_experiment_id = mysql_fetch_assoc($get_distinct_experiment_ids);
						if($_GET['networktype'] == "wifi")
							$credits_to_contribute = $credits_to_contribute + $get_distinct_experiment_id[wifidata];
						elseif($_GET['networktype'] == "cellular")
							$credits_to_contribute = $credits_to_contribute + $get_distinct_experiment_id[cellulardata];
					}
					$temp_check_for_null_val = $temp_check_for_null_val + 1;
				}	
				if($_GET['networktype'] == "wifi") {
					mysql_query("update usercredits set contributed_wifi_credits = contributed_wifi_credits + $credits_to_contribute where username = '$_GET[username]'", $dbconnection);
					mysql_query("update usercredits set available_wifi_credits = available_wifi_credits - $credits_to_contribute where username = '$_GET[username]'", $dbconnection);
				}
				if($_GET['networktype'] == "cellular") {
					mysql_query("update usercredits set contributed_cellular_credits = contributed_cellular_credits + $credits_to_contribute where username = '$_GET[username]'", $dbconnection);
					mysql_query("update usercredits set available_cellular_credits = available_cellular_credits - $credits_to_contribute where username = '$_GET[username]'", $dbconnection);
				}
			}
            else {
				$pendingtestset = mysql_query("SELECT 'NoPendingTransactions' as content");
				$pendingtestrow=mysql_fetch_assoc($pendingtestset);
				$output[]=$pendingtestrow;
				print(json_encode($output));
			}
			if($_GET['networktype'] == "wifi")
				mysql_query("update userdevice set timespingedwifi = timespingedwifi + 1 where deviceid = $_GET[deviceid]", $dbconnection);
			if($_GET['networktype'] == "cellular")
				mysql_query("update userdevice set timespingedcellular = timespingedcellular + 1 where deviceid = $_GET[deviceid]", $dbconnection);
		}				
        else if ($loginresultrow['status'] == "0") {
            $pendingtestset = mysql_query("SELECT 'InvalidLogin' as content");
            $pendingtestrow=mysql_fetch_assoc($pendingtestset);
            $output[]=$pendingtestrow;
			print(json_encode($output));			
        }				
    }
    mysql_close();
}
else
	echo "Missing arguments";
?>