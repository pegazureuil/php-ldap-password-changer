<?php
/*
 ***************************************
 *        LDAP PASSWORD CHANGER        *
 ***************************************
 *	
 *	Auteur : Sébastien Marchandeau
 *	Date : 2015/09/02
 *	
 *	Note 1 : l'extension PHP LDAP doit être activée dans le php.ini !
 *	Note 2 : sous Windows pour se connecter en LDAPs, un certificat valide doit être installé. Dans le cas contraire :
 *		- créer un répertoire C:\OpenLDAP\sysconf
 *		- y placer un fichier ldap.conf avec la ligne : TLS_REQCERT never
 *	
 *	Script de renouvellement de mot de passe LDAP :
 *		- l'utilisateur effectue une demande de changement de mot de passe ;
 *		- le login est vérifié dans le LDAP ;
 *		- une confirmation de changement est envoyée par mail ;
 *		- la confirmation mène à un lien d'activation de changement ;
 *		- le mot de passe est généré puis enregistré dans l'AD via un compte admin ;
 *		- un email d'information de changement est envoyé à l'utilisateur.
 *	
 */
session_start();


/***************
 * PARAMETRAGE *
 ***************/
 
/* Environnement */
define ('FOLDER_CSS', 'css/');										/* Répertoire des fichiers CSS. */
define ('CURRENT_PAGE', $_SERVER['PHP_SELF']);						/* Fichier en cours. */
define ('CURRENT_URL', $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);	/* URL en cours. */
define ('DEBUG_MODE', false);										/* Mode debug (affichage des erreurs). */
define ('KEY_DEFAULT_LENGHT', 15);									/* Longueur des clés générées. */
define ('SEND_MAIL', true);											/* Activation de l'envoi des emails. */

/* LDAP */
define ('MAIL_SUFFIX', '@mail.suffix.com');							/* Suffixe automatique de l'email. */
define ('LDAP_SERVER', 'ldaps://xxxxxxxxx');						/* Serveur LDAP. */
define ('LDAP_PORT', '636');										/* Port LDAP. */
define ('LDAP_ROOT', 'OU=xxxxxxxxx,OU=xxxxxxxxx,DC=xxxxxxxxx,DC=xxxxxxxxx,DC=xxxxxxxxx');	/* Racine LDAP. */
define ('LDAP_DN', 'cn=xxxxxxxxx,'.LDAP_ROOT);						/* DN LDAP. */
define ('LDAP_PWD', 'xxxxxxxxx');									/* Password LDAP. */
define ('LDAP_BASEDN', "OU=xxxxxxxxx,OU=xxxxxxxxx,DC=xxxxxxxxx,DC=xxxxxxxxx,DC=xxxxxxxxx");	/* Base DN LDAP pour la recherche. */
define ('LDAP_ANON', false);										/* Connexion anonyme au LDAP. */

/* SMTP */
define ('SMTP_ADDRESS', 'xxxxxxxxx');								/* Adresse SMTP (anonyme avec IP autorisée) - drelais01. */
define ('SMTP_FROM', 'no.reply@xxxxxxxxxxxxxx.xxx');				/* Adresse Expéditeur. */


/*************
 * FONCTIONS *
 *************/

/* CONNEXION LDAP
 * > se connecte au LDAP et renvoie les information
 * @string $server = adresse du serveur LDAP
 * @int $port = port du serveur LDAP
 */
