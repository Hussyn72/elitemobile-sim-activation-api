<?php
date_default_timezone_set('Asia/calcutta');
global $DBConn,$logfile;
$logfile = '/tmp/Elitemobile_activations.log';

file_put_contents($logfile,date('y-m-d H:i:s').'****** START ******'.PHP_EOL,FILE_APPEND);

//Database connection
$DBConn = pg_connect("dbname='e2fax' user='domains'");
if($DBConn)
{
	echo "Database connection Established Sucessfully";
	file_put_contents($logfile,date('Y-m-d H:i:s').'Database connection Established Sucessfully'.PHP_EOL,FILE_APPEND);
}
else
{
	echo "Unable to Open database";
	file_put_contents($logfile,date('Y-m-d H:i:s').'Unable to Open database'.PHP_EOL,FILE_APPEND);
}
global $documents_uploaded;
$Query = "SELECT c.order_no, s.country, s.sim_phone_no, s.simno AS simno, c.from_date, c.status, c.is_kyc_required,coalesce (c.emailadd,su.emailadd) as emailadd FROM clienttrip c JOIN sim_stock s ON c.sim_phone_no = s.sim_phone_no join sim_user su on su.username = c.username WHERE s.vendor ilike 'elite%' AND c.from_date >= CURRENT_DATE - INTERVAL '1 day' AND c.from_date < CURRENT_DATE + INTERVAL '1 day' AND c.activated = false AND s.activation_reqd = true AND s.country NOT LIKE '%leb%' AND c.sim_phone_no NOT IN (SELECT sim_phone_no FROM tsim_simphoneno_exception_list) and c.status in ('Delivery pending','Delivered')";

file_put_contents($logfile,date('Y-m-d H:i:s')."Query for Getting Records for Activation ]->".$Query.PHP_EOL,FILE_APPEND);
$ExecuteQuery = pg_query($DBConn,$Query);
$rows = pg_fetch_all($ExecuteQuery);
if (!$rows) 
{ 	
	// Check if $rows is null or empty
    	file_put_contents($logfile, date('Y-m-d H:i:s') . "No records found." . PHP_EOL, FILE_APPEND);
} 
else 
{
	foreach($rows as $row)
	{
		file_put_contents($logfile,date('Y-m-d H:i:s').'START OF NEW Iteration *************]'.PHP_EOL,FILE_APPEND);
		$sim_phone_no = $row['sim_phone_no'];
		$sim_phone_no_proper = $row['sim_phone_no'];
		$sim_phone_no = preg_replace('/^44/', '0', $sim_phone_no);
		$country = $row['country'];
		$orderno = $row['order_no'];	
		$status = $row['status'];
		$iskycrequired = $row['is_kyc_required'];
		$emailadd = $row['emailadd'];

		if($emailadd == "japan001@tsim.in")
		{
			file_put_contents($logfile, date('Y-m-d H:i:s') ."Email is this skipping this iteration  ".$emailadd . PHP_EOL, FILE_APPEND);
			Continue;
		}


		file_put_contents($logfile,date('y-m-d h:i:s')."\nSimPhoneno->$sim_phone_no\nOrderNo->$orderno\nStatus->$status\nisKYCrequired->$iskycrequired\n".PHP_EOL,FILE_APPEND);

		if($iskycrequired=='t')
		{
			$Query = "select * from tsim_order_documents where order_no='$orderno'";
			file_put_contents($logfile,date('Y-m-d H:i:s')."Query for the present order number we are checking for documents requirement ]->".$Query.PHP_EOL,FILE_APPEND);
			$ExecuteQuery = pg_query($DBConn,$Query);
			$orderdata = pg_fetch_all($ExecuteQuery);
			$documents_uploaded = $orderdata[0]['documents_uploaded'];
			$documents_uploaded = isset($documents_uploaded) ? $documents_uploaded : 'f' ;
		}
		else
		{
			$documents_uploaded = 'f';
		}

		file_put_contents($logfile,date('Y-m-d H:i:s').'Sim Phone No ]-> '.$sim_phone_no.PHP_EOL,FILE_APPEND);
		file_put_contents($logfile,date('Y-m-d H:i:s').'Country ]-> '.$country.PHP_EOL,FILE_APPEND);
		file_put_contents($logfile,date('Y-m-d H:i:s').'Proper Sim Phone No ]-> '.$sim_phone_no_proper.PHP_EOL,FILE_APPEND);
		file_put_contents($logfile,date('Y-m-d H:i:s').'iskycrequired ]-> '.$iskycrequired.PHP_EOL,FILE_APPEND);
                file_put_contents($logfile,date('Y-m-d H:i:s').'documents_uploaded  ]-> '.$documents_uploaded.PHP_EOL,FILE_APPEND);
		

		file_put_contents($logfile,date('Y-m-d H:i:s').'Calling Confirm Contact Function '.PHP_EOL,FILE_APPEND);
		$isdone = ConfirmContact($sim_phone_no,$country,$sim_phone_no_proper);		
		if($isdone)
		{
			if (($iskycrequired == 't' && $documents_uploaded == 't') || ($iskycrequired == 'f' && $documents_uploaded == 'f' )) 
			{	
				$rechargeSuccessful = TopupRechargeRequest($sim_phone_no,$country,$sim_phone_no_proper);
				if($rechargeSuccessful)
				{
					OnSuccessUpdateCommentandSendMail($sim_phone_no,$country);
				}
				else
				{
					file_put_contents($logfile,date('Y-m-d H:i:s').'Error Occured updated in the comment Execution Stopped,'.PHP_EOL,FILE_APPEND);
				}
			}
			else
			{
				  file_put_contents($logfile,date('Y-m-d H:i:s').'Looks like Documents are not verified for this order ]-> '.$orderno.' Sim phone No -> '.$sim_phone_no.PHP_EOL,FILE_APPEND);
			}
		}
		else
			continue;

		unset($documents_uploaded,$iskycrequired,$sim_phone_no_proper,$country,$sim_phone_no,$orderno);
	}
}

 

