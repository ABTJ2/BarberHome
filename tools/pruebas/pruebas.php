<?php
/**
 * Herramientas compartidas por las pruebas automáticas de BarberHome.
 *
 * Este archivo no es parte de la aplicación: lo usan solamente los scripts de
 * tools/pruebas. Contiene cuatro cosas:
 *
 *   Pruebas .............. verificaciones y conteo de resultados.
 *   BaseDatos ............conexión y consultas de comprobación.
 *   ClienteHttp ..........peticiones con sesión, como las que hace el navegador.
 *   ServidorPhp ..........servidor web propio para poder probar la aplicación.
 *
 * No se usa PHPUnit ni ninguna librería externa: alcanza con PHP y curl.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    exit('Este archivo solo se ejecuta desde la línea de comandos.');
}

/* ================================================================== */
/* Pruebas: verificaciones y resultado final                          */
/* ================================================================== */

class Pruebas
{
    private $total = 0;
    private $fallas = 0;
    private $fallos = array();

    /**
     * Escribe un título para separar un bloque de verificaciones.
     */
    public function seccion($titulo)
    {
        echo "\n=== $titulo ===\n";
        flush();
    }

    /**
     * Verifica que una condición sea verdadera.
     *
     * @param bool   $condicion    lo que se quiere comprobar.
     * @param string $descripcion  qué se está comprobando, en palabras.
     * @param string $detalle      valor real, para entender el fallo.
     */
    public function verificar($condicion, $descripcion, $detalle = '')
    {
        if ($condicion) {
            $this->total++;
            echo "  PASA  $descripcion\n";

            return TRUE;
        }

        $this->total++;
        $this->fallas++;
        $this->fallos[] = $descripcion . ($detalle === '' ? '' : ' (' . $detalle . ')');
        echo "  FALLA $descripcion" . ($detalle === '' ? '' : "  -> $detalle") . "\n";
        flush();

        return FALSE;
    }

    /**
     * Verifica que dos valores sean iguales (operador ==, sin distinguir tipos).
     */
    public function igual($esperado, $obtenido, $descripcion)
    {
        return $this->verificar(
            $esperado == $obtenido,
            $descripcion,
            'esperado=' . $this->texto($esperado) . ' obtenido=' . $this->texto($obtenido)
        );
    }

    /**
     * Verifica que un texto esté dentro de otro (por ejemplo, un mensaje en el HTML).
     */
    public function contiene($aguja, $pila, $descripcion)
    {
        return $this->verificar(
            strpos($pila, $aguja) !== FALSE,
            $descripcion,
            'no se encontró "' . $aguja . '"'
        );
    }

    /**
     * Verifica que un texto NO esté dentro de otro.
     */
    public function no_contiene($aguja, $pila, $descripcion)
    {
        return $this->verificar(
            strpos($pila, $aguja) === FALSE,
            $descripcion,
            'apareció "' . $aguja . '" y no debía'
        );
    }

    /**
     * Muestra el resumen y devuelve TRUE si no hubo ningún fallo.
     */
    public function resumen($titulo)
    {
        echo "\n----------------------------------------\n";
        echo "$titulo: $this->total verificaciones, $this->fallas fallaron\n";

        if ($this->fallos) {
            echo "\nVerificaciones que fallaron:\n";
            foreach ($this->fallos as $fallo) {
                echo " - $fallo\n";
            }
        }

        echo "----------------------------------------\n";

        return $this->fallas === 0;
    }

    /**
     * Convierte un valor en algo corto y legible para mostrarlo en los fallos.
     */
    private function texto($valor)
    {
        if (is_bool($valor)) {
            return $valor ? 'TRUE' : 'FALSE';
        }

        if ($valor === NULL) {
            return 'NULL';
        }

        if (is_scalar($valor)) {
            return (string) $valor;
        }

        return json_encode($valor);
    }
}

/* ================================================================== */
/* BaseDatos: conexión y consultas de comprobación                    */
/* ================================================================== */

class BaseDatos
{
    private $servidor;
    private $usuario;
    private $clave;
    private $base;
    private $link = NULL;

    public function __construct($servidor, $usuario, $clave, $base)
    {
        $this->servidor = $servidor;
        $this->usuario = $usuario;
        $this->clave = $clave;
        $this->base = $base;
    }

