<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador de turnos (agenda).
 *
 * Responsabilidades:
 * - recibir los datos del formulario;
 * - validar lo básico antes de llamar al modelo;
 * - delegar las reglas de agenda a Appointment_model;
 * - elegir la vista y manejar los mensajes de éxito o error.
 *
 * No contiene consultas SQL.
 */
class Appointments extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        // Cualquier usuario con sesión puede usar la agenda.
        $this->require_login();

        $this->load->model(array(
            'Appointment_model',
            'Client_model',
            'Barber_model',
            'Service_model',
        ));
    }

    /**
     * Listado de turnos con filtros por fecha, peluquero, estado y texto.
     *
     * Con fecha se ve la agenda del día completo; sin fecha (date=) se
     * lista el historial, que sí se pagina.
     */
    public function index()
    {
        // Fecha vacía significa buscar en toda la agenda.
        $date = $this->input->get('date', TRUE);
        if ($date === NULL) {
            $date = date('Y-m-d');
        }

        // Una fecha inválida no debe llegar al modelo: se usa la de hoy.
        if ($date !== '' && !valid_day($date)) {
            $date = date('Y-m-d');
        }

        $barber_id = (int) $this->input->get('barber_id');
        $q = trim((string) $this->input->get('q', TRUE));
        $status = (string) $this->input->get('status', TRUE);

        // Solo se aceptan los estados que existen en la base.
        $allowed_statuses = array('', 'reserved', 'attended', 'cancelled', 'no_show');
        if (!in_array($status, $allowed_statuses, TRUE)) {
            $status = '';
        }

        $barber = $barber_id ?: NULL;

        // La agenda de un día se muestra entera; el historial se pagina.
        $limite = 0;
        $offset = 0;
        $paginacion = array();

        if ($date === '') {
            $paginacion = $this->paginacion(
                $this->Appointment_model->count_for_date($date, $barber, $q, $status)
            );

            $limite = $paginacion['limit'];
            $offset = $paginacion['offset'];
        }

        $this->render('appointments/index', array(
            'date'        => $date,
            'q'           => $q,
            'status'      => $status,
            'barber_id'   => $barber_id,
            'appointments'=> $this->Appointment_model->for_date($date, $barber, $q, $status, $limite, $offset),
            'barbers'     => $this->Barber_model->all(),
            'paginacion'  => $paginacion,
            'ruta_paginacion' => 'turnos',
            // date vacío es lo que mantiene abierto el historial.
            'filtros_paginacion' => array(
                'date'      => '',
                'q'         => $q,
                'status'    => $status,
                'barber_id' => $barber_id,
            ),
        ), 'Agenda / Turnos');
    }

    /**
     * Alta de un turno con reserva previa.
     */
    public function create()
    {
        $this->render_form(NULL, array(), FALSE);
    }

    /**
     * Alta de una atención sin turno previo (walk-in).
     */
    public function walkin()
    {
        $this->render_form(NULL, array(), TRUE);
    }

    /** Consulta de horarios para el formulario; el guardado vuelve a validar. */
    public function availability()
    {
        $barber_id = (int) $this->input->get('barber_id');
        $date_input = $this->input->get('date', TRUE);
        $date = is_scalar($date_input) ? (string) $date_input : '';
        $service_ids = $this->input->get('service_ids');
        $exclude_id = (int) $this->input->get('appointment_id');

        if ($exclude_id) {
            $appointment = $this->Appointment_model->find($exclude_id);
            if (!$appointment || $appointment['payment']) {
                show_404();
                return;
            }
        }

        $result = $this->Appointment_model->available_slots(
            $barber_id, $date, $service_ids, $exclude_id ?: NULL
        );
        $this->output->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($result));
    }

    /**
     * Edición de un turno existente.
     */
    public function edit($id)
    {
        $appointment = $this->Appointment_model->find($id);

        if (!$appointment) {
            show_404();
        }

        // Un turno cobrado no se edita: la corrección se hace sobre el cobro.
        if ($appointment['payment']) {
            $this->session->set_flashdata('error', 'Un turno cobrado no puede modificarse. El Encargado puede corregir el cobro.');
            redirect('turnos/ver/' . $id);
            return;
        }

        $this->render_form($appointment, array(), (bool) $appointment['walk_in']);
    }

    /**
     * Dibuja el formulario de alta o de edición.
     *
     * @param mixed  $appointment  datos del turno (NULL en alta)
     * @param array  $errors       errores de validación a mostrar
     * @param bool   $walkin       si es una atención sin turno previo
     * @param bool   $posted       si los datos vienen de un reintento de POST
     */
    private function render_form($appointment, $errors, $walkin, $posted = FALSE)
    {
        // Servicios checkboxes marcados: en un reintento se toma lo que
        // acaba de enviar el formulario, si no los del turno guardado.
        if ($posted) {
            $posted_ids = $this->input->post('service_ids');
            $selected = is_array($posted_ids) ? array_map('intval', $posted_ids) : array();
        } elseif ($appointment && !empty($appointment['services'])) {
            $selected = array_map('intval', array_column($appointment['services'], 'service_id'));
        } else {
            $query_ids = $this->input->get('service_ids');
            $selected = is_array($query_ids) ? array_map('intval', $query_ids) : array();
        }

        $available = $this->Service_model->all(TRUE);

        // En una edición, el estimado usa los snapshots del turno y no la
        // tarifa de hoy, para mostrar exactamente lo que se guardó.
        if ($appointment && !empty($appointment['services'])) {
            foreach ($available as &$service) {
                foreach ($appointment['services'] as $saved) {
                    if ($service['id'] == $saved['service_id']) {
                        $service['price'] = $saved['price_snapshot'];
                        $service['duration_minutes'] = $saved['duration_snapshot'];
                    }
                }
            }
            unset($service);
        }

        $editing = $appointment && !empty($appointment['id']);
        $title = $editing ? 'Editar turno' : ($walkin ? 'Atención sin turno' : 'Nuevo turno');

        $this->render('appointments/form', array(
            'appointment'      => $appointment,
            'errors'           => $errors,
            'walkin'           => $walkin,
            'clients'          => $this->Client_model->search(),
            'barbers'          => $this->Barber_model->all(TRUE),
            'services'         => $available,
            'selected_services'=> $selected,
        ), $title);
    }

    /**
     * Validación básica del formulario.
     *
     * Devuelve array($errores, $inicio, $fin, $servicios).
     * Las reglas finas de agenda las aplica Appointment_model.
     */
    private function validate_form($exclude_id)
    {
        $errors = array();

        $client_id  = (int) $this->posted('client_id');
        $barber_id  = (int) $this->posted('barber_id');
        $service_ids= $this->input->post('service_ids');
        $date       = $this->posted('date');
        $time       = $this->posted('time');

        // El cliente debe existir: un id inventado rompería la clave foránea.
        if (!$this->Client_model->find($client_id)) {
            $errors[] = 'Seleccioná un cliente válido.';
        }

        if (!$barber_id) {
            $errors[] = 'Seleccioná un peluquero.';
        }

        if (!is_array($service_ids) || !$service_ids) {
            $errors[] = 'Seleccioná al menos un servicio.';
        }

        if (!valid_day($date) || !valid_clock($time)) {
            $errors[] = 'Indicá una fecha y hora válidas.';
        }

        if ($errors) {
            return array($errors, NULL, NULL, array());
        }

        // El modelo calcula duración, comprueba el horario del peluquero
        // y busca cruces reales con otros turnos.
        list($ok, $message, $start, $end, $services) = $this->Appointment_model->slot_validation(
            $barber_id,
            $service_ids,
            $date,
            $time,
            $exclude_id
        );

        if (!$ok) {
            $errors[] = $message;
        }

        return array($errors, $start, $end, $services);
    }

    /**
     * Flujo común de alta y de edición.
     *
     * @param mixed $id     NULL para crear, id para actualizar
     * @param bool  $walkin si el turno es una atención sin reserva
     */
    private function save($id, $walkin)
    {
        $this->require_post();

        $existing = $id ? $this->Appointment_model->find($id) : NULL;

        if ($id && !$existing) {
            show_404();
        }

        if ($existing && $existing['payment']) {
            show_error('No se puede modificar un turno cobrado.', 409, 'Turno cobrado');
            return;
        }

        list($errors, $start, $end, $services) = $this->validate_form($id);

        // Si hay errores se vuelve a mostrar el formulario con los datos
        // que envió el usuario, para que no pierda lo que había tipeado.
        if ($errors) {
            $posted = array_merge($this->input->post(NULL, TRUE), array(
                'id' => $id,
                'services' => $existing ? $existing['services'] : array(),
            ));
            $this->render_form($posted, $errors, $walkin, TRUE);
            return;
        }

        $status = $this->posted('status', 'reserved');
        $allowed_statuses = array('reserved', 'attended', 'cancelled', 'no_show');

        if (!in_array($status, $allowed_statuses, TRUE)) {
            $posted = array_merge($this->input->post(NULL, TRUE), array(
                'id' => $id,
                'services' => $existing ? $existing['services'] : array(),
            ));
            $this->render_form($posted, array('Estado inválido.'), $walkin, TRUE);
            return;
        }

        // Datos que van a la tabla appointments.
        $data = array(
            'client_id'  => (int) $this->posted('client_id'),
            'barber_id'  => (int) $this->posted('barber_id'),
            'start_at'   => $start,
            'end_at'     => $end,
            'status'     => $status,
            'notes'      => trim($this->posted('notes', '')),
            'walk_in'    => $walkin ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        // En la alta se completa también la auditoría de creación.
        if (!$id) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $this->current_user['id'];
        }

        $saved_id = $this->Appointment_model->save_appointment($id, $data, $services);

        // El modelo puede fallar por una regla de negocio (por ejemplo,
        // un horario tomado entre el formulario y el guardado).
        if (!$saved_id) {
            $this->session->set_flashdata('error', $this->Appointment_model->last_error);
            redirect('turnos');
            return;
        }

        $this->session->set_flashdata('success', $id ? 'Turno actualizado.' : 'Turno registrado.');
        redirect('turnos/ver/' . $saved_id);
    }

    public function store()
    {
        $this->save(NULL, (bool) $this->posted('walk_in', 0));
    }

    public function update($id)
    {
        $this->save($id, (bool) $this->posted('walk_in', 0));
    }

    /**
     * Detalle de un turno.
     */
    public function show($id)
    {
        $appointment = $this->Appointment_model->find($id);

        if (!$appointment) {
            show_404();
        }

        $this->render('appointments/show', array(
            'appointment' => $appointment,
        ), 'Detalle del turno');
    }

    /**
     * Cambio de estado desde el detalle del turno.
     */
    public function status($id)
    {
        $this->require_post();

        $appointment = $this->Appointment_model->find($id);

        if (!$appointment) {
            show_404();
        }

        $status = $this->posted('status');
        $allowed_statuses = array('reserved', 'attended', 'cancelled', 'no_show');

        // El estado tiene que ser uno conocido. La incompatibilidad con un
        // turno cobrado y la validación del horario al reactivar se resuelven
        // dentro del modelo, con los bloqueos correspondientes.
        if (!in_array($status, $allowed_statuses, TRUE)) {
            $this->session->set_flashdata('error', 'No se puede usar un estado inválido.');
            redirect('turnos/ver/' . $id);
            return;
        }

        list($ok, $message) = $this->Appointment_model->change_status($id, $status);

        $this->session->set_flashdata($ok ? 'success' : 'error', $message);

        redirect('turnos/ver/' . $id);
    }

    /**
     * Eliminar un turno.
     *
     * No se borra la fila: se cancela, así el historial del cliente
     * queda intacto y el horario se libera para otro turno.
     */
    public function delete($id)
    {
        $this->require_post();

        $appointment = $this->Appointment_model->find($id);

        if (!$appointment) {
            show_404();
        }

        // Igual que en el cambio de estado, el cobro se valida dentro del
        // modelo para que una operación simultánea no pueda esquivarlo.
        list($ok, $message) = $this->Appointment_model->change_status($id, 'cancelled');

        $this->session->set_flashdata($ok ? 'success' : 'error', $ok
            ? 'Turno eliminado de la agenda activa (cancelado).'
            : $message);

        redirect('turnos/ver/' . $id);
    }
}
