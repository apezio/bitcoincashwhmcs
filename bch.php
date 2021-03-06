<?php

// /root/.bitcoin/bitcoin.conf
use Illuminate\Database\Capsule\Manager as Capsule;

/*
 *  Blockchain.info Gateway
 */

function bch_config() {

    return array(
        "FriendlyName" => array("Type" => "System", "Value" => "bch"),
        "ip_address" => array("FriendlyName" => "IP Address of Bitcoined", "Type" => "text", "Size" => "64", "Description" => "Enter bitcoined server IP address."),
        "rpc_username" => array("FriendlyName" => "RPC Username", "Type" => "text", "Size" => "64", "Description" => "Enter rpc username"),
        "rpc_password" => array("FriendlyName" => "RPC Password", "Type" => "text", "Size" => "64", "Description" => "Enter rpc password"),
        "rpc_port" => array("FriendlyName" => "RPC Port", "Type" => "text", "Size" => "64", "Description" => "Enter rpc port"),
        //"receiving_xpub" => array("FriendlyName" => "xPub Key", "Type" => "text", "Size" => "64", "Description" => "Enter your account xpub key"),
        "confirmations_required" => array("FriendlyName" => "Confirmations Required", "Type" => "text", "Size" => "4", "Description" => "Number of confirmations required before an invoice is marked 'Paid'."),
        "hidden_fee" => array("FriendlyName" => "Hidden Fee in (%)", "Type" => "text", "Size" => "40", "Description" => "Enter hidden fee in percentage (%)"),
    );
}