    /**
     * Abre la conexión. Si MySQL no está encendido avisa con un mensaje claro
     * en lugar de mostrar un error de PHP.
     */
    public function conectar()
    {
        if ($this->link !== NULL) {
            return $this->link;
        }

        $link = @new mysqli($this->servidor, $this->usuario, $this->clave, $this->base);

        if ($link->connect_errno) {
            throw new RuntimeException(
                'No se pudo conectar a MySQL en ' . $this->servidor
                . ' (' . $link->connect_error . '). ¿Está iniciado XAMPP?'
            );
        }

        $link->set_charset('utf8mb4');
        $this->link = $link;

        return $this->link;
    }

    /**
     * Ejecuta una consulta simple y devuelve el resultado.
     */
    public function consulta($sql)
    {
        $resultado = $this->conectar()->query($sql);

        if ($resultado === FALSE) {
            throw new RuntimeException('Consulta fallida: ' . $sql . ' (' . $this->conectar()->error . ')');
        }

        return $resultado;
    }

    /**
     * Ejecuta un archivo con varias sentencias (el esquema de la base).
     */
    public function ejecutar_script($sql)
    {
        // Se lee el archivo sin BOM: si quedó guardado con BOM, MySQL no
        // reconoce la primera sentencia.
        $sql = ltrim($sql, "\xEF\xBB\xBF");

        if ($this->conectar()->multi_query($sql) === FALSE) {
            throw new RuntimeException('No se pudo cargar el esquema: ' . $this->conectar()->error);
        }

        // Hay que leer todos los resultados para que la conexión quede libre.
        while ($this->conectar()->more_results() && $this->conectar()->next_result()) {
            // Se vacían los resultados intermedios.
        }
    }

    /**
     * Devuelve la primera columna de la primera fila.
     */
    public function valor($sql, $columna = 0)
    {
        $fila = $this->consulta($sql)->fetch_row();

        return $fila === NULL ? NULL : $fila[$columna];
    }

    /**
     * Devuelve la primera fila como array asociativo.
     */
    public function fila($sql)
    {
        return $this->consulta($sql)->fetch_assoc();
    }

    /**
     * Cuenta filas sin traerlas todas.
     */
    public function contar($sql)
    {
        return (int) $this->valor($sql);
    }

    /**
     * Cierra la conexión con MySQL.
     */
    public function cerrar()
    {
        if ($this->link !== NULL) {
            $this->link->close();
            $this->link = NULL;
        }
    }
}

/* ================================================================== */
/* ClienteHttp: peticiones con sesión                                 */
/* ================================================================== */

class ClienteHttp
{
    private $base;
    private $jar;

    /**
     * @param string $base dirección del servidor, por ejemplo http://127.0.0.1:8765
     * @param string $jar  archivo donde se guardan las cookies de la sesión.
     */
    public function __construct($base, $jar)
    {
        $this->base = rtrim($base, '/');
        $this->jar = $jar;
    }

    public function base()
    {
        return $this->base;
    }

    /**
     * Archivo donde se guardan las cookies de la sesión.
     */
    public function jar()
    {
        return $this->jar;
    }

    /**
     * Empieza una sesión nueva (equivale a cerrar el navegador y abrirlo de nuevo).
     */
    public function cerrar_sesion()
    {
        if (is_file($this->jar)) {
            unlink($this->jar);
        }
    }

    /**
     * Petición GET.
     */
    public function get($ruta, $otra_base = NULL)
    {
        return $this->pedir('GET', $this->url($ruta, $otra_base));
    }

    /**
     * Petición POST con los datos del formulario.
     */
    public function post($ruta, array $datos = array(), $otra_base = NULL)
    {
        return $this->pedir('POST', $this->url($ruta, $otra_base), $datos);
    }

    /**
     * Devuelve el token CSRF de una página con formulario.
     *
     * @return string el token, o cadena vacía si la página no tiene formulario.
     */
    public function csrf($ruta, $otra_base = NULL)
    {
        $respuesta = $this->get($ruta, $otra_base);

        if (preg_match('/name="bh_csrf"[^>]*value="([^"]+)"/', $respuesta['cuerpo'], $coincidencias)) {
            return $coincidencias[1];
        }

        return '';
    }

