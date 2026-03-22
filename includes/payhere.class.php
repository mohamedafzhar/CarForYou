<?php
// PayHere API Integration
// Sign up at https://payhere.lk/merchant for credentials

class PayHerePayment {
    private $merchant_id;
    private $merchant_secret;
    private $mode;
    
    public function __construct() {
        $this->merchant_id = PAYHERE_MERCHANT_ID;
        $this->merchant_secret = PAYHERE_MERCHANT_SECRET;
        $this->mode = PAYHERE_MODE;
    }
    
    public function getBaseUrl() {
        return $this->mode === 'sandbox' 
            ? 'https://sandbox.payhere.lk' 
            : 'https://payhere.lk';
    }
    
    public function createPayment($order_id, $amount, $currency, $items, $customer, $notify_url, $return_url, $cancel_url) {
        $hash = strtoupper(md5(
            $this->merchant_id . 
            $order_id . 
            number_format($amount, 2, '.', '') . 
            $currency . 
            strtoupper(md5($this->merchant_secret))
        ));
        
        return [
            'merchant_id' => $this->merchant_id,
            'return_url' => $return_url,
            'cancel_url' => $cancel_url,
            'notify_url' => $notify_url,
            'order_id' => $order_id,
            'items' => $items,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'hash' => $hash,
            'first_name' => $customer['first_name'] ?? '',
            'last_name' => $customer['last_name'] ?? '',
            'email' => $customer['email'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'address' => $customer['address'] ?? '',
            'city' => $customer['city'] ?? '',
            'country' => $customer['country'] ?? 'Sri Lanka',
        ];
    }
    
    public function verifyPayment($post_data) {
        $local_hash = strtoupper(md5(
            $this->merchant_id . 
            ($post_data['order_id'] ?? '') . 
            ($post_data['amount'] ?? '') . 
            ($post_data['currency'] ?? 'LKR') . 
            strtoupper(md5($this->merchant_secret))
        ));
        
        $md5sig = strtoupper($post_data['md5sig'] ?? '');
        $status_code = intval($post_data['status_code'] ?? -1);
        
        return [
            'verified' => $local_hash === $md5sig,
            'success' => $status_code == 2,
            'status' => $this->getStatusMessage($status_code),
            'status_code' => $status_code,
            'payment_id' => $post_data['payment_id'] ?? ''
        ];
    }
    
    public function getStatusMessage($code) {
        $messages = [
            0 => 'Pending',
            1 => 'Canceled',
            2 => 'Completed Successfully',
            -1 => 'Failed'
        ];
        return $messages[$code] ?? 'Unknown';
    }
    
    public function getCheckoutUrl() {
        return $this->getBaseUrl() . '/checkout/pay';
    }
}
