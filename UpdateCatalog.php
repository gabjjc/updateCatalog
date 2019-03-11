<?php
/**
 * Actualizacion de stock y precios
 * Servicio que se ejecuta una vez al dia para actualizar precios y stock.
 * La ejecucion se realiza desde una tarea cron en cpanel.
 */
$host = "http://200.16.208.67:81";
$inicioProceso = date("Y-m-d H:i:s");

// Variable que almacenará el resultado de la ejecución.
 // ob_start();

// Obtenemos las sucursales
$sucursales = getSucursales();
echo "\nInicializando el proceso: ".$inicioProceso;


// obtenemos los articulos por sucursales
foreach ($sucursales as $sucursal) {
    clearstatcache();
    $listaArticulosxSucursalRosmi = array();
    $codigosAactualizar = array();
    
    $idSucursal = $sucursal['id'];
   
    // Obtenemos codigo actualizados
    $codigosActualizadosRosmi = getCodigosActualizados();

    // Obtenemos los articulos por sucursal del modulo Rosmi
    $listaArticulosxSucursalRosmi = getArticulosxSucursal($idSucursal);

    echo  "\n\n----- Sucursal: " . $idSucursal . " ------- Articulos existentes en Modulo:" . count($listaArticulosxSucursalRosmi);

    // Obtenemos los articulos por sucursal del modulo Tango
    $listaArticulosxSucursalTango = getArticulosxSucursalTango($idSucursal, $host);

    if (!$listaArticulosxSucursalTango) {
        echo  "\n\tSucursal (" . $idSucursal . ") se encuentra sin datos";
   
    } else {
        
        $countInsertArt = 0;
        $countUpdateArt = 0;
        $countWithOutAction = 0;
        $countCodigosAdd = 0;
        foreach ($listaArticulosxSucursalTango['Resultado'] as $articuloTango) {
              
            validarExistenciaCodigoRosmi($articuloTango, $codigosActualizadosRosmi, $codigosAactualizar, $countCodigosAdd);
            
            //Validamos si el Articulo Tango existe en Rosmi
            $key = array_search($articuloTango['Codigo'], array_column($listaArticulosxSucursalRosmi, 'codigo'));
            
            if(!$key){
                // No existe ->  // Insertar
                $countInsertArt ++;
                insertarArticulo($articuloTango, $idSucursal);
            }else{
                // Existe Articulo, se valida si tiene mismos datos en Rosmi
                if( ValidarArticuloRosmiTango( $articuloTango ,$listaArticulosxSucursalRosmi, $key ) ){
                    // No cambia nada -> guardar en una variable
                    $countWithOutAction ++;
                    echo " -> articulo sin cambios". $articuloTango['Codigo'];
                }else{
                    //Modificaciones en el articulo -> actualizar el articulo
                    $countUpdateArt ++;
                    actualizarArticulo($articuloTango, $idSucursal);
                }
            }
        }
        
        // Si existen codigo nuevos los inserta en BD
        if (! empty($codigosAactualizar)) {
            $countCodigosAdd = actualizarCodigos($codigosAactualizar);
        }
        
        echo  "\n\n\tSucursal: " . $idSucursal . " Se agregaron " . $countCodigosAdd . " Codigos nuevos ";
        echo  "\n\n\tSucursal: " . $idSucursal . " Se agregaron " . $countInsertArt . " Articulos  y se actualizaron " . $countUpdateArt . " Codigos. \n";
        echo  "\n\n\tSucursal: " . $idSucursal . " Articulos sin modificaciones " . $countWithOutAction; 
    }
}

$finProceso = date("Y-m-d H:i:s");
echo  "\n Inicio Proceso: " . $inicioProceso;
echo  "\n Fin Proceso: " . $finProceso;
//$logs = ob_get_contents();
// ob_end_clean();

return $logs;

function getConnection()
{
    $hostname = "200.80.43.110";
    $database = "rosmi_compras";
    $username = "rosmi_admin";
    $password = "manolo11";
    
    $mysqli = new mysqli($hostname, $username, $password, $database);
    
    if ($mysqli->connect_errno) {
        echo "\n Error: Fallo al conectarse a MySQL debido a: \n";
        echo "\n Errno: " . $mysqli->connect_errno . "\n";
        exit();
    }
    return $mysqli;
}

