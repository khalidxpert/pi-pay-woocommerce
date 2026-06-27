<?php
if (!defined('ABSPATH')) exit;

class Pi_Pay_API {

    private $api_key;
    private $sandbox;
    private $base_url;

    public function __construct($api_key, $sandbox = true) {
        $this->api_key  = $api_key;
        $this->sandbox  = $sandbox;
        $this->base_url = 'https://api.minepi.com';
    }

    private function request($method, $endpoint, $data = []) {
        $url  = $this->base_url . $endpoint;
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Key ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function get_payment($payment_id) {
        return $this->request('GET', '/v2/payments/' . $payment_id);
    }

    public function approve_payment($payment_id) {
        return $this->request('POST', '/v2/payments/' . $payment_id . '/approve');
    }

    public function complete_payment($payment_id, $order_id) {
        return $this->request('POST', '/v2/payments/' . $payment_id . '/complete', [
            'txid' => $order_id,
        ]);
    }

    public function get_live_rate() {
        $response = wp_remote_get('https://api.coingecko.com/api/v3/simple/price?ids=pi-network&vs_currencies=usd', [
            'timeout' => 10,
        ]);
        if (is_wp_error($response)) return null;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['pi-network']['usd'] ?? null;
    }
}
