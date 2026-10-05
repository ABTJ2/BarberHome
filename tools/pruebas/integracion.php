<?php
/**
 * Pruebas de integración de BarberHome.
 *
 * Estas pruebas manejan la aplicación como lo haría una persona: entran por
 * formulario, cambian estados, cobran, anulan y restauran. Se comprueba lo que
 * quedó en la base y lo que se muestra en pantalla.
 *
 * Nada de esto toca la base real:
 *
 *   1. Se crea la base barber_house_test a partir de database/barber_house.sql.
 *   2. Se levanta un servidor PHP propio (router.php) con CI_ENV=test, que usa
 *      application/config/test/database.php y apunta a barber_house_test.
 *   3. Al terminar se borra la base de pruebas.
 *
 * Uso: php tools/pruebas/ejecutar.php
 */

require_once __DIR__ . '/pruebas.php';

// Nombre de la base de pruebas. Tiene que ser distinto del de la aplicación.
define('BASE_PRUEBAS', 'barber_house_test');

// Rutas y usuarios que usan las pruebas.
define('RUTA_ESQUEMA', dirname(__DIR__, 2) . '/database/barber_house.sql');
define('RUTA_ROUTER', __DIR__ . '/router.php');
define('USUARIO_ENCARGADO', 'encargado');
define('CLAVE_EJEMPLO', '123456');

// Pares de peticiones simultáneas que se hacen en la prueba de concurrencia.
define('PARES_SIMULTANEOS', 12);

/* ================================================================== */
/* Escenario: todo lo que comparten las pruebas                       */
/* ================================================================== */

class Escenario
{
    public $pruebas;
    public $db;
    public $encargado;
    public $recepcion;
    public $servidor;
    public $servidor_b;

    /**
     * Vuelve a crear la base de pruebas desde cero y deja la sesión del
     * Encargado iniciada, para que cada bloque de pruebas empiece en el mismo
     * punto.
     */
    public function reiniciar()
    {
        $this->db->cerrar();
        $this->db = crear_base_de_pruebas(dirname(__DIR__, 2));
        $this->encargado->iniciar_sesion(USUARIO_ENCARGADO, CLAVE_EJEMPLO);
    }
}

/* ================================================================== */
/* Base de datos de pruebas                                           */
/* ================================================================== */

/**
 * Arma el SQL del esquema para la base de pruebas.
 *
 * Lee database/barber_house.sql y le cambia el nombre de la base por el de
 * pruebas. Además elimina la línea DROP DATABASE: aunque algo se escribiera
 * mal en el nombre, las pruebas no podrían borrar ninguna base.
 */
function esquema_de_pruebas()
{
    static $sql = NULL;

    if ($sql !== NULL) {
        return $sql;
    }

    if (!is_file(RUTA_ESQUEMA)) {
        throw new RuntimeException('No se encontró el esquema ' . RUTA_ESQUEMA);
    }

    $sql = file_get_contents(RUTA_ESQUEMA);
    $config = leer_configuracion_base_datos(dirname(__DIR__, 2));
    $base_real = $config['base'];

    // 1. Se anulan las dos sentencias que Sagan de la base de datos:
    //    DROP DATABASE y CREATE DATABASE. La base de pruebas la crea y borra
    //    el propio runner, así que el archivo nunca puede tocar ninguna base.
    $sql = preg_replace(
        '/^[ \t]*DROP\s+DATABASE\s+IF\s+EXISTS\s+[^;]+;?[ \t]*$/mi',
        '-- DROP DATABASE omitido en las pruebas',
        $sql
    );
    $sql = preg_replace(
        '/^[ \t]*CREATE\s+DATABASE\s+[^;]+;?[ \t]*$/mi',
        '-- CREATE DATABASE lo hace el runner de pruebas',
        $sql
    );

    // 2. En todo lo que queda se usa el nombre de la base de pruebas.
    $sql = preg_replace('/\b' . preg_quote($base_real, '/') . '\b/', BASE_PRUEBAS, $sql);

    // 3. Comprobación final: no puede quedar ninguna sentencia sobre la base real.
    if (preg_match('/\b(DROP|CREATE|USE)\s+DATABASE\s+`?' . preg_quote($base_real, '/') . '`?\b/i', $sql)) {
        throw new RuntimeException(
            'El esquema de pruebas todavía menciona la base ' . $base_real . '. No se continúa por seguridad.'
        );
    }

    // Se guarda en memoria porque todas las pruebas lo vuelven a usar.
    $sql = (string) $sql;

    return $sql;
}

/**
 * Crea (o vuelve a crear) la base de pruebas.
 *
 * @return BaseDatos conexión lista para las consultas de comprobación.
 */
