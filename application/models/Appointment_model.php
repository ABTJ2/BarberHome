<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modelo de turnos (agenda).
 *
 * Concentra las reglas de la agenda:
 * - servicios permitidos y duración total;
 * - horario de trabajo del peluquero;
 * - cruces de turnos (no se pueden superponer);
 * - bloqueo de cambios cuando el turno ya fue cobrado.
 *
 * Las consultas SQL viven únicamente en este archivo.
 */
class Appointment_model extends CI_Model {

    // Mensaje del último error de negocio, para poder mostrarlo en la vista.
    public $last_error = 'No se pudo guardar el turno.';

    /**
     * Filtros del listado de turnos: fecha, peluquero, estado y texto.
     *
     * Viven en un solo lugar porque los usan tanto el listado como el
     * conteo de registros para la paginacion.
     *
     * @param string $date      fecha YYYY-MM-DD, o vacio para toda la agenda
     * @param mixed  $barber_id id del peluquero, o NULL para todos
     * @param string $q         texto para filtrar por cliente o peluquero
     * @param string $status    estado a filtrar, o vacio para todos
     */
    private function filtros_agenda($date, $barber_id, $q, $status)
    {
        // Filtros opcionales del buscador.
        if ($date !== '' && $date !== NULL) {
            $this->db->where('DATE(a.start_at)', $date);
        }

        if ($barber_id) {
            $this->db->where('a.barber_id', $barber_id);
        }

        if ($status !== '') {
            $this->db->where('a.status', $status);
        }

        if ($q !== '') {
            // group_start()/group_end() encerran el OR para que no rompa
            // los filtros de fecha, peluquero o estado aplicados antes.
            $this->db->group_start()
                ->like('c.first_name', $q)
                ->or_like('c.last_name', $q)
                ->or_like('c.phone', $q)
                ->or_like('b.full_name', $q)
                ->group_end();
        }
    }

