<?php
	use App\Lib\Response,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception,
		App\Lib\MiddlewareToken;
	use Envms\FluentPDO\Literal;
	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Csv;

	error_reporting(0);

	$app->group('/registro/', function () {
		$this->get('', function ($req, $res, $args) {
			 return $res->withHeader('Content-type', 'text/html')->write('Soy ruta de registro');
			//return $this->view->render($res, '404.phtml', $args);
		});

        // Ruta para obtener los datos de registro por medio del ID
		$this->get('get/{id}', function ($req, $res, $args) {
			return $res->withJson($this->model->registro->get($args['id']));
		});

        // Ruta para obtener los datos de los registro
		$this->get('getAll/', function ($req, $res, $args) {
			$resultado = $this->model->registro->getAll();
			return $res->withJson($resultado);
		});

		$this->get('validar/{campo}/{valor}/', function ($req, $res, $args) {
			return $res->withJson($this->model->registro->getBy($args['campo'], $args['valor']));
		});

		// Ruta para obtener los datos de los registro
        $this->get('getAllRegisAjax/{inicial}/{limite}/{busqueda}', function($request, $response, $arguments) {
			include_once('../public/core/actions.php');
			$inicial = isset($_GET['start'])? $_GET['start']: $arguments['inicial'];
			$limite = isset($_GET['length'])? $_GET['length']: $arguments['limite'];
			$busqueda = isset($_GET['search']['value'])? (strlen($_GET['search']['value'])>0? $_GET['search']['value']: '_'): $arguments['busqueda'];

			$orden = isset($_GET['order'])
			? $_GET['columns'][$_GET['order'][0]['column']]['data']
			: 'registro.id, registro.checkin';
			$orden .= isset($_GET['order'])? " ".$_GET['order'][0]['dir']: " asc";
			if(count($_GET['order'])>1){
				for ($i=1; $i < count($_GET['order']); $i++) { 
					$orden .= ', '.$_GET['columns'][$_GET['order'][$i]['column']]['data'].' '.$_GET['order'][$i]['dir'];
				}
			}

			$resultado = $this->model->registro->getAllRegisAjax($inicial, $limite, $busqueda, $orden);

			$modulo = 2; 
			$user = $_SESSION['usuario']->id; 
			$perm = $this->model->usuario->getAcciones($user, $modulo); 
			$permisos = getPermisos($perm);
			
			$data = [];
			foreach($resultado->result as $registro) {

				$estado='';
				if($registro->status==1){
					$estado = '<span class="status label label-success">Activo</span>';
				}else if($registro->status==2){
					$estado = '<span class="status label label-warning">Inactivo</span>';
				}else{
					$estado = '<span class="status label label-danger">Baja</span>';
				}

				$acciones='';

				// $acciones .= (in_array(MOD_REGISTROS_EMAIL, $permisos) 
				// 	? "<a href='#' data-id='$registro->id' data-popup='tooltip' title='Send' class='btnSend text-primary'><i class='mdi mdi-send fa-lg'></i></a>"
				// 	: "");

				//$acciones .= ' <a href="#" data-popup="tooltip" title="Reenviar WhatsApp" class="btnWhats text-info" data-id="'.$registro->id.'"><i class="mdi mdi-whatsapp fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				//$acciones .= ' <a href="#" data-popup="tooltip" title="Reenviar Email" class="btnEmail text-info" data-id="'.$registro->id.'"><i class="mdi mdi-email-open-outline fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				$acciones .= ' <a href="#" data-popup="tooltip" title="Entregar" class="btnEntregar text-info" data-id="'.$registro->id.'"><i class="mdi mdi-account-box-outline fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				$acciones .= ' <a href="#" data-popup="tooltip" title="Imprimir" class="btnPrint text-info" data-id="'.$registro->id.'"><i class="mdi mdi-printer fa-lg"></i></a><br>';

				$acciones .= ' &nbsp;&nbsp;&nbsp;<a href="#" data-popup="tooltip" title="Editar" class="btnEdit text-info" data-id="'.$registro->id.'"><i class="mdi mdi-account-edit fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';

				$acciones .= ' <a href="#" data-popup="tooltip" title="Dar de baja" class="btnBaja text-info" data-id="'.$registro->id.'"><i class="mdi mdi-delete fa-lg"></i></a>&nbsp;&nbsp;&nbsp;';
				
				//if($registro->checkin==null || $registro->checkin=='0000-00-00 00:00:00'){
					//$acciones .= ' <a href="#" data-popup="tooltip" title="CheckIn" class="btnCheckin text-primary" data-id="'.$registro->id.'"><i class="mdi mdi-check-circle fa-lg"></i></a>';
				//}

				// $acciones .="<a href='#' data-id='$registro->id' data-url='$url' data-popup='tooltip' title='Copiar link formulario' class='btnCopy text-secondary'><i class='mdi mdi-content-copy fa-lg'></i></a>";
				$data[] = array(
					"acciones"					=> $acciones,
					"ID"	 					=> $registro->id, 
					"nombre" 					=> "$registro->nombre $registro->paterno $registro->materno",
					"nombre_gafete"	 			=> $registro->apodo, 
					"puesto"		 			=> $registro->puesto, 
					"sucursal"		 			=> $registro->sucursal, 
					"telefono" 					=> "<small>$registro->telefono</small>",
					"email"	 					=> "<small>$registro->email</small>",
					"fecha_entrega"	 			=> "<small>$registro->fecha_entrega</small>",
					"fecha_impresion"	 		=> "<small>$registro->fecha_impresion</small>",
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
			$resultado = $this->model->registro->totalRegistros();
			return $res->withJson($resultado);
		});

		// Ruta para obtener total checks de los registros
		$this->get('totalChecks/', function ($req, $res, $args) {
			$resultado = $this->model->registro->totalChecks();
			return $res->withJson($resultado);
		});

		// CheckIn de registros
		$this->post('checkinEntrega/{id}/{usuario}', function ($req, $res, $args) {
			$this->model->transaction->iniciaTransaccion();
			date_default_timezone_set('America/Mexico_City');
			$info = $this->model->registro->get($args['id']);

			if($info->response){
				$nombre = $info->result->nombre.' '.$info->result->paterno.' '.$info->result->materno;
				if($info->result->fecha_entrega == null || $info->result->fecha_entrega == '0000-00-00 00:00:00'){
					$data = array('fecha_entrega' => new Literal('NOW()'));
					$resultado = $this->model->registro->edit($data, $args['id']);
					if($resultado->response){
						$resultado->checkin = date('Y-m-d H:i:s');
						$resultado->setResponse(true, 'Bienvenido(a) ,'.$nombre);
						$seg_log = $this->model->seg_log->add('Agregar Entrega', $args['id'], 'registro'); 
						if(!$seg_log->response){
								$seg_log->state = $this->model->transaction->regresaTransaccion(); return $resultado->withJson($seg_log);
						}
						$resultado->state = $this->model->transaction->confirmaTransaccion(); 
					}else{
						$resultado->state = $this->model->transaction->regresaTransaccion();
						return $res->withJson($resultado->setResponse(false,'Ocurrio algo extraño. Vuelve a intentar'));
					}
				}else{
					$info->state = $this->model->transaction->regresaTransaccion();
					return $res->withJson($info->setResponse(false, 'Ya se registró el entrega anteriormente '.$info->result->fecha_entrega));
				}
			}else{
				$info->state = $this->model->transaction->regresaTransaccion();
				return $res->withJson($info->setResponse(false, 'Ocurrio algo extraño, intenta nuevamente'));
			}
			return $res->withJson($resultado);
		});

        // Ruta para agregar un registro
		$this->post('add/', function ($req, $res, $args) {
			$this->model->transaction->iniciaTransaccion();
			$data = $req->getParsedBody();
			$uploadedFiles = $req->getUploadedFiles();
			$resultado = (object)[];
			$pdfUrl = '';

			if(!isset($uploadedFiles['selfie'])) {
				$this->model->transaction->regresaTransaccion();
				$response = new Response();
				return $res->withJson($response->SetResponse(false, 'La selfie es obligatoria'));
			}

			$uploadedFile = $uploadedFiles['selfie'];
			if($uploadedFile->getError() !== UPLOAD_ERR_OK) {
				$this->model->transaction->regresaTransaccion();
				$response = new Response();
				return $res->withJson($response->SetResponse(false, 'No se pudo cargar la selfie'));
			}

			$registro = $this->model->registro->add($data);
			if(!$registro->response) {
				$this->model->transaction->regresaTransaccion();
				return $res->withJson($registro);
			}

			$idReg = $registro->result;
			$filename = $this->model->registro->moveUploadedFile('data/selfie', $uploadedFile, $data['apodo'].'_'.$idReg);
			if($filename == '0') {
				$this->model->transaction->regresaTransaccion();
				$response = new \App\Lib\Response();
				return $res->withJson($response->SetResponse(false, 'Extensión de archivo inválida, solo se aceptan imágenes JPG, JPEG o PNG'));
			} else {
				// Generar QR
				$fileUrl = 'data/qr/'.$idReg.'.png';
				$qrUrl = 'https://quickchart.io/qr?text='.urlencode($idReg).'&margin=1';
				$QR = file_get_contents($qrUrl);
				if($QR === false) {
					$this->model->transaction->regresaTransaccion();
					$response = new \App\Lib\Response();
					return $res->withJson($response->SetResponse(false, 'No se pudo generar el código QR'));
				}
				$file = fopen($fileUrl, 'w');
				if($file === false) {
					$this->model->transaction->regresaTransaccion();
					$response = new \App\Lib\Response();
					return $res->withJson($response->SetResponse(false, 'No se pudo guardar el código QR en el servidor'));
				}
				fwrite($file, $QR);
				fclose($file);

				// Generar y almacenar PDF base
				$registroInfo = $this->model->registro->get($idReg);
				if(!$registroInfo->response) {
					$this->model->transaction->regresaTransaccion();
					$response = new \App\Lib\Response();
					return $res->withJson($response->SetResponse(false, 'No se pudo obtener la información del registro para generar el PDF'));
				}

				$pdfDir = 'data/pases';
				if(!is_dir($pdfDir) && !mkdir($pdfDir, 0777, true)) {
					$this->model->transaction->regresaTransaccion();
					$response = new \App\Lib\Response();
					return $res->withJson($response->SetResponse(false, 'No se pudo crear el directorio para guardar PDFs'));
				}

				$pdfName = $idReg.'.pdf';
				$pdfPath = $pdfDir.'/'.$pdfName;
				$pdfData = $registroInfo->result;
				$info = $pdfData;
				$outputPath = $pdfPath;
				$outputDest = 'F';
				$viewMode = false;

				try {
					ob_start();
					include __DIR__.'/../../templates/pdf_base.phtml';
					ob_end_clean();
				} catch(\Throwable $th) {
					if(ob_get_level() > 0) {
						ob_end_clean();
					}
					$this->model->transaction->regresaTransaccion();
					$response = new \App\Lib\Response();
					return $res->withJson($response->SetResponse(false, 'Error al generar PDF base: '.$th->getMessage()));
				}

				if(!file_exists($pdfPath)) {
					$this->model->transaction->regresaTransaccion();
					$response = new \App\Lib\Response();
					return $res->withJson($response->SetResponse(false, 'No se pudo generar el archivo PDF base'));
				}

				$pdfUrl = URL_ROOT.'/'.$pdfPath;

				// Enviar WhatsApp
				$body = '*¡Gracias por tu apoyo y por ser parte de esta gran experiencia!*';
				$resultado = json_decode($this->model->registro->sendWhPDF($data['telefono'], $body, $pdfUrl, $data['apodo'].'.pdf'));
				error_log('Respuesta envío de whatsApp: '.json_encode($resultado)." Teléfono: ".$data['telefono']." Registro: ".$idReg);
			}

			$this->model->transaction->confirmaTransaccion();
			if(is_object($registro)) {
				$registro->enviado = $resultado;
				$registro->pdf_base = $pdfUrl;
				$registro->response = true;
			}
			return $res->withJson($registro);
		});
	
        // Ruta para modificar un registro
		$this->put('edit/{id}', function ($req, $res, $args) {
			$this->model->transaction->iniciaTransaccion();
			$data = $req->getParsedBody();
			// $nombres = $data['nombres'];
			// unset($data['nombres']);
			// if($data['confirmacion'] == 0 || $data['confirmacion'] == 2){
				// $acompañantes = $this->model->registro->getAcomp($args['id'])->result;
				
				// foreach($acompañantes as $acomp){
					// $del = $this->model->registro->del($acomp->id);
				// }
				// if($data['confirmacion'] == 0) $seg_log = $this->model->seg_log->add('Cancela registro del invitado', $args['id'], 'registro');
				// else if($data['confirmacion'] == 2) $seg_log = $this->model->seg_log->add('Devuelve a pre-registro', $args['id'], 'registro');

				// $data['invitados'] = 0;
			// }else{
				// foreach($nombres as $nombre){
					// $this->model->registro->edit($nombre, $nombre['id']);
				// }
			// }
			$update = $this->model->registro->edit($data, $args['id']);
			if($update->response){
				$seg_log = $this->model->seg_log->add('Editar registro', $args['id'], 'registro'); 
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

		// Ruta para reenviar invitación
		$this->put('reenviar/{id}', function ($req, $res, $args) {
			$this->model->transaction->iniciaTransaccion();
			$info2 = $this->model->registro->getByID($args['id']);
			$correo = $info2->result->email;
			$nombreP = mb_strtoupper($info2->result->nombre.' '.$info2->result->paterno.' '.$info2->result->materno, 'utf-8');

			if($info2->response){
				// $qrCode = $args['id'].'WTMECW'.$info2->result->categoria.'W'.$info2->result->tipo;
				// $fileUrl = 'data/qr/'.$qrCode.'.png';
				// $qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chld=H|1&chs=400x400&chl='.urlencode($qrCode);
				// $QR = file_get_contents($qrUrl);
				// $file = fopen($fileUrl, 'w');
				// fwrite($file, $QR);
				// fclose($file);

				$to = $correo;
				$subject = 'Invitación Personal Corredor BMC';
				$body = '<div>';
				$body .= '<center>';
				$body .= '<img id="logo" src="'.URL_ROOT.'/assets/images/banner_mail.jpg'.'" alt="logo" class="img-responsive img-thumbnail" style="max-width: 600px;">';
				$body .= '<h2 class="alert-heading">Invitación Personal</h2>';
				$body .= '<h3 class="alert-heading">'.$nombreP.'</h3>';
				$body .= '<p>Caxxor se complace en invitarle al lanzamiento oficial del proyecto Corredor<br>';
				$body .= 'TMEC en una primera etapa enfocada a los activos de Sinaloa y Durango.</p>';
				$body .= '<br>';
				$body .= '<a href="'.URL_ROOT.'/registro/success/'.md5(sha1($args['id'])).'" style="background-color:#26dad2; border-radius: 5px; color:#fff; display:inline-block; font-size:16px; padding:12px 24px; text-align:center; text-decoration:none;">Descarga invitaciones QR</a>';
				$body .= '<br><br>';
				$body .= 'El Corredor TMEC es la inversión privada en infraestructura logística más<br>';
				$body .= 'importante de México, es uno de los desarrollos multimodales más<br>';
				$body .= 'trascendentes de Norteamérica que permitirá una reconfiguración estratégica a<br>';
				$body .= 'nivel global.</p>';
				$body .= '<br>';
				$body .= '<p>Por primera vez se mostrará la configuración y la propuesta de valor tanto de la<br>';
				$body .= 'Terminal Marítima de Sinaloa, del Puerto TMEC Durango, el Ferrocarril<br>';
				$body .= 'Durango-Sinaloa, así como la modernización de la oferta industrial y logística<br>';
				$body .= 'en la frontera de México y Estados Unidos.</p>';
				$body .= '<br><br>';
				$body .= '<a href="https://www.google.com/maps/place/Campo+Marte/@19.4245945,-99.1994549,17z/data=!4m6!3m5!1s0x85d201f96481562b:0xc913a93ef3917f87!8m2!3d19.4245953!4d-99.1973175!16s%2Fm%2F0crgkyf?entry=ttu" style="background-color:#26dad2; border-radius: 5px; color:#fff; display:inline-block; font-size:16px; padding:12px 24px; text-align:center; text-decoration:none;">Campo Marte</a>';

				$body .= '</div>';
				$body .= '</center>';
				$sent = sendMailSMTP($to, $subject, $body, '', '');
				$info2->sent = $sent;
				if($sent){
					$info2->state = $this->model->transaction->confirmaTransaccion();
					return $res->withJson($info2);
				}else{
					$info2->state = $this->model->transaction->regresaTransaccion();
					return $res->withJson($info2);
				}
			}else{
				$info2->state = $this->model->transaction->regresaTransaccion();
				return $res->withJson($info2->setResponse(false, 'Ocurrio algo extraño. Vuelve a intentar'));
			}
			return $res->withJson($info2);
		});

        // Ruta para dar de baja un registro
		$this->put('del/{id}', function ($req, $res, $args) {
			return $res->withJson($this->model->registro->del($args['id']));
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
			$sheet->setCellValue("C2", 'Nombre de Gafete');
			$sheet->setCellValue("D2", 'Puesto');
			$sheet->setCellValue("E2", 'Sucursal');
			$sheet->setCellValue("F2", 'Telefono');
			$sheet->setCellValue("G2", 'Correo Electrónico');
			$sheet->setCellValue("H2", 'Fecha Entrega');
			$sheet->setCellValue("I2", 'Fecha Impresión');

			$registros = $this->model->registro->getAll2()->result;

			$fila = 3;
			foreach($registros as $res){

				$sheet->setCellValue("A".$fila, $res->id);
				$sheet->setCellValue("B".$fila, "$res->nombre $res->paterno $res->materno");
				$sheet->setCellValue("C".$fila, $res->apodo);
				$sheet->setCellValue("D".$fila, $res->puesto);
				$sheet->setCellValue("E".$fila, $res->sucursal);
				$sheet->setCellValue("F".$fila, $res->telefono);
				$sheet->setCellValue("G".$fila, $res->email);
				$sheet->setCellValue("H".$fila, $res->fecha_entrega);
				$sheet->setCellValue("I".$fila, $res->fecha_impresion);

				$fila++;
			}
			$writer = new Csv($spreadsheet);
			$writer->setUseBOM(true);
			header('Content-Type: text/csv');
			header("Content-Disposition: attachment; filename=\"Reporte Kapital"."_".date('YmdHi').".csv\"");
			$writer->save('php://output');
		});

		// Enviar WhatsApp (individual o masivo)
		$this->put('sendGroupWhats/', function($request, $response, $arguments) {
			set_time_limit(0);
			$info = $request->getParsedBody();

			$registros = $this->model->registro->getPhones();
			if($registros->response){
				$telefonos = implode(' ', array_map(function($reg) {
					return $reg->telefono;
				}, $registros->result));
			}else{
				return $response->withJson([
					'response' => false,
					'message' => 'No se pudieron obtener los números de teléfono.',
				]);
			}
			// print_r($telefonos); exit;

			// Separar por coma, punto y coma o salto de línea
			$numeros = preg_split('/[\s,;]+/', $telefonos, -1, PREG_SPLIT_NO_EMPTY);
			$numeros = array_filter(array_map('trim', $numeros));

			// Validar que sean exactamente 10 dígitos y eliminar duplicados
			$invalidos = [];
			$numerosValidos = [];
			foreach($numeros as $numero) {
				if(!preg_match('/^\d{10}$/', $numero)) {
					$invalidos[] = $numero;
				} else {
					$numerosValidos[] = $numero;
				}
			}
			$numerosValidos = array_values(array_unique($numerosValidos));

			if(count($numerosValidos) === 0) {
				return $response->withJson([
					'response'     => false,
					'message'  => 'No hay números válidos (deben ser exactamente 10 dígitos).',
					'invalidos' => $invalidos,
				]);
			}

			$body = "Prueba de prueba
";

			$total = count($numerosValidos);
			$enviados = 0;
			$fallidos = [];

			foreach($numerosValidos as $telefono) {
				$resultado = json_decode($this->model->registro->sendWhats($telefono, $body));
				//print_r($resultado); exit;
				if(isset($resultado->sent) && $resultado->sent === 'true') {
					$enviados++;
				} else {
					$fallidos[] = $telefono;
				}
			}

			return $response->withJson([
				'response'      => true,
				'total'     => $total,
				'enviados'  => $enviados,
				'fallidos'  => count($fallidos),
				'invalidos' => $invalidos,
				'errors'    => $fallidos,
				'message'   => "Se enviaron $enviados de $total mensajes."
					. (count($invalidos) > 0 ? ' <br>Se omitieron '.count($invalidos).' número(s) inválido(s).' : '')
					. ($total < count($numeros) ? ' <br>Se eliminaron '.(count($numeros) - $total).' duplicado(s).' : ''),
			]);
		});

		//	Enviar whatsapp desde Meta Api
		$this->post('sendWhatsAppMessage/', function ($req, $res, $args) {
			$data = json_decode($req->getBody()->getContents(), true);
			if (empty($data['telefono'])) {
				return $res->withJson([
					'success' => false,
					'error' => 'El parámetro "telefono" es obligatorio'
				], 400);
			}
			$telefono = $data['telefono'];
			$resultado = $this->model->registro->sendWhatsAppMessage($telefono);
			return $res->withJson($resultado);
		});

		/*$this->get('imprimir/{codigo}/{id}', function($req, $res, $args){
			$registro = $this->model->registro->getByID($args['id'])->result;
			$params['correo'] = $registro->email;
			$params['codigo'] = $args['codigo'];
			$params['data'] = $registro;
			return $this->view->render($res, 'pdf.phtml', $params);
		});*/

		/*$this->get('print/{id}', function($req, $res, $args){
			$registro = $this->model->registro->getByID($args['id'])->result;
			$params['codigo'] = $registro->id.'WEFI'.$registro->id;
			$params['data'] = $registro;

			if($registro->impresion == null){
				$data = array('impresion' => new Literal('NOW()'));
				$resultado = $this->model->registro->edit($data, $args['id']);
			}

			return $this->view->render($res, 'pdf_gafete.phtml', $params);
		});*/

		$this->get('print/gafete/{id}', function($req, $res, $args){
			$this->model->transaction->iniciaTransaccion();
			date_default_timezone_set('America/Mexico_City');
			$data = array('fecha_impresion' => new Literal('NOW()'));
			$resultado = $this->model->registro->edit($data, $args['id']);
			if($resultado->response){
				$registro = $this->model->registro->get($args['id'])->result;
				$params['data'] = $registro;
				$resultado->state = $this->model->transaction->confirmaTransaccion();
				return $this->view->render($res, 'pdf_gafete.phtml', $params);
			}else{
				$resultado->state = $this->model->transaction->regresaTransaccion();
				return $resultado->withJson($resultado);
			}
		});

		$this->get('print/base/{id}', function($req, $res, $args){
			$registro = $this->model->registro->get($args['id'])->result;
			$params['data'] = $registro;
			return $this->view->render($res, 'pdf_base.phtml', $params);
		});

		/*$this->get('gafete/{id}', function($req, $res, $args){
			$registro = $this->model->registro->getByID($args['id'])->result;
			$compania = $registro->compania;
			$registro->compania = $compania;
			$params['data'] = $registro;

			return $this->view->render($res, 'gafete.phtml', $params);
		});*/

		// Obtener WhatsApp
		$this->put('registroWA/', function($request, $response, $arguments) {
				
			//$info = $this->model->registro->get($arguments['id']);
			//$info2 = $this->model->encuesta->getByRegistro($arguments['id'])->result;
			
			//$qrCode = $arguments['id'].'U'.$info->result->codigo.'W'.$info2->id;

			$params['header'] = 'https://universal.clase.digital/assets/images/Universal2024.png';
			$params['telefono'] = '7711617545';
			$params['body'] = '
*Daniel*, has quedado *confirmado* al evento de *Universal Workshop Zapopan, Jal.* 
	
*Programa*

*8:00 am* Registro
*8:00 am* Desayuno tipo bufet
*9:00 am* Presentación Universal Destinations & Experiences
*10:00 am* Sesiones con operadores
*11:00 am* Optativa Super Nintendo World
*11:30 am* Optativa Tips de Expertos
Fin del evento

Te esperamos el día *Jueves 18 de abril* en *Cinemex Plaza Patria*, ubicado en Av. Patria 1950 colonia Jacarandas, Avenida Americas y Avila Camacho, Plaza Patria, 45160 Zapopan, Jal. a las *08:00 am*.

*¡Aún hay más!*
Presenta tu certificado vigente de entrenamientos de Universal Destinations & Experiences impreso y recibe una sorpresa el día del evento.

Para tener los certificados debes concluir los entrenamientos que hay en Universal Partner Community
	
';
			$this->view->render($response, 'registroWA.php', $params);
			return 'ok';
		});

	});

	function sendMailSMTP($to, $subject, $body, $cc, $files){
		if (!isset($_SESSION)) session_start();
		$disc = "<br><br><br><small>======================================================<br>";
		$disc .="Este correo fue enviado desde una cuenta no monitoreada. Por favor no responda este correo.</small>";
		$body = $body.$disc;

		$mail = new PHPMailer;
		// $mail->SMTPDebug = 3;

		$mail->isSMTP();
		$mail->SMTPOptions = array(
			'ssl'=> array(
				'verify_peer' => false,
				'verify_peer_name'=> false,
				'allow_self_signed' => true
			)
		);

		$mail->SMTPAuth = true;
		$mail->SMTPSecure = 'tls';
		$mail->Host = 'smtp.gmail.com';
		$mail->Username = $_SESSION['mail_username'];
		$mail->Password = $_SESSION['mail_pwd'];
		$mail->Port = 587;
		// $mail->Mailer = 'mail';

		$mail->setFrom($_SESSION['mail_username'], SITE_NAME);
		// $mail->setFrom('notifica@clase.digital', 'Women Economic Forum',0);

		$mail->addAddress($to);
		if($cc != '') $mail->AddCC($cc);

		$mail->isHTML(true);
		$mail->CharSet = 'UTF-8';

		$mail->Subject = $subject;
		$mail->Body = $body;

		for($x=0;$x<count($files);$x++){
			$filename = explode('/', $files[$x]);
			$filename = $filename[count($filename)-1];
			$mail->AddAttachment($files[$x],$filename);
		}

		if(!$mail->send()){
			return "Mailer Error: " . $mail->ErrorInfo;
			//return "FALSE";
		}else{
			//return "Message has been sent successfully";
			return "TRUE";
		}
	}

	/*function moveUploadedFileFoto($directory, UploadedFile $uploadedFile, $id){
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        if($extension == 'jpg' || $extension == 'jpeg' ){
            $extension = 'jpg';
        }else{
            return '0';
        }
        $basename = $id;
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }*/
?>