function crear_base_de_pruebas($raiz)
{
    $config = leer_configuracion_base_datos($raiz);

    if ($config['base'] === BASE_PRUEBAS) {
        throw new RuntimeException(
            'La base de la aplicación se llama ' . BASE_PRUEBAS . ', que es la de pruebas.'
            . ' Las pruebas se abortan para no tocar la base real.'
        );
    }

    $db = new BaseDatos($config['servidor'], $config['usuario'], $config['clave'], '');
    $db->conectar();

    // Solo se borra y se crea la base de pruebas. La de la aplicación no se
    // menciona en ninguna sentencia.
    $db->consulta('DROP DATABASE IF EXISTS `' . BASE_PRUEBAS . '`');
    $db->consulta('CREATE DATABASE `' . BASE_PRUEBAS . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $conexion = new BaseDatos($config['servidor'], $config['usuario'], $config['clave'], BASE_PRUEBAS);
    $conexion->ejecutar_script(esquema_de_pruebas());

    return $conexion;
}

/**
 * Borra la base de pruebas.
 */
function borrar_base_de_pruebas($raiz)
{
    $config = leer_configuracion_base_datos($raiz);

    $db = new BaseDatos($config['servidor'], $config['usuario'], $config['clave'], '');
    $db->conectar();
    $db->consulta('DROP DATABASE IF EXISTS `' . BASE_PRUEBAS . '`');
}

/* ================================================================== */
/* Bloques de pruebas                                                 */
/* ================================================================== */

/**
 * 1. El sistema arranca: se puede entrar y la pantalla principal responde.
 */
function probar_inicio(Escenario $e)
{
    $e->pruebas->seccion('1. Inicio de sesión');

    // Sin sesión no se entra a ninguna pantalla.
    $r = $e->encargado->get('/cobros');
    $e->pruebas->verificar(
        $r['codigo'] === 307,
        'Sin sesión se vuelve al login',
        'código=' . $r['codigo']
    );

    // El Encargado entra con el usuario de ejemplo.
    $respuesta = $e->encargado->iniciar_sesion(USUARIO_ENCARGADO, CLAVE_EJEMPLO);
    $e->pruebas->verificar(
        $respuesta['codigo'] === 303,
        'El Encargado puede iniciar sesión',
        'código=' . $respuesta['codigo']
    );

    $r = $e->encargado->get('/inicio');
    $e->pruebas->verificar(
        $r['codigo'] === 200 && $r['cuerpo'] !== '',
        'La pantalla de inicio responde',
        'código=' . $r['codigo'] . ' ' . $r['error']
    );

    // Un usuario que no existe no puede entrar.
    $otro = new ClienteHttp($e->servidor->url(), tempnam(sys_get_temp_dir(), 'bh_pruebas_'));
    $respuesta = $otro->iniciar_sesion('no-existe', 'incorrecta');
    $e->pruebas->verificar(
        $respuesta['codigo'] === 200 && strpos($respuesta['cuerpo'], 'incorrectos') !== FALSE,
        'Un usuario inexistente no puede entrar',
        'código=' . $respuesta['codigo']
    );
    $otro->cerrar_sesion();
}

/**
 * 2. Todas las pantallas del sistema cargan sin errores.
 */
function probar_pantallas(Escenario $e)
{
    $e->pruebas->seccion('2. Pantallas');

    $pantallas = array(
        '/inicio' => 'Inicio',
        '/turnos' => 'Agenda',
        '/turnos/nuevo' => 'Nuevo turno',
        '/turnos/ver/1' => 'Turno',
        '/clientes' => 'Clientes',
        '/clientes/ver/1' => 'Cliente',
        '/clientes/nuevo' => 'Nuevo cliente',
        '/cobros' => 'Cobros',
        '/cobros?archived=1' => 'Cobros',
        '/formas-pago' => 'Formas de pago',
        '/contabilidad' => 'Contabilidad',
        '/egresos' => 'Egresos',
        '/peluqueros' => 'Peluqueros',
        '/peluqueros/nuevo' => 'Nuevo peluquero',
        '/peluqueros/editar/2' => 'Editar',
        '/peluqueros/reasignar/2' => 'Reasignar',
        '/servicios' => 'Servicios',
        '/usuarios' => 'Usuarios',
        '/usuarios/nuevo' => 'Nuevo usuario',
    );

    foreach ($pantallas as $ruta => $texto) {
        $r = $e->encargado->get($ruta);
        $e->pruebas->verificar(
            $r['codigo'] === 200 && strpos($r['cuerpo'], $texto) !== FALSE,
            'La pantalla ' . $ruta . ' carga y muestra "' . $texto . '"',
            'código=' . $r['codigo']
        );
    }
}

/**
 * 3. Cada perfil entra a lo que le corresponde.
 */
function probar_permisos_por_rol(Escenario $e)
{
    $e->pruebas->seccion('3. Permisos por perfil');

    // El Recepcionista entra con su propia sesión.
    $e->recepcion->iniciar_sesion('recepcion', CLAVE_EJEMPLO);

    // Los Recepcionistas no entran a las secciones de administración.
    $prohibido = array(
        '/contabilidad' => 'contabilidad',
        '/egresos' => 'egresos',
        '/usuarios' => 'usuarios',
        '/peluqueros' => 'peluqueros',
        '/servicios' => 'servicios',
    );

    foreach ($prohibido as $ruta => $nombre) {
        $r = $e->recepcion->get($ruta);
        $e->pruebas->verificar(
            $r['codigo'] === 403,
            'El Recepcionista no puede entrar a ' . $nombre,
            'código=' . $r['codigo']
        );
    }

    // Pero sí a la parte de atención al público.
    $permitido = array('/turnos', '/clientes', '/cobros');

    foreach ($permitido as $ruta) {
        $r = $e->recepcion->get($ruta);
        $e->pruebas->verificar(
            $r['codigo'] === 200,
            'El Recepcionista sí puede entrar a ' . $ruta,
            'código=' . $r['codigo']
        );
    }

    // Volvemos a la sesión del Encargado para las pruebas siguientes.
    $e->encargado->iniciar_sesion(USUARIO_ENCARGADO, CLAVE_EJEMPLO);
}

/**
 * 4. PROBLEMA 1: un turno cobrado no puede quedar cancelado ni ausente.
 */
function probar_turno_cobrado_no_se_cancela(Escenario $e)
{
    $e->pruebas->seccion('4. Un turno cobrado no se puede cancelar');

    $e->reiniciar();

    // El turno 2 viene cobrado y atendido en la base de ejemplo.
    $antes = $e->db->fila(
        'SELECT a.status,
                (SELECT COUNT(*) FROM payments p WHERE p.appointment_id = a.id) AS cobros
           FROM appointments a
          WHERE a.id = 2'
    );

    $e->pruebas->verificar(
        $antes['status'] === 'attended' && (int) $antes['cobros'] === 1,
        'El turno 2 empieza atendido y cobrado',
        json_encode($antes)
    );

    // 4a) Cancelar desde la pantalla de estados del turno.
    $e->encargado->post('/turnos/estado/2', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/ver/2'),
        'status' => 'cancelled',
    ));

    $e->pruebas->igual(
        'attended',
        $e->db->valor('SELECT status FROM appointments WHERE id = 2'),
        'Cancelar un turno cobrado se rechaza'
    );

    // 4b) El botón Eliminar también cancelaba: tiene que rechazarse igual.
    $e->encargado->post('/turnos/eliminar/2', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/ver/2'),
    ));

    $e->pruebas->igual(
        'attended',
        $e->db->valor('SELECT status FROM appointments WHERE id = 2'),
        'Eliminar un turno cobrado se rechaza'
    );

    // 4c) Marcarlo como ausente tampoco debe pasar.
    $e->encargado->post('/turnos/estado/2', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/ver/2'),
        'status' => 'no_show',
    ));

    $e->pruebas->igual(
        'attended',
        $e->db->valor('SELECT status FROM appointments WHERE id = 2'),
        'Marcar como ausente un turno cobrado se rechaza'
    );

    // 4d) Control: el turno 1 no está cobrado, ahí sí se puede cancelar.
    $e->encargado->post('/turnos/estado/1', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/ver/1'),
        'status' => 'cancelled',
    ));

    $e->pruebas->igual(
        'cancelled',
        $e->db->valor('SELECT status FROM appointments WHERE id = 1'),
        'Control: cancelar un turno sin cobros sí funciona'
    );

    // 4e) Control: reactivar el turno cancelado.
    $e->encargado->post('/turnos/estado/1', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/ver/1'),
        'status' => 'reserved',
    ));

    $e->pruebas->igual(
        'reserved',
        $e->db->valor('SELECT status FROM appointments WHERE id = 1'),
        'Control: reactivar un turno cancelado funciona'
    );

    // 4f) Invariante general de toda la base.
    $e->pruebas->igual(
        0,
        $e->db->contar(
            'SELECT COUNT(*)
               FROM payments p
               JOIN appointments a ON a.id = p.appointment_id
              WHERE a.status IN ("cancelled", "no_show")'
        ),
        'Invariante: ningún turno cancelado o ausente tiene cobros'
    );
}

/**
 * 5. PROBLEMA 2: cambiar el horario de un peluquero no puede romper turnos futuros.
 */
function probar_horarios_no_rompen_turnos(Escenario $e)
{
    $e->pruebas->seccion('5. Cambiar horarios no rompe turnos futuros');

    $e->reiniciar();

    // Situación inicial: el turno 1 es el lunes 2026-10-05 de 15:00 a 15:45
    // con el peluquero 2, que atiende de 10:00 a 19:00.
    $lunes = $e->db->valor('SELECT start_time FROM barber_schedules WHERE barber_id = 2 AND day_of_week = 1');
    $e->pruebas->verificar($lunes === '10:00:00', 'El lunes del peluquero 2 abre a las 10:00', "valor=$lunes");

    // 5a) Nuevo horario que deja afuera el turno de las 15:00: no se guarda.
    guardar_peluquero($e, 2, array(1 => array('16:00', '19:00')), array(1, 2, 3, 4));
    $lunes = $e->db->valor('SELECT start_time FROM barber_schedules WHERE barber_id = 2 AND day_of_week = 1');
    $e->pruebas->verificar($lunes === '10:00:00', '5a) Un horario que deja el turno afuera NO se guarda', "valor=$lunes");

    $r = $e->encargado->get('/peluqueros');
    $e->pruebas->contiene('fuera del horario', $r['cuerpo'], '5a) Se avisa el conflicto en pantalla');

    // 5b) Quitar un servicio que usa un turno futuro: no se guarda.
    guardar_peluquero($e, 2, array(1 => array('09:00', '20:00')), array(2, 3, 4));
    $servicios = $e->db->contar('SELECT COUNT(*) FROM barber_services WHERE barber_id = 2');
    $e->pruebas->verificar($servicios === 4, '5b) Quitar un servicio en uso NO se guarda', "servicios=$servicios");

    $r = $e->encargado->get('/peluqueros');
    $e->pruebas->contiene('ya no realiza', $r['cuerpo'], '5b) Se avisa el conflicto de servicio en pantalla');

    // 5c) Un horario nuevo que sigue cubriendo el turno sí se guarda.
    guardar_peluquero($e, 2, array(1 => array('09:00', '20:00')), array(1, 2, 3, 4));
    $lunes = $e->db->valor('SELECT start_time FROM barber_schedules WHERE barber_id = 2 AND day_of_week = 1');
    $e->pruebas->verificar($lunes === '09:00:00', '5c) Un horario compatible sí se guarda', "valor=$lunes");

    $sabado = $e->db->valor('SELECT end_time FROM barber_schedules WHERE barber_id = 2 AND day_of_week = 6');
    $e->pruebas->verificar($sabado === '19:00:00', '5c) Los demás días también se guardaron', "valor=$sabado");

    // 5d) Se puede volver al horario anterior.
    guardar_peluquero($e, 2, array(1 => array('10:00', '19:00'), 6 => array('10:00', '16:00')), array(1, 2, 3, 4));
    $lunes = $e->db->valor('SELECT start_time FROM barber_schedules WHERE barber_id = 2 AND day_of_week = 1');
    $e->pruebas->verificar($lunes === '10:00:00', '5d) Se puede volver al horario anterior', "valor=$lunes");

    // 5e) Desmarcar un día con turno futuro: no se guarda.
    guardar_peluquero($e, 2, array(1 => array('10:00', '19:00')), array(1, 2, 3, 4), array(1));
    $lunes = $e->db->contar('SELECT COUNT(*) FROM barber_schedules WHERE barber_id = 2 AND day_of_week = 1');
    $e->pruebas->verificar($lunes === 1, '5e) Desmarcar un día con turno futuro NO se guarda', "filas=$lunes");

    $r = $e->encargado->get('/peluqueros');
    $e->pruebas->contiene('sin horario', $r['cuerpo'], '5e) Se avisa el conflicto de día en pantalla');
}

/**
 * Envía el formulario completo de un peluquero, como lo haría la pantalla.
 *
 * @param int   $id      peluquero a guardar.
 * @param array $dias    día => array(horario de inicio, horario de fin).
 * @param array $servicios ids de los servicios que quedan.
 * @param array $cerrados días que se dejan desmarcados.
 */
