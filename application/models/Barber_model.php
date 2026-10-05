<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modelo de peluqueros.
 *
 * Maneja los datos del peluquero, sus días y horarios de trabajo
 * y los servicios que puede realizar. También verifica que los turnos
 * ya cargados sigan siendo válidos cuando se cambia esa disponibilidad.
 */
class Barber_model extends CI_Model {

    // Mensaje del último error de negocio, para mostrarlo en la vista.
    public $last_error = '';

    /**
     * Lista de peluqueros.
     *
     * @param bool  $active_only  solo los que están activos
     * @param bool  $archived     true = los eliminados; false = los vigentes
     * @param string $q           texto para filtrar por nombre o teléfono
     */
    public function all($active_only = FALSE, $archived = FALSE, $q = '')
    {
        $this->db->from('barbers');

        // Borrado lógico: los eliminados siguen en la base para el historial.
        $this->db->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL', NULL, FALSE);

        if ($active_only) {
            $this->db->where('active', 1);
        }

        if ($q !== '') {
            // El grupo mantiene el OR dentro del filtro por texto.
            $this->db->group_start()
                ->like('full_name', $q)
                ->or_like('phone', $q)
                ->group_end();
        }

        return $this->db->order_by('full_name')->get()->result_array();
    }

    /**
     * Un peluquero por su id, o NULL si no existe o fue eliminado.
     */
    public function find($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('barbers')
            ->row_array();
    }

    /**
     * ¿El peluquero está eliminado (para poder restaurarlo)?
     */
    public function is_archived($id)
    {
        return $this->db->where('id', (int) $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->count_all_results('barbers') > 0;
    }

    /**
     * Baja lógica: se marca inactive y se conserva todo su historial.
     */
    public function archive($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('barbers', array(
                'active'     => 0,
                'deleted_at' => date('Y-m-d H:i:s'),
            ));
    }

    /**
     * Restaura un peluquero eliminado.
     */
    public function restore($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('barbers', array(
                'active'     => 1,
                'deleted_at' => NULL,
            ));
    }

    /**
     * Días y horarios configurados para un peluquero.
     */
    public function schedules($barber_id)
    {
        return $this->db->where('barber_id', $barber_id)
            ->order_by('day_of_week')
            ->get('barber_schedules')
            ->result_array();
    }

    /**
     * Ids de los servicios que puede realizar un peluquero.
     */
    public function service_ids($barber_id)
    {
        $rows = $this->db->select('service_id')
            ->where('barber_id', $barber_id)
            ->get('barber_services')
            ->result_array();

        return array_map('intval', array_column($rows, 'service_id'));
    }

    /**
     * ¿El peluquero puede realizar TODOS los servicios indicados?
     */
    public function supports_services($barber_id, $service_ids)
    {
        $service_ids = array_values(array_unique(array_map('intval', $service_ids)));

        if (!$service_ids) {
            return FALSE;
        }

        $found = $this->db->where('barber_id', $barber_id)
            ->where_in('service_id', $service_ids)
            ->count_all_results('barber_services');

        return $found === count($service_ids);
    }

    /**
     * ¿El turno cae dentro de un día y horario de trabajo del peluquero?
     */
    public function within_schedule($barber_id, $start_at, $end_at)
    {
        // Un turno que termina de otro día no se puede validar.
        if (substr($start_at, 0, 10) !== substr($end_at, 0, 10)) {
            return FALSE;
        }

        // date('N') devuelve 1 para lunes y 7 para domingo, igual que la tabla.
        $day_of_week = (int) date('N', strtotime($start_at));
        $start_time  = date('H:i:s', strtotime($start_at));
        $end_time    = date('H:i:s', strtotime($end_at));

        return $this->db->where('barber_id', $barber_id)
            ->where('day_of_week', $day_of_week)
            ->where('active', 1)
            ->where('start_time <=', $start_time)
            ->where('end_time >=', $end_time)
            ->count_all_results('barber_schedules') > 0;
    }

    /**
     * Guarda el peluquero con sus horarios y servicios.
     *
     * @param mixed $id            NULL para crear, id para actualizar
     * @param array $data          datos de la tabla barbers
     * @param array $schedule_rows filas de horarios (day, enabled, start, end)
     * @param array $service_ids   ids de servicios marcados
     *
     * @return mixed id del peluquero guardado o FALSE si no se pudo
     */
    public function save($id, $data, $schedule_rows, $service_ids)
    {
        // Se normaliza a array: si el campo no viene como lista, se trata
        // como una lista vacía en lugar de romper el guardado.
        $service_ids = (array) $service_ids;

        $this->db->trans_begin();

        if ($id) {
            // Bloquear la fila del peluquero evita que dos personas
            // guarden la disponibilidad al mismo tiempo.
            $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($id));

            $this->db->where('id', $id)->update('barbers', $data);
        } else {
            $this->db->insert('barbers', $data);
            $id = $this->db->insert_id();
        }

        // La disponibilidad se reemplaza completa: lo que viene del formulario
        // es el estado real, y los días desmarcados se borran.
        $this->db->where('barber_id', $id)->delete('barber_schedules');

        foreach ($schedule_rows as $row) {
            if (empty($row['enabled'])) {
                continue;
            }

            $this->db->insert('barber_schedules', array(
                'barber_id'   => $id,
                'day_of_week' => $row['day'],
                'start_time'  => $row['start'],
                'end_time'    => $row['end'],
                'active'      => 1,
            ));
        }

        // Lo mismo con los servicios: son los checkboxes del formulario.
        $this->db->where('barber_id', $id)->delete('barber_services');

        foreach (array_unique(array_map('intval', $service_ids)) as $service_id) {
            $this->db->insert('barber_services', array(
                'barber_id'  => $id,
                'service_id' => $service_id,
            ));
        }

        // Antes de confirmar se revisa que los turnos futuros ya reservados
        // sigan cabiendo en la nueva disponibilidad. Si no, se deshace todo:
        // es preferible avisar que dejar una reserva fuera de horario.
        $conflicts = $this->schedule_conflicts($id, $schedule_rows, $service_ids);

        if ($conflicts) {
            $this->db->trans_rollback();
            $this->last_error = implode(' ', $conflicts);
            return FALSE;
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            $this->last_error = 'No se pudieron guardar los datos del peluquero.';
            return FALSE;
        }

        $this->db->trans_commit();

        return $id;
    }

