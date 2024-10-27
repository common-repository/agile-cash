<?php

// This is the business logic for the Auto-Payment Feature
// This feature:
//		Will 
class AGC_Payment {

	public static function check_all_addresses_for_matching_payment($transactionLifetime) {		
		$paymentRepo = new AGC_Payment_Repo();

		// get a unique list of unpaid "payments" to crypto addresses
		$addressesToCheck = $paymentRepo->get_distinct_unpaid_addresses();

		$cryptos = AGC_Cryptocurrencies::get();		

		foreach ($addressesToCheck as $record) {
			$address = $record['address'];

			$cryptoId = $record['cryptocurrency'];
			$crypto = $cryptos[$cryptoId];

			self::check_address_transactions_for_matching_payments($crypto, $address, $transactionLifetime);
		}
	}

	private static function check_address_transactions_for_matching_payments($crypto, $address, $transactionLifetime) {
		global $woocommerce;

		$gatewaySettings = get_option('woocommerce_agc_gateway_settings');
		$paymentRepo = new AGC_Payment_Repo();

		AGC_Util::log(__FILE__, __LINE__, '===========================================================================');
		AGC_Util::log(__FILE__, __LINE__, 'Starting payment verification for: ' . $crypto->get_id() . ' - ' . $address);
		
		try {
			$transactions = self::get_address_transactions($crypto->get_id(), $address);
		}
		catch (\Exception $e) {
			AGC_Util::log(__FILE__, __LINE__, 'Unable to get transactions for ' . $crypto->get_id());
			return;
		}
		
		AGC_Util::log(__FILE__, __LINE__, 'Transcations found for ' . $crypto->get_id() . ' - ' . $address . ': ' . print_r($transactions, true));		

		foreach ($transactions as $transaction) {

			if (! array_key_exists($crypto->get_id() . '_autopayment_required_confirmations', $gatewaySettings)) {
				// This could be handled more elegantly
				$requiredConfirmations = 1;
			}
			else {
				$requiredConfirmations = $gatewaySettings[$crypto->get_id() . '_autopayment_required_confirmations'];	
			}			

			$confirmations = $transaction->get_confirmations();
			$txTimeStamp = $transaction->get_time_stamp();

			$timeSinceTx = time() - $txTimeStamp;
			$consumedTransactions = get_option($crypto->get_id() . '_transactions_consumed_for_' . $address, array());			
			
			AGC_Util::log(__FILE__, __LINE__, '---confirmations: ' . $confirmations . ' Required: ' . $requiredConfirmations);
			AGC_Util::log(__FILE__, __LINE__, '---time since transaction: ' . $timeSinceTx . ' TX Lifetime: ' . $transactionLifetime);

			if ($confirmations < $requiredConfirmations) {
				continue;
			}

			if ($timeSinceTx > $transactionLifetime) {
				continue;
			}
			
			if ($consumedTransactions) {
				if (in_array($transaction->get_hash(), $consumedTransactions)) {
					AGC_Util::log(__FILE__, __LINE__, '---Collision occurred for old transaction, skipping...');
					continue;
				}
			}


			$paymentRecords = $paymentRepo->get_unpaid_for_address($crypto->get_id(), $address);

			$matchingPaymentRecords = array();

			foreach ($paymentRecords as $record) {
				$paymentAmount = $record['order_amount'];
				$paymentAmountSmallestUnit = $paymentAmount * (10**$crypto->get_round_precision());

				$transactionAmount = $transaction->get_amount();

				if (! array_key_exists($crypto->get_id() . '_autopayment_percent_to_process', $gatewaySettings)) {
					continue;
				}

				$autoPaymentPercent = $gatewaySettings[$crypto->get_id() . '_autopayment_percent_to_process'];
				$difference = abs($transactionAmount - $paymentAmountSmallestUnit);

				$percentDifference = $difference / $transactionAmount;
				
				AGC_Util::log(__FILE__, __LINE__, '---CryptoId, paymentAmount, paymentAmountSmallestUnit, transactionAmount, percentDifference:' . $crypto->get_id() . ',' . $paymentAmount .',' . $paymentAmountSmallestUnit . ',' .  $transactionAmount . ',' .  $percentDifference);


				if ($percentDifference <= (1 - $autoPaymentPercent)) {
					$matchingPaymentRecords[] = $record;
				}
			}

			// Transaction does not match any order payment
			if (count($matchingPaymentRecords) == 0) {
				// Do nothing
			}
			if (count($matchingPaymentRecords) > 1) {
				// We have a collision, send admin note to each order
				foreach ($matchingPaymentRecords as $matchingRecord) {
					$orderId = $matchingRecord['order_id'];
					$order = new WC_Order($orderId);
					$order->add_order_note('This order has a possible transaction but we cannot verify it due to other orders with similar payment totals. Please reconcile manually.');					
				}

				// Make sure we don't check the transaction again
				$consumedTransactions[] = $transaction->get_hash();

				update_option($crypto->get_id() . '_transactions_consumed_for_' . $address, $consumedTransactions);
			}
			if (count($matchingPaymentRecords) == 1) {
				// We have validated a transaction: update database to paid, update order to processing, add transaction to consumed transactions
				$orderId = $matchingPaymentRecords[0]['order_id'];
				$orderAmount = $matchingPaymentRecords[0]['order_amount'];
				$paymentRepo->set_status($orderId, $orderAmount, 'paid');
				$paymentRepo->set_hash($orderId, $orderAmount, $transaction->get_hash());

				$order = new WC_Order($orderId);
				$orderNote = sprintf(
						'Order payment of %s %s verified at %s.',
						AGC_Cryptocurrencies::get_price_string($crypto->get_id(), $transactionAmount / (10**$crypto->get_round_precision())),
						$crypto->get_id(),
						date('Y-m-d H:i:s', time()));
				
				$order->payment_complete();
				$order->add_order_note($orderNote);

				$consumedTransactions[] = $transaction->get_hash();

				update_option($crypto->get_id() . '_transactions_consumed_for_' . $address, $consumedTransactions);
			}
		}		
	}

