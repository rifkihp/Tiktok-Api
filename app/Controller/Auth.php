<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Auth extends \CodeIgniter\Controller
{
    use ResponseTrait;

    function getAuthorizedShops() {   

        $host       = getenv('TIKTOK_API_HOST');
		$version    = getenv('TIKTOK_API_VERSION');
        $path       = sprintf("/authorization/%s/shops", $version);

        $appKey        = getenv('TIKTOK_API_APP_KEY');
        $secretKey     = getenv('TIKTOK_API_SECRET_KEY');

        $timest = time();
        $baseString = sprintf("%s%sapp_key%stimestamp%s%s", $secretKey, $path, $appKey, $timest, $secretKey);
        $sign = hash_hmac('sha256', $baseString, $secretKey);


        $model         = new \App\Models\Auth();
        $dataShop      = $model->getDataShop(2);

        /*$response = [
            'status' => 200,
            'data' => $dataShop
        ];

        return $this->respond($response, 200);*/
        
        $url = sprintf("%s%s?app_key=%s&sign=%s&timestamp=%s", $host, $path, $appKey, $sign, $timest);
    
        $client = \Config\Services::curlrequest();
        $response = $client->request('GET', $url, [
            'headers' => [
                'Content-Type'       => 'application/json',
                'Accept'             => 'application/json',
                'x-tts-access-token' =>  $dataShop['access_token']
            ]
        ]);

         // Read response
        $code   = $response->getStatusCode();
        $reason = $response->getReason();

        if($code == 200){ // Success

            // Read data 
            $response = json_decode($response->getBody(), true);
            return $this->respond($response, $code);

        } else {
            return $this->respond($reason, $code);
        }

        
        
    }

    function refreshTokenShopLevel() {

        $host          = "https://auth.tiktok-shops.com";
        $path          = "/api/v2/token/refresh";
        
        $appKey        = getenv('TIKTOK_API_APP_KEY');
        $secretKey     = getenv('TIKTOK_API_SECRET_KEY');

        $model         = new \App\Models\Auth();
        $dataShop      = $model->getDataShop(2);

        /*$response = [
            'status' => 200,
            'data' => $dataShop
        ];

        return $this->respond($response, 200);*/
        
        $refreshToken  = $dataShop['refresh_token_for_request'];
        
        $url = sprintf("%s%s?app_key=%s&app_secret=%s&refresh_token=%s&grant_type=refresh_token", $host, $path, $appKey, $secretKey, $refreshToken);
    
        $client = \Config\Services::curlrequest();
        $response = $client->request('GET', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ]
        ]);

         // Read response
        $code   = $response->getStatusCode();
        $reason = $response->getReason();

        if($code == 200){ // Success

            // Read data 
            $response = json_decode($response->getBody(), true);
            $error    = (int) $response['code'];
            $message  = $response['message'];

            if($error==0) {
                //update data
                $date = new \DateTime("now", new \DateTimeZone('Asia/Jakarta'));
                $_DATA = [
                    'access_token'              => ($error==0?$response['data']['access_token']:''),
                    'refresh_token_for_reset'   => ($error==0?$response['data']['refresh_token']:''),
                    'refresh_token_for_request' => ($error==0?$response['data']['refresh_token']:''),
                    'error'                     => $error,
                    'message'                   => $message,
                    'last_update'               => $date->format('Y-m-d H:i:s')
                ];
                $model->where(['id' => 2])->set($_DATA)->update();
                return $this->respond($response, $code);

            } else {
                return $this->respond($response, 500);
            }

        } else {
            return $this->respond($reason, $code);
        }
    }

    public function Login() {
		
        $_DATA = [
            'username' => $this->request->getPost('userid'),
            'password' =>  $this->request->getPost('password')
        ];

        $validation = \Config\Services::validation();
		if($validation->run($_DATA, 'auth') == FALSE) {
            foreach($validation->getErrors() as $value) {
                $response = [                
                    'success' => false,
                    'message' => $value
                ];
    
                return $this->respond($response, 500);
            }
        }

        $MCrypt = new \App\Libraries\MCrypt();
		$_DATA['password'] = $MCrypt->encrypt($_DATA['password']);  

        $model = new \App\Models\User();
        $user  = $model->select('
            id,
            nama,
            email,
            nohp,
            username,
            photo,
            aktif,
            tipe_user,
            id_tipe_user
        ')->where($_DATA)->get()->getRowArray();

		if($user) {
            if($user['aktif']==0) {
                $response = [                
                    'success' => false,
                    'message' => 'User tidak aktif.'
                ];
    
                return $this->respond($response, 500);
            } 
            
            $mdl_menu = new \App\Models\Menu();
            $menu = $mdl_menu->getMenu(0, $user['tipe_user']);
			$response = [
                'success' => true,
                'data' => [
                    $user
                ],
                'menu' => [
                    'children' => $menu,
                ]
            ];

            $session = \Config\Services::session();
            $session->set(['user' => $user, 'menu' => $menu]);

			return $this->respond($response, 200);
		} else {
			$response = [
                'success' => false,
                'message' => 'User ID dan password tidak sesuai.'
            ];

            return $this->respond($response, 500);	
		}
    }

    public function Logout() {
		$session = \Config\Services::session();
		$session->destroy();

		$response = [
            'status' => 200,
            'success' => true,
            'data' => [
                'message' => 'Proses logout berhasil.'
            ],
        ];

        return $this->respond($response, 200);	
	}

}