//function OnSuccessUpdateCommentandSendMail($sim_phone_no,$country,$orderno,$status,$tripid,$emailadd,$clientname,$fromdate,$simno,$payment,$iskycrequired,$documents_uploaded)
function OnSuccessUpdateCommentandSendMail($sim_phone_no,$country)
{
	global $DBConn,$logfile;
	file_put_contents($logfile,date('Y-m-d H:i:s').'CAME IN UPDATE COMMENT AND SENDMAIL FUNCTION'.PHP_EOL,FILE_APPEND);
		
	$sim_phone_no = preg_replace('/^0/', '44', $sim_phone_no);
	file_put_contents($logfile,date('y-m-d H:i:s').'SIM PHONE NO -> '.$sim_phone_no.PHP_EOL,FILE_APPEND);

	$query = "SELECT c.order_no, c.tripid, COALESCE(c.emailadd, u.emailadd) AS emailadd, COALESCE(c.clientname, u.clientname) AS clientname, c.from_date, s.simno, CASE WHEN c.order_no IS NOT NULL AND c.order_no != '' THEN CASE WHEN c.status = 'Delivered' THEN 'Delivered' ELSE 'Not Delivered' END ELSE '' END AS status, CASE WHEN c.order_no IS NOT NULL AND c.order_no != '' AND pd.order_no IS NOT NULL THEN 't' ELSE 'f' END AS payment_discount_entry,c.is_kyc_required FROM clienttrip c JOIN sim_stock s ON s.sim_phone_no = c.sim_phone_no JOIN sim_user u ON u.username = c.username LEFT JOIN payment_discount pd ON pd.order_no = c.order_no WHERE c.sim_phone_no = '$sim_phone_no'";
        file_put_contents($logfile,date('y-m-d h:i:s').$query.PHP_EOL,FILE_APPEND);
        $Executequery = pg_query($DBConn,$query);

        if($Executequery)
        {

                $getdata = pg_fetch_all($Executequery);
                $tripid = $getdata[0]['tripid'];
                $emailadd = $getdata[0]['emailadd'];
                $clientname = $getdata[0]['clientname'];
                $fromdate = $getdata [0]['from_date'];
                $simno = $getdata[0]['simno'];
                $orderno = $getdata[0]['order_no'];
                $status = $getdata[0]['status'];
		$payment = $getdata[0]['payment_discount_entry'];
		$iskycrequired = $getdata[0]['is_kyc_required'];
 
                file_put_contents($logfile,date('y-m-d h:i:s')."  $tripid\n$emailadd\n$clientname\n$fromdate\n$simno\n$orderno\n$status\n$payment\n$iskycrequired".PHP_EOL,FILE_APPEND);
 

		$queryforpinno = "select pinno from sim_stock where active =true and pinno != '0000' and pinno is not null and pinno != '0' and pinno != '1234' and sim_phone_no in (select sim_phone_no from clienttrip where status not in ('Canceled', 'cardlost', 'Cancelled', 'Returned'))and pinno ~ '^[0-9]+$' and pinno::numeric > 0  and simno='$sim_phone_no'";
		file_put_contents($logfile,date('y-m-d h:i:s').$queryforpinno.PHP_EOL,FILE_APPEND);
		$Executequery = pg_query($DBConn,$queryforpinno);
		$getpinno = pg_fetch_all($Executequery);
		$pinno = $getpinno[0]['pinno'];
		file_put_contents($logfile,date('y-m-d h:i:s').$pinno.PHP_EOL,FILE_APPEND);
		$pinno = trim($pinno);	

		
		if($pinno == null)
		{
			// Check if the query returns any rows
        	        $UpdateQuery = "SELECT * FROM config_values WHERE name='{$country}_activation_msg' AND key='msg_on_activation'";
                	file_put_contents($logfile,date('y-m-d h:i:s').$UpdateQuery.PHP_EOL,FILE_APPEND);
	                $ExecuteUpdateQuery = pg_query($DBConn,$UpdateQuery);
	
        	        $rows = pg_fetch_all($ExecuteUpdateQuery);

	                if (count($rows) < 1)
        	        {
                	        // Determine sim type
                        	$simtype = (strpos($tripid, 'esim') !== false) ? 'Esim' : 'physical sim';
	
        	                if ($simtype === 'physical sim')
                	        {
                        	        $query = "SELECT * FROM config_values WHERE name='default_activation_msg' AND key='msg_on_activation'";
	                        }
        	                else
                	        {
                        	        $query = "SELECT * FROM config_values WHERE key='Esim_msg_on_activation'";
	                        }

	                }

        	        // Get the activation message
	                if (isset($rows[0]['value']))
        	        {
                		$msg_on_activation = $rows[0]['value'];
	                }

        	        // Default message if no message is found
	                if (empty($msg_on_activation))
                	{
        	                $msg_on_activation = "Hello, <FIRSTNAME> \n\n Your TSIM SIM card is activated: \n\n Activation Date: <DATE> \n Sim Serial No: <SIMNO1> \n Sim Phone number: <UPDATEDNO> \n\n Please insert your SIM card into your phone only on reaching your destination. You can reply to this email for assistance.\nWe are constantly striving to provide the ideal experience for our customers, and your input helps us to define that experience. Please tell us how we are doing by going to https://www.amazon.com/gp/css/order-history and clicking on \"Leave seller feedback\" to the right of your order details. \n\n Happy Journey \n\n Thank you,\n TSIM team";
	                }

       		       	$subject = "TSIM Sim Card Activated";

			// Replace placeholders in the message
        	        $dispmessage = string_replace_all($msg_on_activation, "<FIRSTNAME>", $clientname);
	                $dispmessage = string_replace_all($dispmessage, "<DATE>", $fromdate);
        	        $dispmessage = string_replace_all($dispmessage, "<SIMNO1>", $simno);
                	$dispmessage = string_replace_all($dispmessage, "<UPDATEDNO>", $sim_phone_no);

	                file_put_contents($logfile,date('y-m-d H:i:s'). $dispmessage . PHP_EOL, FILE_APPEND);
			
		}
		else
		{
			 // Check if the query returns any rows
                        $UpdateQuery = "SELECT * FROM config_values WHERE name='{$country}_activation_msg' AND key='msg_on_activation_with_pin'";
                        file_put_contents($logfile,date('y-m-d h:i:s').$UpdateQuery.PHP_EOL,FILE_APPEND);
                        $ExecuteUpdateQuery = pg_query($DBConn,$UpdateQuery);

                        $rows = pg_fetch_all($ExecuteUpdateQuery);

                        if (count($rows) < 1)
                        {
                                // Determine sim type
                                $simtype = (strpos($tripid, 'esim') !== false) ? 'Esim' : 'physical sim';

                                if ($simtype === 'physical sim')
                                {
                                        $query = "SELECT * FROM config_values WHERE name='default_activation_msg' AND key='msg_on_activation_with_pin'";
                                }
                                else
                                {
                                        $query = "SELECT * FROM config_values WHERE key='Esim_msg_on_activation_with_pin'";
                                }

                        }

                        // Get the activation message
                        if (isset($rows[0]['value']))
                        {
                                $msg_on_activation = $rows[0]['value'];
                        }

                        // Default message if no message is found
                        if (empty($msg_on_activation))
                        {
                                $msg_on_activation = "Hello, <FIRSTNAME> \n\n Your TSIM SIM card is activated: \n\n Activation Date: <DATE> \n Sim Serial No: <SIMNO1> \n Sim Phone number: <UPDATEDNO> \n\n Pin No: <PINNO>\n\n Please insert your SIM card into your phone only on reaching your destination. You can reply to this email for assistance.\nWe are constantly striving to provide the ideal experience for our customers, and your input helps us to define that experience. Please tell us how we are doing by going to https://www.amazon.com/gp/css/order-history and clicking on \"Leave seller feedback\" to the right of your order details. \n\n Happy Journey \n\n Thank you,\n TSIM team";
                        }

                        $subject = "TSIM Sim Card Activated";

                        // Replace placeholders in the message
                        $dispmessage = string_replace_all($msg_on_activation, "<FIRSTNAME>", $clientname);
                        $dispmessage = string_replace_all($dispmessage, "<DATE>", $fromdate);
                        $dispmessage = string_replace_all($dispmessage, "<SIMNO1>", $simno);
			$dispmessage = string_replace_all($dispmessage, "<UPDATEDNO>", $sim_phone_no);
			$dispmessage = string_replace_all($dispmessage, "<PINNO>", $pinno);

                        file_put_contents($logfile,date('y-m-d H:i:s'). $dispmessage . PHP_EOL, FILE_APPEND);
	
		}



        	//$to="husain@staff.ownmail.com";
	        $to=$emailadd;

		$UpdateQuery = "update clienttrip set activated=true,comment=COALESCE(comment, '') || ' Autoactivated via API ' || NOW()  where sim_phone_no='$sim_phone_no'";
	       	file_put_contents($logfile,date('y-m-d h:i:s').$UpdateQuery.PHP_EOL,FILE_APPEND);
		$ExecuteUpdateQuery = pg_query($DBConn,$UpdateQuery);
		if($ExecuteUpdateQuery)
		{
			file_put_contents($logfile,date('Y-m-d H:i:s').'Update Query Executed Successfully '.PHP_EOL,FILE_APPEND);
		        //Send Activated Mail to client.
	                $headers ="From: TSIM Team <services@tsim.mobi>\n";
			$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

                        $isSent = mail($to,$subject,$dispmessage,$headers,'-fservices@tsim.mobi');
	                if ($isSent)
        	        {
                	        echo json_encode(['status' => 'success', 'message' => 'Email sent successfully.']);
                        	file_put_contents($logfile, date("Y-m-d H:i:s") .'Email Sent Sucessfully to ->'.$to.  PHP_EOL, FILE_APPEND);
	
        	                $command = "echo -e \"From: Tsim Team <services@tsim.mobi>\r\nTo: $to\r\nSubject: $subject\r\nX-PutIn: Sent\r\nContent-type: text/html; charset=UTF-8\r\n\r\n$dispmessage\" | /usr/bin/sendmail -t -i services@tsim.mobi";
               	                $savetosent = shell_exec($command);
                      	        file_put_contents($logfile, date("Y-m-d H:i:s") .' Email saved to Sent folder of services@tsim.mobi' . PHP_EOL, FILE_APPEND);
	              	}
        	        else
                	{
                                echo json_encode(['status' => 'fail', 'message' => 'Email sending failed.']);
                               	file_put_contents($logfile, date("Y-m-d H:i:s") .'Email Sending Failed to ->'.$to.  PHP_EOL, FILE_APPEND);
	                }

		}
                else
                {
                	file_put_contents($logfile,date('Y-m-d H:i:s').'Update Query Execution Failed'.PHP_EOL,FILE_APPEND);
                }
        }
        else
        {
                file_put_contents($logfile,date('y-m-d H:i:s').'Query Execution Failed.' . PHP_EOL, FILE_APPEND);
	}
 
}


