<?php
	use App\Lib\Response,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception,
		App\Lib\MiddlewareToken;
	use Envms\FluentPDO\Literal;
	use Slim\Http\UploadedFile;
	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Csv;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

	error_reporting(0);

	$app->group('/acceso/', function () {
		$this->get('', function ($req, $res, $args) {
			 return $res->withHeader('Content-type', 'text/html')->write('Soy ruta de acceso');
			//return $this->view->render($res, '404.phtml', $args);
		});

        // Ruta para obtener los datos de acceso por medio del ID
		$this->get('get/{id}', function ($req, $res, $args) {
			return $res->withJson($this->model->acceso->get($args['id']));
		});

        // Ruta para obtener los datos de los acceso
		$this->get('getAll/', function ($req, $res, $args) {
			$resultado = $this->model->acceso->getAll();
			return $res->withJson($resultado);
		});

		// Ruta para obtener los datos de los acceso
        $this->get('getAllRegisAjax/{inicial}/{limite}/{busqueda}', function($request, $response, $arguments) {
			include_once('../public/core/actions.php');
			$inicial = isset($_GET['start'])? $_GET['start']: $arguments['inicial'];
			$limite = isset($_GET['length'])? $_GET['length']: $arguments['limite'];
			$busqueda = isset($_GET['search']['value'])? (strlen($_GET['search']['value'])>0? $_GET['search']['value']: '_'): $arguments['busqueda'];

			$orden = isset($_GET['order'])
			? $_GET['columns'][$_GET['order'][0]['column']]['data']
			: 'acceso.id, acceso.checkin';
			$orden .= isset($_GET['order'])? " ".$_GET['order'][0]['dir']: " asc";
			if(count($_GET['order'])>1){
				for ($i=1; $i < count($_GET['order']); $i++) { 
					$orden .= ', '.$_GET['columns'][$_GET['order'][$i]['column']]['data'].' '.$_GET['order'][$i]['dir'];
				}
			}

			$resultado = $this->model->acceso->getAllRegisAjax($inicial, $limite, $busqueda, $orden);

			$modulo = 2; 
			$user = $_SESSION['usuario']->id; 
			$perm = $this->model->usuario->getAcciones($user, $modulo); 
			$permisos = getPermisos($perm);
			
			$data = [];
			foreach($resultado->result as $acceso) {

				$estado='';
				if($acceso->status==1){
					$estado = '<span class="status label label-success">Activo</span>';
				}else if($acceso->status==2){
					$estado = '<span class="status label label-warning">Inactivo</span>';
				}else{
					$estado = '<span class="status label label-danger">Baja</span>';
				}

				$acciones='';

				// $acciones .= (in_array(MOD_REGISTROS_EMAIL, $permisos) 
				// 	? "<a href='#' data-id='$acceso->id' data-popup='tooltip' title='Send' class='btnSend text-primary'><i class='mdi mdi-send fa-lg'></i></a>"
				// 	: "");

				//$acciones .= ' <a href="#" data-popup="tooltip" title="Reenviar WhatsApp" class="btnWhats text-info" data-id="'.$acceso->id.'"><i class="mdi mdi-whatsapp fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				//$acciones .= ' <a href="#" data-popup="tooltip" title="Reenviar Email" class="btnEmail text-info" data-id="'.$acceso->id.'"><i class="mdi mdi-email-open-outline fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				//$acciones .= ' <a href="#" data-popup="tooltip" title="Imprimir" class="btnPrint text-info" data-id="'.$acceso->id.'"><i class="mdi mdi-printer fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				//$acciones .= ' <a href="#" data-popup="tooltip" title="Editar" class="btnEdit text-info" data-id="'.$acceso->id.'"><i class="mdi mdi-account-edit fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				$acciones .= ' <a href="#" data-popup="tooltip" title="Dar de baja" class="btnBaja text-info" data-id="'.$acceso->id.'"><i class="mdi mdi-delete fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';
				
				//if($acceso->checkin==null || $acceso->checkin=='0000-00-00 00:00:00'){
					//$acciones .= ' <a href="#" data-popup="tooltip" title="CheckIn" class="btnCheckin text-primary" data-id="'.$acceso->id.'"><i class="mdi mdi-check-circle fa-lg"></i></a>';
				//}

				// $acciones .="<a href='#' data-id='$acceso->id' data-url='$url' data-popup='tooltip' title='Copiar link formulario' class='btnCopy text-secondary'><i class='mdi mdi-content-copy fa-lg'></i></a>";
				$data[] = array(
					"acciones"					=> $acciones,
					"ID"	 					=> $acceso->fk_registro, 
					"nombre" 					=> "$acceso->nombre $acceso->paterno $acceso->materno",
					"dia" 						=> "<small>$acceso->dia</small>",
					"evento" 					=> "<small>$acceso->evento</small>",
					"checkin" 					=> "<small>$acceso->checkin</small>",
					"estatus" 					=> $estado,
				);
			}
			
			echo json_encode(array(
				'draw'=>$_GET['draw'],
				'data'=>$data,
				'recordsTotal'=>$resultado->totalRegist,
				'recordsFiltered'=>$resultado->total,
			));
			exit(0);
		});

		// Ruta para obtener total de los registros
		$this->get('totalRegistros/', function ($req, $res, $args) {
			$resultado = $this->model->acceso->totalRegistros();
			return $res->withJson($resultado);
		});

		// Ruta para obtener total checks de los registros
		$this->get('totalChecks/', function ($req, $res, $args) {
			$resultado = $this->model->acceso->totalChecks();
			return $res->withJson($resultado);
		});

		// CheckIn de accesos
		$this->post('checkin/{id}/{evento}/{usuario}', function ($req, $res, $args) {
			$this->model->transaction->iniciaTransaccion();
			date_default_timezone_set('America/Mexico_City');
			$info = $this->model->registro->get($args['id']);
			if($info->response){

				$info2 = $this->model->acceso->getAcceso($args['id'], date('Y-m-d'), $args['evento']);
				// print_r($args['id']." ".date('Y-m-d')." ".$args['evento']);
				// print_r($info2); exit;
				if(!$info2->response){
					$data = [
						'fk_registro'	=> $args['id'],
						'dia'			=> new Literal('DATE(NOW())'),
						'evento'		=> $args['evento'],
						'checkin' 		=> new Literal('NOW()')
					];
					//print_r($data); exit;
					$resultado = $this->model->acceso->add($data);
					//print_r($resultado); exit;
					if($resultado->response){
						$resultado->checkin = date('Y-m-d H:i:s');
						$resultado->setResponse(true, 'Bienvenido(a) a,'.$args['evento']);
						$seg_log = $this->model->seg_log->add('Agregar Acceso '.$args['evento'], $args['id'], 'acceso'); 
						if(!$seg_log->response){
								$seg_log->state = $this->model->transaction->regresaTransaccion(); return $resultado->withJson($seg_log);
						}
						$resultado->state = $this->model->transaction->confirmaTransaccion(); 
					}else{
						$resultado->state = $this->model->transaction->regresaTransaccion();
						return $res->withJson($resultado->setResponse(false,'Ocurrio algo extraño. Vuelve a intentar'));
					}
				}else{
					$info2->state = $this->model->transaction->regresaTransaccion();
					return $res->withJson($info2->setResponse(false, 'Ya se registró el CheckIn anteriormente '.$info2->result->checkin));
				}
			}else{
				$info->state = $this->model->transaction->regresaTransaccion();
				return $res->withJson($info->setResponse(false, 'No existe el código ingresado'));
			}
			return $res->withJson($resultado);
		});

        // Ruta para agregar un acceso
		$this->post('add/', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			//$ultimo = $this->model->acceso->getUltimoFkRegistro()->result->id;
			// $parsedBody['fk_registro'] = intval($ultimo)+1;
			// $parsedBody['confirmacion'] = "0";
			// $parsedBody['especial'] = "0";
			// $parsedBody['invitados'] = "0";
			// $parsedBody['tipo'] = "1";
			$acceso = $this->model->acceso->add($parsedBody);
			if($acceso->response){
				$registro_id = $acceso->result;
				$seg_log = $this->model->seg_log->add('Agregar nuevo acceso', $registro_id, 'acceso'); 
				if(!$seg_log->response){
						$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
				}
			}else{
				$acceso->state = $this->model->transaction->regresaTransaccion(); 
				return $response->withJson($acceso); 
			}
			$acceso->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($acceso);
		});
	
        // Ruta para modificar un acceso
		$this->put('edit/{id}', function ($req, $res, $args) {
			$this->model->transaction->iniciaTransaccion();
			$data = $req->getParsedBody();
			// $nombres = $data['nombres'];
			// unset($data['nombres']);
			// if($data['confirmacion'] == 0 || $data['confirmacion'] == 2){
				// $acompañantes = $this->model->acceso->getAcomp($args['id'])->result;
				
				// foreach($acompañantes as $acomp){
					// $del = $this->model->acceso->del($acomp->id);
				// }
				// if($data['confirmacion'] == 0) $seg_log = $this->model->seg_log->add('Cancela acceso del invitado', $args['id'], 'acceso');
				// else if($data['confirmacion'] == 2) $seg_log = $this->model->seg_log->add('Devuelve a pre-acceso', $args['id'], 'acceso');

				// $data['invitados'] = 0;
			// }else{
				// foreach($nombres as $nombre){
					// $this->model->acceso->edit($nombre, $nombre['id']);
				// }
			// }
			$update = $this->model->acceso->edit($data, $args['id']);
			if($update->response){
				$seg_log = $this->model->seg_log->add('Editar acceso', $args['id'], 'acceso'); 
				if(!$seg_log->response){
						$seg_log->state = $this->model->transaction->regresaTransaccion(); return $update->withJson($seg_log);
				}
			}else{
				$update->state = $this->model->transaction->regresaTransaccion(); 
				return $update->withJson($update); 
			}
			$update->state = $this->model->transaction->confirmaTransaccion();
			return $res->withJson($update);
		});

        // Ruta para dar de baja un acceso
		$this->put('del/{id}', function ($req, $res, $args) {
			return $res->withJson($this->model->acceso->del($args['id']));
		});

		// Obtener CSV de todos los registros
		$this->get('getExcel/', function($request, $response, $arguments){
			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();

			$titulo = "Reporte Kapital";

			$arrMes = array('','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre');
    		$subtitulo = "Al ".date('d')." de ".$arrMes[intval(date('m'))]." de ".date('Y')." ".date('H:i:s');

			$sheet->setCellValue("A1", $titulo);
			$sheet->setCellValue("E1", $subtitulo);

			$sheet->setCellValue("A2", 'ID');
			$sheet->setCellValue("B2", 'Nombre completo');
			$sheet->setCellValue("C2", 'Día');
			$sheet->setCellValue("D2", 'Evento');
			$sheet->setCellValue("E2", 'Fecha de Entrada');

			$registros = $this->model->acceso->getAll2()->result;

			$fila = 3;
			foreach($registros as $res){

				$sheet->setCellValue("A".$fila, $res->id);
				$sheet->setCellValue("B".$fila, "$res->nombre $res->paterno $res->materno");
				$sheet->setCellValue("C".$fila, $res->dia);
				$sheet->setCellValue("D".$fila, $res->evento);
				$sheet->setCellValue("E".$fila, $res->checkin);

				$fila++;
			}
			$writer = new Csv($spreadsheet);
			$writer->setUseBOM(true);
			header('Content-Type: text/csv');
			header("Content-Disposition: attachment; filename=\"Reporte accesos Kapital"."_".date('YmdHi').".csv\"");
			$writer->save('php://output');
		});

	});

?>