function ldapConnect($server = LDAP_SERVER, $port = LDAP_PORT) {
	$id = ldap_connect($server, $port);
	ldap_set_option($id, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option( $id, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($id, LDAP_OPT_DEBUG_LEVEL, 7);
	$_SESSION['ldapID'] = $id;
	return $id;
}

/* LDAP BINDING
 * > initialise la liaison au LDAP
 * @object $id = handle de la connexion au LDAP
 * @string $rootDn = racine LDAP
 * @string $pwd = password LDAP
 * @boolean $anonymous = connexion anonyme
 */
function ldapBinding($id, $anonymous = false, $rootDn = LDAP_DN, $pwd = LDAP_PWD) {
	($anonymous) ? $binding = ldap_bind($id) : $binding = ldap_bind($id, $rootDn, $pwd);
	$_SESSION['ldapBinding'] = $binding;
	return $binding;
}

/* LDAP SEARCH
 * > recherche une entrée dans le LDAP
 * > retourne false si l'entrée n'a pas été trouvée
 * @object $id = handle de la connexion au LDAP
 * @string $filter = entrée à rechercher
 * @string $restriction = information à récupérer
 * @string $baseDn = base LDAP
 */
function ldapSearch($id, $filter, $restriction = "", $baseDn = LDAP_BASEDN) {
	$searchResults = ldap_search($id, $baseDn, $filter, $restriction);
	$search = ldap_get_entries($id, $searchResults);
	$_SESSION['ldapSearch'] = $search;
	return $search;
}

/* LDAP CLOSE
 * > termine la connexion au LDAP
 * @object $id = handle de la connexion au LDAP
 */
function ldapClose($id) {
	ldap_close($id);
}

/* ENVOI D'EMAIL
 * > envoie un email
 * @string $to = destinataire
 * @string $subject = sujet de l'email
 * @string $content = contenu de l'email
 */
function sendMail($to, $subject, $content) {
	if (SEND_MAIL) {
		/* Headers pour envoi en HTML. */
		$headers  = "From: ".SMTP_FROM."\r\n";
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
		mail ($to, $subject, $content, $headers);
	}
}

/* SUPPRESSION DES ACCENTS D'UN FICHIER
 * > supprime les accents d'une chaîne (utile en mode console Windows)
 * @string $str = chaîne de caractère à analyser
 */
 function stripAccents($str) {
	return strtr($str,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}

/* SUPPRESSION DES QUOTES et DOUBLES QUOTES
 * > supprime les quotes et guillements d'une chaîne
 * @string $str = chaîne de caractère à analyser
 */
 function stripQuotes($str) {
	return str_replace(array('\'', '"'), '', $str);
}

/* SUPPRESSION DES ESPACES
 * > supprime les espaces d'une chaîne
 * @string $str = chaîne de caractère à analyser
 */
 function stripSpaces($str) {
	return str_replace(' ', '', $str);
}

/* NETTOYAGE DE CHAINE
 * > nettoie une chaine de ses caractères indésirables
 * @string $str = chaîne de caractère à analyser
 * @boolean $toLower = chaîne à retourner en minuscule
 */
 function strClean($str, $toLower = false) {
	$str = stripSpaces(stripQuotes(stripAccents($str)));
	if ($toLower) $str = strToLower($str);
	return $str;
}

/* SUPPRESSION DE VARIABLES SENSIBLES
 * > supprime les variables de session sensibles
 */
function clearVars() {
	$_SESSION['email'] = '';
	$_SESSION['pwd'] = '';
	$_SESSION['login'] = '';
	$_SESSION['hash'] = '';
	$_SESSION['ldapID'] = '';
	$_SESSION['ldapBinding'] = '';
	$_SESSION['ldapSearch'] = '';
	$_SESSION['userDn'] = '';
}

/* DUMP D'ERREUR
 * > affiche les erreurs
 * @object $str = erreur à afficher
 * @string $type = type de l'erreur
 */
function errorDump($str, $type = "warning") {
	/* Gestion du paragraphe en fonction du type d'erreur. */
	switch ($type) {
		case "primary":
			$p = '<div class="bg-primary">';
			break;
		case "success":
			$p = '<div class="bg-success">';
			break;
		case "info":
			$p = '<div class="bg-info">';
			break;
		case "warning":
			$p = '<div class="bg-warning">';
			break;
		case "danger":
			$p = '<div class="bg-danger">';
			break;
		default:
			$p = '<div class="bg-primary">';
	}
	/* Retour de l'erreur. */
	return $p.$str.'</div>';
}

/* KEY GEN
 * > génère chaîne aléatoire
 * @int $length = longueur de la chaîne
 */
function keyGen($length = KEY_DEFAULT_LENGHT)
{
    $pwd = substr(strtolower(md5(uniqid(rand()))), 2, $length);
	/* Suppression des caractères ambigus. */
    $pwd = strtr($pwd, 'o0OQCiIl15Ss7', 'BEFHJKMNPRTUVWX');
    return $pwd;
}

/* ENCODEUR DE PASSWORD
 * > encore un password au bon format pour le LDAP
 * @int $length = longueur de la chaîne
 */
function pwdEncode($pwd)
{
    $pwd = "\"".$pwd."\"";
	$len = strlen($pwd);
	$_pwd = "";
	for ($i = 0; $i < $len; $i++) {
		$_pwd .= "{$pwd{$i}}\000";
	}
	// $_pwd = mb_convert_encoding($_pwd, "UTF-16LE");
	// $_pwd = iconv( 'UTF-8', 'UTF-16LE', $_pwd );
    return $_pwd;
}

/* DEBUG MODE
 * > ajoute une erreur au tableau d'erreurs
 * @string $msg = message à afficher
 * @string $type = type de l'erreur
 */
function _debug($msg, $type) {
	if (DEBUG_MODE) $_SESSION['errors'][] = errorDump($msg, $type);
}

/* DUMP DE VARIABLE
 * > affiche les informations complètes d'une variable PHP à l'écran en utilisant la balise HTML <pre>
 * @object $str = nom de la variable
 */
function varDump($str) {
	echo '<pre>';
	print_r($str);
	echo '</pre>';
}

/* REDIRECTION D'URL
 * > redirige la page vers une url donnée
 * > doit être appellée avant que le header ne soit passé
 * @string $url = url à rediriger
 * @boolean $permanent = flag permanent
 */
function redirect($url = CURRENT_PAGE, $permanent = false) {
    if (headers_sent() === false) {
    	header('Location: ' . $url, true, ($permanent === true) ? 301 : 302);
    }
	exit();
}


/*********
* STEPS *
*********/

/* Step 1 : check */

if (isset($_GET['step']) && ($_GET['step'] == 'check')) {
	
	/* Initialisation du tableau d'erreurs. */
	$_SESSION['errors'] = [];
	
	/* Récupération et nettoyage du login. */
	(isset($_POST['txtLogin'])) ? $login = strClean($_POST['txtLogin'], true) : $login = "";
	
	/* Vérification d'un login non vide. */
	if ($login == "") $_SESSION['errors'][] = errorDump("Aucun login de saisi", 'danger');
	
	/* Poursuite si aucune erreur. */
	if (empty($_SESSION['errors'])) {
		
		/* Connexion au LDAP */
		_debug("Connexion au LDAP...", "info"); /* Debug. */
		$ldapId = ldapConnect();
		if ($ldapId) {
			
			_debug("Connexion au LDAP réussie", 'success'); /* Debug. */
			
			/* LDAP binding */
			_debug("LDAP binding...", "info"); /* Debug. */
			(LDAP_ANON) ? $ldapBind = ldapBinding($ldapId, true) : $ldapBind = ldapBinding($ldapId);
			
			/* Si binding réussi. */
			if ($ldapBind) {
				
				_debug("LDAP binding réussie", "success"); /* Debug. */
				
				/* Recherche du login dans le LDAP. */
				_debug("Recherche de l'entrée $login dans le LDAP...", "info"); /* Debug. */
				$filter = "(&(objectClass=user)(objectCategory=person)(sn=$login*))";
				$search = ldapSearch($ldapId, $filter, array("cn", "sn", "saMaccountName", "mail"));
				
				/* Si utilisateur trouvé. */
				if ($search['count'] > 0) {
					
					$_SESSION['errors'][] = errorDump("Utilisateur $login trouvé", 'success');
					
					/* Récupération du login et de l'adresse email. */
					$_SESSION['login'] = $login;
					$_SESSION['email'] = $search[0]['mail'][0];
					
					/* Génération d'un hash pour le mot de passe à modifier. */
					$_SESSION['hash'] = keyGen();
					_debug("Hash généré pour la confirmation par mail : ".$_SESSION['hash'], "info"); /* Debug. */
					
					/* Génération d'une URL contenant le lien de validation pour le changement de mot de passe. */
					$url = CURRENT_URL."?step=change&hash=".$_SESSION['hash'];
					_debug("URL générée pour la confirmation par mail : <small><a href=\"http://$url\">$url</a></small>", "info"); /* Debug. */
					
					/* Envoi d'un email à l'utilisateur. */
					$mailSubject = "Demande de changement de mot de passe LDAP";
					$mailContent = '<html>
										<head>
											<title>Demande de changement de mot de passe LDAP</title>
										</head>
										<body>
											Bonjour,<br><br>
											Vous avez effectué une demande de changement de mot de passe LDAP pour le compte '.$login.'. Veuillez cliquer sur l\'URL suivante pour valider cette demande :
											<br><a href="http://'.$url.'">Valider la demande de changement de mot de passe</a>
											<br><br>Si vous n\'êtes pas à l\'origine de cette demande, merci de bien vouloir ignorer cet e-mail.
											<br><br>Cordialement,
											<br><br>Le service informatique
										</body>
									</html>';
					
					_debug("Envoi du mail de confirmation...", "info"); /* Debug. */
					sendMail ($_SESSION['email'], $mailSubject, $mailContent);
					$_SESSION['errors'][] = errorDump("Un e-mail de confirmation a été envoyé à l'adresse ".$_SESSION['email']."<br>Merci de ne pas fermer la fenêtre en cours", 'info');
				}
				else {
					$_SESSION['errors'][] = errorDump("Impossible de trouver l'utilisateur $login", 'warning');
				}
				
			}
			else {
				$_SESSION['errors'][] = errorDump("Impossible d'effectuer la liaison au LDAP (LDAP binding)", 'danger');
			}
			
			/* Fermeture de la connexion au LDAP. */
			_debug("Fermeture de la connexion au LDAP...", 'info'); /* Debug. */
			ldapClose($ldapId);
			_debug("Fermeture de la connexion au LDAP réussie", 'success'); /* Debug. */
			
		}
		else {
			$_SESSION['errors'][] = errorDump("Impossible d'effectuer la connexion au LDAP", 'danger');
		}
		
	}

}
 
/* Step 2 : confirm */
elseif (isset($_GET['step']) && ($_GET['step'] == 'change')) {
	
	/* Initialisation du tableau d'erreurs. */
	$_SESSION['errors'] = [];
	
	/* Récupération et nettoyage du login. */
	(isset($_GET['hash'])) ? $hash = strClean($_GET['hash']) : $hash = "";
	
	/* Vérification d'un hash non vide. */
	if ($hash == "") $_SESSION['errors'][] = errorDump("Aucun hash de récupéré - l'opération ne peut se poursuivre", 'danger');
	
	/* Vérification d'un hash de session. */
	if ($_SESSION['hash'] == "") $_SESSION['errors'][] = errorDump("Aucun hash de session récupéré - l'opération ne peut se poursuivre", 'danger');
	
	/* Vérification d'un hash identique. */
	if ($hash != $_SESSION['hash']) $_SESSION['errors'][] = errorDump("Aucune demande de modification effectuée pour cet utilisateur", 'danger');
	
	/* Vérification d'un email. */
	if ($_SESSION['email'] == "") $_SESSION['errors'][] = errorDump("Aucune adresse e-mail de récupérée - l'opération ne peut se poursuivre", 'danger');
	
	/* Poursuite si aucune erreur. */
	if (empty($_SESSION['errors'])) {
		
		/* Connexion au LDAP */
		_debug("Connexion au LDAP...", "info"); /* Debug. */
		$ldapId = ldapConnect();
		
		if ($ldapId) {
			
			_debug("Connexion au LDAP réussie", 'success'); /* Debug. */
			
			/* LDAP binding */
			_debug("LDAP binding...", "info"); /* Debug. */
			(LDAP_ANON) ? $ldapBind = ldapBinding($ldapId, true) : $ldapBind = ldapBinding($ldapId);
			
			/* Si binding réussi. */
			if ($ldapBind) {
				
				_debug("LDAP binding réussie", "success"); /* Debug. */
				
				/* Récupération de l'email. */
				$mail = $_SESSION['email'];
				
				/* Recherche du login dans le LDAP. */
				_debug("Recherche de l'entrée $mail dans le LDAP...", "info"); /* Debug. */
				$filter = "(&(objectClass=user)(objectCategory=person)(mail=$mail*))";
				$search = ldapSearch($ldapId, $filter, array("cn", "sn", "saMaccountName", "mail"));
				
				/* Si utilisateur trouvé. */
				if ($search['count'] == 1) {
					
					$_SESSION['errors'][] = errorDump("Utilisateur $mail trouvé", 'success');
					
					/* Génération d'un mot de passe pour l'utilisateur à modifier. */
					$_SESSION['pwd'] = keyGen(8);
					$pwd = $_SESSION['pwd'];
					$login = $_SESSION['login'];
					_debug("Mot de passe généré : ".$_SESSION['pwd'], "info"); /* Debug. */
					
					/* Modification de l'entrée dans le LDAP. */
					$userDn = $search[0]['dn'];
					$_SESSION['userDn'] = $userDn;
					$pwdEntry['unicodePwd']= array(pwdEncode($pwd));
					$_SESSION['unicodePwd'] = $pwdEntry;
					if (ldap_modify($ldapId, $userDn, $pwdEntry)) {
						$_SESSION['errors'][] = errorDump("Le mot de passe pour l'utilisateur $mail a été changé", 'success');
						$_SESSION['userDn'] = $userDn;
						
						/* Envoi d'un email à l'utilisateur. */
						$mailSubject = "Confirmation de changement de mot de passe LDAP";
						$mailContent = '<html>
											<head>
												<title>Confirmation de changement de mot de passe LDAP</title>
											</head>
											<body>
												Bonjour,<br><br>
												Votre demande de changement de mot de passe LDAP pour le compte '.$login.' a bien été prise en compte. Voici votre nouveau mot de passe :
												<br>'.$pwd.'
												<br><br>Merci de bien vouloir supprimer cet e-mail après avoir mémorisé votre nouveau mot de passe.
												<br><br>Cordialement,
												<br><br>Le service informatique
											</body>
										</html>';
						
						_debug("Envoi du mail de confirmation...", "info"); /* Debug. */
						sendMail ($_SESSION['email'], $mailSubject, $mailContent);
						$_SESSION['errors'][] = errorDump("Un e-mail de confirmation contenant votre nouveau mot de passe a été envoyé à l'adresse ".$_SESSION['email'], 'info');
					}
					else $_SESSION['errors'][] = errorDump("Impossible de modifier le mot de passe pour l'utilisateur $mail", 'danger');
					
				}
				else {
					$_SESSION['errors'][] = errorDump("Impossible de trouver l'utilisateur $mail", 'warning');
				}
				
			}
			else {
				$_SESSION['errors'][] = errorDump("Impossible d'effectuer la liaison au LDAP (LDAP binding)", 'danger');
			}
			
			/* Fermeture de la connexion au LDAP. */
			_debug("Fermeture de la connexion au LDAP...", 'info'); /* Debug. */
			ldapClose($ldapId);
			_debug("Fermeture de la connexion au LDAP réussie", 'success'); /* Debug. */
			
			/* Suppression des variables sensibles. */
			if (!DEBUG_MODE) clearVars();
			
		}
		else {
			$_SESSION['errors'][] = errorDump("Impossible d'effectuer la connexion au LDAP", 'danger');
		}
		
	}

}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

	<title>Demande de renouvellement de mot de passe LDAP</title>
	<meta charset="utf8">
	<!-- Twitter Bootstrap CSS -->
	<link rel="stylesheet" href="<?php echo FOLDER_CSS; ?>bootstrap.css">

</head>

<body>

	<div class="container">
		
		<!-- Form -->
		<form action="./index.php?step=check" method="post">
			<div class="row">
				<div class="col-md-10 col-md-offset-1 text-center"><h1 style="margin-top: 50px; margin-bottom: 50px;"><a href="./" title="Demande de renouvellement de mot de passe LDAP">Demande de renouvellement de mot de passe LDAP</a></h1></div>
				<div class="col-md-6 col-md-offset-3 input-group">
					<input type="text" name="txtLogin" class="form-control" placeholder="Entrez le login" title="Entrez le login inhérent la demande de mot de passe" aria-describedby="addonDomain"<?php if (isset($login)) echo ' value="'.$login.'"'; ?>>
					<span class="input-group-addon" id="addonDomain"><?php echo MAIL_SUFFIX ?></span>
				</div>
				<div class="col-md-2 col-md-offset-5 input-group" style="margin-top: 10px;">
					<button type="submit" name="btnValidate" class="btn btn-primary btn-block" title="Effectuer la demande de changement de mot de passe">Changer</button>
				</div>
			</div>
		</form>
		
		<!-- Error -->
		<div class="row" style="margin-top: 20px; margin-bottom: 20px;">
			<div class="col-md-8 col-md-offset-2">
				<?php if (!empty($_SESSION['errors'])) { foreach ($_SESSION['errors'] as $error) { echo $error; } } ?>
			</div>
		</div>
		
	</div>
	
</body>

</html>

<?php

/* Suppression des variables. */
$_SESSION['errors'] = array();

/* Debug. */
if (DEBUG_MODE) varDump($_SESSION);

 ?>