    /**
     * Inicia sesión con uno de los usuarios de ejemplo.
     *
     * @return array la respuesta del POST de login.
     */
    public function iniciar_sesion($usuario = 'encargado', $clave = '123456', $otra_base = NULL)
    {
        $this->cerrar_sesion();

        $token = $this->csrf('/login', $otra_base);

        return $this->post('/login', array(
            'bh_csrf' => $token,
            'username' => $usuario,
            'password' => $clave,
        ), $otra_base);
    }

    private function url($ruta, $otra_base)
    {
        return rtrim($otra_base === NULL ? $this->base : $otra_base, '/') . '/' . ltrim($ruta, '/');
    }

    /**
     * Hace la petición con curl y devuelve código, cuerpo y error.
     */
    private function pedir($metodo, $url, array $datos = array())
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => TRUE,
            // Las redirecciones se miran, no se siguen: así se puede verificar
            // que la aplicación manda al lugar correcto.
            CURLOPT_FOLLOWLOCATION => FALSE,
            // Tiempos máximos para que ninguna petición pueda quedar colgada.
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
        ));

        if ($metodo === 'POST') {
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($datos));
        }

        $cuerpo = curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return array(
            'codigo' => (int) $codigo,
            'cuerpo' => (string) $cuerpo,
            'error' => $error,
        );
    }
}

/* ================================================================== */
/* Varias peticiones al mismo tiempo                                  */
/* ================================================================== */

/**
 * Envía varias peticiones en paralelo y devuelve las respuestas en el mismo
 * orden en que se pasaron. Sirve para probar dos peticiones que actúan sobre
 * el mismo turno al mismo tiempo, por ejemplo cobrarlo y cancelarlo.
 *
 * @param array $peticiones cada una: array('url' => ..., 'datos' => array(...), 'jar' => archivo)
 */
function enviar_simultaneas(array $peticiones)
{
    $lote = curl_multi_init();
    $manijas = array();

    foreach ($peticiones as $indice => $peticion) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $peticion['url'],
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_FOLLOWLOCATION => FALSE,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => http_build_query(isset($peticion['datos']) ? $peticion['datos'] : array()),
            CURLOPT_COOKIEFILE => isset($peticion['jar']) ? $peticion['jar'] : NULL,
        ));

        curl_multi_add_handle($lote, $curl);
        $manijas[$indice] = $curl;
    }

    // Se espera a que terminen todas, mirando el tiempo límite de la corrida.
    $ejecutando = NULL;
    do {
        curl_multi_exec($lote, $ejecutando);

        // Si no hay nada que esperar, curl_multi_select devuelve -1: en ese
        // caso se duerme un poco para no girar en vacío.
        if (curl_multi_select($lote, 0.05) === -1) {
            usleep(1000);
        }

        exigir_tiempo();
    } while ($ejecutando > 0);

    $respuestas = array();

    foreach ($manijas as $indice => $curl) {
        $respuestas[$indice] = array(
            'codigo' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'cuerpo' => (string) curl_multi_getcontent($curl),
        );

        curl_multi_remove_handle($lote, $curl);
        curl_close($curl);
    }

    curl_multi_close($lote);

    return $respuestas;
}

/* ================================================================== */
/* ServidorPhp: servidor propio para probar la aplicación             */
/* ================================================================== */

class ServidorPhp
{
    private $puerto;
    private $proceso;
    private $pid;
    private $bitacora;

    /**
     * Levanta un servidor con el PHP que está ejecutando las pruebas.
     *
     * El comando se pasa como array (y no como texto) para que PHP lo ejecute
     * sin pasar por cmd.exe: así el PID que devuelve es el del php.exe del
     * servidor y se lo puede terminar con seguridad.
     *
     * @param string $raiz   carpeta del proyecto (raíz de la aplicación).
     * @param string $router script router.php de tools/pruebas.
     * @param int    $desde  primer puerto que se intenta usar.
     *
     * @return ServidorPhp
     */
    public static function levantar($raiz, $router, $desde = 8765)
    {
        $servidor = new self();
        $servidor->puerto = puerto_libre($desde);

        if ($servidor->puerto === NULL) {
            throw new RuntimeException('No se encontró un puerto libre para las pruebas.');
        }

        $servidor->bitacora = tempnam(sys_get_temp_dir(), 'bh_pruebas_');

        $comando = array(
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $servidor->puerto,
            $router,
        );

        // La salida del servidor va a un archivo: si quedara en una tubería sin
        // leer, el servidor se bloquearía al llenarse y, además, mantendría
        // abierta la salida de esta consola.
        $proceso = proc_open(
            $comando,
            array(
                0 => array('pipe', 'r'),
                1 => array('file', $servidor->bitacora, 'a'),
                2 => array('file', $servidor->bitacora, 'a'),
            ),
            $tuberias,
            $raiz
        );

        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar el servidor de pruebas.');
        }