// String replacement function
function string_replace_all($subject, $search, $replace) {
    return str_replace($search, $replace, $subject);
}

function TopupRechargeRequest($sim_phone_no,$country,$sim_phone_no_proper)
{
	global $DBConn,$logfile;
	file_put_contents($logfile,date('Y-m-d H:i:s').'CAME IN TOP UP RECHARGE REQUEST'.PHP_EOL,FILE_APPEND);

	//Get Token
	$Token = generateToken();	

	//get topup value data.
	$Query = "select * from elite_activation_details where country ='$country'";
        file_put_contents($logfile,date('y-m-d H:I:s').']->'.$Query.PHP_EOL,FILE_APPEND);
        $ExecQuery = pg_query($DBConn,$Query);
        $gettoken = pg_fetch_all($ExecQuery);
	$BundleProductCode = trim($gettoken[0]['bundle_product_code']);
	$BundleValue = trim($gettoken[0]['bundle_value']);
	$Topupvalue = trim($gettoken[0]['country_mapped_topup_value']);

	file_put_contents($logfile,date('Y-m-d H:i:s').'values ->'.$sim_phone_no.' '.$country.' '.$BundleProductCode.' '.$BundleValue.' '.$Topupvalue.PHP_EOL,FILE_APPEND);

	$url = 'https://api.simply.elitemobile.com/RechargeTopup';

	$headers = [
		'Authorization:Bearer '.$Token,
		'Content-Type:application/json',
		'Accept:application/json'	
	];

	$data = json_encode([
		"ContactNumber"=> $sim_phone_no,
		"Networkname"=>"3",
		"TopUpValue"=> $Topupvalue,
		"BundleValue"=>$BundleValue,
		"BundleProductCode"=>$BundleProductCode,
		"UserPIN"=>"8546"
	]);


	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_POST,true);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
 	curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	
	$json_response = curl_exec($ch);

	//file_put_contents($logfile, curl_getinfo($ch, CURLINFO_HEADER_OUT) . PHP_EOL, FILE_APPEND);
	
	if(curl_errno($ch))
		file_put_contents($logfile,date('Y-m-d H:I:S').'error ->'.curl_error($ch).PHP_EOL,FILE_APPEND);
	else
		file_put_contents($logfile,date('y-m-d h:i:s').'Request Response -> '.$json_response.PHP_EOL,FILE_APPEND);

	curl_close($ch);

	
	// Decode the JSON response
	$response = json_decode($json_response, true);


	// Check if the Status is 1 and the Message indicates success
	if (isset($response['Status']) && $response['Status'] == 1 && isset($response['Message']) && $response['Message'] == "Top Up done successfully") 
	{
    		file_put_contents($logfile, date('Y-m-d H:i:s') . ' Success: ' . $response['Message'] . PHP_EOL, FILE_APPEND);
		return true;
	} 
	else 
	{
		file_put_contents($logfile, date('Y-m-d H:i:s') . ' Error: ' . ($response['Message'] ?? 'Unknown error') . PHP_EOL, FILE_APPEND);
		$errormsg = $response['Message'];

		$UpdateQuery = "update clienttrip set comment=COALESCE(comment, '') || '$errormsg' || NOW()  where sim_phone_no='$sim_phone_no_proper'";
        	file_put_contents($logfile,date('y-m-d h:i:s').$UpdateQuery.PHP_EOL,FILE_APPEND);
        	$ExecuteUpdateQuery = pg_query($DBConn,$UpdateQuery);
        	if($ExecuteUpdateQuery)
                	file_put_contents($logfile,date('Y-m-d H:i:s').'Update Query Executed Successfully '.PHP_EOL,FILE_APPEND);
        	else
			file_put_contents($logfile,date('Y-m-d H:i:s').'Update Query Execution Failed'.PHP_EOL,FILE_APPEND);

		return false;
	}
		
}