function getSucursales()
{
    $mysqli = getConnection();
    $sql = "SELECT * FROM sucursales order by id asc";
    if (! $resultado = $mysqli->query($sql)) {
        echo "\n Error: La ejecucion de la consulta fallo debido a: \n";
        echo "\n Query: " . $sql . "\n";
        echo "\n Errno: " . $mysqli->errno . "\n";
        throw new Exception($mysqli->errno);
    }
    if ($resultado->num_rows === 0) {
        throw new Exception("\n Lo sentimos. No se pudo encontrar una coincidencia. Int谷ntelo de nuevo");
    }
    
    $mysqli->close();
    
    return $resultado;
}

function getArticulosxSucursal($branch)
{
    $mysqli = getConnection();
    $sql = "SELECT id_sucursal, codigo, descripcion, precio, stock FROM articulos where id_sucursal = 1";
    if (! $resultado = $mysqli->query($sql)) {
        echo "Error: La ejecucion de la consulta fallo debido a: \n";
        echo "Query: " . $sql . "\n";
        echo "Errno: " . $mysqli->errno . "\n";
        throw new Exception($mysqli->errno);
    }
    if ($resultado->num_rows === 0) {
        echo "No existen Datos para esta sucursal";
        $a = array();
        return $a;
    }
    
    $rows = array();
    while ($row = $resultado->fetch_row()) {
        $articulo = array(
            "id_sucursal" => $row[0],
            "codigo" => $row[1],
            "descripcion" => $row[2],
            "precio" =>  $row[3],
            "stock" =>$row[4]
        );
        array_push($rows, $articulo);
    }
    
    $mysqli->close();
    return $rows;
}

function getCodigosActualizados()
{
    $mysqli = getConnection();
    $sql = "SELECT codigo FROM codigos_actualizados";
    $resultado = $mysqli->query($sql);
    
    if (! $resultado) {
        echo "\n Error: La ejecucion de la consulta fallo debido a: \n";
        echo "\n Query: " . $sql . "\n";
        echo "\n Errno: " . $mysqli->errno . "\n";
        throw new Exception($mysqli->errno);
    }


    $detalle = array();
     if ($resultado->num_rows > 0) {
        while ($codigo = $resultado->fetch_assoc()) {
            array_push($detalle, $codigo['codigo']);
        }
     }
    
    
    $mysqli->close();
    
    return $detalle;
}

function getArticulosxSucursalTango($sucursal, $host)
{
    // $fecha = getActualDate();
    $url = $host . "/api/PrecioStock?Sucursal=" . $sucursal; // . "&Delta=" . $fecha;
    echo  "\nobteniendo datos desde :" . $url;
    
    $stockAndPrice = postRestPostService($url);
    echo  "\nSe obtuvieron (";
    if ($stockAndPrice != 0) {
        echo  sizeof($stockAndPrice['Resultado']);
    } else {
        echo  "0";
    }
    echo  ")Articulos en la sucursal " . $sucursal . " segun TANGO ";
    
    
    if( $stockAndPrice['Status'] === "OK" ){
        return $stockAndPrice;
    }
    return false;
}

function validarExistenciaCodigoRosmi($articuloTango, $codigosActualizadosRosmi, $codigosAactualizar, $countCodigosAdd){

    $codArticuloTango = $articuloTango['Codigo'];
    // validamos si el articulo existe en la tabla codigos_actualizados, si no existen se cargan.
    if ( empty($codigosActualizadosRosmi) && 
            ! in_array($codArticuloTango, $codigosActualizadosRosmi)) {
        $datos = array(
            'Codigo' => $codArticuloTango,
            'Descripcion' => $articuloTango['Descripcion']
        );
        array_push($codigosAactualizar, $datos);
        $countCodigosAdd++;
     }

}

function ValidarArticuloRosmiTango($articuloRosmi, $articulosTango, $posicionTango){

   $resultado = false; 
    //Valido si los datos recibidos son iguales a los que existen en el modulo
    
   $artTango = $articulosTango[$posicionTango];

   $precioRosmi = floatval($articuloRosmi['Precio']);
   $stockRosmi = floatval($articuloRosmi['Stock']);
    
   $precioTango = floatval($artTango['precio']);
   $stockTango = floatval($artTango['stock']); 
   
   echo "\n[Rosmi-Tango] Precio: [". $precioRosmi . "-" . $precioTango. "] Stock: [" . $stockRosmi ."-". $stockTango ."]";
   
   
   if($precioRosmi === $precioTango &&
       $stockRosmi === $stockTango){
            echo "[Iguales]";
           $resultado = true;
   }
   
   return $resultado;
}

