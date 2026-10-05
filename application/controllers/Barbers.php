<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador de peluqueros (solo Encargado).
 *
 * Recibe el formulario, valida los datos y delega en Barber_model
 * el guardado de la disponibilidad y los servicios.
 */
class Barbers extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->require_manager();

        $this->load->model(array(
            'Barber_model',
            'Service_model',
            'Appointment_model',
        ));
    }

    /**
     * Listado de peluqueros.
     */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        $this->render('barbers/index', array(
            'barbers'  => $this->Barber_model->all(FALSE, $archived, $q),
            'q'        => $q,
            'archived' => $archived,
        ), 'Peluqueros');
    }

    public function create()
    {
        $this->render_form(NULL, array());
    }

    public function edit($id)
    {
        $barber = $this->Barber_model->find($id);

        if (!$barber) {
            show_404();
        }

        $this->render_form($barber, array());
    }

    /** REQ-19: consulta de la agenda y los huecos de un peluquero. */
    public function availability($id)
    {
        $barber = $this->Barber_model->find($id);
        if (!$barber) {
            show_404();
            return;
        }

        $date_input = $this->input->get('date', TRUE);
        $date = is_scalar($date_input) ? (string) $date_input : '';
        if (!valid_day($date)) {
            $date = date('Y-m-d');
        }
        $service_ids = $this->input->get('service_ids');
        $selected = is_array($service_ids) ? $service_ids : array();
        $availability = $this->Appointment_model->available_slots($id, $date, $selected);

        $this->render('barbers/availability', array(
            'barber' => $barber,
            'date' => $date,
            'services' => $this->Service_model->all(TRUE),
            'selected_services' => $selected,
            'availability' => $availability,
        ), 'Disponibilidad del peluquero');
    }

    /**
     * Dibuja el formulario de alta o de edición.
     *
     * @param mixed $barber  datos del peluquero (NULL en alta)
     * @param array $errors  errores de validación a mostrar
     * @param bool  $posted  si los datos vienen de un reintento de POST
     */
    private function render_form($barber, $errors, $posted = FALSE)
    {
        if ($posted) {
            // Reintento: se conserva lo que el usuario había marcado.
            $schedules = array();
            foreach ($this->schedule_rows() as $row) {
                if ($row['enabled']) {
                    $schedules[] = array(
                        'day_of_week' => $row['day'],
                        'start_time'  => $row['start'] . ':00',
                        'end_time'    => $row['end'] . ':00',
                    );
                }
            }

            $posted_services = $this->input->post('service_ids');
            $selected = array_map('intval', is_array($posted_services) ? $posted_services : array());
        } elseif ($barber && !empty($barber['id'])) {
            // Edición: se muestran los horarios y servicios ya guardados.
            $schedules = $this->Barber_model->schedules($barber['id']);
            $selected  = $this->Barber_model->service_ids($barber['id']);
        } else {
            $schedules = array();
            $selected  = array();
        }

        $editing = $barber && !empty($barber['id']);

        $this->render('barbers/form', array(
            'barber'           => $barber,
            'errors'           => $errors,
            'services'         => $this->Service_model->all(TRUE),
            'schedules'        => $schedules,
            'selected_services'=> $selected,
        ), $editing ? 'Editar peluquero' : 'Nuevo peluquero');
    }

    /**
     * Los siete días de la semana con lo que llegó del formulario.
     *
     * Se usan los mismos nombres (day_1, start_1, ...) que usa el formulario,
     * y así el modelo y la vista siempre reciben la misma estructura.
     */
    private function schedule_rows()
    {
        $rows = array();

        for ($day = 1; $day <= 7; $day++) {
            $rows[] = array(
                'day'     => $day,
                'enabled' => $this->posted('day_' . $day, 0) ? 1 : 0,
                'start'   => $this->posted('start_' . $day, '09:00'),
                'end'     => $this->posted('end_' . $day, '18:00'),
            );
        }

        return $rows;
    }

    /**
     * Validaciones del formulario. Devuelve la lista de errores.
     */
    private function validate()
    {
        $errors = array();

        if (trim($this->posted('full_name', '')) === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        if (!valid_percent($this->posted('commission_percent'))) {
            $errors[] = 'El porcentaje debe estar entre 0 y 100.';
        }

        // Los servicios no pueden estar repetidos ni ser inexistentes.
        $service_ids = $this->input->post('service_ids');
        $without_duplicates = array_unique(array_map('intval', (array) $service_ids));
        $existing = $this->Service_model->get_many($service_ids ? (array) $service_ids : array());

        if (!is_array($service_ids) || !$service_ids
            || count($without_duplicates) !== count($service_ids)
            || count($existing) !== count($without_duplicates)) {
            $errors[] = 'Seleccioná servicios activos válidos sin repetir.';
        }

        // Cada día habilitado necesita una franja horaria coherente.
        foreach ($this->schedule_rows() as $row) {
            if (!$row['enabled']) {
                continue;
            }

            if (!valid_clock($row['start']) || !valid_clock($row['end']) || $row['start'] >= $row['end']) {
                $errors[] = 'Revisá los horarios de ' . day_name($row['day']) . '.';
            }
        }

        return $errors;
    }

    /**
     * Datos del peluquero que van a la tabla barbers.
     */
    private function data()
    {
        return array(
            'full_name'         => trim($this->posted('full_name', '')),
            'phone'             => trim($this->posted('phone', '')),
            'commission_percent'=> (float) $this->posted('commission_percent', 0),
            'active'            => $this->posted('active', 0) ? 1 : 0,
            'updated_at'        => date('Y-m-d H:i:s'),
        );
    }

    /**
     * Alta de peluquero.
     */
    public function store()
    {
        $this->require_post();

        $errors = $this->validate();

        if ($errors) {
            $posted = array_merge($this->input->post(NULL, TRUE), array('id' => NULL));
            $this->render_form($posted, $errors, TRUE);
            return;
        }

        $data = $this->data();
        $data['created_at'] = date('Y-m-d H:i:s');

        $saved = $this->Barber_model->save(
            NULL,
            $data,
            $this->schedule_rows(),
            $this->input->post('service_ids')
        );

        $this->respond_to_save($saved, 'Peluquero creado.');
    }

    /**
     * Edición de peluquero.
     */
    public function update($id)
    {
        $this->require_post();

        $barber = $this->Barber_model->find($id);

        if (!$barber) {
            show_404();
        }

        $errors = $this->validate();

        if ($errors) {
            $posted = array_merge($barber, $this->input->post(NULL, TRUE));
            $this->render_form($posted, $errors, TRUE);
            return;
        }

        $saved = $this->Barber_model->save(
            $id,
            $this->data(),
            $this->schedule_rows(),
            $this->input->post('service_ids')
        );

        $this->respond_to_save($saved, 'Peluquero actualizado.');
    }

    /**
     * Muestra el resultado del guardado y vuelve al listado.
     *
     * @param mixed  $saved    id devuelto por el modelo o FALSE
     * @param string $success  mensaje de éxito
     */
    private function respond_to_save($saved, $success)
    {
        // El modelo devuelve FALSE, entre otras cosas, cuando los turnos
        // futuros no entran en la nueva disponibilidad.
        if (!$saved) {
            $this->session->set_flashdata('error', $this->Barber_model->last_error
                ?: 'No se pudo guardar el peluquero.');
        } else {
            $this->session->set_flashdata('success', $success);
        }

        redirect('peluqueros');
    }

    /**
     * Reasigna un turno futuro a otro peluquero.
     */
    public function reassign($id)
    {
        $barber = $this->Barber_model->find($id);

        if (!$barber) {
            show_404();
        }

        $errors = array();

        if ($this->input->method(TRUE) === 'POST') {
            $appointment_id = (int) $this->posted('appointment_id');
            $new_barber_id  = (int) $this->posted('barber_id');
            $date           = $this->posted('date');
            $time           = $this->posted('time');

            $appointment = $this->Appointment_model->find($appointment_id);

            // Solo se pueden mover turnos de este peluquero y sin cobrar.
            if (!$appointment || $appointment['barber_id'] != $id || $appointment['payment']) {
                $errors[] = 'El turno no corresponde al peluquero o ya fue cobrado.';
            } else {
                // Se vuelve a validar todo: horario del nuevo peluquero,
                // servicios que realiza y ausencia de cruces.
                $service_ids = array_column($appointment['services'], 'service_id');
                list($ok, $message, $start, $end) = $this->Appointment_model->slot_validation(
                    $new_barber_id,
                    $service_ids,
                    $date,
                    $time,
                    $appointment_id
                );

                if (!$ok) {
                    $errors[] = $message;
                } elseif (!$this->Appointment_model->reassign($appointment_id, $new_barber_id, $start, $end)) {
                    $errors[] = $this->Appointment_model->last_error;
                } else {
                    $this->session->set_flashdata('success', 'Turno reasignado.');
                    redirect('peluqueros/reasignar/' . $id);
                    return;
                }
            }
        }

        $this->render('barbers/reassign', array(
            'barber'      => $barber,
            'appointments'=> $this->Barber_model->future_appointments($id),
            'barbers'     => $this->Barber_model->all(TRUE),
            'errors'      => $errors,
        ), 'Reasignar turnos');
    }

    /**
     * Eliminación lógica de un peluquero.
     */
    public function delete($id)
    {
        $this->require_post();

        if (!$this->Barber_model->find($id)) {
            show_404();
        }

        // No se puede dejar un peluquero con turnos futuros sin confirmar:
        // primero hay que reasignarlos o cancelarlos.
        if ($this->Barber_model->future_appointments($id)) {
            $this->session->set_flashdata('error', 'Reasigná o cancelá los turnos futuros antes de eliminar al peluquero.');
        } else {
            $this->Barber_model->archive($id);
            $this->session->set_flashdata('success', 'Peluquero eliminado de la lista. Su historial se conservó.');
        }

        redirect('peluqueros');
    }

    /**
     * Restaura un peluquero eliminado.
     */
    public function restore($id)
    {
        $this->require_post();

        // Solo se restaura si estaba efectivamente eliminado.
        if (!$this->Barber_model->is_archived($id)) {
            show_404();
        }

        $this->Barber_model->restore($id);

        $this->session->set_flashdata('success', 'Peluquero restaurado. Revisá sus horarios y servicios.');
        redirect('peluqueros?archived=1');
    }
}
