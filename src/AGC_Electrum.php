<?php

// This is the business logic for the Electrum Feature
// This feature:
//     Produces a new wallet address every new order
//	   Automatically verifies order payment through blockchain API calls
//     Automatically cancels orders that have no received enough payment
//     Adds notes and emails if payment has been received but is under payment threshold
//     Never will use an address that has been used in the past

// Database table:
//		'mpk' string,
//		'mpk_index' string,
//		'address' unique string,    --Wallet address associate with order
//		'cryptocurrency' string,
//		'status' string,
//		'total_received' float,
//		'last_checked' datetime,
//		'assigned_at' datetime,		-- when the order was completed
// 		'order_id' int,
//		'order_amount' float

// Most of this class is run by a cron job, every minute or so we check api's for total wallet amounts

// We assume wallets have never been used before so if the 
// wallet amount is greater than the order value we assume it is paid for

// We maintain 10 clean addresses in the database at all times 
// so we don't have to always generate a new address on the fly (expensive math library and api call)
class AGC_Electrum {

	public static function buffer_ready_addresses($cryptoId, $mpk, $amount) {
		$electrumRepo = new AGC_Electrum_Repo($cryptoId, $mpk);
		$readyCount = $electrumRepo->count_ready($mpk);
		$neededAddresses = $amount - $readyCount;

		for ($i = 0; $i < $neededAddresses; $i++) {		
			try {
				self::force_new_address($cryptoId, $mpk);
			}
			catch ( \Exception $e ) {
				AGC_Util::log(__FILE__, __LINE__, $e->getMessage());
			}
		}
	}

	public static function check_all_pending_addresses_for_payment($cryptoId, $mpk, $requiredConfirmations, $percentToVerify) {
		global $woocommerce;
		$electrumRepo = new AGC_Electrum_Repo($cryptoId, $mpk);

		$pendingRecords = $electrumRepo->get_pending();

		foreach ($pendingRecords as $record) {

			try {
				$blockchainTotalReceived = self::get_total_received_for_address($cryptoId, $record['address'], $requiredConfirmations);
			}
			catch ( \Exception $e ) {
				// just go to next record if the endpoint is not responding			
				continue;
			}
			$recordTotalReceived = $record['total_received'];
			$newPaymentAmount = $blockchainTotalReceived - $recordTotalReceived;

			// if we received a new payment
			if ($newPaymentAmount > 0.0000001) {

				$address = $record['address'];
				$orderAmount = $record['order_amount'];
				AGC_Util::log(__FILE__, __LINE__, 'Address ' . $address . ' received a new payment of ' . AGC_Cryptocurrencies::get_price_string($cryptoId, $newPaymentAmount) . ' ' . $cryptoId);
				// set total in database because we received a payment
				$electrumRepo->set_total_received($address, $blockchainTotalReceived);
				
				$amountToVerify = ((float) $orderAmount) * $percentToVerify;
				$paymentAmountVerified = $blockchainTotalReceived >= $amountToVerify;
				// if new total is enough to process the order
				if ($paymentAmountVerified) {

					$order_id = $record['order_id'];
					$order = new WC_Order( $order_id );

					$orderNote = sprintf(
						'Order payment of %s %s verified at %s.',
						AGC_Cryptocurrencies::get_price_string($cryptoId, $blockchainTotalReceived),
						$cryptoId,
						date('Y-m-d H:i:s', time()));					
					
					//$order->update_status('wc-processing', $orderNote);					
					$order->payment_complete();
					$order->add_order_note($orderNote);
					
					$electrumRepo->set_status($address, 'complete');
				}
				// we received payment but it was not enough to meet store admin's processing requirement
				else {
					$order_id = $record['order_id'];
					$order = new WC_Order( $order_id );
					
					// handle multiple underpayments, just add a new note
					if ($record['status'] === 'underpaid') {
						$orderNote = sprintf(
							'New payment was received but is still under order total. Received payment of %s %s.<br>Remaining payment required: %s<br>Wallet Address: %s',
							AGC_Cryptocurrencies::get_price_string($cryptoId, $newPaymentAmount),
							$cryptoId,
							AGC_Cryptocurrencies::get_price_string($cryptoId, $amountToVerify - $blockchainTotalReceived),
							$address);

						add_filter('woocommerce_email_subject_customer_note', 'AGC_change_partial_email_note_subject_line', 1, 2);
	    				add_filter('woocommerce_email_heading_customer_note', 'AGC_change_partial_email_heading', 1, 2);

						$order->add_order_note($orderNote, true);
					}
					// handle first underpayment, update status to pending payment (since we use on-hold for orders with no payment yet)
					else {						
						$orderNote = sprintf(
							'Payment of %s %s received at %s. This is under the amount required to process this order.<br>Remaining payment required: %s<br>Wallet Address: %s',
							AGC_Cryptocurrencies::get_price_string($cryptoId, $blockchainTotalReceived),
							$cryptoId,
							date('m/d/Y g:i a', time() + (60 * 60 * get_option('gmt_offset'))),
							AGC_Cryptocurrencies::get_price_string($cryptoId, $amountToVerify - $blockchainTotalReceived),
							$address);
						
						add_filter('woocommerce_email_subject_customer_note', 'AGC_change_partial_email_note_subject_line', 1, 2);
	    				add_filter('woocommerce_email_heading_customer_note', 'AGC_change_partial_email_heading', 1, 2);
						
						$order->add_order_note($orderNote, true);
						$electrumRepo->set_status($address, 'underpaid');
					}
				}
			}
		}
	}

