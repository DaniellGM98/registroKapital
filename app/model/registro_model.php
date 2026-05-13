<?php
	namespace App\Model;
	use PDOException;
	use App\Lib\Response;
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
				->where('!checkin', 'null')
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

	}
?>