function guardar_peluquero(Escenario $e, $id, array $dias, array $servicios, array $cerrados = array())
{
    $formulario = array(
        'bh_csrf' => $e->encargado->csrf('/peluqueros/editar/' . $id),
        'full_name' => 'Jesús Funes',
        'phone' => '264 555 2233',
        'commission_percent' => '50',
        'active' => '1',
    );

    // Los siete días. Por defecto atienden de 10:00 a 19:00.
    for ($dia = 1; $dia <= 7; $dia++) {
        // Un día desmarcado no envía su checkbox.
        if (!in_array($dia, $cerrados, TRUE)) {
            $formulario['day_' . $dia] = '1';
        }

        $formulario['start_' . $dia] = '10:00';
        $formulario['end_' . $dia] = '19:00';
    }

    // Se pisan los días indicados.
    foreach ($dias as $dia => $horarios) {
        $formulario['start_' . $dia] = $horarios[0];
        $formulario['end_' . $dia] = $horarios[1];
    }

    foreach ($servicios as $id_servicio) {
        $formulario['service_ids'][] = $id_servicio;
    }

    return $e->encargado->post('/peluqueros/actualizar/' . $id, $formulario);
}

/**
 * 6. El recorrido completo: cliente, turno, cobro, corrección, anulación,
 *    egreso y usuario. Es el camino que más se usa en el día a día.
 */
function probar_recorrido_completo(Escenario $e)
{
    $e->pruebas->seccion('6. Recorrido completo: cliente, turno y cobro');

    $e->reiniciar();

    // 6a) Alta de cliente.
    $r = $e->encargado->post('/clientes/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/clientes/nuevo'),
        'first_name' => 'Ana',
        'last_name' => 'Nuevo',
        'phone' => '264 555 0000',
        'notes' => 'Cliente de prueba',
    ));

    $e->pruebas->verificar($r['codigo'] === 303, '6a) El alta de cliente redirige', 'código=' . $r['codigo']);

    $cliente = $e->db->valor("SELECT id FROM clients WHERE phone = '264 555 0000'");
    $e->pruebas->verificar((int) $cliente > 0, '6a) El cliente quedó guardado', "id=$cliente");

    // 6b) No se puede repetir el teléfono.
    $r = $e->encargado->post('/clientes/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/clientes/nuevo'),
        'first_name' => 'Ana',
        'last_name' => 'Nuevo',
        'phone' => '264 555 0000',
    ));

    $e->pruebas->contiene('Ya existe un cliente', $r['cuerpo'], '6b) Un cliente repetido se rechaza');

    // 6c) Alta de turno: martes 2026-10-06 a las 15:00, peluquero 3, servicio 1.
    $r = $e->encargado->post('/turnos/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/nuevo'),
        'client_id' => $cliente,
        'barber_id' => 3,
        'service_ids' => array(1),
        'date' => '2026-10-06',
        'time' => '15:00',
        'status' => 'reserved',
        'walk_in' => '0',
        'notes' => 'Turno de prueba',
    ));

    $e->pruebas->verificar($r['codigo'] === 303, '6c) El alta de turno redirige', 'código=' . $r['codigo']);

    $turno = $e->db->valor('SELECT id FROM appointments WHERE client_id = ' . (int) $cliente);
    $e->pruebas->verificar((int) $turno > 0, '6c) El turno quedó guardado', "id=$turno");

    // La hora de fin sale de la duración del servicio (45 minutos).
    $fila = $e->db->fila('SELECT start_at, end_at, status FROM appointments WHERE id = ' . (int) $turno);
    $e->pruebas->verificar(
        $fila['start_at'] === '2026-10-06 15:00:00' && $fila['end_at'] === '2026-10-06 15:45:00',
        '6c) La hora de fin se calculó con la duración del servicio',
        json_encode($fila)
    );

    // Se guarda el precio y la duración del momento, no los de hoy.
    $snap = $e->db->fila(
        'SELECT price_snapshot, duration_snapshot
           FROM appointment_services
          WHERE appointment_id = ' . (int) $turno
    );
    $e->pruebas->verificar(
        $snap['price_snapshot'] == '12000.00' && $snap['duration_snapshot'] == 45,
        '6c) Se guardaron las fotos de precio y duración',
        json_encode($snap)
    );

    // 6d) No se puede superponer un turno con otro del mismo peluquero.
    $r = $e->encargado->post('/turnos/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/nuevo'),
        'client_id' => 1,
        'barber_id' => 3,
        'service_ids' => array(1),
        'date' => '2026-10-06',
        'time' => '15:00',
        'status' => 'reserved',
        'walk_in' => '0',
    ));

    $e->pruebas->contiene('superpone', $r['cuerpo'], '6d) Un turno superpuesto se rechaza');
    $e->pruebas->igual(
        1,
        $e->db->contar("SELECT COUNT(*) FROM appointments WHERE barber_id = 3 AND start_at = '2026-10-06 15:00:00'"),
        '6d) No se creó el turno superpuesto'
    );

    // 6e) Tampoco fuera del horario de trabajo.
    $r = $e->encargado->post('/turnos/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/nuevo'),
        'client_id' => 1,
        'barber_id' => 3,
        'service_ids' => array(1),
        'date' => '2026-10-06',
        'time' => '09:00',
        'status' => 'reserved',
        'walk_in' => '0',
    ));

    $e->pruebas->contiene('horarios de trabajo', $r['cuerpo'], '6e) Un turno fuera de horario se rechaza');

    // 6f) El cobro pasa el turno a atendido y calcula la comisión (40%).
    $r = $e->encargado->post('/cobros/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/cobros/nuevo/' . (int) $turno),
        'appointment_id' => $turno,
        'amount' => '10000',
        'payment_method_id' => 1,
        'notes' => 'Cobro de prueba',
    ));

    $e->pruebas->verificar($r['codigo'] === 303, '6f) El cobro se registró', 'código=' . $r['codigo']);

    $cobro = $e->db->fila(
        'SELECT id, amount, commission_percent_snapshot, commission_amount, voided_at
           FROM payments
          WHERE appointment_id = ' . (int) $turno
    );
    $e->pruebas->verificar(
        $cobro && $cobro['commission_amount'] === '4000.00',
        '6f) La comisión quedó congelada en el 40%',
        json_encode($cobro)
    );
    $e->pruebas->igual(
        'attended',
        $e->db->valor('SELECT status FROM appointments WHERE id = ' . (int) $turno),
        '6f) El turno quedó atendido'
    );

    // 6g) Un turno pagado no se puede cobrar de nuevo.
    $e->encargado->post('/cobros/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/cobros'),
        'appointment_id' => $turno,
        'amount' => '10000',
        'payment_method_id' => 1,
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM payments WHERE appointment_id = ' . (int) $turno),
        '6g) El mismo turno no se puede cobrar dos veces'
    );

    // 6h) El cobro suma al ingreso del día.
    $total = $e->db->valor(
        'SELECT COALESCE(SUM(amount), 0) FROM payments
          WHERE voided_at IS NULL AND paid_at >= CURDATE()'
    );
    $e->pruebas->verificar((float) $total === 10000.0, '6h) El cobro suma al ingreso del día', "total=$total");

    // 6i) Al corregir el cobro, la comisión se recalcula.
    $e->encargado->post('/cobros/actualizar/' . (int) $cobro['id'], array(
        'bh_csrf' => $e->encargado->csrf('/cobros/editar/' . (int) $cobro['id']),
        'amount' => '12000',
        'payment_method_id' => 1,
        'notes' => 'Corregido',
    ));

    $cobro = $e->db->fila(
        'SELECT id, commission_amount FROM payments WHERE appointment_id = ' . (int) $turno
    );
    $e->pruebas->igual(
        '4800.00',
        $cobro['commission_amount'],
        '6i) La comisión se recalcula al corregir el cobro (12000 x 40%)'
    );

    // 6j) Anular el cobro lo saca de los ingresos.
    $e->encargado->post('/cobros/eliminar/' . (int) $cobro['id'], array(
        'bh_csrf' => $e->encargado->csrf('/cobros'),
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar(
            'SELECT COUNT(*) FROM payments
              WHERE appointment_id = ' . (int) $turno . ' AND voided_at IS NOT NULL'
        ),
        '6j) El cobro quedó anulado'
    );
    $total = $e->db->valor(
        'SELECT COALESCE(SUM(amount), 0) FROM payments
          WHERE voided_at IS NULL AND paid_at >= CURDATE()'
    );
    $e->pruebas->verificar((float) $total === 0.0, '6j) Un cobro anulado no suma al ingreso', "total=$total");

    // 6k) Restaurarlo lo vuelve a sumar.
    $e->encargado->post('/cobros/restaurar/' . (int) $cobro['id'], array(
        'bh_csrf' => $e->encargado->csrf('/cobros?archived=1'),
    ));

    $total = $e->db->valor(
        'SELECT COALESCE(SUM(amount), 0) FROM payments
          WHERE voided_at IS NULL AND paid_at >= CURDATE()'
    );
    $e->pruebas->verificar((float) $total === 12000.0, '6k) El cobro restaurado vuelve a sumar', "total=$total");

    // 6l) Un turno cobrado no se puede editar ni cambiar de horario.
    $r = $e->encargado->get('/turnos/editar/' . (int) $turno);
    $e->pruebas->verificar(
        $r['codigo'] === 307,
        '6l) El editor de un turno cobrado redirige con aviso',
        'código=' . $r['codigo']
    );

    $r = $e->encargado->post('/turnos/actualizar/' . (int) $turno, array(
        'bh_csrf' => $e->encargado->csrf('/turnos'),
        'client_id' => $cliente,
        'barber_id' => 3,
        'service_ids' => array(1),
        'date' => '2026-10-06',
        'time' => '17:00',
        'status' => 'reserved',
        'walk_in' => '0',
    ));

    $e->pruebas->verificar($r['codigo'] === 409, '6l) Guardar cambios en un turno cobrado da 409', 'código=' . $r['codigo']);
    $e->pruebas->igual(
        '2026-10-06 15:00:00',
        $e->db->valor('SELECT start_at FROM appointments WHERE id = ' . (int) $turno),
        '6l) El turno cobrado quedó intacto'
    );

    // 6m) Egreso: alta y anulación.
    $e->encargado->post('/egresos/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/contabilidad'),
        'expense_date' => date('Y-m-d'),
        'category_id' => 1,
        'concept' => 'Egreso de prueba',
        'amount' => '5000',
    ));

    $egreso = $e->db->fila("SELECT id, amount FROM expenses WHERE concept = 'Egreso de prueba'");
    $e->pruebas->verificar(
        $egreso && (float) $egreso['amount'] === 5000.0,
        '6m) El egreso se registró',
        json_encode($egreso)
    );

    $e->encargado->post('/egresos/anular/' . (int) $egreso['id'], array(
        'bh_csrf' => $e->encargado->csrf('/egresos'),
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM expenses WHERE id = ' . (int) $egreso['id'] . ' AND voided_at IS NOT NULL'),
        '6m) El egreso quedó anulado'
    );

    $total = $e->db->valor(
        'SELECT COALESCE(SUM(amount), 0) FROM expenses
          WHERE voided_at IS NULL AND expense_date >= CURDATE()'
    );
    $e->pruebas->verificar((float) $total === 0.0, '6m) Un egreso anulado no suma', "total=$total");

    // 6n) Alta de usuario: la contraseña se guarda hasheada.
    $r = $e->encargado->post('/usuarios/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/usuarios/nuevo'),
        'full_name' => 'Usuario Prueba',
        'username' => 'prueba',
        'password' => 'clave123',
        'role_id' => 1,
        'active' => '1',
    ));

    $hash = $e->db->valor("SELECT password_hash FROM users WHERE username = 'prueba'");
    $e->pruebas->verificar(
        $hash && strpos($hash, '$2y$') === 0,
        '6n) El usuario se creó con la contraseña hasheada',
        "hash=$hash"
    );

    // Una contraseña corta se avisa antes de guardar.
    $r = $e->encargado->post('/usuarios/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/usuarios/nuevo'),
        'full_name' => 'Corto',
        'username' => 'corto',
        'password' => '123',
        'role_id' => 1,
    ));

    $e->pruebas->contiene('6 caracteres', $r['cuerpo'], '6n) Una contraseña corta se rechaza');

    // 6o) Protección CSRF: un POST sin token no guarda nada.
    $e->encargado->post('/clientes/guardar', array(
        'first_name' => 'Sin',
        'last_name' => 'Token',
        'phone' => '264 555 1111',
    ));

    $e->pruebas->igual(
        0,
        $e->db->contar("SELECT COUNT(*) FROM clients WHERE phone = '264 555 1111'"),
        '6o) Un POST sin token CSRF no crea el cliente'
    );

    // 6p) Las bajas y los borrados no se pueden pedir con GET.
    $r = $e->encargado->get('/clientes/eliminar/1');
    $e->pruebas->verificar(
        $r['codigo'] === 405 && $e->db->contar('SELECT COUNT(*) FROM clients WHERE id = 1 AND deleted_at IS NULL') === 1,
        '6p) Borrar con GET da 405 y no borra',
        'código=' . $r['codigo']
    );
}

