<?php
require_once "vendor/autoload.php";

use \Lcobucci\JWT\Builder;
use \Lcobucci\JWT\Parser;
use \Lcobucci\JWT\ValidationData;

$dbhost = "localhost";
$dbuser = "zagent";
$dbpassword = "f46hd35g";
$dbname = "retail";

$link = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$link) { 
    header('Location: error.php?code=3', true, 303); 
    die('Connect Error: ' . mysqli_connect_error());
    }
mysqli_query($link,"set character set utf8");
mysqli_query($link,"set names utf8");

if (strlen($_REQUEST['jti']) > 0) {
    $users = mysqli_fetch_array(mysqli_query($link, "select email,jti from test_users where jti='" . mysqli_escape_string($link, $_REQUEST['jti']) . "'"));
    if (strlen($users['jti']) > 0) {
        mysqli_query($link, "update test_users set validation='1' where jti='" . $users['jti'] . "';");
        echo get_token($users['email'], $users['jti']);
    } else {
        header('Location: error.php?code=0', true, 303);
    }
}

if ($_REQUEST['fn'] == "registration") {
    $email = mysqli_escape_string($link, $_POST['email']);
    $name = mysqli_escape_string($link, $_POST['name']);
    $password = mysqli_escape_string($link, $_POST['password']);
    $jti = md5(uniqid(rand(), true));
    mail($email, "Регистрация на тестовом сервисе", "Для подтверждения регистрации перейдите по ссылке http://pashkoff.net/jwt/auth.php?jti=" . $jti,
        "From: noreplay@pashkoff.net \r\n"
        . "X-Mailer: PHP/" . phpversion());
    mysqli_query($link, "insert into test_users set email='" . $email . "', name='" . $name . "', password=md5('" . $password . "'), jti='" . $jti . "';");
    header('Location: confirmation.php', true, 303);
}

if ($_REQUEST['fn'] == "autorisation") {
    $email = mysqli_escape_string($link, $_POST['email']);
    $password = mysqli_escape_string($link, $_POST['password']);
    $users = mysqli_fetch_array(mysqli_query($link, "select jti from test_users where email='$email' and password=md5('" . $password . "') and validation='1';"));
    if (strlen($users['jti']) > 0) {
        echo get_token($email, $users['jti']);
    } else {
        header('Location: error.php?code=1', true, 303);
    }
}

if ($_REQUEST['fn'] == "info") {
    $token = mysqli_escape_string($link, $_POST['token']);
    $jti = get_jti($token);
    if (strlen($jti) > 0) {
        $users = mysqli_fetch_array(mysqli_query($link, "select email,name from test_users where jti='$jti';"));
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
    $token = mysqli_escape_string($link, $_POST['token']);
    $newname = mysqli_escape_string($link, $_POST['newname']);
    $jti = get_jti($token);
    if (strlen($jti) > 0) {
        $users = mysqli_fetch_array(mysqli_query($link, "select email,name from test_users where jti='$jti';"));
        if (strlen($users['email']) > 0) {
            mysqli_query($link, "update test_users set name='$newname' where jti='" . $jti . "';");
            echo json_encode(['name' => $newname, 'email' => $users['email']]);
        } else {
            header('Location: error.php?code=2', true, 303);
        }
    } else {
        header('Location: error.php?code=2', true, 303);
    }
}

mysqli_close($link);

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
        $token = (new Parser())->parse($token);
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