	private static function get_address_transactions($cryptoId, $address) {
		if ($cryptoId === 'ETH') {
			$result = AGC_Blockchain::get_eth_address_transactions($address);
		}
		if ($cryptoId === 'BCH') {
			$result = AGC_Blockchain::get_bch_address_transactions($address);
		}
		if ($cryptoId === 'DOGE') {
			$result = AGC_Blockchain::get_doge_address_transactions($address);
		}
		if ($cryptoId === 'ZEC') {
			$result = AGC_Blockchain::get_zec_address_transactions($address);
		}
		if ($cryptoId === 'DASH') {
			$result = AGC_Blockchain::get_dash_address_transactions($address);
		}
		if ($cryptoId === 'XRP') {
			$result = AGC_Blockchain::get_xrp_address_transactions($address);
		}
		if ($cryptoId === 'ETC') {
			$result = AGC_Blockchain::get_etc_address_transactions($address);
		}
		if ($cryptoId === 'XLM') {
			$result = AGC_Blockchain::get_xlm_address_transactions($address);
		}
		if ($cryptoId === 'BSV') {
			$result = AGC_Blockchain::get_bsv_address_transactions($address);
		}
		if ($cryptoId === 'EOS') {
			$result = AGC_Blockchain::get_eos_address_transactions($address);
		}
		if ($cryptoId === 'TRX') {
			$result = AGC_Blockchain::get_trx_address_transactions($address);
		}
		if ($cryptoId === 'ONION') {
			$result = AGC_Blockchain::get_onion_address_transactions($address);
		}
		if ($cryptoId === 'BLK') {
			$result = AGC_Blockchain::get_blk_address_transactions($address);
		}
		if ($cryptoId === 'ADA') {
			$result = AGC_Blockchain::get_ada_address_transactions($address);	
		}
		if ($cryptoId === 'XTZ') {
			$result = AGC_Blockchain::get_xtz_address_transactions($address);	
		}
		if ($cryptoId === 'REP') {
			$result = AGC_Blockchain::get_erc20_address_transactions('REP', $address);	
		}
		if ($cryptoId === 'MLN') {
			$result = AGC_Blockchain::get_erc20_address_transactions('MLN', $address);	
		}
		if ($cryptoId === 'GNO') {
			$result = AGC_Blockchain::get_erc20_address_transactions('GNO', $address);	
		}
		if ($cryptoId === 'LTC') {
			$result = AGC_Blockchain::get_ltc_address_transactions($address);
		}
		if ($cryptoId === 'BTC') {
			$result = AGC_Blockchain::get_btc_address_transactions($address);	
		}
		if ($cryptoId === 'BAT') {
			$result = AGC_Blockchain::get_erc20_address_transactions('BAT', $address);	
		}
		if ($cryptoId === 'BNB') {
			$result = AGC_Blockchain::get_erc20_address_transactions('BNB', $address);	
		}
		if ($cryptoId === 'HOT') {
			$result = AGC_Blockchain::get_erc20_address_transactions('HOT', $address);	
		}
		if ($cryptoId === 'LINK') {
			$result = AGC_Blockchain::get_erc20_address_transactions('LINK', $address);	
		}
		if ($cryptoId === 'OMG') {
			$result = AGC_Blockchain::get_erc20_address_transactions('OMG', $address);	
		}
		if ($cryptoId === 'ZRX') {
			$result = AGC_Blockchain::get_erc20_address_transactions('ZRX', $address);	
		}
		if ($cryptoId === 'GUSD') {
			$result = AGC_Blockchain::get_erc20_address_transactions('GUSD', $address);	
		}
		if ($cryptoId === 'WAVES') {
			$result = AGC_Blockchain::get_waves_address_transactions($address);	
		}
		if ($cryptoId === 'DCR') {
			$result = AGC_Blockchain::get_dcr_address_transactions($address);	
		}
		if ($cryptoId === 'LSK') {
			$result = AGC_Blockchain::get_lsk_address_transactions($address);	
		}
		if ($cryptoId === 'XEM') {
			$result = AGC_Blockchain::get_xem_address_transactions($address);	
		}


		if ($result['result'] === 'error') {
			error_log($cryptoId . ' has error: ' . print_r($result, true));
			
			AGC_Util::log(__FILE__, __LINE__, 'BAD API CALL');
			throw new \Exception('Could not reach external service to do auto payment processing.');
		}		

		return $result['transactions'];
	}

