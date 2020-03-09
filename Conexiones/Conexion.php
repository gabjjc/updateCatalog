<?php
$hostname = "localhost";//servidor
$database = "";//base de datos
$username = "";//usuario
$password = "";//contrase�a
//conectar con el servidor de base de datos
$cnn = mysql_connect($hostname, $username, $password) or trigger_error(mysql_error(),E_USER_ERROR); 
//seleccionar la base de datos a trabajar
mysql_select_db($database, $cnn);
//Cotejamiento de la base de datos y Tablas: utf8_general_ci
//setear el conjunto de caracteres
mysql_query("SET NAMES 'utf8'", $cnn) or die(mysql_error());
?>

	