    /**
     * Turnos de una fecha concreta.
     *
     * Si $date llega vacío se devuelve toda la agenda, que es lo que
     * permite el buscador del panel.
     *
     * @param string $date      fecha YYYY-MM-DD, o vacío para toda la agenda
     * @param mixed  $barber_id id del peluquero, o NULL para todos
     * @param string $q         texto para filtrar por cliente o peluquero
     * @param string $status    estado a filtrar, o vacío para todos
     * @param int    $limite    registros por página (0 = todos)
     * @param int    $offset    cuántos registros se saltean
     */
    public function for_date($date, $barber_id = NULL, $q = '', $status = '', $limite = 0, $offset = 0)
    {
        // Las subconsultas evitan una consulta por cada turno (N+1):
        // cada fila ya trae el total, los minutos, si tiene cobro y los servicios.
        $this->db->select("
            a.*,
            CONCAT(c.first_name, ' ', c.last_name) client_name,
            c.phone client_phone,
            b.full_name barber_name,
            (SELECT COALESCE(SUM(x.price_snapshot), 0)
               FROM appointment_services x
              WHERE x.appointment_id = a.id) estimated_total,
            (SELECT COALESCE(SUM(x.duration_snapshot), 0)
               FROM appointment_services x
              WHERE x.appointment_id = a.id) service_minutes,
            (SELECT COUNT(*)
               FROM payments p
              WHERE p.appointment_id = a.id) has_payment,
            (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ')
               FROM appointment_services x
               JOIN services s ON s.id = x.service_id
              WHERE x.appointment_id = a.id) services", FALSE);

        $this->db->from('appointments a');
        $this->db->join('clients c', 'c.id = a.client_id');
        $this->db->join('barbers b', 'b.id = a.barber_id');

        $this->filtros_agenda($date, $barber_id, $q, $status);

        if ($limite > 0) {
            $this->db->limit($limite, $offset);
        }

        return $this->db->order_by('a.start_at')->get()->result_array();
    }

    /**
     * Cuántos turnos hay con los mismos filtros del listado.
     *
     * No trae las subconsultas de la tabla: solo necesita el total.
     */
    public function count_for_date($date, $barber_id = NULL, $q = '', $status = '')
    {
        $this->db->from('appointments a');
        $this->db->join('clients c', 'c.id = a.client_id');
        $this->db->join('barbers b', 'b.id = a.barber_id');

        $this->filtros_agenda($date, $barber_id, $q, $status);

        return $this->db->count_all_results();
    }

    /**
     * Un turno con su cliente, peluquero, servicios y cobro asociado.
     * Devuelve NULL si no existe.
     */
    public function find($id)
    {
        $row = $this->db->select("
                a.*,
                CONCAT(c.first_name, ' ', c.last_name) client_name,
                c.phone client_phone,
                b.full_name barber_name", FALSE)
            ->from('appointments a')
            ->join('clients c', 'c.id = a.client_id')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('a.id', $id)
            ->get()
            ->row_array();

        if (!$row) {
            return NULL;
        }

        // Los servicios y el cobro se cargan aparte porque son datos
        // de tablas hijas (relación 1 a N).
        $row['services'] = $this->services($id);
        $row['payment'] = $this->db->select('p.*, pm.name payment_method')
            ->from('payments p')
            ->join('payment_methods pm', 'pm.id = p.payment_method_id')
            ->where('p.appointment_id', $id)
            ->get()
            ->row_array();

        return $row;
    }

    /**
     * Servicios del turno con el precio y la duración guardados al crearlo.
     */
    public function services($appointment_id)
    {
        return $this->db->select('x.*, s.name')
            ->from('appointment_services x')
            ->join('services s', 's.id = x.service_id')
            ->where('x.appointment_id', $appointment_id)
            ->order_by('s.name')
            ->get()
            ->result_array();
    }

    /**
     * ¿El turno ya tiene un cobro registrado?
     *
     * Un cobro anulado también cuenta: así se conserva la traza de que
     * esa atención fue cobrada y no se puede reutilizar el mismo turno
     * como si nunca hubiera pasado por caja.
     */
    public function has_payment($appointment_id)
    {
        return $this->db->where('appointment_id', $appointment_id)
            ->count_all_results('payments') > 0;
    }

    /**
     * ¿El peluquero ya tiene otro turno que se superpone con el horario dado?
     *
     * Dos intervalos se cruzan si el comienzo de uno es anterior al final
     * del otro. Solo se consideran los turnos activos (reservado o atendido).
     */
    public function overlap_exists($barber_id, $start, $end, $exclude_id = NULL)
    {
        $this->db->from('appointments')
            ->where('barber_id', $barber_id)
            ->where_in('status', array('reserved', 'attended'))
            ->where('start_at <', $end)
            ->where('end_at >', $start);

        // Al editar un turno se lo excluye a sí mismo de la comparación.
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }

        return $this->db->count_all_results() > 0;
    }

    /**
     * Valida un turno y devuelve los datos listos para guardar.
     *
     * Devuelve: array($ok, $mensaje, $inicio, $fin, $servicios)
     *
     * Se usa tanto al crear como al editar, para que las reglas
     * de la agenda estén escritas en un solo lugar.
     */
    public function slot_validation($barber_id, $service_ids, $date, $time, $exclude_id = NULL)
    {
        $this->load->model('Service_model');
        $this->load->model('Barber_model');

        // 1) Los servicios deben ser una lista válida, sin repetidos.
        if (!is_array($service_ids) || !$service_ids) {
            return array(FALSE, 'Seleccioná servicios válidos.', NULL, NULL, array());
        }

        $ids = array();
        foreach ($service_ids as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id)
                || (int) $id < 1 || in_array((int) $id, $ids, TRUE)) {
                return array(FALSE, 'Seleccioná servicios válidos sin repetir.', NULL, NULL, array());
            }

            $ids[] = (int) $id;
        }

