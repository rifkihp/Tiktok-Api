<?php

namespace App\Controllers;
use CodeIgniter\API\ResponseTrait;
use App\Controllers\BaseController;

class Produk extends BaseController
{
	use ResponseTrait;
	
	function getProductList() {

        $host       = getenv('TIKTOK_API_HOST');
		$version    = getenv('TIKTOK_API_VERSION');
        $path       = sprintf("/product/%s/products/search", $version);
        		
		$appKey        = getenv('TIKTOK_API_APP_KEY');
        $secretKey     = getenv('TIKTOK_API_SECRET_KEY');
		$shopChiper    = getenv('TIKTOK_API_SHOP_CHIPER');
		$pageSize      = $this->request->getVar('pageSize');
		$nextPageToken = $this->request->getVar('nextPageToken');
		$status        = $this->request->getVar('status');
		
        $timest      = time();
		$params      = sprintf("app_key=%s&page_size=%s&page_token=%s&shop_cipher=%s&timestamp=%s&version=%s", $appKey, $pageSize, $nextPageToken, $shopChiper, $timest, $version);		
		
		parse_str($params, $data);
		$baseString = '';
		foreach($data as $key => $value) {
			$baseString.=$key.$value;
		}
		if(strlen($status)>0) {
			$payload = json_encode( array( "status" => $status ) );
			$baseString.=$payload;
		}
		

		$baseString  = sprintf("%s%s".$baseString."%s", $secretKey, $path, $secretKey);
		$sign        = hash_hmac('sha256', $baseString, $secretKey);
        $url         = sprintf("%s%s?%s&sign=%s", $host, $path, $params, $sign);
        
        $model         = new \App\Models\Auth();
        $dataShop      = $model->getDataShop(2);

		/*$response = [
            'status' => 200,
            'data' => $dataShop,
			'base' => $baseString
        ];
        return $this->respond($response, 200);*/

		/*$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => $payload,
		  CURLOPT_HTTPHEADER => array(
			'x-tts-access-token: '.$dataShop['access_token'],
			'Content-Type: application/json'
		  ),
		));		
		$response = curl_exec($curl);
		curl_close($curl);
		$response = json_decode($response, true);
		return $this->respond($response, 200);*/

        $client = \Config\Services::curlrequest();
        $response = $client->setBody($payload)->request('POST', $url, [
			'headers' => [
                'Content-Type'       => 'application/json',
                'x-tts-access-token' =>  $dataShop['access_token']
            ]
		]);

         // Read response
        $code   = $response->getStatusCode();
        $reason = $response->getReason();

        if($code == 200) { //success

            // Read data 
            $response = json_decode($response->getBody(), true);
            return $this->respond($response, $code);

        } else {
            return $this->respond($reason, $code);
        }
    }

	function DownloadProductList() {

        $host       = getenv('TIKTOK_API_HOST');
		$version    = getenv('TIKTOK_API_VERSION');
        $path       = sprintf("/product/%s/products/search", $version);
        		
		$appKey        = getenv('TIKTOK_API_APP_KEY');
        $secretKey     = getenv('TIKTOK_API_SECRET_KEY');
		$shopChiper    = getenv('TIKTOK_API_SHOP_CHIPER');
		$pageSize      = $this->request->getVar('pageSize');
		$nextPageToken = $this->request->getVar('nextPageToken');
		$status        = $this->request->getVar('status');

		$model         = new \App\Models\Auth();
        $dataShop      = $model->getDataShop(2);

		$stop = false;
		$products = [];

		$md_tiktok_product_list = new \App\Models\TiktokProdukList();
		$md_tiktok_product_sku  = new \App\Models\TiktokProdukSku();
		
		$md_tiktok_product_list->truncate();
		$md_tiktok_product_sku->truncate();

		do {
			$timest      = time();
			$params      = sprintf("app_key=%s&page_size=%s&page_token=%s&shop_cipher=%s&timestamp=%s&version=%s", $appKey, $pageSize, $nextPageToken, $shopChiper, $timest, $version);		
			
			parse_str($params, $data);
			$baseString = '';
			foreach($data as $key => $value) {
				$baseString.=$key.$value;
			}
			if(strlen($status)>0) {
				$payload = json_encode( array( "status" => $status ) );
				$baseString.=$payload;
			}
			

			$baseString  = sprintf("%s%s".$baseString."%s", $secretKey, $path, $secretKey);
			$sign        = hash_hmac('sha256', $baseString, $secretKey);
			$url         = sprintf("%s%s?%s&sign=%s", $host, $path, $params, $sign);
			
			$client = \Config\Services::curlrequest();
			$response = $client->setBody($payload)->request('POST', $url, [
				'headers' => [
					'Content-Type'       => 'application/json',
					'x-tts-access-token' =>  $dataShop['access_token']
				]
			]);

			// Read response
			$code   = $response->getStatusCode();
			$reason = $response->getReason();

			if($code == 200) { //success

				// Read data 
				$response      = json_decode($response->getBody(), true);
				$data          = $response['data'];
				$nextPageToken = $data['next_page_token'];
				$stop          = ($nextPageToken=="");
								
				if(!$stop) {
					foreach($data['products'] as $value) {
						array_push($products, $value);
						$md_tiktok_product_list->insert([
							"id"           => $value["id"],
							"sales_region" => $value["sales_regions"][0],
							"status"       => $value["status"],
							"title"        => $value["title"],
							"create_time"  => $value["create_time"],
							"update_time"  => $value["update_time"],
						]);
						foreach($value['skus'] as $values) {
							$md_tiktok_product_sku->insert(
								[
									"id_produk_list"            => $value["id"],
									"id"			            => $values["id"],
									"inventory_quantity"        => $values["inventory"][0]["quantity"],
									"inventory_warehouse_id"    => $values["inventory"][0]["warehouse_id"],
									"price_currency"            => $values["price"]["currency"],
									"price_tax_exclusive_price" => $values["price"]["tax_exclusive_price"],
									"seller_sku"                => $values["seller_sku"]
								]
							);
						}
						
					}
				}
				
			} else {
				return $this->respond($reason, $code);
			}

			
		} while (!$stop);

    
		$result = [
			'total_data' => count($products),
			'data' 		 => $products
		];
		return $this->respond($result, $code);

	}