/**
 * 7. Dos peticiones al mismo tiempo sobre el mismo turno.
 *
 * Se manda a la vez un cobro y una cancelación del turno 1, contra dos
 * servidores PHP distintos. El sistema tiene que respetarlo siempre: si el
 * cobro se guarda, el turno no puede quedar cancelado.
 */
function probar_concurrencia(Escenario $e)
{
    $e->pruebas->seccion('7. Concurrencia: cobrar y cancelar al mismo tiempo');

    $e->reiniciar();

    $violaciones = 0;

    for ($intento = 1; $intento <= PARES_SIMULTANEOS; $intento++) {
        // El turno 1 vuelve siempre al mismo punto de partida.
        $e->db->consulta('DELETE FROM payments WHERE appointment_id = 1');
        $e->db->consulta("UPDATE appointments SET status = 'reserved' WHERE id = 1");

        $token = $e->encargado->csrf('/turnos/ver/1');
        $jar = $e->encargado->jar();

        $respuestas = enviar_simultaneas(array(
            // Servidor 1: cobrar el turno.
            array(
                'url' => $e->servidor->url('/cobros/guardar'),
                'datos' => array(
                    'bh_csrf' => $token,
                    'appointment_id' => 1,
                    'amount' => '10000',
                    'payment_method_id' => 1,
                ),
                'jar' => $jar,
            ),
            // Servidor 2: cancelarlo.
            array(
                'url' => $e->servidor_b->url('/turnos/estado/1'),
                'datos' => array(
                    'bh_csrf' => $token,
                    'status' => 'cancelled',
                ),
                'jar' => $jar,
            ),
        ));

        $invalidos = $e->db->contar(
            'SELECT COUNT(*)
               FROM payments p
               JOIN appointments a ON a.id = p.appointment_id
              WHERE p.appointment_id = 1
                AND a.status IN ("cancelled", "no_show")'
        );

        if ($invalidos > 0) {
            $violaciones++;
        }
    }

    $e->pruebas->verificar(
        $violaciones === 0,
        'El invariante se respeta en ' . PARES_SIMULTANEOS . ' pares de peticiones simultáneas',
        "violaciones=$violaciones"
    );
}

/**
 * 8. Altas, bajas y restauraciones de clientes, servicios, peluqueros,
 *    usuarios y formas de pago.
 */