    /**
     * Turnos futuros reservados que quedarían fuera de la disponibilidad
     * que se está por guardar.
     *
     * Devuelve un arreglo con un mensaje por conflicto; vacío si no hay
     * ninguno. Solo se miran los turnos futuros: los pasados son historia
     * y los cancelados o ausentes ya no ocupan agenda.
     */
    private function schedule_conflicts($barber_id, $schedule_rows, $service_ids)
    {
        $conflicts = array();

        $service_ids = (array) $service_ids;

        // Días con horario cargado en el formulario. Los demás, sin atención.
        $days = array();
        foreach ($schedule_rows as $row) {
            if (empty($row['enabled'])) {
                continue;
            }

            $days[(int) $row['day']] = array($row['start'], $row['end']);
        }

        $allowed_services = array_unique(array_map('intval', $service_ids));

        $appointments = $this->db->select('id, start_at, end_at')
            ->from('appointments')
            ->where('barber_id', $barber_id)
            ->where('status', 'reserved')
            ->where('start_at >=', date('Y-m-d H:i:s'))
            ->order_by('start_at')
            ->get()
            ->result_array();

        foreach ($appointments as $appointment) {
            $appointment_id = (int) $appointment['id'];
            $day_of_week    = (int) date('N', strtotime($appointment['start_at']));
            $start_time     = date('H:i', strtotime($appointment['start_at']));
            $end_time       = date('H:i', strtotime($appointment['end_at']));

            // 1) El turno tiene que caer en un día con horario.
            if (!isset($days[$day_of_week])) {
                $conflicts[] = 'El turno #' . $appointment_id . ' queda en un día sin horario.';
                continue;
            }

            // 2) Y dentro de la franja horario de ese día.
            list($open_time, $close_time) = $days[$day_of_week];

            if ($start_time < $open_time || $end_time > $close_time) {
                $conflicts[] = 'El turno #' . $appointment_id . ' (' . $start_time . '-' . $end_time . ') queda fuera del horario de ' . day_name($day_of_week) . '.';
            }

            // 3) El peluquero debe seguir pudiendo hacer los servicios del turno.
            $saved = $this->db->select('service_id')
                ->where('appointment_id', $appointment_id)
                ->get('appointment_services')
                ->result_array();

            foreach (array_map('intval', array_column($saved, 'service_id')) as $service_id) {
                if (!in_array($service_id, $allowed_services, TRUE)) {
                    $conflicts[] = 'El turno #' . $appointment_id . ' usa un servicio que el peluquero ya no realiza.';
                    break;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Turnos futuros reservados de un peluquero.
     * Se usa para poder reasignarlos antes de eliminarlo.
     */
    public function future_appointments($barber_id)
    {
        return $this->db->select("a.*, CONCAT(c.first_name, ' ', c.last_name) client_name")
            ->from('appointments a')
            ->join('clients c', 'c.id = a.client_id')
            ->where('a.barber_id', $barber_id)
            ->where('a.start_at >=', date('Y-m-d H:i:s'))
            ->where_in('a.status', array('reserved'))
            ->order_by('a.start_at')
            ->get()
            ->result_array();
    }
}