	private static function get_total_received_for_address($cryptoId, $address, $requiredConfirmations) {
		if ($cryptoId === 'BTC') {
			return self::get_total_received_for_bitcoin_address($address, $requiredConfirmations);
		}
		if ($cryptoId === 'LTC') {
			return self::get_total_received_for_litecoin_address($address, $requiredConfirmations);
		}
		if ($cryptoId === 'QTUM') {
			return self::get_total_received_for_qtum_address($address, $requiredConfirmations);
		}
	}

	private static function get_total_received_for_bitcoin_address($address, $requiredConfirmations) {
		
		$primaryResult = AGC_Blockchain::get_blockchaininfo_total_received_for_btc_address($address, $requiredConfirmations);
		
		if ($primaryResult['result'] === 'success') {
			return $primaryResult['total_received'];
		}

		$secondaryResult = AGC_Blockchain::get_blockexplorer_total_received_for_btc_address($address);

		if ($secondaryResult['result'] === 'success') {
			AGC_Util::log(__FILE__, __LINE__, 'Address ' . $address . ' falling back to blockexplorer info.');		
			return $secondaryResult['total_received'];
		}
		
		throw new \Exception("Unable to get btc address information from external sources.");
	}

	private static function get_total_received_for_litecoin_address($address, $requiredConfirmations) {
		$primaryResult = AGC_Blockchain::get_blockcypher_total_received_for_ltc_address($address, $requiredConfirmations);

		if ($primaryResult['result'] === 'success') {
			return $primaryResult['total_received'];
		}

		$secondaryResult = AGC_Blockchain::get_chainso_total_received_for_ltc_address($address);

		if ($secondaryResult['result'] === 'success') {
			return $secondaryResult['total_received'];
		}

		throw new \Exception("Unable to get ltc address information from external sources.");
	}

	private static function get_total_received_for_qtum_address($address, $requiredConfirmations) {
		$result = AGC_Blockchain::get_qtuminfo_total_received_for_qtum_address($address, $requiredConfirmations);

		if ($result['result'] === 'success') {
			return $result['total_received'];
		}		

		throw new \Exception("Unable to get ltc address information from external sources.");
	}

	public static function cancel_expired_addresses($cryptoId, $mpk, $orderCancellationTimeSec) {
		global $woocommerce;
		$electrumRepo = new AGC_Electrum_Repo($cryptoId, $mpk);

		$assignedRecords = $electrumRepo->get_assigned();

		foreach ($assignedRecords as $record) {
			
			$assignedAt = $record['assigned_at'];
			$totalReceived = $record['total_received'];
			$address = $record['address'];
			$orderId = $record['order_id'];

			$assignedFor = time() - $assignedAt;
			AGC_Util::log(__FILE__, __LINE__, 'address ' . $address . ' has been assigned for ' . $assignedFor . '... cancel time: ' . $orderCancellationTimeSec);
			if ($assignedFor > $orderCancellationTimeSec && $totalReceived == 0) {
				// since order was cancelled we can re-use the address, set status to ready
				$electrumRepo->set_status($address, 'ready');
				$electrumRepo->set_order_amount($address, 0.0);

				$order = new WC_Order($orderId);
				$orderNote = sprintf(
					'Your order was <strong>cancelled</strong> because you were unable to pay for %s minute(s). Please do not send any funds to the payment address.',
					round($orderCancellationTimeSec/60, 1),
					$address);

				add_filter('woocommerce_email_subject_customer_note', 'AGC_change_cancelled_email_note_subject_line', 1, 2);
	    		add_filter('woocommerce_email_heading_customer_note', 'AGC_change_cancelled_email_heading', 1, 2);
				
				$order->update_status('wc-cancelled');				
				$order->add_order_note($orderNote, true);
				
				AGC_Util::log(__FILE__, __LINE__, 'Cancelled order: ' . $orderId . ' which was using address: ' . $address . 'due to non-payment.');
			}
		}
	}

