<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Webhook extends \CodeIgniter\Controller
{
    use ResponseTrait;

    function getShopeeResponse() {   

        $post_data_expected = file_get_contents("php://input");
        $decoded_data = json_decode($post_data_expected, true);
        
        $model = new \App\Models\Webhook();
        $date = new \DateTime("now", new \DateTimeZone('Asia/Jakarta'));
		$_DATA = [
			'response'       => $post_data_expected,
            'code_mp'        => 1,
			'code'           => 0,
			'tanggal_jam'    => $date->format('Y-m-d H:i:s')
		];
        
        $model->insert($_DATA);

        $response = [
            'success' => true,
            'message' => 'Shopee Webhook Success.'
        ];

        return $this->respond($response, 200);
    }

    function getTiktokResponse() {   

        $post_data_expected = file_get_contents("php://input");
        $decoded_data = json_decode($post_data_expected, true);
        
        $data      = json_encode($decoded_data['data']);
        $shop_id   = $decoded_data['shop_id'];
        $code      = $decoded_data['type'];
        $timestamp = $decoded_data['timestamp'];

        $model = new \App\Models\Webhook();
        $date = new \DateTime("now", new \DateTimeZone('Asia/Jakarta'));
		$_DATA = [
			'tanggal_jam'    => $date->format('Y-m-d H:i:s'),
            'code_mp'        => 2,
			'response'       => $post_data_expected,
			'data'           => $data,
			'shop_id'        => $shop_id,
			'code'           => $code,
			'timestamp'      => $timestamp,
		];
        
        $model->insert($_DATA);

        $response = [
            'success' => true,
            'message' => 'Tiktok Webhook Success.'
        ];

        return $this->respond($response, 200);
    }
}