        fclose($tuberias[0]);

        $servidor->proceso = $proceso;

        $estado = proc_get_status($proceso);
        $servidor->pid = isset($estado['pid']) ? (int) $estado['pid'] : 0;

        if ($servidor->pid <= 0) {
            throw new RuntimeException('No se pudo obtener el PID del servidor de pruebas.');
        }

        // Si el script termina de cualquier manera (incluso por un error fatal),
        // este servidor se apaga igual.
        registro_servidor($servidor);

        $servidor->esperar();

        return $servidor;
    }

    public function puerto()
    {
        return $this->puerto;
    }

    public function pid()
    {
        return $this->pid;
    }

    public function url($ruta = '')
    {
        return 'http://127.0.0.1:' . $this->puerto . '/' . ltrim($ruta, '/');
    }

    /**
     * Devuelve el archivo con el log del servidor (si algo sale mal).
     */
    public function bitacora()
    {
        return $this->bitacora;
    }

    /**
     * True si el proceso del servidor sigue vivo.
     */
    public function vivo()
    {
        if ($this->pid > 0 && !proceso_existe($this->pid)) {
            return FALSE;
        }

        if (!is_resource($this->proceso)) {
            return FALSE;
        }

        $estado = proc_get_status($this->proceso);

        return !empty($estado['running']);
    }

    /**
     * Apaga el servidor y se saca de la lista de los que hay que apagar al
     * final. Se puede llamar varias veces sin problema.
     */
    public function detener()
    {
        if (is_resource($this->proceso)) {
            // En Windows, /T mata también los procesos hijos.
            if (DIRECTORY_SEPARATOR === '\\' && $this->pid > 0) {
                @exec('taskkill /F /T /PID ' . $this->pid . ' 2>nul');
            }

            proc_terminate($this->proceso);
            proc_close($this->proceso);
            $this->proceso = NULL;
        }

        // Si todavía sigue vivo, se intenta de nuevo con el PID.
        if ($this->pid > 0 && proceso_existe($this->pid)) {
            if (DIRECTORY_SEPARATOR === '\\') {
                @exec('taskkill /F /PID ' . $this->pid . ' 2>nul');
            } else {
                @exec('kill -9 ' . $this->pid . ' 2>/dev/null');
            }
        }

        $this->pid = 0;

        if ($this->bitacora !== NULL && is_file($this->bitacora)) {
            unlink($this->bitacora);
            $this->bitacora = NULL;
        }

        olvidar_servidor($this);
    }

    /**
     * Espera a que el servidor empiece a responder.
     */
    private function esperar()
    {
        $limite = microtime(TRUE) + 20;

        while (microtime(TRUE) < $limite) {
            if (!proceso_existe($this->pid)) {
                throw new RuntimeException(
                    'El servidor de pruebas se apagó al arrancar.'
                    . ' Log: ' . (string) $this->bitacora
                );
            }

            $curl = curl_init($this->url('/login'));

            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
            ));

            curl_exec($curl);
            $codigo = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_error($curl);
            curl_close($curl);

            if ($codigo > 0) {
                return;
            }

            usleep(100000);
        }
        $this->detener();

        throw new RuntimeException(
            'El servidor de pruebas no respondió en el puerto ' . $this->puerto . '.'
        );
    }
}

/**
 * Devuelve el primer puerto libre a partir de $desde.
 */
function puerto_libre($desde)
{
    for ($puerto = $desde; $puerto < $desde + 50; $puerto++) {
        $socket = @stream_socket_server('tcp://127.0.0.1:' . $puerto, $codigo, $texto);

        if ($socket) {
            fclose($socket);

            return $puerto;
        }
    }

    return NULL;
}