function probar_altas_bajas_y_restauraciones(Escenario $e)
{
    $e->pruebas->seccion('8. Altas, bajas y restauraciones');

    $e->reiniciar();

    // 8a) Un servicio que usa un turno futuro no se puede eliminar.
    $e->encargado->post('/servicios/eliminar/1', array('bh_csrf' => $e->encargado->csrf('/servicios')));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM services WHERE id = 1 AND deleted_at IS NULL'),
        '8a) Un servicio con turnos futuros no se elimina'
    );

    $r = $e->encargado->get('/servicios');
    $e->pruebas->contiene('turnos futuros', $r['cuerpo'], '8a) Se avisa que está en turnos futuros');

    // 8b) Un servicio libre sí se elimina y se restaura.
    $e->encargado->post('/servicios/eliminar/4', array('bh_csrf' => $e->encargado->csrf('/servicios')));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM services WHERE id = 4 AND deleted_at IS NOT NULL'),
        '8b) Un servicio sin turnos futuros sí se elimina'
    );

    $e->encargado->post('/servicios/restaurar/4', array('bh_csrf' => $e->encargado->csrf('/servicios?archived=1')));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM services WHERE id = 4 AND deleted_at IS NULL'),
        '8b) El servicio eliminado se restaura'
    );

    // 8c) Restaurar algo que está vigente da 404.
    $restaurar = array(
        '/servicios/restaurar/2' => 'un servicio',
        '/clientes/restaurar/1' => 'un cliente',
        '/usuarios/restaurar/1' => 'un usuario',
        '/peluqueros/restaurar/1' => 'un peluquero',
    );

    foreach ($restaurar as $ruta => $nombre) {
        $r = $e->encargado->post($ruta, array('bh_csrf' => $e->encargado->csrf('/usuarios?archived=1')));
        $e->pruebas->verificar(
            $r['codigo'] === 404,
            '8c) Restaurar ' . $nombre . ' que no está eliminado da 404',
            'código=' . $r['codigo']
        );
    }

    // 8d) Cliente: baja, papelera, y bloqueo si tiene turnos futuros.
    // Se usa un cliente nuevo porque con reservas futuras la baja se bloquea.
    $e->encargado->post('/clientes/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/clientes/nuevo'),
        'first_name' => 'Temporal',
        'last_name' => 'Baja',
        'phone' => '264 555 2222',
    ));

    $cliente = $e->db->valor("SELECT id FROM clients WHERE phone = '264 555 2222'");

    $e->encargado->post('/clientes/eliminar/' . (int) $cliente, array(
        'bh_csrf' => $e->encargado->csrf('/clientes'),
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM clients WHERE id = ' . (int) $cliente . ' AND deleted_at IS NOT NULL'),
        '8d) El cliente se elimina'
    );

    $r = $e->encargado->get('/clientes?archived=1');
    $e->pruebas->contiene('Temporal', $r['cuerpo'], '8d) El cliente aparece en la papelera');

    // Un cliente con turnos futuros no se puede eliminar.
    $e->encargado->post('/clientes/eliminar/3', array('bh_csrf' => $e->encargado->csrf('/clientes')));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM clients WHERE id = 3 AND deleted_at IS NULL'),
        '8d) Un cliente con turnos futuros no se elimina'
    );

    $r = $e->encargado->get('/clientes');
    $e->pruebas->contiene('tiene turnos futuros', $r['cuerpo'], '8d) Se pide cancelar los turnos primero');

    // Si ya hay un cliente activo con los mismos datos, la restauración falla.
    $e->db->consulta(
        "INSERT INTO clients (first_name, last_name, phone, created_at)
         VALUES ('Temporal', 'Baja', '264 555 2222', NOW())"
    );

    $e->encargado->post('/clientes/restaurar/' . (int) $cliente, array(
        'bh_csrf' => $e->encargado->csrf('/clientes?archived=1'),
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM clients WHERE id = ' . (int) $cliente . ' AND deleted_at IS NOT NULL'),
        '8d) No se restaura si ya existe un cliente igual'
    );

    $r = $e->encargado->get('/clientes?archived=1');
    $e->pruebas->contiene('Ya existe un cliente activo', $r['cuerpo'], '8d) Se avisa del duplicado');

    // Liberando el teléfono, ahora sí se restaura.
    $e->db->consulta("DELETE FROM clients WHERE phone = '264 555 2222' AND deleted_at IS NULL");
    $e->encargado->post('/clientes/restaurar/' . (int) $cliente, array(
        'bh_csrf' => $e->encargado->csrf('/clientes?archived=1'),
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM clients WHERE id = ' . (int) $cliente . ' AND deleted_at IS NULL'),
        '8d) Ahora sí se restaura'
    );

    // 8e) Usuarios: no se puede borrar la propia cuenta; desactivar cierra la sesión.
    $r = $e->encargado->post('/usuarios/eliminar/2', array('bh_csrf' => $e->encargado->csrf('/usuarios')));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM users WHERE id = 2 AND deleted_at IS NULL'),
        '8e) No se puede eliminar la propia cuenta'
    );

    $r = $e->encargado->get('/usuarios');
    $e->pruebas->contiene('propia cuenta', $r['cuerpo'], '8e) Se avisa que no se puede eliminar la propia cuenta');

    // El Recepcionista (usuario 1) tiene su propia sesión en el segundo servidor.
    $e->recepcion->iniciar_sesion('recepcion', CLAVE_EJEMPLO, $e->servidor_b->url());
    $r = $e->recepcion->get('/turnos', $e->servidor_b->url());
    $e->pruebas->verificar($r['codigo'] === 200, '8e) El Recepcionista entra con su sesión', 'código=' . $r['codigo']);

    // El Encargado lo elimina.
    $e->encargado->post('/usuarios/eliminar/1', array('bh_csrf' => $e->encargado->csrf('/usuarios/nuevo')));

    $usuario = $e->db->fila('SELECT deleted_at IS NOT NULL AS d, active FROM users WHERE id = 1');
    $e->pruebas->verificar(
        (int) $usuario['d'] === 1 && (int) $usuario['active'] === 0,
        '8e) El usuario queda eliminado y deshabilitado',
        json_encode($usuario)
    );

    // Su sesión deja de servir.
    $r = $e->recepcion->get('/turnos', $e->servidor_b->url());
    $e->pruebas->verificar(
        $r['codigo'] === 307,
        '8e) La sesión del usuario desactivado se cierra',
        'código=' . $r['codigo']
    );

    // Y se puede restaurar.
    $e->encargado->post('/usuarios/restaurar/1', array('bh_csrf' => $e->encargado->csrf('/usuarios?archived=1')));

    $usuario = $e->db->fila('SELECT deleted_at IS NULL AS d, active FROM users WHERE id = 1');
    $e->pruebas->verificar(
        (int) $usuario['d'] === 1 && (int) $usuario['active'] === 1,
        '8e) El usuario restaurado queda habilitado',
        json_encode($usuario)
    );

    // 8f) Quedó registrado el último ingreso.
    $e->pruebas->verificar(
        $e->db->valor("SELECT last_login_at FROM users WHERE username = 'encargado'") !== NULL,
        '8f) Se registró la última entrada del Encargado'
    );

    // 8g) Formas de pago: alta, duplicado y nombre demasiado largo.
    $e->encargado->post('/formas-pago/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/formas-pago'),
        'name' => 'Transferencia',
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar("SELECT COUNT(*) FROM payment_methods WHERE name = 'Transferencia'"),
        '8g) La forma de pago se crea'
    );

    $e->encargado->post('/formas-pago/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/formas-pago'),
        'name' => 'Transferencia',
    ));

    $e->pruebas->igual(
        1,
        $e->db->contar("SELECT COUNT(*) FROM payment_methods WHERE name = 'Transferencia'"),
        '8g) Una forma de pago repetida se rechaza'
    );

    $e->encargado->post('/formas-pago/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/formas-pago'),
        'name' => str_repeat('X', 100),
    ));

    $r = $e->encargado->get('/formas-pago');
    $e->pruebas->verificar(
        $e->db->contar("SELECT COUNT(*) FROM payment_methods WHERE name LIKE 'XXXXX%'") === 0
        && strpos($r['cuerpo'], '80 caracteres') !== FALSE,
        '8g) Un nombre demasiado largo se rechaza'
    );

    // 8h) Un peluquero con turnos futuros no se puede eliminar.
    $e->encargado->post('/peluqueros/eliminar/2', array('bh_csrf' => $e->encargado->csrf('/peluqueros')));

    $e->pruebas->igual(
        1,
        $e->db->contar('SELECT COUNT(*) FROM barbers WHERE id = 2 AND deleted_at IS NULL'),
        '8h) Un peluquero con turnos futuros no se elimina'
    );

    $r = $e->encargado->get('/peluqueros');
    $e->pruebas->contiene('Reasigná o cancelá', $r['cuerpo'], '8h) Se pide reasignar o cancelar antes');

    // 8i) Reasignación de un turno futuro.
    $r = $e->encargado->post('/peluqueros/reasignar/2', array(
        'bh_csrf' => $e->encargado->csrf('/peluqueros/reasignar/2'),
        'appointment_id' => 1,
        'barber_id' => 3,
        'date' => '2026-10-05',
        'time' => '18:00',
    ));

    $e->pruebas->igual(
        '3',
        $e->db->valor('SELECT barber_id FROM appointments WHERE id = 1'),
        '8i) El turno se reasignó al peluquero 3'
    );

    // 8j) No se puede reasignar a un horario que ya está tomado.
    $e->encargado->post('/peluqueros/reasignar/3', array(
        'bh_csrf' => $e->encargado->csrf('/peluqueros/reasignar/3'),
        'appointment_id' => 1,
        'barber_id' => 3,
        'date' => '2026-10-05',
        'time' => '17:00',
    ));

    $e->pruebas->igual(
        '3',
        $e->db->valor('SELECT barber_id FROM appointments WHERE id = 1'),
        '8j) No se reasigna a un horario superpuesto'
    );

    // 8k) Un turno cobrado tampoco se puede reasignar.
    $e->encargado->post('/peluqueros/reasignar/1', array(
        'bh_csrf' => $e->encargado->csrf('/peluqueros/reasignar/1'),
        'appointment_id' => 2,
        'barber_id' => 3,
        'date' => date('Y-m-d', strtotime('+3 days')),
        'time' => '15:00',
    ));

    $e->pruebas->igual(
        '1',
        $e->db->valor('SELECT barber_id FROM appointments WHERE id = 2'),
        '8k) Un turno cobrado no se reasigna'
    );
}

/**
 * 9. El proyecto mantiene las reglas de estructura acordadas.
 */
/**
 * Paginación de los listados largos.
 *
 * Los datos se cargan por SQL a propósito: lo que se prueba acá es que las
 * páginas se armen bien, no el alta de clientes ni de cobros.
 */