        // 2) Los servicios deben existir y estar activos.
        $services = $this->Service_model->get_many($ids);
        if (count($services) !== count($ids)) {
            return array(FALSE, 'Seleccioná servicios activos y válidos.', NULL, NULL, $services);
        }

        // 3) Duración total del turno.
        // En una edición se respetan los valores originales (snapshot),
        // así el cambio de precios de hoy no altera turnos ya cargados.
        $previous = array();
        if ($exclude_id) {
            foreach ($this->services($exclude_id) as $old) {
                $previous[$old['service_id']] = $old;
            }
        }

        $minutes = 0;
        foreach ($services as &$service) {
            if (isset($previous[$service['id']])) {
                $service['price'] = $previous[$service['id']]['price_snapshot'];
                $service['duration_minutes'] = $previous[$service['id']]['duration_snapshot'];
            }

            $minutes += (int) $service['duration_minutes'];
        }
        unset($service);

        if ($minutes <= 0) {
            return array(FALSE, 'La duración del turno no es válida.', NULL, NULL, $services);
        }

        // 4) Fecha y hora coherentes.
        if (!valid_day($date) || !valid_clock($time)) {
            return array(FALSE, 'La fecha u hora no es válida.', NULL, NULL, $services);
        }

        // El fin del turno se calcula sumando la duración de los servicios.
        $start = $date . ' ' . $time . ':00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $minutes . ' minutes'));

        if (substr($start, 0, 10) !== substr($end, 0, 10)) {
            return array(FALSE, 'El turno debe terminar el mismo día.', NULL, NULL, $services);
        }

        // 5) El peluquero debe estar activo y poder hacer esos servicios.
        $barber = $this->Barber_model->find($barber_id);
        if (!$barber || !$barber['active']) {
            return array(FALSE, 'El peluquero no está activo.', NULL, NULL, $services);
        }

        if (!$this->Barber_model->supports_services($barber_id, $ids)) {
            return array(FALSE, 'El peluquero seleccionado no realiza todos los servicios elegidos.', NULL, NULL, $services);
        }

        // 6) El turno debe caer dentro del horario de trabajo del peluquero.
        if (!$this->Barber_model->within_schedule($barber_id, $start, $end)) {
            return array(FALSE, 'El turno queda fuera de los días u horarios de trabajo del peluquero.', NULL, NULL, $services);
        }

        // 7) No puede superponerse con otro turno del mismo peluquero.
        if ($this->overlap_exists($barber_id, $start, $end, $exclude_id)) {
            return array(FALSE, 'El peluquero ya tiene un turno que se superpone con ese horario.', NULL, NULL, $services);
        }

