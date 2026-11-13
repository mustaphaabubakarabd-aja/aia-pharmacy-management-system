<?php
session_start();
date_default_timezone_set('Africa/Lagos');
const APP_NAME = 'AIA PHARMACY';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'aia_pharmacy';
const DB_USER = 'YOUR_DATABASE_USERNAME';
const DB_PASS = 'YOUR_DATABASE_PASSWORD';
const BASE_URL = '/aia_pharmacy/';
function db(){ static $pdo; if(!$pdo){$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);} return $pdo; }
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function redirect($url){header('Location: '.BASE_URL.ltrim($url,'/'));exit;}
function flash($type,$msg){$_SESSION['flash']=['type'=>$type,'msg'=>$msg];}
function require_login(){if(empty($_SESSION['user'])) redirect('login.php');}
function csrf(){if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf'];}
function check_csrf(){if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')) die('Invalid request token.');}
function money($n){return '₦'.number_format((float)$n,2);}
