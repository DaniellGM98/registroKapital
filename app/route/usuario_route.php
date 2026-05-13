<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception,
		Slim\Http\UploadedFile;
		require_once './core/defines.php';

	$app->group('/usuario/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Ruta de usuario');
		});

        // Agregar usuario
        $this->post('add/', function ($req, $res, $args) {
			$parsedBody = $req->getParsedBody();
			$idUsuario = $this->model->usuario->add($parsedBody);
			if($idUsuario->response){
				$userId = $idUsuario->result;
				$this->model->seg_log->add('Alta usuario', $userId, 'seg_usuario');
				for ($i=1; $i < 7; $i++) { 
					$this->model->seg_permiso->add(array('usuario_id' => $userId, 'seg_accion_id' => $i));
				}
			}
			return $res->withHeader('Content-type', 'application/json')
				->write(json_encode($idUsuario));
    	});

        // Obtener todos los usuarios
		$this->get('getAll/{pagina}/{limite}[/{usuario_tipo}[/{busqueda}]]', function($request, $response, $arguments) {
			$arguments['usuario_tipo'] = isset($arguments['usuario_tipo'])? $arguments['usuario_tipo'] : 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: null;
			return $response->withJson($this->model->usuario->getAll($arguments['pagina'], $arguments['limite'], $arguments['usuario_tipo'], $arguments['busqueda']));
		})->add( new MiddlewareToken() );

		// Editar usuario
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$usuario_id = $arguments['id'];
			$usuario = $this->model->usuario->edit($parsedBody, $usuario_id); 
			if($usuario->response) {
				$seg_log = $this->model->seg_log->add('Actualización información usuario', $usuario_id, 'seg_usuario', 1);
				if(!$seg_log->response) {
					$seg_log->state = $this->model->transaction->regresaTransaccion(); 
					return $response->withJson($seg_log);
				}
				$usuario->SetResponse(true, 'Contraseña actualizada');
			}else{
				$this->model->transaction->regresaTransaccion(); 
				return $response->withJson($usuario); 
			}
			$this->model->transaction->confirmaTransaccion();
			return $response->withJson($usuario);
		})->add( new MiddlewareToken() );

		// Eliminar usuario
		$this->put('del/{id}', function ($req, $res, $args) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$resultado = $this->model->usuario->del($args['id']);
			if($resultado){
				$add = $this->model->seg_log->add('Elimina Usuario', $args['id'], 'seg_usuario');
				if(!$add->response){
					$this->model->transaction->regresaTransaccion();
					return $this->response->withJson($add); 
				}
			}
			$this->response->state = $this->model->transaction->confirmaTransaccion();
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($resultado));
	    })->add( new MiddlewareToken() 
		);

		// Obtener usuario por id
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->get($arguments['id']));
		})->add( new MiddlewareToken() );

		// Buscar usuario
		$this->get('find/{busqueda}[/{usuario_tipo}]', function($request, $response, $arguments) {
			$arguments['usuario_tipo'] = isset($arguments['usuario_tipo'])? $arguments['usuario_tipo']: 0;
			return $response->withJson($this->model->usuario->find($arguments['busqueda'], $arguments['usuario_tipo']));
		})->add( new MiddlewareToken() );

		// Ver datos de session
		$this->get('getSession/', function($request, $response, $arguments) {
			print_r ($_SESSION);
		})->add( new MiddlewareToken() );

		// Inicio de sesion
		$this->post('login/', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$parsedBody= $request->getParsedBody();
			$email= $parsedBody['username'];
			$password = $parsedBody['password'];
			$usuario = $this->model->usuario->login($email, $password);
			if($usuario->response) {
				$token = $this->model->seg_sesion->crearToken($usuario->result);
				$data = [
					'usuario_id' => $usuario->result->id,
					'ip_address' => $_SERVER['REMOTE_ADDR'],
					'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					'iniciada' => date('Y-m-d H:i:s'),
					'token' => $token
				];
				$this->model->seg_sesion->add($data);
				$this->model->seg_log->add('Inicio de sesión', $usuario->result->id, 'seg_usuario');
				$this->logger->info("Slim-Skeleton 'usuario/login/' ".$usuario->result->id);
				$_SESSION['usuario'] = $this->model->usuario->get($usuario->result->id)->result;

				if(array_search('/accesos', array_column($_SESSION['permisos'], 'url')) !== false) $home = URL_ROOT.'/accesos';
				if(array_search('/usuarios', array_column($_SESSION['permisos'], 'url')) !== false) $home = URL_ROOT.'/usuarios';
				if(array_search('/estadisticas', array_column($_SESSION['permisos'], 'url')) !== false) $home = URL_ROOT.'/estadisticas';
				if(array_search('/registros', array_column($_SESSION['permisos'], 'url')) !== false) $home = URL_ROOT.'/registros';
				$_SESSION['home'] = $home;
				$usuario->home = $home;
			}
			return $response->withJson($usuario);
		});

		// Obtener permisos
        $this->get('getPermisos/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->getPermisos($arguments['id']));
		})->add( new MiddlewareToken() );

		// Cambiar contraseña de usuario
		$this->put('editPassword/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$usuario_id = $arguments['id'];
			$dataUsuario = [
				'nombre'=>$parsedBody['nombre'],
				'apellidos'=>$parsedBody['apellidos'], 
				'email'=>$parsedBody['email'], 
				'tipo'=>$parsedBody['tipo'], 
				'password'=>$parsedBody['password']
			];
			$usuario = $this->model->usuario->edit($dataUsuario, $usuario_id); 
			if($usuario->response) {
				$seg_log = $this->model->seg_log->add('Contraseña actualizada', $usuario_id, 'seg_usuario', 1);
				if(!$seg_log->response) {
					$seg_log->state = $this->model->transaction->regresaTransaccion(); 
					return $response->withJson($seg_log);
				}
				$usuario->SetResponse(true, 'Contraseña actualizada');
			}else{
				$this->model->transaction->regresaTransaccion(); 
				return $response->withJson($usuario); 
			}
			$this->model->transaction->confirmaTransaccion();
			return $response->withJson($usuario);
		})->add( new MiddlewareToken() );

		// Ruta para buscar
		$this->get('findBy/{f}/{v}', function ($req, $res, $args) {
			return $res->withHeader('Content-type', 'application/json')
						->write(json_encode($this->model->usuario->findBy($args['f'], $args['v'])));			
		});

		// Cierre de sesión
		$this->get('logout', function($request, $response, $arguments) use ($app) {
			if(!isset($_SESSION)) { 
				session_start(); 
			}
			$this->model->seg_log->add('Cierra sesión', $_SESSION['usuario']->id, 'seg_usuario');
			$resultado = $this->model->seg_sesion->logout();
			return $this->response->withRedirect('../login');
		});

		$this->post('uploadImagenUsuario[/{usuario_id}]', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$usuario_id = isset($arguments['usuario_id'])? $arguments['usuario_id']: $_SESSION['usuario']->id;

			$directory = 'data/foto/';
			$uploadedFiles = $request->getUploadedFiles();
			$uploadedFile = $uploadedFiles['imagen'];
			$filename = '0';
			if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
				//session_start();
				$fileName = md5($usuario_id).'.jpg';
				$filename = $this->model->usuario->moveUploadedFile($directory, $uploadedFile, $fileName);
				if($filename == '0') {
					$this->response->result = 0;
					return $this->response->SetResponse(false, 'Extensión de archivo invalido, solo se aceptan imagenes en formato jpg');
				} else {
					if(!isset($arguments['usuario_id'])) { $_SESSION['usuario']->foto = true; }
					$this->response->result = 1;
					$this->response->filename = $filename.'?'.rand();
					$this->response->SetResponse(true,'Archivo cargado con exito: ' . $filename);
					return $response->withjson($this->response);
				}
			}

			$this->response->result = 1;
			return $this->response->SetResponse(true,'Archivo cargado con exito: ' . $filename);
		})->add( new MiddlewareToken() );

		$this->put('changePassword/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->usuario->changePassword($request->getParsedBody(), $_SESSION['usuario']->id);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cambiar Contraseña', $_SESSION['usuario']->id, 'usuario');
				if($seg_log->response) {
					$this->response->result = $resultado->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->put('editProfile/', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			$profileInfo = $this->model->usuario->get($_SESSION['usuario']->id)->result;
			$areTheSame = true; foreach($profileInfo as $field => $value) { if(isset($parsedBody[$field]) && $parsedBody[$field] != $value) { $areTheSame = false; break; }}
			$resultado = $this->model->usuario->edit($parsedBody, $_SESSION['usuario']->id);
			if($resultado->response || $areTheSame) { $resultado->areTheSame = $areTheSame;
				if(!$resultado->areTheSame) {
					$_SESSION['usuario']->nombre = $parsedBody['nombre'];
					$_SESSION['usuario']->apellidos = $parsedBody['apellidos'];
					$_SESSION['usuario']->telefono = $parsedBody['telefono'];
					$resultado->nombre = $parsedBody['nombre'].' '.$parsedBody['apellidos'];
				}

				$resultado->SetResponse(true);
			}
			return $response->withJson($resultado);
		})->add( new MiddlewareToken() );

		// solicitar codigo de recuperacion
		$this->post('recoveryPass', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$cadena = "1234567890";
			$longitudCadena=strlen($cadena);
			$pass = "";
			$longitudPass = 6;
			for($i=1 ; $i<=$longitudPass ; $i++) {
				$pos = rand(0, $longitudCadena-1);
				$pass .= substr($cadena, $pos, 1);
			}
			if(!is_null($parsedBody)){
				$var = $this->model->usuario->verifyEmail($parsedBody['email']);
				if($var->response) {
					$correo = $parsedBody['email'];
					$body = $this->model->usuario->getRecoverPasswordMailTemplate($pass);
					//$this->model->usuario->changeRecoveryCode($pass, $correo);
					$this->model->usuario->changePasswordByEmail($pass, $correo);				
					$this->model->usuario->sendEmail($correo, 'Restablecer contraseña', $body);
					$this->response->result = '1';
					return $response->withjson($this->response->SetResponse(true, 'Correo enviado correctamente'));
				}else {
					$this->response->result = '0';
					return $response->withjson($this->response->SetResponse(false, 'Correo no esta dado de alta'));
				}
			}else{
				$this->response->result = '0';
				return $response->withjson($this->response->SetResponse(false, 'Correo invalido'));
			}
		});

		// Inicio de sesion app
		$this->post('app/login/', function ($req, $res, $args) {
			$parsedBody= $req->getParsedBody();
			$email= $parsedBody['email'];
			$pass = $parsedBody['password'];
			$resultado = $this->model->usuario->loginApp($email, $pass);
			if(is_object($resultado->result)){
				//$resultado->result = 0;
				//$resultado->SetResponse(true,'Acceso correcto');
			}else{
				$resultado->result = (object)[];
				$resultado->SetResponse(false,'Verifique sus datos');
			}
			return json_encode($resultado);
   		});
	});	
?>