        return array(TRUE, '', $start, $end, $services);
    }

    /**
     * REQ-05/19: horarios ofrecibles en intervalos de 15 minutos.
     * Cada candidato pasa por la misma validación que el guardado; al guardar
     * se valida nuevamente dentro de la transacción por posibles concurrencias.
     */
    public function available_slots($barber_id, $date, $service_ids, $exclude_id = NULL)
    {
        $result = array('schedule' => NULL, 'occupied' => array(), 'slots' => array(),
            'minutes' => 0, 'error' => '');

        if (!valid_day($date)) {
            $result['error'] = 'Seleccioná una fecha válida.';
            return $result;
        }

        $this->load->model(array('Barber_model', 'Service_model'));
        $barber = $this->Barber_model->find($barber_id);
        if (!$barber || !$barber['active']) {
            $result['error'] = 'El peluquero no está activo.';
            return $result;
        }

        $day = (int) date('N', strtotime($date));
        foreach ($this->Barber_model->schedules($barber_id) as $schedule) {
            if ((int) $schedule['day_of_week'] === $day && (int) $schedule['active'] === 1) {
                $result['schedule'] = $schedule;
                break;
            }
        }

        $result['occupied'] = $this->db->select('start_at, end_at')
            ->from('appointments')
            ->where('barber_id', $barber_id)
            ->where_in('status', array('reserved', 'attended'))
            ->where('start_at >=', $date . ' 00:00:00')
            ->where('start_at <=', $date . ' 23:59:59')
            ->order_by('start_at')
            ->get()->result_array();

        if (!$result['schedule']) {
            $result['error'] = 'El peluquero no trabaja ese día.';
            return $result;
        }

        if (!is_array($service_ids) || !$service_ids) {
            $result['error'] = 'Seleccioná al menos un servicio.';
            return $result;
        }

        $ids = array();
        foreach ($service_ids as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id)
                || (int) $id < 1 || in_array((int) $id, $ids, TRUE)) {
                $result['error'] = 'Seleccioná servicios válidos sin repetir.';
                return $result;
            }
            $ids[] = (int) $id;
        }

        $services = $this->Service_model->get_many($ids);
        if (count($services) !== count($ids) || !$this->Barber_model->supports_services($barber_id, $ids)) {
            $result['error'] = 'El peluquero no realiza todos los servicios seleccionados o están inactivos.';
            return $result;
        }

        $previous = array();
        if ($exclude_id) {
            foreach ($this->services($exclude_id) as $old) {
                $previous[$old['service_id']] = $old;
            }
        }
        foreach ($services as $service) {
            $result['minutes'] += isset($previous[$service['id']])
                ? (int) $previous[$service['id']]['duration_snapshot']
                : (int) $service['duration_minutes'];
        }

        if ($result['minutes'] <= 0) {
            $result['error'] = 'La duración de los servicios no es válida.';
            return $result;
        }

        $opening = strtotime($date . ' ' . $result['schedule']['start_time']);
        $closing = strtotime($date . ' ' . $result['schedule']['end_time']);
        // Un turno debe entrar entero antes del cierre. Los extremos contiguos no se cruzan.
        for ($start = $opening; $start + $result['minutes'] * 60 <= $closing; $start += 15 * 60) {
            $time = date('H:i', $start);
            list($ok) = $this->slot_validation($barber_id, $ids, $date, $time, $exclude_id);
            if ($ok) {
                $result['slots'][] = $time;
            }
        }

        return $result;
    }

    /**
     * Crea o actualiza un turno junto con sus servicios.
     *
     * Devuelve el id del turno guardado o FALSE si no se pudo.
     */
    public function save_appointment($id, $data, $services)
    {
        $this->db->trans_begin();

        // Bloquear la fila del peluquero serializa los guardados simultáneos
        // de su agenda: el segundo que llega espera y vuelve a validar el hueco.
        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($data['barber_id']));

        if ($id) {
            $this->db->query('SELECT id FROM appointments WHERE id = ? FOR UPDATE', array($id));

            // Un turno cobrado es inmutable: se avisa en vez de guardar.
            if ($this->has_payment($id)) {
                $this->db->trans_rollback();
                $this->last_error = 'El turno ya fue cobrado y no puede modificarse.';
                return FALSE;
            }
        }

        // Se vuelve a validar dentro de la transacción porque entre el
        // formulario y este guardado otro usuario pudo ocupar el horario.
        $ids = array();
        foreach ($services as $service) {
            $ids[] = $service['id'];
        }

        list($valid, $reason) = $this->slot_validation(
            $data['barber_id'],
            $ids,
            substr($data['start_at'], 0, 10),
            substr($data['start_at'], 11, 5),
            $id
        );

        if (!$valid) {
            $this->db->trans_rollback();
            $this->last_error = $reason;
            return FALSE;
        }

        if ($id) {
            $this->db->where('id', $id)->update('appointments', $data);
        } else {
            $this->db->insert('appointments', $data);
            $id = $this->db->insert_id();
        }

        // Los servicios del turno se reemplazan por completo: son los
        // checkboxes del formulario, no un agregado.
        $previous = array();
        foreach ($this->services($id) as $old) {
            $previous[$old['service_id']] = $old;
        }

        $this->db->where('appointment_id', $id)->delete('appointment_services');

        // Se guarda el snapshot (precio y duración del momento) para que
        // los cambios de tarifas posteriores no alteren este turno.
        foreach ($services as $service) {
            $old = isset($previous[$service['id']]) ? $previous[$service['id']] : NULL;

            $this->db->insert('appointment_services', array(
                'appointment_id'   => $id,
                'service_id'       => $service['id'],
                'price_snapshot'   => $old ? $old['price_snapshot'] : $service['price'],
                'duration_snapshot'=> $old ? $old['duration_snapshot'] : $service['duration_minutes'],
            ));
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();

        return $id;
    }

    /**
     * Cambia el estado de un turno de forma segura.
     *
     * Devuelve array($ok, $mensaje).
     *
     * No se usa un update directo porque el estado del turno y su cobro
     * tienen que cambiar juntos: si dos personas operan al mismo tiempo,
     * una no puede dejar un turno ya cobrado en estado cancelado o ausente.
     */
    public function change_status($id, $status)
    {
        // Primera lectura sin bloqueo: sirve para conocer al peluquero y
        // bloquear siempre en el mismo orden que en save_appointment().
        $appointment = $this->db->where('id', $id)->get('appointments')->row_array();

        if (!$appointment) {
            return array(FALSE, 'El turno no existe.');
        }

        $this->db->trans_begin();

        // Bloqueos en orden fijo (peluquero y después turno) para que dos
        // operaciones concurrentes queden en fila en vez de contradecirse.
        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($appointment['barber_id']));
        $appointment = $this->db->query('SELECT * FROM appointments WHERE id = ? FOR UPDATE', array($id))->row_array();

        // Regla de negocio: un turno con cobro solo admite estados
        // compatibles con una atención realizada.
        if ($this->has_payment($id) && $status !== 'attended') {
            $this->db->trans_rollback();
            return array(FALSE, 'El turno ya tiene un cobro registrado, por lo que no puede pasar a ese estado.');
        }

        // Al reactivar un turno cancelado o ausente se vuelve a comprobar
        // horario, servicios y disponibilidad del peluquero.
        $was_inactive = in_array($appointment['status'], array('cancelled', 'no_show'), TRUE);

        if ($was_inactive && in_array($status, array('reserved', 'attended'), TRUE)) {
            $service_ids = array_column($this->services($id), 'service_id');

            list($ok, $reason) = $this->slot_validation(
                $appointment['barber_id'],
                $service_ids,
                substr($appointment['start_at'], 0, 10),
                substr($appointment['start_at'], 11, 5),
                $id
            );

            if (!$ok) {
                $this->db->trans_rollback();
                return array(FALSE, $reason);
            }
        }

        $this->db->where('id', $id)->update('appointments', array(
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return array(FALSE, 'No se pudo actualizar el estado del turno.');
        }

        $this->db->trans_commit();

        return array(TRUE, 'Estado actualizado.');
    }

    /**
     * Cambia el peluquero y el horario de un turno existente.
     */
    public function reassign($id, $barber_id, $start, $end)
    {
        $this->db->trans_begin();

        // Mismos bloqueos que al guardar un turno: así la reasignación
        // tampoco puede pisar un cobro ni un horario ocupado.
        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($barber_id));
        $this->db->query('SELECT id FROM appointments WHERE id = ? FOR UPDATE', array($id));

        if ($this->has_payment($id)) {
            $this->db->trans_rollback();
            $this->last_error = 'Un turno cobrado no se puede reasignar.';
            return FALSE;
        }

        $ids = array();
        foreach ($this->services($id) as $service) {
            $ids[] = $service['service_id'];
        }

        list($valid, $reason) = $this->slot_validation(
            $barber_id,
            $ids,
            substr($start, 0, 10),
            substr($start, 11, 5),
            $id
        );

        if (!$valid) {
            $this->db->trans_rollback();
            $this->last_error = $reason;
            return FALSE;
        }

        $this->db->where('id', $id)->update('appointments', array(
            'barber_id'  => $barber_id,
            'start_at'   => $start,
            'end_at'     => $end,
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();

        return TRUE;
    }
}