	public static function cancel_expired_payments() {
		global $woocommerce;
		$gatewaySettings = get_option('woocommerce_agc_gateway_settings');

		$paymentRepo = new AGC_Payment_Repo();
		$unpaidPayments = $paymentRepo->get_unpaid();

		foreach ($unpaidPayments as $paymentRecord) {
			$orderTime = $paymentRecord['ordered_at'];
			$cryptoId = $paymentRecord['cryptocurrency'];

			if (! array_key_exists($cryptoId . '_autopayment_order_cancellation_time_hr', $gatewaySettings)) {
				continue;
			}

			$paymentCancellationTimeHr = $gatewaySettings[$cryptoId . '_autopayment_order_cancellation_time_hr'];
			$paymentCancellationTimeSec = $paymentCancellationTimeHr * 60 * 60;
			$timeSinceOrder = time() - $orderTime;
			AGC_Util::log(__FILE__, __LINE__, 'cryptoID: ' . $cryptoId . ' payment cancellation time sec: ' . $paymentCancellationTimeSec . ' time since order: ' . $timeSinceOrder);

			if ($timeSinceOrder > $paymentCancellationTimeSec) {
				$orderId = $paymentRecord['order_id'];
				$orderAmount = $paymentRecord['order_amount'];
				$address = $paymentRecord['address'];
				
				$paymentRepo->set_status($orderId, $orderAmount, 'cancelled');

				$order = new WC_Order($orderId);

				$orderNote = sprintf(
					'Your ' . $cryptoId . ' order was <strong>cancelled</strong> because you were unable to pay for %s minute(s). Please do not send any funds to the payment address.',
					round($paymentCancellationTimeSec/60, 1),
					$address);

				add_filter('woocommerce_email_subject_customer_note', 'AGC_change_cancelled_email_note_subject_line', 1, 2);
	    		add_filter('woocommerce_email_heading_customer_note', 'AGC_change_cancelled_email_heading', 1, 2);   
	    		
				$order->update_status('wc-cancelled');
				$order->add_order_note($orderNote, true);

				AGC_Util::log(__FILE__, __LINE__, 'Cancelled ' . $cryptoId . ' payment: ' . $orderId . ' which was using address: ' . $address . 'due to non-payment.');
			}
		}
	}
}

?>