	function DownloadProductDetail() {

        $host       = getenv('TIKTOK_API_HOST');
		$version    = getenv('TIKTOK_API_VERSION');
        $path       = sprintf("/product/%s/products/search", $version);
        		
		$appKey        = getenv('TIKTOK_API_APP_KEY');
        $secretKey     = getenv('TIKTOK_API_SECRET_KEY');
		$shopChiper    = getenv('TIKTOK_API_SHOP_CHIPER');
		$pageSize      = $this->request->getVar('pageSize');
		$nextPageToken = $this->request->getVar('nextPageToken');
		$status        = $this->request->getVar('status');

		$model         = new \App\Models\Auth();
        $dataShop      = $model->getDataShop(2);

		$stop = false;
		$products = [];

		$md_tiktok_product_list = new \App\Models\TiktokProdukList();
		$md_tiktok_product_sku  = new \App\Models\TiktokProdukSku();
		
		$md_tiktok_product_list->truncate();
		$md_tiktok_product_sku->truncate();

		do {
			$timest      = time();
			$params      = sprintf("app_key=%s&page_size=%s&page_token=%s&shop_cipher=%s&timestamp=%s&version=%s", $appKey, $pageSize, $nextPageToken, $shopChiper, $timest, $version);		
			
			parse_str($params, $data);
			$baseString = '';
			foreach($data as $key => $value) {
				$baseString.=$key.$value;
			}
			if(strlen($status)>0) {
				$payload = json_encode( array( "status" => $status ) );
				$baseString.=$payload;
			}
			

			$baseString  = sprintf("%s%s".$baseString."%s", $secretKey, $path, $secretKey);
			$sign        = hash_hmac('sha256', $baseString, $secretKey);
			$url         = sprintf("%s%s?%s&sign=%s", $host, $path, $params, $sign);
			
			$client = \Config\Services::curlrequest();
			$response = $client->setBody($payload)->request('POST', $url, [
				'headers' => [
					'Content-Type'       => 'application/json',
					'x-tts-access-token' =>  $dataShop['access_token']
				]
			]);

			// Read response
			$code   = $response->getStatusCode();
			$reason = $response->getReason();

			if($code == 200) { //success

				// Read data 
				$response      = json_decode($response->getBody(), true);
				$data          = $response['data'];
				$nextPageToken = $data['next_page_token'];
				$stop          = ($nextPageToken=="");
								
				if(!$stop) {
					foreach($data['products'] as $value) {
						array_push($products, $value);
						$md_tiktok_product_list->insert([
							"id"           => $value["id"],
							"sales_region" => $value["sales_regions"][0],
							"status"       => $value["status"],
							"title"        => $value["title"],
							"create_time"  => $value["create_time"],
							"update_time"  => $value["update_time"],
						]);
						foreach($value['skus'] as $values) {
							$md_tiktok_product_sku->insert(
								[
									"id_produk_list"            => $value["id"],
									"id"			            => $values["id"],
									"inventory_quantity"        => $values["inventory"][0]["quantity"],
									"inventory_warehouse_id"    => $values["inventory"][0]["warehouse_id"],
									"price_currency"            => $values["price"]["currency"],
									"price_tax_exclusive_price" => $values["price"]["tax_exclusive_price"],
									"seller_sku"                => $values["seller_sku"]
								]
							);
						}
						
					}
				}
				
			} else {
				return $this->respond($reason, $code);
			}

			
		} while (!$stop);

    
		$result = [
			'total_data' => count($products),
			'data' 		 => $products
		];
		return $this->respond($result, $code);

	}
	