function bch_link($params) {

    require_once('bitcoind/easybitcoin.php');

    $bitcoinIp = $params['ip_address'];
    $rpcUsername = $params['rpc_username'];
    $rpcPassword = $params['rpc_password'];
    $rpcPort = $params['rpc_port'];
    $currency = $params['currency'];

    $bitcoin = new Bitcoin($rpcUsername, $rpcPassword, $bitcoinIp, $rpcPort);


    if (!Capsule::schema()->hasTable('bch_payments')) {
        Capsule::schema()->create('bch_payments', function ($table) {
            $table->integer('invoice_id');
            $table->string('amount');
			$table->string('amountusd');
            $table->string('address');
            $table->string('secret');
            $table->integer('confirmations');
            $table->enum('status', array('unpaid', 'confirming', 'paid'));
            $table->timestamps();
        }
        );
    }

    $hidden_fee = $params['hidden_fee'];
    $hidden_fee = round(($hidden_fee * $params['amount']) / 100, 2);
    $extra_amount = $params['amount'] + $hidden_fee;
    $ch = curl_init();
    
//bitcoin segwit
 //   curl_setopt($ch, CURLOPT_URL, "https://blockchain.info/tobtc?currency={$params['currency']}&value={$extra_amount}");
 //   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
 //   curl_setopt($ch, CURLOPT_CAINFO, "./modules/gateways/bitcoind/DigiCertCABundle.crt");
 //   $amount = curl_exec($ch);
 //   $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 //   curl_close($ch);
    
    
        //bitcoin cash
    curl_setopt($ch, CURLOPT_URL, "https://api.coinmarketcap.com/v1/ticker/bitcoin-cash/?convert={$params['currency']}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CAINFO, "./modules/gateways/bitcoind/DigiCertCABundle.crt");
    $response = curl_exec($ch);
    curl_close($ch);
    $amount = "0.00";
    $response = preg_replace("/[^A-Za-z0-9:\".]/", '', $response);
	$response = preg_replace("/\"\"/", ';', $response);
    $response = preg_replace("/\"/", '', $response);
	$response = explode(";",$response);
	    for ($i = 0; $i < count($response); $i++) {
	    	$amount = explode(":",$response[$i]);
			if ($amount[0] == "priceusd") {
				$bchprice = $amount[1];
				$amount2 = $extra_amount / $bchprice;
			}
		}
		
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);


/*
    $bitch = curl_init();
    curl_setopt($bitch, CURLOPT_URL, "https://bitpay.com/api/rates");
    curl_setopt($bitch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($bitch);
    curl_close($bitch);
    $data = json_decode($response);
    $bitamount = 0.00;
    for ($i = 0; $i < count($data); $i++) {
        if ($data[$i]->code == $currency) {
            $bitamount = $data[$i]->rate;
            break;
        }
    }
*/

//    $secamount = round($extra_amount / $bitamount, 8);
//    $amount2 = round(($amount2 + $secamount) / 2, 8);
    $amount2 = round($amount2, 8);
//    if ($status >= 300 || $amount2 < 0.0005) { // Blockchain.info will only relay a transaction if it's 0.0005 BTC or larger
//        return "Transaction amount too low. Please try another payment method or open a ticket with Billing.";
//    }

    $record = Capsule::table('bch_payments')->where('invoice_id', $params['invoiceid'])->get();

    if (!$record[0]->address) {

        $address = $bitcoin->getnewaddress();

        if (empty($address)) {
            return "An error has occurred, please contact Billing or choose a different payment method. (Error ID: 1)";
        }

        Capsule::table('bch_payments')->insert(
                ['invoice_id' => $params['invoiceid'], 'amount' => $amount2, 'amountusd' => $extra_amount, 'address' => $address, 'secret' => "dhfudhfuehWRRRT575757", 'confirmations' => $params['confirmations_required'], 'status' => 'unpaid']
        );
    } else {

        $amountstring = $record[0]->amount . "," . $amount2;
        Capsule::table('bch_payments')->where('invoice_id', $params['invoiceid'])->update(['amount' => $amountstring]);
    }
    return "<iframe src='{$params['systemurl']}/modules/gateways/bch.php?invoice={$params['invoiceid']}' style='border:none; height:400px'>Your browser does not support frames.</iframe>";
}
//if (isset($_GET['invoice'])) {
if ($_GET['invoice']) {

    require('./../../init.php');
    include("./../../includes/gatewayfunctions.php");
    $gateway = getGatewayVariables('bch');
    ?>
    <!doctype html>
    <html>
        <head>
            <title>Bitcoin Cash Invoice Payment</title>
            <?php
            $record = Capsule::table('bch_payments')->where('invoice_id', $_GET['invoice'])->get();
            if ($record[0]->status != 'paid') {
                ?>
                <META HTTP-EQUIV="REFRESH" CONTENT="2">
            <?php } ?>
            <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
            <script src="bitcoind/jquery.qrcode.js"></script>
            <script src="bitcoind/qrcode.js"></script>
            <script type="text/javascript">
                function checkStatus() {
                    $.get("bch.php?checkinvoice=<?php echo $_GET['invoice']; ?>", function (data) {
                        if (data == 'paid') {
                            parent.location.href = '<?php echo $gateway['systemurl']; ?>/viewinvoice.php?id=<?php echo $_GET['invoice']; ?>';
                                        } else if (data == 'unpaid') {
                                            setTimeout(checkStatus, 10000);
                                        } else {
                                            $("#content").html("Transaction confirming... " + data + "/<?php echo $gateway['confirmations_required']; ?> confirmationssss");
                                            setTimeout(checkStatus, 10000);
                                        }
                                    });
                                }
            </script>
            <style>
                body {
                    font-family:Tahoma;
                    font-size:12px;
                    text-align:center;
                }
                a:link, a:visited {
                    color:#08c;
                    text-decoration:none;
                }
                a:hover {
                    color:#005580;
                    text-decoration:underline
                }
            </style>
        </head>
        <body onload="checkStatus()">
            <p id="content"><center><div id="qrcodeCanvas"></div></center><br><br><?php echo bch_get_frame(); ?></p>
    </body>
    </html>
    <?php
//}
}

function bch_get_frame() {
    global $gateway;
    $record = Capsule::table('bch_payments')->where('invoice_id', $_GET['invoice'])->get();
    if (!$record[0]->address) {
        return "An error has occurred, please contact Billing or choose a different payment method. (Error ID: 3)";
    }

    // QR code string for BTC wallet apps
    $amountstring = $record[0]->amount;
    $amountarray = explode(",", $amountstring);
    $amount2 = $amountarray[count($amountarray) - 1];
    $qr_string = "bitcoin:{$record[0]->address}?amount={$amount2}&label=" . urlencode($gateway['companyname'] . ' Invoice #' . $record[0]->invoice_id);
    return "<script>jQuery('#qrcodeCanvas').qrcode({ text : '{$qr_string}'});</script>Please send <b>{$amount2} BCH</b> (\${$record[0]->amountusd} USD)  to address:<br /><br />{$record[0]->address}<br /><br /><img src='" . $gateway['systemurl'] . "/modules/gateways/bitcoind/loading.gif' />";
}

if ($_GET['checkinvoice']) {

    header('Content-type: text/plain');
    require('./../../init.php');

    $record = Capsule::table('bch_payments')->where('invoice_id', $_GET['checkinvoice'])->get();
    if ($record[0]->status == 'paid') {
        echo 'paid';
    } elseif ($record[0]->status == 'confirming') {
        echo $record[0]->confirmations;
    } else {
        echo 'unpaid';
    }
}
?>