function probar_paginacion(Escenario $e)
{
    $e->pruebas->seccion('9. Paginación');

    $e->reiniciar();

    $ahora = date('Y-m-d H:i:s');

    // 30 clientes de prueba, con un teléfono que los hace fáciles de contar
    // en el HTML: cada fila de la tabla muestra el teléfono.
    for ($i = 1; $i <= 30; $i++) {
        $e->db->consulta(
            "INSERT INTO clients (first_name, last_name, phone, created_at, updated_at)
             VALUES ('Pagin', 'Cliente{$i}', 'PAGIN {$i}', '{$ahora}', '{$ahora}')"
        );
    }

    $total_clientes = $e->db->contar('SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL');

    $e->pruebas->igual(
        ceil($total_clientes / 25),
        $e->db->contar('SELECT CEIL(COUNT(*) / 25) FROM clients WHERE deleted_at IS NULL'),
        '9a) El total de clientes se cuenta bien'
    );

    // 9b) Primera página: 25 filas y enlace a la segunda.
    // Cada fila tiene un enlace 'Ver', así que se cuentan filas de verdad.
    $r = $e->encargado->get('/clientes');

    $e->pruebas->igual(
        25,
        substr_count($r['cuerpo'], '/clientes/ver/'),
        '9b) La primera página de clientes trae 25 filas'
    );

    $e->pruebas->contiene('Página 1 de', $r['cuerpo'], '9b) Se indica en qué página se está');
    $e->pruebas->contiene('page=2', $r['cuerpo'], '9b) Hay enlace a la página siguiente');
    $e->pruebas->no_contiene('page=0', $r['cuerpo'], '9b) No hay enlace hacia atrás en la primera página');

    // 9c) La última página trae lo que falta y no ofrece seguir.
    $r = $e->encargado->get('/clientes?page=2');

    $e->pruebas->igual(
        $total_clientes - 25,
        substr_count($r['cuerpo'], '/clientes/ver/'),
        '9c) La segunda página trae el resto'
    );

    $e->pruebas->contiene('Página 2 de', $r['cuerpo'], '9c) La segunda página es la última');
    $e->pruebas->no_contiene('Siguiente', $r['cuerpo'], '9c) En la última página no se sigue');

    // 9d) Una página que no existe se corrige en vez de mostrar una tabla vacía.
    $r = $e->encargado->get('/clientes?page=99');

    $e->pruebas->contiene('Página 2 de', $r['cuerpo'], '9d) La página 99 cae en la última');

    $r = $e->encargado->get('/clientes?page=0');

    $e->pruebas->contiene('Página 1 de', $r['cuerpo'], '9d) La página 0 vuelve a la primera');

    $r = $e->encargado->get('/clientes?page=-5');

    $e->pruebas->contiene('Página 1 de', $r['cuerpo'], '9d) Una página negativa vuelve a la primera');

    // 9e) Los filtros se mantienen al cambiar de página.
    $r = $e->encargado->get('/clientes?q=PAGIN');

    $e->pruebas->igual(
        25,
        substr_count($r['cuerpo'], 'PAGIN '),
        '9e) El buscador filtra antes de paginar'
    );

    $e->pruebas->contiene('q=PAGIN', $r['cuerpo'], '9e) El enlace de página conserva el texto buscado');

    $r = $e->encargado->get('/clientes?archived=1');

    $e->pruebas->no_contiene('Página 1 de', $r['cuerpo'], '9e) Sin resultados no hay barra de páginas');

    // 9f) El historial de turnos se pagina; la agenda del día no.
    $turnos = array();

    for ($i = 0; $i < 30; $i++) {
        $fecha = date('Y-m-d 10:00:00', strtotime('-' . (30 - $i) . ' days'));
        $fin = date('Y-m-d 10:45:00', strtotime('-' . (30 - $i) . ' days'));

        $e->db->consulta(
            "INSERT INTO appointments (client_id, barber_id, start_at, end_at, status, walk_in, created_by, created_at, updated_at)
             VALUES (1, 1, '$fecha', '$fin', 'attended', 0, 1, '$ahora', '$ahora')"
        );

        $turnos[] = $e->db->valor('SELECT LAST_INSERT_ID()');
    }

    $r = $e->encargado->get('/turnos?date=');

    $e->pruebas->contiene('Página 1 de', $r['cuerpo'], '9f) El historial de turnos se pagina');
    $e->pruebas->contiene('date=', $r['cuerpo'], '9f) El enlace conserva el historial (date vacío)');

    // La agenda de un solo día se muestra entera, sin barra de páginas.
    $dia = date('Y-m-d', strtotime('-1 days'));
    $r = $e->encargado->get('/turnos?date=' . $dia);

    $e->pruebas->no_contiene('Página 1 de', $r['cuerpo'], '9f) La agenda de un día no se pagina');
    $e->pruebas->contiene('10:00', $r['cuerpo'], '9f) La agenda del día se sigue mostrando');

    // 9g) Los cobros se paginan y conservan el rango de fechas.
    for ($i = 0; $i < 26; $i++) {
        $pagado = date('Y-m-d H:i:s', strtotime('-' . (26 - $i) . ' days'));
        $e->db->consulta(
            "INSERT INTO payments (appointment_id, payment_method_id, amount, commission_percent_snapshot,
                                   commission_amount, paid_at, created_by, updated_at)
             VALUES (" . (int) $turnos[$i] . ", 1, 1000.00, 50.00, 500.00, '$pagado', 1, '$ahora')"
        );
    }

    $total_cobros = $e->db->contar('SELECT COUNT(*) FROM payments');

    $r = $e->encargado->get('/cobros');

    $e->pruebas->igual(
        25,
        substr_count($r['cuerpo'], '/cobros/editar/'),
        '9g) La primera página de cobros trae 25 filas'
    );

    $e->pruebas->contiene('Página 1 de', $r['cuerpo'], '9g) El listado de cobros se pagina');

    $r = $e->encargado->get('/cobros?page=2');

    $e->pruebas->igual(
        $total_cobros - 25,
        substr_count($r['cuerpo'], '/cobros/editar/'),
        '9g) La segunda página de cobros trae el resto'
    );

    $e->pruebas->contiene('Página 2 de', $r['cuerpo'], '9g) La segunda página de cobros existe');

    $r = $e->encargado->get('/cobros?from=2000-01-01&to=2099-12-31');

    $e->pruebas->contiene('from=2000-01-01', $r['cuerpo'], '9g) El enlace conserva el rango pedido');
    $e->pruebas->contiene('to=2099-12-31', $r['cuerpo'], '9g) El enlace conserva el rango pedido');

    // 9h) Los egresos se paginan.
    for ($i = 1; $i <= 30; $i++) {
        $e->db->consulta(
            "INSERT INTO expenses (expense_date, category_id, concept, amount, created_by, created_at, updated_at)
             VALUES ('" . date('Y-m-d') . "', 1, 'Egreso de prueba {$i}', 100.00, 1, '{$ahora}', '{$ahora}')"
        );
    }

    $total_egresos = $e->db->contar('SELECT COUNT(*) FROM expenses WHERE voided_at IS NULL');

    $r = $e->encargado->get('/egresos');

    $e->pruebas->igual(
        25,
        substr_count($r['cuerpo'], '/egresos/editar/'),
        '9h) La primera página de egresos trae 25 filas'
    );

    $e->pruebas->contiene('Página 1 de', $r['cuerpo'], '9h) El listado de egresos se pagina');

    $r = $e->encargado->get('/egresos?page=2');

    $e->pruebas->igual(
        $total_egresos - 25,
        substr_count($r['cuerpo'], '/egresos/editar/'),
        '9h) La segunda página de egresos trae el resto'
    );

    $e->pruebas->contiene('Página 2 de', $r['cuerpo'], '9h) La segunda página de egresos es la última');
}

function probar_estructura_del_proyecto(Escenario $e)
{
    $e->pruebas->seccion('10. Estructura del proyecto');

    $raiz = dirname(__DIR__, 2);

    // 9a) Ningún controlador escribe SQL: todo va por los modelos.
    $controladores = glob($raiz . '/application/controllers/*.php');
    $con_sql = array();

    foreach ($controladores as $archivo) {
        if (strpos(file_get_contents($archivo), '$this->db') !== FALSE) {
            $con_sql[] = basename($archivo);
        }
    }

    $e->pruebas->verificar(
        $con_sql === array(),
        'Los controladores no escriben SQL directamente',
        implode(', ', $con_sql)
    );

    // 9b) Todos los archivos PHP del proyecto se pueden interpretar.
    $con_error = array();
    $archivos = array_merge(
        $controladores,
        glob($raiz . '/application/models/*.php'),
        glob($raiz . '/application/core/*.php'),
        glob($raiz . '/application/helpers/*.php'),
        glob($raiz . '/application/views/*/*.php')
    );

    foreach ($archivos as $archivo) {
        $salida = array();
        $codigo = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($archivo) . ' 2>&1', $salida, $codigo);

        if ($codigo !== 0) {
            $con_error[] = basename($archivo);
        }
    }

    $e->pruebas->verificar(
        $con_error === array(),
        'Todos los archivos PHP pasan la revisión de sintaxis (php -l)',
        implode(', ', $con_error)
    );
}

/** Consulta la misma API que usa el selector de horarios del formulario. */
function consultar_horarios(Escenario $e, $barber_id, $date, array $service_ids, $recepcion = FALSE)
{
    $query = http_build_query(array(
        'barber_id' => $barber_id,
        'date' => $date,
        'service_ids' => $service_ids,
    ));
    $http = $recepcion ? $e->recepcion : $e->encargado;
    $response = $http->get('/turnos/disponibilidad?' . $query);
    $result = json_decode($response['cuerpo'], TRUE);

    $e->pruebas->verificar(
        $response['codigo'] === 200 && is_array($result) && isset($result['slots']),
        'La consulta de disponibilidad responde JSON válido para ambos perfiles',
        'código=' . $response['codigo'] . ' ' . $response['error']
    );
    return is_array($result) ? $result : array('slots' => array(), 'occupied' => array(), 'error' => '');
}