function insertarArticulo($articulo, $sucursal)
{
    $mysqli = getConnection();
    
    $categoria = isset($articulo['Categoria']) ? $articulo['Categoria'] : "";
    $registro = isset($articulo['Registro']) ? $articulo['Registro'] : "";
    $tipo = isset($articulo['Tipo']) ? $articulo['Tipo'] : "";
    $marca = isset($articulo['Marca']) ? $articulo['Marca'] : "";
    
    $codigo = $articulo['Codigo'];
    $descripcion = $mysqli->real_escape_string($articulo['Descripcion']);
    
    $sql = "INSERT INTO  articulos (id_sucursal,codigo, descripcion, categoria, registro, tipo, marca, precio, stock)
         VALUES (".$sucursal.",
         '" . $codigo . "',
         '" . $descripcion . "',
         '" . $categoria . "',
         '" . $registro . "',
         '" . $tipo . "',
         '" . $marca . "',
         '" . $articulo['Precio'] . "',
         '" . $articulo['Stock'] . "')";
    
    echo "\n\tInsertando SQL[".$sql."]";
    
    if ($mysqli->query($sql) != TRUE) {
        echo "\n [insertarArticulo] Error al insertar articulo: " . $sql . "Error " . $mysqli->error;
    } else {
        echo "\n\tAlmacenado:" . $sucursal . " Codigo:" . $codigo;
    }
    
    $mysqli->close();
}

function actualizarArticulo($articulo, $sucursal)
{
    $mysqli = getConnection();
    
    $categoria = isset($articulo['Categoria']) ? $articulo['Categoria'] : "";
    $registro = isset($articulo['Registro']) ? $articulo['Registro'] : "";
    $tipo = isset($articulo['Tipo']) ? $articulo['Tipo'] : "";
    $marca = isset($articulo['Marca']) ? $articulo['Marca'] : "";
    
    $codigo = $articulo['Codigo'];
    $descripcion = $mysqli->real_escape_string($articulo['Descripcion']);
    
    $sql = "UPDATE articulos SET id_sucursal = ". $sucursal .",
            codigo = '" . $codigo . "',
            descripcion = '" . $descripcion . "',
            categoria = '" . $categoria . "',
            registro = '" . $registro . "',
            tipo = '" . $tipo . "',
            marca = '" . $marca . "',
            precio = '" . $articulo['Precio'] . "',
            stock = '" . $articulo['Stock'] . "'
            WHERE codigo = '" . $articulo['Codigo'] . "'
            AND id_sucursal = ". $sucursal;
    
     echo "\n\tActualizando SQL[".$sql."]";
    
    if ($mysqli->query($sql) != TRUE) {
        echo "\n[actualizarArticulo] Error al actualizar Articulo: " . $sql . "<br>" . $mysqli->error;
    } else {
        echo " -> Actualizado:" . $sucursal . " Codigo:" . $codigo;
    }
    $mysqli->close();
}

function actualizarCodigos($datos)
{
    $mysqli = getConnection();
    $count = 0;
    foreach ($datos as $key) {
        
        $codigo = isset($key['Codigo']) ? $key['Codigo'] : "";
        $descripcion = isset($key['Descripcion']) ? $key['Descripcion'] : "";
        
        $descripcion = $mysqli->real_escape_string($descripcion);
        
        $sql = "INSERT INTO codigos_actualizados (codigo,descripcion) VALUES ( '" . $codigo . "', '" . $descripcion . "')";
        
        if ($mysqli->query($sql) != TRUE) {
            echo "\n[actualizarCodigos] Error al registrar codigo: " . $sql . "<br>" . $mysqli->error;
        } else {
            echo "\n\tAlmacenado  Codigo " . $codigo;
            
            $count ++;
        }
    }
    $mysqli->close();
    return $count;
}

function postRestPostService($url)
{
    $content = json_encode("your data to be sent");

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "Content-type: application/json"
    ));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status != 200) {
        echo ("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        return 0;
    }

    curl_close($curl);

    $response = json_decode($json_response, true);

    return $response;
}

function getRestService($url)
{
    $data = null;
    try {
        $data = json_decode(file_get_contents($url), true);
    } catch (Exception $e) {
        error_log("El servicio no entrega datos...", 0);
    }
    return $data;
}

function getActualDate()
{
    $fecha = date('Y/m/d');
    return $fecha;
}

?>

