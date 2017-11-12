<?php
require_once "vendor/autoload.php";

use \Lcobucci\JWT\Builder;
use \Lcobucci\JWT\Parser;
use \Lcobucci\JWT\ValidationData;

$dbhost = "localhost";
$dbuser = "zagent";
$dbpassword = "f46hd35g";
$dbname = "retail";

$link = mysql_connect($dbhost, $dbuser, $dbpassword);
mysql_query("set character set utf8", $link);
mysql_query("set names utf8", $link);
mysql_select_db($dbname, $link);

if (strlen($_REQUEST['jti']) > 0) {
    $users = mysql_fetch_array(mysql_query("select email,jti from test_users where jti='" . mysql_escape_string($_REQUEST['jti']) . "'", $link));
    if (strlen($users['jti']) > 0) {
        mysql_query("update test_users set validation='1' where jti='" . $users['jti'] . "';", $link);
        echo get_token($users['email'], $users['jti']);
    } else {
        header('Location: error.php?code=0', true, 303);
    }
}

if ($_REQUEST['fn'] == "registration") {
    $email = mysql_escape_string($_POST['email']);
    $name = mysql_escape_string($_POST['name']);
    $password = mysql_escape_string($_POST['password']);
    $jti = md5(uniqid(rand(), true));
    mail($email, "Регистрация на тестовом сервисе", "Для подтверждения регистрации перейдите по ссылке http://pashkoff.net/jwt/auth.php?jti=" . $jti,
        "From: noreplay@pashkoff.net \r\n"
        . "X-Mailer: PHP/" . phpversion());
    mysql_query("insert into test_users set email='" . $email . "', name='" . $name . "', password=md5('" . $password . "'), jti='" . $jti . "';", $link);
    header('Location: confirmation.php', true, 303);
}

if ($_REQUEST['fn'] == "autorisation") {
    $email = mysql_escape_string($_POST['email']);
    $password = mysql_escape_string($_POST['password']);
    $users = mysql_fetch_array(mysql_query("select jti from test_users where email='$email' and password=md5('" . $password . "') and validation='1';", $link));
    if (strlen($users['jti']) > 0) {
        echo get_token($email, $users['jti']);
    } else {
        header('Location: error.php?code=1', true, 303);
    }
}

if ($_REQUEST['fn'] == "info") {
    $token = mysql_escape_string($_POST['token']);
    $jti = get_jti($token);
    if (strlen($jti) > 0) {
        $users = mysql_fetch_array(mysql_query("select email,name from test_users where jti='$jti';", $link));
        if (strlen($users['email']) > 0) {
            echo json_encode(['name' => $users['name'], 'email' => $users['email']]);
        } else {
            header('Location: error.php?code=2', true, 303);
        }
    } else {
        header('Location: error.php?code=2', true, 303);
    }
}

if ($_REQUEST['fn'] == "newname") {
    $token = mysql_escape_string($_POST['token']);
    $newname = mysql_escape_string($_POST['newname']);
    $jti = get_jti($token);
    if (strlen($jti) > 0) {
        $users = mysql_fetch_array(mysql_query("select email,name from test_users where jti='$jti';", $link));
        if (strlen($users['email']) > 0) {
            mysql_query("update test_users set name='$newname' where jti='" . $jti . "';", $link);
            echo json_encode(['name' => $newname, 'email' => $users['email']]);
        } else {
            header('Location: error.php?code=2', true, 303);
        }
    } else {
        header('Location: error.php?code=2', true, 303);
    }
}

mysql_close($link);

function get_token($email, $jti)
{
    $token = (new Builder())->setIssuer('pashkoff.net')
        ->setId($jti, true)
        ->setIssuedAt(time())
        ->setNotBefore(time())
        ->setExpiration(time() + 3600)
        ->set('uid', 1)
        ->getToken();
    $token->getHeaders();
    $token->getClaims();
    return ($token);
}

function get_jti($token)
{
    try {
        $token = (new Parser())->parse((string)$token);
    } catch (Exception $e) {
        return "";
    }
    $token->getHeaders();
    $token->getClaims();
    try {
        $jti = $token->getHeader('jti');
    } catch (Exception $e) {
        return "";
    }
    $data = new ValidationData();
    $data->setIssuer('pashkoff.net');
    $data->setId($jti);
    if ($token->validate($data)) {
        return $token->getHeader('jti');
    }
    return "";
}

?>