	function updateInventory() {

        $host       = getenv('TIKTOK_API_HOST');
		$version    = '202309'; //getenv('TIKTOK_API_VERSION');
        		
		$appKey        = getenv('TIKTOK_API_APP_KEY');
        $secretKey     = getenv('TIKTOK_API_SECRET_KEY');
		$shopChiper    = getenv('TIKTOK_API_SHOP_CHIPER');

		$productId     = $this->request->getVar('productId');
		$skuId         = $this->request->getVar('skuId');
		$qty           = (int) $this->request->getVar('qty'); 	

		$path          = sprintf("/product/%s/products/%s/inventory/update", $version, $productId);

        $timest      = time();
		$params      = sprintf("app_key=%s&shop_cipher=%s&timestamp=%s&version=%s", $appKey, $shopChiper, $timest, $version);		
		
		parse_str($params, $data);
		$baseString = '';
		foreach($data as $key => $value) {
			$baseString.=$key.$value;
		}
		
		$payload = json_encode( array("skus" => array( array("id"=> $skuId, "inventory" => array( array ("quantity" => $qty ) ) ) ) ) );
		$baseString.=$payload;
		
		$baseString  = sprintf("%s%s".$baseString."%s", $secretKey, $path, $secretKey);
		$sign        = hash_hmac('sha256', $baseString, $secretKey);
        $url         = sprintf("%s%s?%s&sign=%s", $host, $path, $params, $sign);
        
        $model         = new \App\Models\Auth();
        $dataShop      = $model->getDataShop(2);

		/*$response = [
            'status' => 200,
            'data' => $dataShop,
			'base' => $baseString
        ];
        return $this->respond($response, 200);*/

		/*$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => $payload,
		  CURLOPT_HTTPHEADER => array(
			'x-tts-access-token: '.$dataShop['access_token'],
			'Content-Type: application/json'
		  ),
		));		
		$response = curl_exec($curl);
		curl_close($curl);
		$response = json_decode($response, true);
		return $this->respond($response, 200);*/

        $client = \Config\Services::curlrequest();
        $response = $client->setBody($payload)->request('POST', $url, [
			'headers' => [
                'Content-Type'       => 'application/json',
                'x-tts-access-token' =>  $dataShop['access_token']
            ]
		]);

         // Read response
        $code   = $response->getStatusCode();
        $reason = $response->getReason();

        if($code == 200) { //success

            // Read data 
            $response = json_decode($response->getBody(), true);
            return $this->respond($response, $code);

        } else {
            return $this->respond($reason, $code);
        }
    }