/** REQ-05, 19 y 42: los horarios mostrados coinciden con los guardables. */
function probar_disponibilidad_y_walkin(Escenario $e)
{
    $e->pruebas->seccion('11. Disponibilidad y atención sin reserva');
    $e->reiniciar();
    $date = '2030-01-07'; // Lunes, independiente de la fecha de ejecución.

    $form = $e->encargado->get('/turnos/nuevo');
    $e->pruebas->contiene('data-availability-url', $form['cuerpo'], 'El alta ofrece la consulta de huecos');
    $e->recepcion->iniciar_sesion('recepcion', CLAVE_EJEMPLO);

    $free = consultar_horarios($e, 1, $date, array(1), TRUE);
    $e->pruebas->verificar(in_array('10:00', $free['slots'], TRUE), 'El Recepcionista ve las 10:00 libres');

    $r = $e->encargado->post('/turnos/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/nuevo'),
        'client_id' => 1, 'barber_id' => 1, 'service_ids' => array(1),
        'date' => $date, 'time' => '10:00', 'status' => 'reserved', 'walk_in' => '0',
    ));
    $booking = (int) $e->db->valor("SELECT id FROM appointments WHERE barber_id = 1 AND start_at = '2030-01-07 10:00:00'");
    $e->pruebas->verificar($r['codigo'] === 303 && $booking > 0, 'La reserva ocupa 10:00-10:45');

    $free = consultar_horarios($e, 1, $date, array(1));
    $e->pruebas->igual(1, count($free['occupied']), 'La consulta muestra el turno activo ocupado');
    $e->pruebas->verificar(in_array('09:15', $free['slots'], TRUE), 'Un turno termina exactamente al iniciar el ocupado');
    $e->pruebas->verificar(in_array('10:45', $free['slots'], TRUE), 'Un turno comienza exactamente al finalizar el ocupado');
    $e->pruebas->verificar(!in_array('09:30', $free['slots'], TRUE) && !in_array('10:00', $free['slots'], TRUE),
        'Se excluyen los intervalos superpuestos');
    $e->pruebas->verificar(in_array('17:15', $free['slots'], TRUE) && !in_array('17:30', $free['slots'], TRUE),
        'Solo se ofrece un turno que entra entero antes del cierre');

    $combined = consultar_horarios($e, 1, $date, array(1, 2));
    $e->pruebas->igual(75, $combined['minutes'], 'Dos servicios suman 75 minutos');
    $e->pruebas->verificar(!in_array('09:00', $combined['slots'], TRUE)
        && in_array('10:45', $combined['slots'], TRUE)
        && in_array('16:45', $combined['slots'], TRUE)
        && !in_array('17:00', $combined['slots'], TRUE),
        'La duración combinada afecta cruces y cierre');

    $barber = $e->encargado->get('/peluqueros/disponibilidad/1?' . http_build_query(array(
        'date' => $date, 'service_ids' => array(1),
    )));
    $e->pruebas->verificar($barber['codigo'] === 200 && strpos($barber['cuerpo'], '09:00 a 18:00') !== FALSE
        && strpos($barber['cuerpo'], '10:00 a 10:45') !== FALSE
        && strpos($barber['cuerpo'], '>10:45</a>') !== FALSE,
        'La ficha del peluquero muestra horario, turnos y huecos de la misma lógica');
    $e->pruebas->igual(403, $e->recepcion->get('/peluqueros/disponibilidad/1')['codigo'],
        'La ficha administrativa respeta el perfil');

    $sunday = consultar_horarios($e, 1, '2030-01-06', array(1));
    $e->pruebas->verificar(!$sunday['slots'] && strpos($sunday['error'], 'no trabaja') !== FALSE,
        'Un día no laboral no ofrece huecos');

    $invalid = $e->encargado->post('/turnos/actualizar/' . $booking, array(
        'bh_csrf' => $e->encargado->csrf('/turnos/editar/' . $booking),
        'client_id' => 2, 'barber_id' => 2, 'service_ids' => array(1, 2),
        'date' => $date, 'time' => '08:30', 'status' => 'attended',
        'notes' => 'Conservar datos tipeados', 'walk_in' => '0',
    ));
    $body = $invalid['cuerpo'];
    $e->pruebas->verificar($invalid['codigo'] === 200
        && strpos($body, 'name="date" value="2030-01-07"') !== FALSE
        && strpos($body, 'name="time" value="08:30"') !== FALSE
        && strpos($body, 'Conservar datos tipeados') !== FALSE,
        'Una edición inválida conserva fecha, hora y observaciones');
    $e->pruebas->verificar(preg_match('/name="barber_id"[\s\S]*?value="2"\s+selected/', $body) === 1
        && preg_match('/name="service_ids\[\]" value="2"[^>]*checked/', $body) === 1
        && preg_match('/name="client_id"[\s\S]*?value="2"\s+selected/', $body) === 1,
        'La edición inválida conserva cliente, peluquero y servicios');
    $e->pruebas->igual('2030-01-07 10:00:00',
        $e->db->valor('SELECT start_at FROM appointments WHERE id = ' . $booking),
        'La edición inválida no modifica el turno guardado');

    $e->encargado->post('/turnos/estado/' . $booking, array(
        'bh_csrf' => $e->encargado->csrf('/turnos/ver/' . $booking), 'status' => 'cancelled',
    ));
    $free = consultar_horarios($e, 1, $date, array(1));
    $e->pruebas->verificar(!$free['occupied'] && in_array('10:00', $free['slots'], TRUE),
        'Cancelar libera el horario y lo quita de los ocupados');

    $boundary = $e->recepcion->post('/turnos/guardar', array(
        'bh_csrf' => $e->recepcion->csrf('/turnos/nuevo'),
        'client_id' => 1, 'barber_id' => 1, 'service_ids' => array(1),
        'date' => $date, 'time' => '09:15', 'status' => 'reserved', 'walk_in' => '0',
    ));
    $e->pruebas->verificar($boundary['codigo'] === 303
        && $e->db->contar("SELECT COUNT(*) FROM appointments WHERE status = 'reserved' AND start_at = '2030-01-07 09:15:00' AND end_at = '2030-01-07 10:00:00'") === 1,
        'Un horario ofrecido en un extremo se puede guardar sin cruzar el siguiente');

    $walkin = $e->recepcion->post('/turnos/guardar', array(
        'bh_csrf' => $e->recepcion->csrf('/turnos/sin-reserva'),
        'client_id' => 2, 'barber_id' => 1, 'service_ids' => array(1),
        'date' => $date, 'time' => '10:00', 'status' => 'attended', 'walk_in' => '1',
    ));
    $e->pruebas->verificar($walkin['codigo'] === 303
        && $e->db->contar("SELECT COUNT(*) FROM appointments WHERE barber_id = 1 AND walk_in = 1 AND status = 'attended' AND start_at = '2030-01-07 10:00:00'") === 1,
        'Un Recepcionista registra el walk-in válido');

    $walkin = $e->recepcion->post('/turnos/guardar', array(
        'bh_csrf' => $e->recepcion->csrf('/turnos/sin-reserva'),
        'client_id' => 2, 'barber_id' => 1, 'service_ids' => array(1),
        'date' => $date, 'time' => '10:15', 'status' => 'attended', 'walk_in' => '1',
    ));
    $e->pruebas->verificar(strpos($walkin['cuerpo'], 'superpone') !== FALSE
        && $e->db->contar("SELECT COUNT(*) FROM appointments WHERE barber_id = 1 AND walk_in = 1 AND start_at >= '2030-01-07 00:00:00'") === 1,
        'El walk-in sin disponibilidad no se guarda');

    $no_work = $e->recepcion->post('/turnos/guardar', array(
        'bh_csrf' => $e->recepcion->csrf('/turnos/sin-reserva'),
        'client_id' => 2, 'barber_id' => 1, 'service_ids' => array(1),
        'date' => '2030-01-06', 'time' => '10:00', 'status' => 'attended', 'walk_in' => '1',
    ));
    $e->pruebas->contiene('horarios de trabajo', $no_work['cuerpo'],
        'El walk-in también rechaza días sin horario');

    $bad_barber = $e->recepcion->post('/turnos/guardar', array(
        'bh_csrf' => $e->recepcion->csrf('/turnos/sin-reserva'),
        'client_id' => 2, 'barber_id' => 999, 'service_ids' => array(1),
        'date' => $date, 'time' => '11:00', 'status' => 'attended', 'walk_in' => '1',
    ));
    $e->pruebas->contiene('no está activo', $bad_barber['cuerpo'],
        'El walk-in rechaza un peluquero no habilitado');

    $bad_service = $e->recepcion->post('/turnos/guardar', array(
        'bh_csrf' => $e->recepcion->csrf('/turnos/sin-reserva'),
        'client_id' => 2, 'barber_id' => 1, 'service_ids' => array(999),
        'date' => $date, 'time' => '11:00', 'status' => 'attended', 'walk_in' => '1',
    ));
    $e->pruebas->contiene('servicios activos', $bad_service['cuerpo'],
        'El walk-in rechaza servicios inexistentes');
}

