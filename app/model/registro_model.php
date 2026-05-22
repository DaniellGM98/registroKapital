<?php
	namespace App\Model;
	use PDOException;
	use App\Lib\Response;
	use Slim\Http\UploadedFile;

	class RegistroModel {
		private $db;
		private $table = 'registro';
		private $response;	

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		// Obtener los datos de registro
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where("$this->table.id", $id)
				->fetch();
			if($this->response->result) {
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'No existe el registro');
			}
			return $this->response;
		}

		public function getBy($campo, $valor){
			$this->response->result = $this->db
				->from($this->table)
				->where($campo, $valor)
				->fetch();
			if($this->response->result) {
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'No existe el registro');
			}
			return $this->response;
		}

		// Obtener los datos de los clientes
		public function getAllRegisAjax($inicial, $limite, $busqueda, $orden = "registro.id, registro.checkin") {
			$registros = $this->db
			->from("registro")->disableSmartJoin()
				->where("CONCAT_WS(' ', registro.id, registro.nombre, registro.paterno, registro.materno, registro.fecha_entrega) LIKE '%$busqueda%'")
				->where('registro.status', 1)
				->limit("$inicial, $limite")
				->orderBy($orden)
				->fetchAll();
			$this->response->result = $registros;
			$this->response->total = $this->db
					->from($this->table)
					->select(null)->select('COUNT(*) Total')
					->where('status', 1)
					->fetch()
					->Total;
			$this->response->totalRegist = $this->db
					->from($this->table)
					->select(null)->select('COUNT(*) Total')
					->where('status', 1)
					->fetch()
					->Total;
			return $this->response->SetResponse(true);
		}

		public function getAll2() {
			ini_set('memory_limit', '480M');
			set_time_limit(3000);
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("id, nombre, paterno, materno, apodo, puesto, sucursal, telefono, email, fecha_entrega, fecha_impresion")
				->where('status', 1)
				->orderBy('id ASC')
				->fetchAll();
			return $this->response->SetResponse(true);
		}

		// Obtener los telefonos de registros
		public function getPhones() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("id, nombre, paterno, materno, telefono")
				->where("$this->table.status", 1)
				->fetchAll();
			if($this->response->result) {
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'No existe el registro');
			}
			return $this->response;
		}

		// Obtener los registros que no tienen fecha de impresión
		public function getPendientes(){
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("id")
				->where('status', 1)
				->where('fecha_impresion IS NULL')
				->fetchAll();
			if($this->response->result) {
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'No existen registros pendientes');
			}
			return $this->response;
		}

		// Obtener registros con fecha de impresión definida
		public function getByImpresion($fecha) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("id, apodo, puesto, sucursal")
				->where('status', 1)
				->where('fecha_impresion', $fecha)
				->orderBy('apodo ASC')
				->fetchAll();
			if($this->response->result) {
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'No existen registros con esa fecha de impresión');
			}
			return $this->response;

		}

		// Obtener total de los registros
		public function totalRegistros() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('status', 1)
				->fetch();
			return $this->response->SetResponse(true);
		}

		// Obtener total checks de los registros
		public function totalChecks() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('status', 1)
				->where('!fecha_entrega', 'null')
				->fetch();
			return $this->response->SetResponse(true);
		}

		// Agregar un registro
		public function add($data) {
			date_default_timezone_set('America/Mexico_City');
			$data['fecha_modificacion'] = date('Y-m-d H:i:s'); 
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();
				if($this->response->result != 0){
					$this->response->SetResponse(true, 'id del registro: '.$this->response->result);
				}else { 
					$this->response->SetResponse(false, 'No se inserto el registro'); 
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: Add model $this->table");
			}
			return $this->response;
		}

		// Modificar un registro
		public function edit($data, $id) {
			date_default_timezone_set('America/Mexico_City');
			$data['fecha_modificacion'] = date('Y-m-d H:i:s'); 
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
				if($this->response->result!=0) { 
					$this->response->SetResponse(true, "id actualizado: $id"); 
				}else { 
					$this->response->SetResponse(false, 'No se edito el registro'); 
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: Edit model $this->table");
			}
			return $this->response;
		}

		// Dar de baja un registro
		public function del($id) {
			date_default_timezone_set('America/Mexico_City');
			$data['fecha_modificacion'] = date('Y-m-d H:i:s'); 
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }    
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}
			return $this->response;
		}

		// Enviar mensaje de whatsapp
		public function sendWhats($telefono, $body){
			$token='x55eza6hgbmsy9nm';
    		$instance="https://api.ultramsg.com/instance85888/messages/chat";

			$params = array(
				'token' => $token,
				'to' => '+52'.$telefono,
				'body' => ''.$body,
			);

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => $instance,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_SSL_VERIFYPEER => 0,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => http_build_query($params),
				CURLOPT_HTTPHEADER => array(
					"content-type: application/x-www-form-urlencoded"
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
				return $err;
			} else {
				return $response;
			}

		}

		public function moveUploadedFile($directory, UploadedFile $uploadedFile, $filename) {
			$extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
			$allowedExtensions = array('jpg', 'jpeg', 'png');

			if(!in_array($extension, $allowedExtensions, true)) {
				return '0';
			}

			$storedFilename = $filename.'.'.$extension;
			$uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $storedFilename);

			return $storedFilename;
		}

		public function sendWhImg($telefono, $body, $header) {
			$token='x55eza6hgbmsy9nm';
    		$instance="https://api.ultramsg.com/instance85888/messages/image";

			$params = array(
				'token' => $token,
				'to' => '+52'.$telefono,
				'image' => ''.$header,
				'caption' => ''.$body,
				'priority' => '10',
				'referenceId' => '',
				'nocache' => '1',
				'msgId' => '',
				'mentions' => ''
			);

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => $instance,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_SSL_VERIFYPEER => 0,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => http_build_query($params),
				CURLOPT_HTTPHEADER => array(
					"content-type: application/x-www-form-urlencoded"
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
				return $err;
			} else {
				return $response;
			}

		}

		public function sendWhPDF($telefono, $body, $pdf, $nombre) {
			$token='x55eza6hgbmsy9nm';
    		$instance="https://api.ultramsg.com/instance85888/messages/document";

			$params = array(
				'token' => $token,
				'to' => '+52'.$telefono,
				'filename' => ''.$nombre,
				'document' => $pdf,
				'caption' => ''.$body
			);

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => $instance,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_SSL_VERIFYPEER => 0,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => http_build_query($params),
				CURLOPT_HTTPHEADER => array(
					"content-type: application/x-www-form-urlencoded"
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
				return $err;
			} else {
				return $response;
			}

		}

		//	Enviar whatsapp desde Meta Api
		function sendWhatsAppMessage($to) {
			$url = 'https://graph.facebook.com/' . API_VERSION . '/' . PHONE_NUMBER_ID . '/messages';
			
			$numeroLimpio = preg_replace('/[^0-9]/', '', $to);
			if (strlen($numeroLimpio) === 10) {
				$numeroLimpio = '+52' . $numeroLimpio;
			} elseif (substr($numeroLimpio, 0, 2) === '52') {
				$numeroLimpio = '+' . $numeroLimpio;
			}

			$data = [
				"messaging_product" => "whatsapp",
				"to" => $numeroLimpio,
				"type" => "template",
				"template" => [
					"name" => "encuesta2", // nombre de la plantilla creada en Meta Business
					"language" => ["code" => "es_MX"],
					"components" => []
				]
			];
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Authorization: Bearer ' . WHATSAPP_TOKEN,
				'Content-Type: application/json'
			]);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($httpCode === 200) {
				$result = json_decode($response, true);
				return ['success' => true, 'message_id' => $result['messages'][0]['id']];
			} else {
				$errorDetail = json_decode($response, true);
				$errorMsg = $errorDetail['error']['message'] ?? 'Error desconocido';
				return ['success' => false, 'error' => $errorMsg];
			}
		}

	}
?>