/**
 * True si el proceso con ese PID sigue en el sistema.
 */
function proceso_existe($pid)
{
    if ($pid <= 0) {
        return FALSE;
    }

    if (function_exists('posix_kill')) {
        // En Linux alcanza con signals 0.
        return @posix_kill($pid, 0) || posix_get_last_error() === PHP_INT_MAX;
    }

    $salida = array();
    $codigo = 0;

    @exec(
        'tasklist /FI "PID eq ' . (int) $pid . '" /NH 2>nul',
        $salida,
        $codigo
    );

    return stripos(implode("\n", $salida), (string) $pid) !== FALSE;
}


/* ================================================================== */
/* Control de tiempo y apagado seguro                                 */
/* ================================================================== */

// Tiempo máximo que puede tomar toda la corrida, en segundos. Si se pasa, las
// pruebas se cortan: así nunca quedan esperándose para siempre.
define('LIMITE_SEGUNDOS', 600);

// Momento en que termina la corrida.
$GLOBALS['bh_limite'] = microtime(TRUE) + LIMITE_SEGUNDOS;

// Servidores que hay que apagar al final, se termine como se termine el script.
$GLOBALS['bh_servidores'] = array();

/**
 * Segundos que quedan antes de que se corten las pruebas.
 */
function tiempo_restante()
{
    return $GLOBALS['bh_limite'] - microtime(TRUE);
}

/**
 * Lanza una excepción si ya se pasó el tiempo límite.
 */
function exigir_tiempo()
{
    if (tiempo_restante() <= 0) {
        throw new RuntimeException(
            'Las pruebas pasaron de ' . LIMITE_SEGUNDOS . ' segundos y se cortaron.'
        );
    }
}

/**
 * Anota un servidor para que se apague al final, aunque el script termine de
 * golpe por un error.
 */
function registro_servidor(ServidorPhp $servidor)
{
    $GLOBALS['bh_servidores'][] = $servidor;
}

/**
 * Saca un servidor de la lista porque ya se apagó.
 */
function olvidar_servidor(ServidorPhp $servidor)
{
    $restantes = array();

    foreach ($GLOBALS['bh_servidores'] as $otro) {
        if ($otro !== $servidor) {
            $restantes[] = $otro;
        }
    }

    $GLOBALS['bh_servidores'] = $restantes;
}

/**
 * Apaga todos los servidores de pruebas que sigan encendidos.
 */
function apagar_servidores()
{
    foreach ($GLOBALS['bh_servidores'] as $servidor) {
        try {
            $servidor->detener();
        } catch (Throwable $error) {
            // Al limpiar no se lanza ningún error: solo se intenta apagar.
        }
    }

    $GLOBALS['bh_servidores'] = array();
}

/* ================================================================== */
/* Utilidades                                                         */
/* ================================================================== */

/**
 * Lee application/config/database.php y devuelve los datos de conexión.
 * Así las pruebas usan las mismas credenciales que la aplicación, sin
 * repetirlas en otro lado.
 */
function leer_configuracion_base_datos($raiz)
{
    $archivo = $raiz . '/application/config/database.php';

    if (!is_file($archivo)) {
        throw new RuntimeException('No se encontró ' . $archivo);
    }

    // El archivo de configuración de CodeIgniter es un array que devuelve
    // $db['default']. Empieza comprobando BASEPATH y usa la constante
    // ENVIRONMENT, así que hay que definir las dos para poder incluirlo.
    if (!defined('BASEPATH')) {
        define('BASEPATH', $raiz . '/system/');
    }

    if (!defined('ENVIRONMENT')) {
        define('ENVIRONMENT', 'test');
    }

    $active_group = 'default';
    $query_builder = TRUE;
    $db = array();

    include($archivo);

    if (!isset($db[$active_group])) {
        throw new RuntimeException('application/config/database.php no define el grupo "' . $active_group . '".');
    }

    $config = $db[$active_group];

    return array(
        'servidor' => isset($config['hostname']) ? $config['hostname'] : '127.0.0.1',
        'usuario' => isset($config['username']) ? $config['username'] : 'root',
        'clave' => isset($config['password']) ? $config['password'] : '',
        'base' => isset($config['database']) ? $config['database'] : '',
    );
}
