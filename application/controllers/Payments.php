<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador de cobros.
 *
 * Registrar un cobro es lo que genera el ingreso del local y marca el
 * turno como atendido. Cualquier usuario con sesión puede registrar un cobro;
 * corregir o anular uno es solo del Encargado.
 */
class Payments extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->require_login();

        $this->load->model(array(
            'Payment_model',
            'Appointment_model',
        ));
    }

    /**
     * Listado de cobros con filtros de texto y período, en páginas.
     */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));

        $from = (string) $this->input->get('from', TRUE);
        $to   = (string) $this->input->get('to', TRUE);

        // Ver los cobros anulados es una tarea de administración.
        $archived = $this->input->get('archived') === '1'
            && $this->current_user['role_code'] === 'encargado';

        // Con filtros de fecha se consulta el período pedido;
        // si vienen incompletos se completan con el mes en curso.
        if ($from !== '' || $to !== '') {
            $rango_desde = valid_day($from) ? $from : date('Y-m-01');
            $rango_hasta = valid_day($to) ? $to : date('Y-m-d');
        } else {
            // Sin filtros se recorre todo el historial, página por página.
            $rango_desde = '1000-01-01';
            $rango_hasta = '9999-12-31';
        }

        $paginacion = $this->paginacion(
            $this->Payment_model->count_between($rango_desde, $rango_hasta, $q, $archived)
        );

        $this->render('payments/index', array(
            'payments'  => $this->Payment_model->between(
                $rango_desde,
                $rango_hasta,
                $q,
                $archived,
                $paginacion['limit'],
                $paginacion['offset']
            ),
            'q'         => $q,
            'from'      => $from,
            'to'        => $to,
            'archived'  => $archived,
            'paginacion'=> $paginacion,
            'ruta_paginacion' => 'cobros',
            // Los filtros se repiten en los enlaces de paginación.
            'filtros_paginacion' => array(
                'q'        => $q,
                'from'     => $from,
                'to'       => $to,
                'archived' => $archived ? '1' : '',
            ),
        ), 'Cobros');
    }

    /**
     * Formulario para cobrar un turno.
     */
    public function create($appointment_id)
    {
        $appointment = $this->Appointment_model->find($appointment_id);

        if (!$appointment) {
            show_404();
        }

        // Un turno que ya tiene cobro no se vuelve a cobrar.
        if (!empty($appointment['payment'])) {
            redirect('cobros');
            return;
        }

        $this->render('payments/form', array(
            'appointment'=> $appointment,
            'methods'    => $this->Payment_model->methods(),
            'errors'     => array(),
        ), 'Registrar cobro');
    }

    /**
     * Registro del cobro.
     */
    public function store()
    {
        $this->require_post();

        $appointment_id = (int) $this->posted('appointment_id');
        $appointment = $this->Appointment_model->find($appointment_id);

        if (!$appointment) {
            show_404();
        }

        $amount = $this->posted('amount');
        $method = (int) $this->posted('payment_method_id');
        $errors = array();

        if (!valid_money($amount, TRUE)) {
            $errors[] = 'El importe debe ser positivo y tener hasta dos decimales.';
        }

        if (!$method) {
            $errors[] = 'Seleccioná una forma de pago.';
        }

        if ($errors) {
            $this->render('payments/form', array(
                'appointment'=> $appointment,
                'methods'    => $this->Payment_model->methods(),
                'errors'     => $errors,
            ), 'Registrar cobro');
            return;
        }

        list($ok, $message) = $this->Payment_model->create_for_appointment(
            $appointment_id,
            $amount,
            $method,
            trim($this->posted('notes', '')),
            $this->current_user['id']
        );

        $this->session->set_flashdata($ok ? 'success' : 'error', $message);

        redirect($ok ? 'cobros' : 'turnos/ver/' . $appointment_id);
    }

    /**
     * Formulario para corregir un cobro (solo Encargado).
     */
    public function edit($id)
    {
        $this->require_manager();

        $payment = $this->Payment_model->find($id);

        if (!$payment || $payment['voided_at']) {
            show_404();
        }

        $this->render('payments/edit', array(
            'payment' => $payment,
            'methods' => $this->Payment_model->methods(),
            'errors'  => array(),
        ), 'Corregir cobro');
    }

    /**
     * Guarda la corrección de un cobro (solo Encargado).
     */
    public function update($id)
    {
        $this->require_manager();
        $this->require_post();

        $payment = $this->Payment_model->find($id);

        if (!$payment) {
            show_404();
        }

        $amount = $this->posted('amount');
        $method = (int) $this->posted('payment_method_id');

        if (!valid_money($amount, TRUE) || !$method) {
            $this->render('payments/edit', array(
                'payment'=> $payment,
                'methods'=> $this->Payment_model->methods(),
                'errors' => array('Revisá el importe y la forma de pago.'),
            ), 'Corregir cobro');
            return;
        }

        $saved = $this->Payment_model->update_payment(
            $id,
            $amount,
            $method,
            trim($this->posted('notes', ''))
        );

        if (!$saved) {
            $this->session->set_flashdata('error', 'Forma de pago inválida.');
            redirect('cobros/editar/' . $id);
            return;
        }

        $this->session->set_flashdata('success', 'Cobro corregido.');
        redirect('contabilidad');
    }

    /**
     * Formas de pago (solo Encargado).
     */
    public function methods()
    {
        $this->require_manager();

        $this->render('payments/methods', array(
            'methods' => $this->Payment_model->methods(FALSE),
        ), 'Formas de pago');
    }

    /**
     * Alta de una forma de pago (solo Encargado).
     */
    public function store_method()
    {
        $this->require_manager();
        $this->require_post();

        $name = trim($this->posted('name', ''));

        if ($name === '' || mb_strlen($name) > 80) {
            $this->session->set_flashdata('error', 'Ingresá un nombre de hasta 80 caracteres.');
        } elseif ($this->Payment_model->method_name_exists($name)) {
            // El nombre es único en la base.
            $this->session->set_flashdata('error', 'Esa forma de pago ya existe.');
        } else {
            $this->Payment_model->create_method($name);
            $this->session->set_flashdata('success', 'Forma de pago agregada.');
        }

        redirect('formas-pago');
    }

    /**
     * Anulación de un cobro (solo Encargado).
     *
     * No se borra la fila: queda anulada y deja de sumar a ingresos
     * y comisiones, pero se conserva el rastro.
     */
    public function delete($id)
    {
        $this->require_manager();
        $this->require_post();

        $payment = $this->Payment_model->find($id);

        if (!$payment || $payment['voided_at']) {
            show_404();
        }

        $this->Payment_model->void_payment($id, $this->current_user['id']);

        $this->session->set_flashdata('success', 'Cobro anulado. Ya no suma a ingresos ni comisiones.');
        redirect('cobros');
    }

    /**
     * Restaura un cobro anulado (solo Encargado).
     */
    public function restore($id)
    {
        $this->require_manager();
        $this->require_post();

        $payment = $this->Payment_model->find($id);

        if (!$payment || !$payment['voided_at']) {
            show_404();
        }

        $this->Payment_model->restore_payment($id);

        $this->session->set_flashdata('success', 'Cobro restaurado.');
        redirect('cobros?archived=1');
    }
}
