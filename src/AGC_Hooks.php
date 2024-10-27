<?php

function AGC_change_cancelled_email_note_subject_line($subject, $order) {

	$subject = 'Order ' . $order->get_id() . ' has been cancelled due to non-payment';

	return $subject;

}

function AGC_change_cancelled_email_heading($heading, $order) {
	$heading = "Your order has been cancelled. Do not send any cryptocurrency to the payment address.";

	return $heading;
}

function AGC_change_partial_email_note_subject_line($subject, $order) {

	$subject = 'Partial payment received for Order ' . $order->get_id();

	return $subject;

}

function AGC_change_partial_email_heading($heading, $order) {
	$heading = 'Partial payment received for Order ' . $order->get_id();

	return $heading;
}

function AGC_update_database_when_admin_changes_order_status( $orderId, $postData ) {
	
	$oldOrderStatus = $postData->post_status;
	$newOrderStatus = $_POST['order_status'];
	
	$paymentAmount = 0.0;

	foreach ($_POST['meta'] as $customAttribute) {
	
		if ($customAttribute['key'] === 'crypto_amount') {
			$paymentAmount = $customAttribute['value'];
		}
	}	

	// this order was not made by us
	if ($paymentAmount == 0.0) {
		return;
	}

	$paymentRepo = new AGC_Payment_Repo();

	// If admin updates from needs-payment to has-payment, stop looking for matching transactions
	if ($oldOrderStatus === 'wc-pending' && $newOrderStatus === 'wc-processing') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'paid');
	}
	if ($oldOrderStatus === 'wc-pending' && $newOrderStatus === 'wc-completed') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'paid');
	}
	if ($oldOrderStatus === 'wc-on-hold' && $newOrderStatus === 'wc-processing') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'paid');
	}
	if ($oldOrderStatus === 'wc-on-hold' && $newOrderStatus === 'wc-completed') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'paid');
	}

	// If admin updates from needs-payment to cancelled, stop looking for matching transactions
	if ($oldOrderStatus === 'wc-pending' && $newOrderStatus === 'wc-cancelled') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'cancelled');
	}
	if ($oldOrderStatus === 'wc-pending' && $newOrderStatus === 'wc-failed') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'cancelled');
	}
	if ($oldOrderStatus === 'wc-on-hold' && $newOrderStatus === 'wc-cancelled') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'cancelled');
	}
	if ($oldOrderStatus === 'wc-on-hold' && $newOrderStatus === 'wc-failed') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'cancelled');
	}

	// If admin updates from cancelled to needs-payment, start looking for matching transactions
	if ($oldOrderStatus === 'wc-cancelled' && $newOrderStatus === 'wc-on-hold') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'unpaid');
		$paymentRepo->set_ordered_at($orderId, $paymentAmount, time());
	}
	if ($oldOrderStatus === 'wc-cancelled' && $newOrderStatus === 'wc-pending') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'unpaid');
		$paymentRepo->set_ordered_at($orderId, $paymentAmount, time());
	}
	if ($oldOrderStatus === 'wc-failed' && $newOrderStatus === 'wc-on-hold') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'unpaid');
		$paymentRepo->set_ordered_at($orderId, $paymentAmount, time());
	}
	if ($oldOrderStatus === 'wc-failed' && $newOrderStatus === 'wc-pending') {
		$paymentRepo->set_status($orderId, $paymentAmount, 'unpaid');
		$paymentRepo->set_ordered_at($orderId, $paymentAmount, time());
	}
}

?>