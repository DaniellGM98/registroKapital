<?php
	namespace App\Model;
	use PDOException;
	use App\Lib\Response;
	class AccesoModel {
		private $db;
		private $table = 'acceso';
		private $tableR = 'registro';
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
				->where("$this->table.status", 1)
				->fetch();
			if($this->response->result) {
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'No existe el registro');
			}
			return $this->response;
		}

		// Obtener los datos de acceso por fk_registro, dia, evento
		public function getAcceso(String $fk_registro, String $dia, String $evento) {
			$this->response->result = $this->db
				->from($this->table)
				->where("$this->table.fk_registro", $fk_registro)
				->where("$this->table.dia", $dia)
				->where("$this->table.evento", $evento)
				->where("$this->table.status", 1)
				->fetch();
			if($this->response->result) {
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'No existe el registro');
			}
			return $this->response;
		}

		// Obtener los datos de los clientes
		public function getAllRegisAjax($inicial, $limite, $busqueda, $orden = "registro.id, acceso.checkin") {
			$registros = $this->db
			->from("acceso")->disableSmartJoin()
				->select(null)->select("$this->table.id, $this->tableR.id as fk_registro, $this->tableR.nombre, $this->tableR.paterno, $this->tableR.materno, $this->table.dia, $this->table.evento, $this->table.checkin, $this->table.status")
				->innerJoin($this->tableR.' ON registro.id = fk_registro')
				->where("CONCAT_WS(' ', registro.id, registro.nombre, registro.paterno, registro.materno, acceso.dia, acceso.evento, acceso.status) LIKE '%$busqueda%'")
				->where('acceso.status', 1)
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

		// Obtener los datos de los registro
		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->tableR.id, $this->tableR.nombre, $this->tableR.paterno, $this->tableR.materno, $this->table.dia, $this->table.evento, $this->table.checkin, $this->table.status")
				->innerJoin($this->tableR.' ON registro.id = fk_registro')
				->where('acceso.status', 1)
				->orderBy('checkin desc')
				->limit("100")
				->fetchAll();
			return $this->response->SetResponse(true);
		}

		public function getAll2() {
			ini_set('memory_limit', '480M');
			set_time_limit(3000);
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->tableR.id, $this->tableR.nombre, $this->tableR.paterno, $this->tableR.materno, $this->table.dia, $this->table.evento, $this->table.checkin, $this->table.status")
				->innerJoin($this->tableR.' ON registro.id = fk_registro')
				->where('acceso.status', 1)
				->orderBy('checkin desc')
				->fetchAll();
			return $this->response->SetResponse(true);
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

		// Obtener listado de registros ordenados del más puntual al menos puntual
		public function getRankingPuntualidad() {
			$sql = "
				SELECT 
					r.id,
					CASE 
						WHEN r.nombre IS NULL OR r.nombre = '' THEN 'Sin nombre'
						ELSE CONCAT(r.nombre, ' ', COALESCE(r.paterno, ''), ' ', COALESCE(r.materno, ''))
					END AS nombre_completo,
					r.email,
					COALESCE(r.sucursal, 'No especificada') AS sucursal,
					COALESCE(r.puesto, 'No especificado') AS puesto,
					r.telefono,
					COUNT(DISTINCT a.evento) AS total_sesiones,
					GROUP_CONCAT(
						DISTINCT CONCAT(a.evento, ' - ', DATE_FORMAT(a.checkin, '%H:%i:%s'))
						ORDER BY a.checkin ASC
						SEPARATOR ' | '
					) AS detalle_sesiones
				FROM registro r
				LEFT JOIN acceso a ON r.id = a.fk_registro AND a.status = 1
				WHERE r.status = 1
				GROUP BY r.id
				ORDER BY 
					total_sesiones DESC,           
					COALESCE(SUM(TIME_TO_SEC(TIME(a.checkin))), 0) ASC
			";
			
			$this->response->result = $this->db->getPdo()->query($sql)->fetchAll();
			return $this->response->SetResponse(true);
		}

		// Ruta para obtener registro de puntualidad
		public function getRegistroPuntualidad() {
			$sql = "
				SELECT 
					r.id,
					CASE 
						WHEN r.nombre IS NULL OR r.nombre = '' THEN 'Sin nombre'
						ELSE CONCAT(r.nombre, ' ', COALESCE(r.paterno, ''), ' ', COALESCE(r.materno, ''))
					END AS nombre_completo,
					r.email,
					COALESCE(r.sucursal, 'No especificada') AS sucursal,
					COALESCE(r.puesto, 'No especificado') AS puesto,
					r.telefono,
					COUNT(DISTINCT a.evento) AS total_sesiones,
					GROUP_CONCAT(
						DISTINCT CONCAT(a.evento, ' - ', DATE_FORMAT(a.checkin, '%H:%i:%s'))
						ORDER BY a.checkin ASC
						SEPARATOR ' | '
					) AS detalle_sesiones
				FROM registro r
				LEFT JOIN acceso a ON r.id = a.fk_registro AND a.status = 1
				WHERE r.status = 1
				GROUP BY r.id
				ORDER BY 
					total_sesiones DESC,           
					COALESCE(SUM(TIME_TO_SEC(TIME(a.checkin))), 0) ASC
				LIMIT 1
			";
			
			$this->response->result = $this->db->getPdo()->query($sql)->fetchAll();
			return $this->response->SetResponse(true);
		}

		// Agregar un registro
		public function add($data) {
			//$data['fecha_modificacion'] = date('Y-m-d H:i:s'); 
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

	}
?>