function ConfirmContact($sim_phone_no,$country,$sim_phone_no_proper)
{
	global $DBConn,$logfile;
	
	file_put_contents($logfile,date('Y-m-d H:i:s')."Inside Confirm Contact Function\n".PHP_EOL,FILE_APPEND);

	$Token = generateToken(); 
	if(!$Token)
	{
		file_put_contents($logfile,date('Y-m-d H:i:s')."Some Error OCcured Token is missing   --->  $Token\n".PHP_EOL,FILE_APPEND);
		return false;
	}

	file_put_contents($logfile,date('Y-m-d H:i:s')."GOT THE Token inside Confirm Contact function  --->  $Token\n".PHP_EOL,FILE_APPEND);


	$urlforconfirmcontact = 'https://api.simply.elitemobile.com/ConfirmContact';
	file_put_contents($logfile,date('Y-m-d H:i:s')." URL FOR Confirm Contact Request -> $urlforconfirmcontact\n".PHP_EOL,FILE_APPEND);

	$data =json_encode([
		"NetworkName"=>"3",
		"ContactNumber"=>$sim_phone_no
	]);

	$headers = [
		'Authorization:Bearer '.$Token,	
		'Content-Type:application/json',
		'Accept:application/json'
	];


	$ch = curl_init();
	
	curl_setopt($ch,CURLOPT_URL,$urlforconfirmcontact);
	curl_setopt($ch,CURLOPT_POST,true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);

	$json_response = curl_exec($ch);

	//file_put_contents($logfile, curl_getinfo($ch, CURLINFO_HEADER_OUT) . PHP_EOL, FILE_APPEND);

	if(curl_errno($ch))
	{
		file_put_contents($logfile,date('y-m-d H:i:s'). 'Error:' . curl_error($ch)  .PHP_EOL,FILE_APPEND);
	}
	else
	{
		file_put_contents($logfile,date('Y-m-d H:i:s')."Output Response -> $json_response\n".PHP_EOL,FILE_APPEND);
	}	
		
	curl_close($ch);

	// Decode the JSON response
	$response = json_decode($json_response, true);

	if (isset($response['Status']) && $response['Status'] == 1) 
	{
		// Success case, return true
    		return true;
	} 
	else 
	{
    		// Break out of the process
		file_put_contents($logfile, date('Y-m-d H:i:s') . ' Error: ' . ($response['Message'] ?? 'Unknown error') . PHP_EOL, FILE_APPEND);

                $errormsg = $response['Message'];

                $UpdateQuery = "update clienttrip set comment=COALESCE(comment, '') || '$errormsg' || NOW()  where sim_phone_no='$sim_phone_no_proper'";
                file_put_contents($logfile,date('y-m-d h:i:s')."Update Query -> $UpdateQuery\n".PHP_EOL,FILE_APPEND);
                $ExecuteUpdateQuery = pg_query($DBConn,$UpdateQuery);
                if($ExecuteUpdateQuery)
                        file_put_contents($logfile,date('Y-m-d H:i:s').'Update Query Executed Successfully '.PHP_EOL,FILE_APPEND);
                else
                        file_put_contents($logfile,date('Y-m-d H:i:s').'Update Query Execution Failed'.PHP_EOL,FILE_APPEND);

		return false;

	}


}