/** Producción bruta real, porcentaje histórico y comisión no son lo mismo. */
function probar_produccion_y_snapshots(Escenario $e)
{
    $e->pruebas->seccion('12. Producción y snapshots');
    $e->reiniciar();
    $date = '2030-01-07';

    foreach (array('12:00', '14:00') as $time) {
        $e->encargado->post('/turnos/guardar', array(
            'bh_csrf' => $e->encargado->csrf('/turnos/nuevo'),
            'client_id' => 1, 'barber_id' => 1, 'service_ids' => array(1),
            'date' => $date, 'time' => $time, 'status' => 'attended', 'walk_in' => '1',
        ));
    }
    $first = (int) $e->db->valor("SELECT id FROM appointments WHERE barber_id = 1 AND start_at = '2030-01-07 12:00:00'");
    $second = (int) $e->db->valor("SELECT id FROM appointments WHERE barber_id = 1 AND start_at = '2030-01-07 14:00:00'");
    $e->pruebas->verificar($first > 0 && $second > 0, 'Se crearon dos atenciones del mismo peluquero');

    $e->encargado->post('/cobros/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/cobros/nuevo/' . $first),
        'appointment_id' => $first, 'payment_method_id' => 1, 'amount' => '10000',
    ));
    // Cambio de porcentaje entre cobros: el primer snapshot no debe variar.
    $e->db->consulta('UPDATE barbers SET commission_percent = 50 WHERE id = 1');
    $e->encargado->post('/cobros/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/cobros/nuevo/' . $second),
        'appointment_id' => $second, 'payment_method_id' => 1, 'amount' => '20000',
    ));

    $today = date('Y-m-d');
    $accounting = $e->encargado->get('/contabilidad?from=' . $today . '&to=' . $today);
    $body = $accounting['cuerpo'];
    $e->pruebas->verificar($accounting['codigo'] === 200
        && preg_match('/Carlos Balmaceda[\s\S]*?\$30\.000[\s\S]*?48,33 %[\s\S]*?\$14\.500/', $body) === 1,
        'Producción = 30000; porcentaje efectivo = 48,33%; pago = 14500');
    $snapshots = $e->db->fila('SELECT SUM(amount) production, SUM(commission_amount) payout FROM payments WHERE voided_at IS NULL AND appointment_id IN (' . $first . ',' . $second . ')');
    $e->pruebas->verificar((float) $snapshots['production'] === 30000.0
        && (float) $snapshots['payout'] === 14500.0,
        'La producción suma importes, no comisiones');

    $second_payment = (int) $e->db->valor('SELECT id FROM payments WHERE appointment_id = ' . $second);
    $e->encargado->post('/cobros/eliminar/' . $second_payment, array(
        'bh_csrf' => $e->encargado->csrf('/cobros'),
    ));
    $accounting = $e->encargado->get('/contabilidad?from=' . $today . '&to=' . $today);
    $e->pruebas->verificar(preg_match('/Carlos Balmaceda[\s\S]*?\$10\.000[\s\S]*?45,00 %[\s\S]*?\$4\.500/', $accounting['cuerpo']) === 1,
        'Un cobro anulado deja de sumar producción y comisión');

    $before = $e->db->fila('SELECT price_snapshot, duration_snapshot FROM appointment_services WHERE appointment_id = ' . $first);
    $e->encargado->post('/servicios/actualizar/1', array(
        'bh_csrf' => $e->encargado->csrf('/servicios/editar/1'),
        'name' => 'Corte', 'price' => '21000', 'duration_minutes' => '60', 'active' => '1',
    ));
    $after = $e->db->fila('SELECT price_snapshot, duration_snapshot FROM appointment_services WHERE appointment_id = ' . $first);
    $e->pruebas->verificar($before === $after && $after['price_snapshot'] === '12000.00'
        && (int) $after['duration_snapshot'] === 45,
        'Cambiar precio y duración no altera snapshots históricos');

    $e->encargado->post('/turnos/guardar', array(
        'bh_csrf' => $e->encargado->csrf('/turnos/nuevo'),
        'client_id' => 2, 'barber_id' => 1, 'service_ids' => array(1),
        'date' => $date, 'time' => '16:00', 'status' => 'reserved', 'walk_in' => '0',
    ));
    $new = (int) $e->db->valor("SELECT id FROM appointments WHERE barber_id = 1 AND start_at = '2030-01-07 16:00:00'");
    $new_snapshot = $e->db->fila('SELECT price_snapshot, duration_snapshot FROM appointment_services WHERE appointment_id = ' . $new);
    $e->pruebas->verificar($new_snapshot['price_snapshot'] === '21000.00'
        && (int) $new_snapshot['duration_snapshot'] === 60,
        'El turno nuevo usa la tarifa y duración actuales');
}

/* ================================================================== */
/* Ejecución                                                          */
/* ================================================================== */

/**
 * Prueba corta: levanta un servidor, pide una página, lo apaga y comprueba que
 * no queda ningún proceso vivo.
 *
 * Sirve para verificar rápidamente que el runner anda bien y que no deja
 * servidores colgados, antes de correr la suite completa.
 *
 * @return bool TRUE si todo salió bien.
 */
function ejecutar_prueba_de_arranque(Pruebas $pruebas)
{
    $raiz = dirname(__DIR__, 2);

    echo "Prueba de arranque del servidor\n";

    $servidor = NULL;

    try {
        $servidor = ServidorPhp::levantar($raiz, RUTA_ROUTER);
        $pruebas->verificar($servidor->vivo(), 'El servidor queda encendido', 'pid=' . $servidor->pid());
        echo "  (puerto " . $servidor->puerto() . ", proceso " . $servidor->pid() . ")\n";

        $http = new ClienteHttp($servidor->url(), tempnam(sys_get_temp_dir(), 'bh_pruebas_'));
        $r = $http->get('/login');

        // No importa el código que devuelva: lo que se comprueba aquí es que el
        // servidor y CodeIgniter están contestando. (Sin la base de pruebas
        // creada, la conexión a MySQL falla y esa pantalla devuelve 500.)
        $pruebas->verificar(
            $r['codigo'] > 0,
            'El servidor responde una petición',
            $r['error']
        );

        $http->cerrar_sesion();
    } catch (Exception $error) {
        $pruebas->verificar(FALSE, 'El servidor de pruebas arranca y responde', $error->getMessage());
    } finally {
        if ($servidor !== NULL) {
            $pid = $servidor->pid();
            $servidor->detener();
            usleep(300000);
            $pruebas->verificar(
                !proceso_existe($pid),
                'Al terminar no queda ningún servidor vivo',
                'sigue vivo el proceso ' . $pid
            );
        }
    }

    return $pruebas->resumen('Prueba de arranque');
}

/**
 * Corre todas las pruebas de integración.
 *
 * @param Pruebas $pruebas
 * @param array   $opciones  'mantener' => TRUE para no borrar la base al final.
 *
 * @return bool TRUE si no hubo fallos.
 */
function ejecutar_pruebas_integracion(Pruebas $pruebas, array $opciones = array())
{
    $raiz = dirname(__DIR__, 2);
    $mantener = isset($opciones['mantener']) && $opciones['mantener'];
    $escenario = new Escenario();

    echo "Pruebas de integración (base " . BASE_PRUEBAS . ")\n";

    try {
        $escenario->db = crear_base_de_pruebas($raiz);
        echo "Base de pruebas creada.\n";

        // Dos servidores: el segundo se usa para las pruebas de concurrencia.
        $escenario->servidor = ServidorPhp::levantar($raiz, RUTA_ROUTER);
        $escenario->servidor_b = ServidorPhp::levantar($raiz, RUTA_ROUTER, $escenario->servidor->puerto() + 1);
        echo 'Servidores de pruebas encendidos en '
            . $escenario->servidor->url() . ' y ' . $escenario->servidor_b->url() . "\n";

        $temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $escenario->encargado = new ClienteHttp(
            $escenario->servidor->url(),
            $temporal . 'bh_pruebas_encargado.txt'
        );
        $escenario->recepcion = new ClienteHttp(
            $escenario->servidor->url(),
            $temporal . 'bh_pruebas_recepcion.txt'
        );
        $escenario->pruebas = $pruebas;

        probar_inicio($escenario);
        probar_pantallas($escenario);
        probar_permisos_por_rol($escenario);
        probar_turno_cobrado_no_se_cancela($escenario);
        probar_horarios_no_rompen_turnos($escenario);
        probar_recorrido_completo($escenario);
        probar_concurrencia($escenario);
        probar_altas_bajas_y_restauraciones($escenario);
        probar_paginacion($escenario);
        probar_estructura_del_proyecto($escenario);
        probar_disponibilidad_y_walkin($escenario);
        probar_produccion_y_snapshots($escenario);
    } catch (Exception $error) {
        $pruebas->verificar(FALSE, 'Las pruebas pudieron terminar sin errores', $error->getMessage());
    } finally {
        // Aunque algo falle, se apagan los servidores y se borra la base.
        if (isset($escenario->servidor)) {
            $escenario->servidor->detener();
        }

        if (isset($escenario->servidor_b)) {
            $escenario->servidor_b->detener();
        }

        if (isset($escenario->db)) {
            $escenario->db->cerrar();
        }

        foreach (array('bh_pruebas_encargado.txt', 'bh_pruebas_recepcion.txt') as $archivo) {
            $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archivo;

            if (is_file($ruta)) {
                unlink($ruta);
            }
        }

        if ($mantener) {
            echo "\nLa base " . BASE_PRUEBAS . " se dejó creada (opción --mantener).\n";
        } else {
            borrar_base_de_pruebas($raiz);
            echo "Base de pruebas borrada.\n";
        }

        apagar_servidores();
    }

    return $pruebas->resumen('Pruebas de integración');
}
