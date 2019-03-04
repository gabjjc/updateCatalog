 <?php 

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
    
    print_r($rows);
    
    
    
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
    
    
  ?>