// Function to generate and return a new token
function generateToken() 
{
	global $logfile;

	// Call the API to get a new token
	$url = 'https://api.simply.elitemobile.com/GenerateToken';	
	$data = [
        	"grant_type"=>"password",
	        "username"=>"TSI005",
        	"password"=>"Taher@ts1m",
	        "client_id"=>"081ef15b-66b1-49c1-9ec2-03f82d2a008b",
        	"client_secret"=>"JfZruUaQRB4E2a4iF35yxSTHYCc9Rlvp20EnKAp076fShrJ4Bi"
	];

	$headers = [
	 	'Content-Type: application/x-www-form-urlencoded',
    		'Accept: application/json'
	];

	// Initialize cURL
	$ch = curl_init();

	// Set the options for the cURL request
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
	    'Content-Type: application/x-www-form-urlencoded',
	    'Accept: application/json',
	]);


	// Execute the request and capture the response
	$json_response = curl_exec($ch);

	// Check for errors
	if (curl_errno($ch))
	{
	        echo 'Error:' . curl_error($ch);
	        file_put_contents($logfile,date('y-m-d H:i:s'). 'Error:' . curl_error($ch)  .PHP_EOL,FILE_APPEND);
	}
	else
	{
	        // Print the response from the API
	       // echo $json_response;
	        file_put_contents($logfile,date('y-m-d H:i:s')."Output -> $json_response\n".PHP_EOL,FILE_APPEND);
	}

	// Close the cURL session
	curl_close($ch);

	$response = json_decode($json_response,true);
	$Token = $response["access_token"];
	file_put_contents($logfile,date('Y-m-d H:i:s')."Token value -> $Token\n".PHP_EOL,FILE_APPEND);	

	return $Token;
}




  	file_put_contents($logfile,date('Y-m-d H:i:s').'************ Script Ends ******************'.PHP_EOL,FILE_APPEND);


?>
