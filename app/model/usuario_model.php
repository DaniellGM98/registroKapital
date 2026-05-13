<?php
	namespace App\Model;
	use PDOException;
	use App\Lib\Response;
	use Slim\Http\UploadedFile;
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
	require '../vendor/autoload.php';

	class UsuarioModel {
		private $db;
		private $table = 'seg_usuario';
		private $tableP = 'seg_permiso';
		private $tableA = 'seg_accion';
		private $tableM = 'seg_modulo';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
			require_once './core/defines.php';
		}

		// Agregar usuario
		public function add($data){
			$data['password'] = strrev(md5(sha1($data['password'])));
			$data['fecha_modificacion'] = date('Y-m-d H:i:s');
			try{
				$resultado = $this->db
									->insertInto($this->table, $data)
									->execute();
			}catch(\PDOException $ex){
				$this->response->result = $resultado;
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, 'catch: Add model usuario');	
			}
			if($resultado!=0){
				$this->response->result = $resultado;
				return $this->response->SetResponse(true, 'Id del registro: '.$resultado);    
			}else{
				$this->response->result = $resultado;
				return $this->response->SetResponse(false, 'No se inserto el registro');
			}	
		}

        // Obtener todos los usuarios
		public function getAll($pagina, $limite, $usuario_tipo_id, $busqueda) {
			if($usuario_tipo_id==null){
				$usuario_tipo_id=0;
			}
			$inicial = $pagina * $limite;
			$busqueda = $busqueda==null ? "_" : $busqueda;
			$usuario = $this->db
				->from($this->table)
                ->select(null)->select("id, nombre, apellidos, email, username, CONCAT_WS(' ',
				nombre, apellidos) as nomcom, estado, telefono, ultimo_acceso")
				->where("CONCAT_WS(' ', nombre, apellidos, email, username) LIKE '%$busqueda%'")
				->where("estado", 1)
				->limit("$inicial, $limite")
				->orderBy('apellidos ASC, nombre ASC')
				->fetchAll();
			if($usuario) {
                unset($usuario->password);
            }
			$this->response->result = $usuario;
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("CONCAT_WS(' ', nombre, apellidos, email, username) LIKE '%$busqueda%'")
				->where("estado", 1)
				->fetch()
				->Total;
			return $this->response->SetResponse(true);
		}

		// Editar usuario
		public function edit($data, $id) {
			if(isset($data['password'])) $data['password'] = strrev(md5(sha1($data['password'])));
			$data['fecha_modificacion'] = date('Y-m-d H:i:s');
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
				if($this->response->result!=0) { 
					$this->response->SetResponse(true, 'Id actualizado: '.$id); 
				} else { 
					$this->response->SetResponse(false, 'No se edito el registro'); 
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: Edit model $this->table");
			}
			return $this->response;
		}

		// Eliminar usuario
		public function del($id){
			$set = array('estado' => 0,'fecha_modificacion' => date("Y-m-d H:i:s"));
			$this->response->result = $this->db
				->update($this->table)
				->set($set)
				->where('id', $id)
				->execute();
			if($id!=0){
				return $this->response->SetResponse(true, "Id baja: $id");
			}else{
				return $this->response->SetResponse(true, "Id incorrecto");
			}
		}

		// Obtener usuario por id
		public function get($id) {
			$usuario = $this->db
				->from($this->table)
				//->select(null)->select("id, nombre, apellidos, email, username")
				->where('id', $id)
				->fetch();
			if($usuario) {
				unset($usuario->contrasena);
				$this->response->result = $usuario;
				$this->response->SetResponse(true);
			}
			else	$this->response->SetResponse(false, 'No existe el registro');
			return $this->response;
		}

		// Buscar usuario
		public function find($busqueda, $usuario_tipo=0) {
			if($usuario_tipo==null){
				$usuario_tipo=0;
			}
			$usuarios = $this->db
				->from($this->table)
				->where("CONCAT_WS(' ', nombre, apellidos, email, username) LIKE '%$busqueda%'")
				->where("estado", 1)
				->fetchAll();
			foreach($usuarios as $usuario) { unset($usuario->contrasena); }
			$this->response->result = $usuarios;
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS total")
				->where("CONCAT_WS(' ', nombre, apellidos, email, username) LIKE '%$busqueda%'")
				->where("estado", 1)
				->fetch()
				->total;
			return $this->response->SetResponse(true);
		}

		// inicio de sesión
		public function login($username, $password) {
			$password = strrev(md5(sha1($password)));
			$usuario = $this->db
				->from($this->table)
				->where('email', $username)
				->where('password', $password)
				->where('estado', 1)
				->fetch();
			if(is_object($usuario)) {
				unset($usuario->password);
				$this->ultimoAcceso($usuario->id);
				$newModulos = array();
				$newModulos = $this->getPermisos($usuario->id);
				$this->addSessionLogin($usuario, $newModulos);
				$this->response->SetResponse(true, 'Acceso correcto');
			} else {
				$this->response->SetResponse(false, 'Verifica tus datos');
			}
			$this->response->result = $usuario;
			return $this->response;
		}

		// Login app
		public function loginApp($email, $password) {
			$password = strrev(md5(sha1($password)));
			$usuario = $this->db
				->from($this->table)
				->where('email', $email)
				->where('password', $password)
				->where('estado', 1)
				->fetch();
			if(is_object($usuario)) {
				unset($usuario->password);
				$this->ultimoAcceso($usuario->id);
				//$this->response->SetResponse(true, 'Acceso correcto,'+$usuario->tipo+','+$usuario->nombre+','+$usuario->nombre_encargado);
				$this->response->SetResponse(true, 'Acceso correcto,' . $usuario->tipo_app . ',' . $usuario->nombre . ',' . $usuario->apellidos);
			} else {
				$this->response->SetResponse(false, 'Verifique sus datos');
			}
			$this->response->result = $usuario;
			return $this->response;
		}

		// Modificar ultimo acceso
		public function ultimoAcceso($id) {
			date_default_timezone_set('America/Mexico_City');
			$data['ultimo_acceso'] = date("Y-m-d H:i:s");
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
				if($this->response->result!=0) { 
					$this->response->SetResponse(true, 'Id actualizado: '.$id);
				} else { 
					$this->response->SetResponse(false, 'No se edito el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: Edit model $this->table");
			}
			return $this->response;
		}

		// Obtener permisos
		public function getPermisos($usuario){
			$newModulos = array();
			$modulos = $this->getModulos();
			foreach ($modulos as $modulo) {
				$acciones = $this->getAcciones($usuario, $modulo->id);
				$contador = count($acciones);
				$accionesUrl = 0;
				if($contador>0){
					$modulo->acciones = $acciones;
					foreach ($acciones as $accion)
						if($accion->url != '') $accionesUrl++;
					$newModulos[] = $modulo;  
				}	
				$modulo->accionesUrl = $accionesUrl;
			}
			return $newModulos;
		}

		// Obtener modulos de permisos
		public function getModulos(){
			return $this->db
				->from($this->tableM)
				->where('estado', 1)
				->orderBy('orden')
				->fetchAll();
		}

		// Obtener acciones de permisos
		public function getAcciones($usuario_id, $seg_modulo_id){
			return $this->db
				->from($this->tableP)
				->select(null)->select("DISTINCT $this->tableA.id, $this->tableA.nombre, $this->tableA.url, $this->tableM.id_html, $this->tableA.icono")
				->innerJoin("$this->tableA on $this->tableA.id = $this->tableP.seg_accion_id")
				->innerJoin("$this->tableM on $this->tableM.id = $this->tableA.seg_modulo_id")
				->where("$this->tableP.usuario_id", $usuario_id)
				->where(intval($seg_modulo_id)>0? "$this->tableA.seg_modulo_id = $seg_modulo_id": "TRUE")
				->where("$this->tableA.estado", 1)
				->fetchAll();
		}

		// Agregar session
		public function addSessionLogin($usuario, $permisos){
			$browser = $_SERVER['HTTP_USER_AGENT'];
			$ipAddr = $_SERVER['REMOTE_ADDR'];
			if (!isset($_SESSION)) { session_start(); }
			$_SESSION['ip']  = $ipAddr;
			$_SESSION['navegador']  = $browser;
			$_SESSION['usuario']  = $usuario;
			$_SESSION['permisos']  = $permisos;
		}

		// find by field = value
		public function findBy($field, $value){
			$this->response->result = $this->db
				->from($this->table)
				->where($field, $value)
				->where('estado', 1)
				->fetchAll();
			return $this->response->SetResponse(true);
		}

		public function changePassword($data, $id) {
			$old_password = strrev(md5(sha1($data['old_password'])));
			$password['password'] = strrev(md5(sha1($data['new_password'])));
			$this->response->result = $this->db
				->update($this->table, $password)
				->where('id', $id)
				->where('password', $old_password)
				->execute();

			if($this->response->result == '1') { $this->response->SetResponse(true, 'contraseña actualizada'); }
			else { $this->response->SetResponse(false, 'Verifica la contraseña actual'); }

			return $this->response;
		}

		public function changePasswordByEmail($data, $email) {
			$password['password'] = strrev(md5(sha1($data)));
			$this->response->result = $this->db
				->update($this->table, $password)
				->where('email', $email)
				->execute();

			if($this->response->result == '1') { $this->response->SetResponse(true, 'contraseña actualizada'); }
			else { $this->response->SetResponse(false, 'Verifica la contraseña actual'); }

			return $this->response;
		}

		function moveUploadedFile($directory, UploadedFile $uploadedFile, $filename) {
			$uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

			return $filename;
		}

		// Verificación de email
		public function verifyEmail($data) {
			
			$usuario = $this->db
				->from($this->table)
				->where('email', $data)
				->where('estado', 1)
				->fetch();

			if(is_object($usuario)) {
				$this->response->SetResponse(true, 'acceso correcto'); 
			} else {
				$this->response->SetResponse(false, 'verifica tus datos'); 
			}
			$this->response->result = $usuario;
			return $this->response;
		}

		// Template para correo
		public function getRecoverPasswordMailTemplate($pass) {
			return "
			<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\"\"http://www.w3.org/TR/REC-html40/loose.dtd\">
			<html>
				<head>
					<base target=\"_blank\">
					<title>Recuperar contrase&ntilde;a</title>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
					<meta name=\"viewport\" content=\"width=device-width; initial-scale=1.0; maximum-scale=1.0;\">
					<link href=\"https://fonts.googleapis.com/css?family=Sarala:400,700|Hind+Vadodara:400,300,500,600,700|Open+Sans:400,800italic,800,700italic,700,600italic,600,400italic,300italic,300|Raleway:400,200,100,300,500,600,700,800,900|Montserrat:400,700,800,900|Rubik:400,300,300italic,400italic,900italic,900,700italic,700,500italic,500|Lato:400,100italic,100,300,300italic,400italic,700,700italic,900,900italic|Questrial|Muli:400,300italic,300,400italic|Work+Sans:400,100,200,300,500,600,700,800,900|Nunito:400,700,300|Anton|Playfair+Display:400,400italic,700,700italic,900,900italic|Varela+Round|Oswald:200,300,400,500,600,700\" rel=\"stylesheet\" type=\"text/css\">

					<style type=\"text/css\">
						td[class].anton {font-family: 'Anton', Arial, sans-serif !important}
						td[class].raleway {font-family: 'Raleway', Arial, sans-serif !important}
						td[class].vadodara {font-family: 'Vadodara', Arial, sans-serif !important}
						td[class].opensans {font-family: 'Open Sans', Arial, sans-serif !important}
						td[class].rubik {font-family: 'Rubik', Arial, sans-serif !important}
						td[class].montserrat {font-family: 'Montserrat', Arial, sans-serif !important}
						td[class].hindvadodara {font-family: 'Hind Vadodara', Arial, sans-serif !important}
						td[class].lato {font-family: 'Lato', Arial, sans-serif !important}
						td[class].questrial {font-family: 'Questrial', Arial, sans-serif !important}
						td[class].muli {font-family: 'Muli', Arial, sans-serif !important}
						td[class].worksans {font-family: 'Work Sans', Arial, sans-serif !important}
						td[class].nunito {font-family: 'Nunito', Arial, sans-serif !important}
						td[class].playfair {font-family: 'Playfair Display', Arial, sans-serif !important}
						td[class].varela {font-family: 'Varela Round', Arial, sans-serif !important}
						td[class].oswald {font-family: 'Oswald', Arial, sans-serif !important}

						div, p, a, li, td { -webkit-text-size-adjust:none; }

						*{-webkit-font-smoothing: antialiased;-moz-osx-font-smoothing: grayscale;}
						td{word-break: break-word;}
						a{word-break: break-word; text-decoration: none; color: inherit;}

						body .ReadMsgBody {width: 100%; background-color: #ffffff;}
						body .ExternalClass {width: 100%; background-color: #ffffff;}
						body{width: 100%; height: 100%; background-color: #ffffff; margin:0; padding:0; -webkit-font-smoothing: antialiased;}
						html{ background-color:#ffffff; width: 100%;}

						body p {padding: 0!important; margin-top: 0!important; margin-right: 0!important; margin-bottom: 0!important; margin-left: 0!important; }
						body img {user-drag: none; -moz-user-select: none; -webkit-user-drag: none;}
						body .hover:hover {opacity:0.85;filter:alpha(opacity=85);}
						body #opacity {opacity:0.90;filter:alpha(opacity=90);}

						body .img120 img {width: 120px; height: auto;}
						body .img20 img {width: 20px; height: auto;}
						body .image490 img {width: 490px; height: auto;}
						body .image190 img {width: 190px; height: auto;}
						body .icon18 img {width: 18px; height: auto;}
						body .img61 img {width: 61px; height: auto;}
						body .img10 img {width: 10px; height: auto;}
						body .img24 img {width: 24px; height: auto;}
						body .image430 img {width: 430px; height: auto;}
						body .img98 img {width: 98px; height: auto;}
						body .img13 img {width: 13px; height: auto;}
					</style>

					<style type=\"text/css\">
						@media only screen and (max-width: 640px){
							body body{width:auto!important;}
							body table[class=full2] {width: 100%!important; clear: both; }
							body .premadeFullScale img{width:100%!important;}
						}
					</style>

					<style type=\"text/css\">
						@media only screen and (max-width: 479px){ 
							body body{width:auto!important;}
							body table[class=full] {width: 100%!important; clear: both; }
							body table[class=full2] {width: 100%!important; clear: both; }
							body table[class=fullCenter] {width: 100%!important; text-align: center!important; clear: both; }
							body td[class].fullCenter {width: 100%!important; text-align: center!important; clear: both; }
							body table[class=fullLeft] {width: 100%!important; text-align: left!important; clear: both; }
							body table[class=fullRight] {width: 100%!important; text-align: right!important; clear: both; }
							body .fullCenter {width: 100%!important; text-align: center!important; clear: both; }
							body .fullLeft {width: 100%!important; text-align: left!important; clear: both; }
							body .fullRight {width: 100%!important; text-align: right!important; clear: both; }
							body *[class=erase] {display: none;}
							body *[class=buttonScale] {float: none!important; text-align: center!important; display: inline-block!important; clear: both;}	
							body *[class=h15] {width: 100%!important; height: 15px!important;}
							body *[class=h20] {width: 100%!important; height: 20px!important;}
							body *[class=h30] {width: 100%!important; height: 30px!important;}
							body *[class=h50] {width: 100%!important; height: 50px!important;}
							body td[class].pad20 {padding-left: 20px!important; padding-right: 20px!important; text-align: center!important; clear: both; }
							body .erase {display: none!important;}
							body .pad20 {padding-left: 20px!important; padding-right: 20px!important; text-align: center!important; clear: both; }
							body .pad10 {padding-left: 10px!important; padding-right: 10px!important; text-align: center!important; clear: both; }
							body .imgFull img {width: 100%!important;}
							body .newRow {display: block!important;}
							body .f40 {font-size: 40px!important; line-height: 48px!important;}
							body .f50 {font-size: 50px!important;}
							body .f60 {font-size: 60px!important;}
							body .f60_2 {font-size: 60px!important; line-height: 70px!important;}
							body .premadeFullScale img{width:100%!important;}
						}
					</style>

				</head>
				<body style='margin: 0; padding: 0;'>
					<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" class=\"full\" bgcolor=\"#bf8c86\" style=\"background-color: #bf8c86;\">
						<tbody>
							<tr>
								<td align=\"center\" bgcolor=\"#ffffff\" style=\"background-image: url('".URL_ROOT."/assets/images/mail/bg1_t3.png'); -webkit-background-size: cover; -moz-background-size: cover; -o-background-size: cover; background-size: cover; background-position: center center; background-repeat: no-repeat;\" background=\"'https://builder.dynamicxx.comimages/bg1_t3.png'\">
									<table class=\"full\" align=\"center\" border=\"0\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
										<tbody>
											<tr>
												<td width=\"100%\" height=\"70\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
											</tr>
											<tr>
												<td align=\"center\" class=\"pad10\">
													<table width=\"510\" border=\"0\" bgcolor=\"#46729b\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" style=\"box-shadow: 7px 7px 20px #000000; -webkit-box-shadow: 7px 7px 20px #000000; -moz-box-shadow: 7px 7px 20px #000000; background-color: #46729b; border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; width: 510px;\" class=\"fullCenter\">
														<tbody>
															<tr>
																<td align=\"center\" style=\"background-image: url('".URL_ROOT."/assets/images/mail/bg2_t3.png'); -webkit-background-size: cover; -moz-background-size: cover; -o-background-size: cover; background-size: cover; background-position: center center; background-repeat: no-repeat;\" background=\"'https://builder.dynamicxx.comimages/bg2_t3.png'\">
																	<table width=\"400\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" class=\"full\" style=\"width: 400px; border-radius: 10px;\">
																		<tbody>
																			<tr>
																				<td width=\"100%\" align=\"center\" class=\"pad20\">
																					<table width=\"320\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" class=\"full\" style=\"width: 320px;\">
																						<tbody>
																							<tr>
																								<td width=\"100%\" height=\"30\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																							</tr>
																							<tr>
																								<td valign=\"middle\" align=\"center\" width=\"100%\" class=\"fullCenter img96\" style=\"line-height: 1px;\">
																									<a href=\"#\" style=\"text-decoration: none;\"> 
																										<img src=\"".URL_ROOT."/assets/images/mail/logo1_t3.png\" width=\"96\" alt=\"\" border=\"0\" class=\"hover\">
																									</a>
																								</td>
																							</tr>
																							<tr>
																								<td width=\"100%\" height=\"30\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																							</tr>
																							<tr>
																								<td width=\"100%\" style=\"color: #ffffff; font-family: Helvetica, Arial, sans-serif, 'Raleway'; font-weight: 700; vertical-align: top; font-size: 22px; text-align: center; line-height: 35px;\" class=\"fullCenter raleway f40\">
																								RECIBIMOS T&Uacute; SOLICITUD
																								</td>
																							</tr>
																							<tr>
																								<td width=\"100%\" height=\"30\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																							</tr>
																							<tr>
																								<td width=\"100%\" style=\"color: #ffffff; font-family: Helvetica, Arial, sans-serif, 'Raleway'; font-weight: 400; vertical-align: top; font-size: 18px; text-align: center; line-height: 32px;\" class=\"fullCenter raleway\">
																								Tu nueva contraseña es: <strong>$pass</strong>
																								Puedes cambiarla en cuanto inicies sesi&oacute;n
																								</td>
																							</tr>
																							<tr>
																								<td width=\"100%\" height=\"30\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																							</tr>
																							<tr>
																								<td width=\"100%\" style=\"font-family: Helvetica, Arial, sans-serif, 'Raleway'; font-weight: 400; vertical-align: top; font-size: 18px; text-align: center; line-height: 32px;\" class=\"fullCenter raleway\">
																								</td>
																							</tr>
																							
																							
																							<tr>
																								<td width=\"100%\" height=\"30\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																							</tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
													<table width=\"510\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" style=\"border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; width: 510px;\" class=\"fullCenter\">
														<tbody>
															<tr>
																<td align=\"center\">
																	<table width=\"400\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" style=\"border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; width: 400px;\" class=\"fullCenter\">
																		<tbody>
																			<tr>
																				<td width=\"100%\" height=\"20\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																			</tr>
																			<tr>
																				<td width=\"100%\" style=\"color: #ffffff; font-family: Helvetica, Arial, sans-serif, 'Varela Round'; font-weight: 400; vertical-align: top; font-size: 12px; text-align: center; line-height: 18px;\" class=\"fullCenter varela\">
																					© ".SITE_NAME.". Todos los derechos reservados.
																				</td>
																			</tr>
																			<tr>
																				<td width=\"100%\" height=\"60\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																			</tr>
																			<tr>
																				<td width=\"100%\" height=\"1\" style=\"font-size: 1px; line-height: 1px;\">&nbsp;</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table> 
								</td>
							</tr>
						</tbody>
					</table>
					<table align=\"center\" class=\"full\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"table-layout:fixed;\">
						<tbody>
							<tr>
								<td class=\"erase androidfix\" style=\"line-height:1px; font-size:1px;\" width=\"400\"><img src=\"".URL_ROOT."/assets/images/mail/transparant.png\" height=\"1\" style=\"max-height:1px; min-height:1px; display:block; width:400px; min-width:400px;\" border=\"0\"></td>
							</tr>
						</tbody>
					</table>
				</body>
			</html>
			";
		}

		public function sendEmail($emailAddress, $subject, $body, $files=[]) {
			require_once './core/defines.php';
			if(!isset($_SESSION)) { session_start(); }
			$mail = new PHPMailer(true);
			try {$mail->SMTPDebug = 0;
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
				$mail->setFrom($_SESSION['mail_username'], SITE_NAME);
				$mail->addAddress($emailAddress);	
				$mail->isHTML(true);
				$mail->CharSet = 'UTF-8';
				$mail->Subject = $subject;
				$mail->Body    = $body;
				for($x=0;$x<count($files);$x++) {
					$filename = explode('/', $files[$x]);
					$filename = $filename[count($filename)-1];
					$mail->AddAttachment($files[$x], $filename);
				}
				$mail->send();
				unset($mail->Username, $mail->Password);
				$this->response->SetResponse($mail);
			}
			catch (Exception $e) {
				$this->response->SetResponse(false, $e);
			}
			return $this->response;
		}
	}
?>