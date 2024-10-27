<?php

function AGC_do_cron_job() {
	global $wpdb;
	$options = get_option('woocommerce_agc_gateway_settings');

	$tableName = $wpdb->prefix . 'agc_payments';
	$query = "ALTER TABLE `$tableName` CHANGE `address` `address` CHAR(255)";

	$wpdb->query($query);	

	$electrumBufferAddressCount = 5;

	// Only look at transactions in the past two hours
	$autoPaymentTransactionLifetimeSec = 3 * 60 * 60;

	$startTime = time();
	AGC_Util::log(__FILE__, __LINE__, 'Starting Cron Job...');

	if (AGC_electrum_has_valid_settings($options, 'BTC')) {

		$mpkBtc = $options['BTC_electrum_mpk'];

		$electrumPercentToVerify = $options['BTC_electrum_percent_to_process'];	
		$electrumRequiredConfirmations = $options['BTC_electrum_required_confirmations'];
		$electrumOrderCancellationTimeHr = $options['BTC_electrum_order_cancellation_time_hr'];
		$electrumOrderCancellationTimeSec = round($electrumOrderCancellationTimeHr * 60 * 60, 0);

		AGC_Electrum::buffer_ready_addresses('BTC', $mpkBtc, $electrumBufferAddressCount);
		AGC_Electrum::check_all_pending_addresses_for_payment('BTC', $mpkBtc, $electrumRequiredConfirmations, $electrumPercentToVerify);
		AGC_Electrum::cancel_expired_addresses('BTC', $mpkBtc, $electrumOrderCancellationTimeSec);
	}

	if (AGC_electrum_has_valid_settings($options, 'LTC')) {

		$mpkLtc = $options['LTC_electrum_mpk'];

		$electrumPercentToVerify = $options['LTC_electrum_percent_to_process'];	
		$electrumRequiredConfirmations = $options['LTC_electrum_required_confirmations'];
		$electrumOrderCancellationTimeHr = $options['LTC_electrum_order_cancellation_time_hr'];
		$electrumOrderCancellationTimeSec = round($electrumOrderCancellationTimeHr * 60 * 60, 0);

		AGC_Electrum::buffer_ready_addresses('LTC', $mpkLtc, $electrumBufferAddressCount);
		AGC_Electrum::check_all_pending_addresses_for_payment('LTC', $mpkLtc, $electrumRequiredConfirmations, $electrumPercentToVerify);
		AGC_Electrum::cancel_expired_addresses('LTC', $mpkLtc, $electrumOrderCancellationTimeSec);
	}

	if (AGC_electrum_has_valid_settings($options, 'QTUM')) {

		$mpkQtum = $options['QTUM_electrum_mpk'];

		$electrumPercentToVerify = $options['QTUM_electrum_percent_to_process'];
		$electrumRequiredConfirmations = $options['QTUM_electrum_required_confirmations'];
		$electrumOrderCancellationTimeHr = $options['QTUM_electrum_order_cancellation_time_hr'];
		$electrumOrderCancellationTimeSec = round($electrumOrderCancellationTimeHr * 60 * 60, 0);

		AGC_Electrum::buffer_ready_addresses('QTUM', $mpkQtum, $electrumBufferAddressCount);
		AGC_Electrum::check_all_pending_addresses_for_payment('QTUM', $mpkQtum, $electrumRequiredConfirmations, $electrumPercentToVerify);
		AGC_Electrum::cancel_expired_addresses('QTUM', $mpkQtum, $electrumOrderCancellationTimeSec);
	}	
	
	AGC_Payment::check_all_addresses_for_matching_payment($autoPaymentTransactionLifetimeSec);	
	
	AGC_Payment::cancel_expired_payments();

	AGC_Util::log(__FILE__, __LINE__, 'total time for cron job: ' . AGC_get_time_passed($startTime));
}

function AGC_get_time_passed($startTime) {
	return time() - $startTime;
}

function AGC_electrum_has_valid_settings($settings, $cryptoId) {
	$mpkExists = array_key_exists($cryptoId . '_electrum_mpk', $settings);

	if (!$mpkExists) {
		return false;
	}

	$mpk = $settings[$cryptoId . '_electrum_mpk'];

	$mpkValid = AGC_Electrum::is_valid_mpk($mpk);
	$percentToVerifyExists = array_key_exists($cryptoId . '_electrum_percent_to_process', $settings);
	$requiredConfirmationsExists = array_key_exists($cryptoId . '_electrum_required_confirmations', $settings);
	$cancellationTimeExists = array_key_exists($cryptoId . '_electrum_order_cancellation_time_hr', $settings);

	return $mpkValid && $percentToVerifyExists && $requiredConfirmationsExists && $cancellationTimeExists;
}

?>