	private static function is_dirty_address($cryptoId, $address) {
		if ($cryptoId === 'BTC') {
			return self::is_dirty_btc_address($address);
		}
		if ($cryptoId === 'LTC') {
			return self::is_dirty_ltc_address($address);
		}
		if ($cryptoId == 'QTUM') {
			return self::is_dirty_qtum_address($address);	
		}
	}

	private static function is_dirty_btc_address($address) {
		$primaryResult = AGC_Blockchain::get_blockchaininfo_total_received_for_btc_address($address, 0);

		if ($primaryResult['result'] === 'success') {
			// if we get a non zero balance from first source then address is dirty
			if ($primaryResult['total_received'] >= 0.00000001) {				
				return true;
			}
			else {
				$secondaryResult = AGC_Blockchain::get_blockexplorer_total_received_for_btc_address($address);

				// we have a primary resource saying address is clean and backup source failed, so return clean
				if ($secondaryResult['result'] === 'error') {					
					return false;
				}
				// backup source gave us data
				else {
					// primary source is clean but if we see a balance we return dirty
					if ($secondaryResult['total_received'] >= 0.00000001) {
						return true;
					}
					// both sources return clean
					else {
						return false;
					}
				}
			}
		}
		else {
			$secondaryResult = AGC_Blockchain::get_blockexplorer_total_received_for_btc_address($address);

			if ($secondaryResult['result'] === 'success') {
				return $secondaryResult['total_received'] >= 0.00000001;
			}
		}
		
		throw new \Exception("Unable to get btc address to verify is address is unused.");
	}

	private static function is_dirty_ltc_address($address) {
		$primaryResult = AGC_Blockchain::get_chainso_total_received_for_ltc_address($address);

		error_log('primary result: ' . print_r($primaryResult, true));

		if ($primaryResult['result'] === 'success') {
			// if we get a non zero balance from first source then address is dirty
			if ($primaryResult['total_received'] >= 0.00000001) {				
				return true;
			}
			else {
				$secondaryResult = AGC_Blockchain::get_blockcypher_total_received_for_ltc_address($address, 0);

				// we have a primary resource saying address is clean and backup source failed, so return clean
				if ($secondaryResult['result'] === 'error') {					
					return false;
				}
				// backup source gave us data
				else {
					// primary source is clean but if we see a balance we return dirty
					if ($secondaryResult['total_received'] >= 0.00000001) {
						return true;
					}
					// both sources return clean
					else {
						return false;
					}
				}
			}
		}
		else {
			$secondaryResult = AGC_Blockchain::get_blockcypher_total_received_for_ltc_address($address, 0);

			if ($secondaryResult['result'] === 'success') {
				return $secondaryResult['total_received'] >= 0.00000001;
			}
		}
		
		throw new \Exception("Unable to get ltc address to verify is address is unused.");
	}

	private static function is_dirty_qtum_address($address) {
		$result = AGC_Blockchain::get_qtuminfo_total_received_for_qtum_address($address, 0);

		if ($result['result'] === 'success') {
			if ($result['total_received'] > 0.00000001) {
				return true;
			}
			else {
				return false;
			}
		}
		
		throw new \Exception("Unable to get ltc address to verify is address is unused.");
	}	
	
	public static function force_new_address($cryptoId, $mpk) {
		
		$electrumRepo = new AGC_Electrum_Repo($cryptoId, $mpk);

		$startIndex = $electrumRepo->get_next_index($mpk);	

		$address = self::create_electrum_address($cryptoId, $mpk, $startIndex);		

		try {
			while (self::is_dirty_address($cryptoId, $address)) {
				
				$electrumRepo->insert($address, $startIndex, 'dirty');
				$startIndex = $startIndex + 1;
				$address = self::create_electrum_address($cryptoId, $mpk, $startIndex);
				set_time_limit(30);
			}
		}
		catch ( \Exception $e ) {
			AGC_Util::log(__FILE__, __LINE__, 'Could not create new addresses: ' . $e->getMessage());
			throw new \Exception($e);
		}

		$electrumRepo->insert($address, $startIndex, 'ready');
	}

	public static function create_electrum_address($cryptoId, $mpk, $index) {
		// 1.5.4 - this is always version 2
		$version = self::get_mpk_version($mpk);

		if (self::is_valid_mpk($mpk)) {
			return ElectrumHelper::mpk_to_bc_address($cryptoId, $mpk, $index, $version);	
		}

		throw new \Exception('Invalid MPK, use Legacy version in Electrum to create your secure addresses via Master Public Key.');
	}

	public static function is_valid_mpk($mpk) {
		$mpkStart = substr($mpk, 0, 5);		
		$validMpk = strlen($mpk) > 55 && $mpkStart === 'xpub6';		
		return $validMpk;
	}	

	public static function get_mpk_version($mpk) {

		if ($mpk[0] === 'x') {
			return 2;
		}
		else {
			return 1;
		}
	}
}

?>