	public function index() {
		
		$page  = $this->request->getVar('page');
		$start = $this->request->getVar('start');
		$limit = $this->request->getVar('limit');

		$query    = $this->request->getVar('query');
		if($query!='') {
			$query = '(nama_produk LIKE \'%'.$query.'%\' OR barcode LIKE \'%'.$query.'%\')';
		}

		$model = new \App\Models\Produk();
		$builder = $model->select('
			id,
			barcode,
			nama_produk,
			produsen,
			hrg_modal,
			hrg_enduser,
			hrg_member,
			ppn,
			mng_value,
			reward_value,
			agen_value,
			ceo_value,
			serba_guna,
			level_1,
			level_2,
			level_3,
			photo,
			aktif
        ');

		$builder->limit($limit, $start);
		if($query!='') {
			$builder->where($query);
		}
		$builder->orderBy('id', 'ASC');
		//$data = $builder->getCompiledSelect();
		$data = $builder->get()->getResultArray();
		
		//GET TOTAL
		$builder->select('COUNT(*) total'); 
		if($query!='') {
			$builder->where($query);
		}
		//$total = $builder->getCompiledSelect();
		$total = $builder->get()->getRowArray();
		$total = $total['total'];

		$response = [
            'data' => $data,
            'total' => $total
        ];
            
		return $this->respond($response, 200);
	}
	
	public function load($id) {
		$model = new \App\Models\Produk();

		$builder = $model->select('
			id,
			barcode,
			nama_produk,
			produsen,
			hrg_modal,
			hrg_enduser,
			hrg_member,
			ppn,
			mng_value,
			reward_value,
			agen_value,
			ceo_value,
			serba_guna,
			level_1,
			level_2,
			level_3,
			photo,
			aktif
        ');
		$builder->where('id', $id);

		$data  =  $builder->get()->getRowArray();
		if($data) {
			$response = [
				'success' 	=> true,
        		'data' 		=> $data
			];

			return $this->respond($response, 200);	
		} else {
			$response = [
				'success' => false,
        		'message' => 'Data tidak ditemukan.'
			];

			return $this->respond($response, 500);
		}
    }

	public function delete($id) {
		$model = new \App\Models\Produk();
		
		$data = $model->whereIn('id', explode(',', $id))->get()->getResultArray();
		foreach($data as $_DATA) {
			if($_DATA['photo']!='' && $_DATA['photo']!='default.png' && file_exists(ROOTPATH . 'public/uploads/produk/'.$_DATA['photo'])) {
				unlink(ROOTPATH . 'public/uploads/produk/'.$_DATA['photo']);			
			}
		}		
		$delete = $model->whereIn('id', explode(',', $id))->delete();
		
		if($delete) {
			$response = [
				'success' => true,
				'message' => 'Hapus data Produk berhasil.'
			];

			return $this->respond($response, 200);
		} else {
			$response = [
				'success' => false,
				'message' => 'Hapus data Produk gagal.'
			];

			return $this->respond($response, 500);
		}
	}

	public function insert() {
		$date = new \DateTime("now", new \DateTimeZone('Asia/Jakarta'));
		$_DATA = [
			'barcode'    	=> $this->request->getPost('barcode'),
			'nama_produk'	=> $this->request->getPost('nama_produk'),
			'produsen'		=> $this->request->getPost('produsen'),
			'hrg_modal'		=> $this->request->getPost('hrg_modal'),
			'hrg_enduser'	=> $this->request->getPost('hrg_enduser'),
			'hrg_member'	=> $this->request->getPost('hrg_member'),
			'ppn'			=> $this->request->getPost('ppn'),
			'mng_value'		=> $this->request->getPost('mng_value'),
			'reward_value'	=> $this->request->getPost('reward_value'),
			'agen_value'	=> $this->request->getPost('agen_value'),
			'ceo_value'		=> $this->request->getPost('ceo_value'),
			'serba_guna'	=> $this->request->getPost('serba_guna'),
			'level_1'		=> $this->request->getPost('level_1'),
			'level_2'		=> $this->request->getPost('level_2'),
			'level_3'		=> $this->request->getPost('level_3'),

			'photo'         => 'default.png',
			'aktif'         => $this->request->getPost('aktif'),
			
			'user_create'   => 0,
			'date_create'   => $date->format('Y-m-d H:i:s'),
			'user_update'	=> 0,
			'date_update'   => $date->format('Y-m-d H:i:s')
		];

		//VALIDASI
		$validation = \Config\Services::validation();
		if($validation->run($_DATA, 'produk') == FALSE) {
            foreach($validation->getErrors() as $value) {
                $response = [                
                    'success' => false,
                    'message' => $value
                ];
    
                return $this->respond($response, 500);
            }
        }

		$model = new \App\Models\Produk();

		//CHECK DUPLIKAT KTP MEMBER;
		$builder = $model->select('COUNT(*) TOTAL');
		$builder->where('barcode', $_DATA['barcode']);
		$check = $builder->get()->getRowArray();
		if($check['TOTAL']>0) {
			$response = [
				'success' => false,
				'message' => 'Barcode sudah terpakai.'
			];

			return $this->respond($response, 500);
		}

		//VALIDASI PHOTO
		helper(['form', 'url']);
		$max_size = 1024*1000*5; //MAX 5MB
		$validated =  $this->validate([
			'file' => [
				'uploaded[photo]',
				'mime_in[photo,image/jpg,image/jpeg,image/png]',
				'max_size[photo,'.$max_size.']',
			]
		]);

		if ($validated == FALSE) {
			$response = [
				'success' => false,
				'message' => 'Kesalahan upload photo.'
			];

			return $this->respond($response, 500);
		} 

		//UPLOAD PHOTO	
		$photo = $this->request->getFile('photo');
		$ext   = $photo->guessExtension();
		$_DATA['photo'] = strtoupper($_DATA["barcode"]."_PHOTO_".str_replace(" ","_",$_DATA["nama_produk"])).".".$ext;
		$photo->move(ROOTPATH . 'public/uploads/produk', $_DATA['photo']);
		
		//PROSES INSERT DATA
		$result = $model->insert($_DATA);
		
		$response = [
			'success' => true,
			'message' => 'Tambah data Produk berhasil.'
		];

		return $this->respond($response, 200);
	}

	public function update($id) {	
		$date = new \DateTime("now", new \DateTimeZone('Asia/Jakarta'));
		$_DATA = [
			'barcode'    	=> $this->request->getPost('barcode'),
			'nama_produk'	=> $this->request->getPost('nama_produk'),
			'produsen'		=> $this->request->getPost('produsen'),
			'hrg_modal'		=> $this->request->getPost('hrg_modal'),
			'hrg_enduser'	=> $this->request->getPost('hrg_enduser'),
			'hrg_member'	=> $this->request->getPost('hrg_member'),
			'ppn'			=> $this->request->getPost('ppn'),
			'mng_value'		=> $this->request->getPost('mng_value'),
			'reward_value'	=> $this->request->getPost('reward_value'),
			'agen_value'	=> $this->request->getPost('agen_value'),
			'ceo_value'		=> $this->request->getPost('ceo_value'),
			'serba_guna'	=> $this->request->getPost('serba_guna'),
			'level_1'		=> $this->request->getPost('level_1'),
			'level_2'		=> $this->request->getPost('level_2'),
			'level_3'		=> $this->request->getPost('level_3'),
            
			'user_update'	=> 0,
			'date_update'   => $date->format('Y-m-d H:i:s')
		];

		//VALIDASI
		$validation = \Config\Services::validation();
		if($validation->run($_DATA, 'produk') == FALSE) {
            foreach($validation->getErrors() as $value) {
                $response = [                
                    'success' => false,
                    'message' => $value
                ];
    
                return $this->respond($response, 500);
            }
        }

		$model      = new \App\Models\Produk();

		//GET DATA MEMBER
		$data = $model->where('id', $id)->get()->getRowArray();
		$_DATA['photo']   = $data['photo'];

		//CHECK DUPLIKAT BARCODE;
		$builder = $model->select('COUNT(*) TOTAL');
		$builder->where(['barcode', $_DATA['barcode'], 'id !=' => $id]);
		$check = $builder->get()->getRowArray();
		if($check['TOTAL']>0) {
			$response = [
				'success' => false,
				'message' => 'Barcode sudah terpakai.'
			];

			return $this->respond($response, 500);
		}

		if (!empty($_FILES['photo']['name'])) {

			//VALIDASI PHOTO
			helper(['form', 'url']);
			$max_size = 1024*1000*5; //MAX 5MB
			$validated =  $this->validate([
				'file' => [
					'uploaded[photo]',
					'mime_in[photo,image/jpg,image/jpeg,image/png]',
					'max_size[photo,'.$max_size.']',
				]
			]);

			if ($validated == FALSE) {
				$response = [
					'success' => false,
					'message' => 'Kesalahan upload photo.'
				];

				return $this->respond($response, 500);
			}

			//DELETE PHOTO LAMA
			if($_DATA['photo']!='' && $_DATA['photo']!='default.png' && file_exists(ROOTPATH . 'public/uploads/produk/'.$_DATA['photo'])) {
				unlink(ROOTPATH . 'public/uploads/produk/'.$_DATA['photo']);			
			}

			$photo = $this->request->getFile('photo');
			$ext   = $photo->guessExtension();
			$_DATA['photo'] = strtoupper($_DATA["barocde"]."_PHOTO_".str_replace(" ","_",$_DATA["nama_produk"])).".".$ext;
			$photo->move(ROOTPATH . 'public/uploads/produk', $_DATA['photo']);
		}

		//PROSES UPDATE
		$model->where(['id' => $id])->set($_DATA)->update();
		
		$response = [
			'success' => true,
			'message' => 'Update Produk berhasil.'
		];

		return $this->respond($response, 200);
	}

	public function aktif($id) {
		$model  = new \App\Models\Produk();

		$date = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
		$_DATA = [
			'aktif'       => $this->request->getPost('status'),
			'date_update' => $date->format('Y-m-d H:i:s')
		];

		$update = $model->where('id', $id)->set($_DATA)->update();
		if($update) {
			$data = $model->where('id', $id)->get()->getRowArray();
			$model_user = new \App\Models\User();
			$model_user->where(['id' => $data['id_user']])->set($_DATA)->update();

			$response = [
				'success' => true,
				'message' => 'Update Produk berhasil.'
			];

			return $this->respond($response, 200);
		} else {
			$response = [
				'success' => false,
				'message' => 'Update Produk gagal.'
			];

			return $this->respond($response, 500);
		}
	}
}
