<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador de la pantalla de inicio.
 *
 * Muestra un resumen de la jornada: turnos de hoy, estados y lo cobrado.
 * Los datos económicos completos (egresos y comisiones) son solo del Encargado.
 */
class Dashboard extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->require_login();

        $this->load->model(array(
            'Appointment_model',
            'Payment_model',
            'Expense_model',
        ));
    }

    public function index()
    {
        $today = date('Y-m-d');

        // Un solo recorrido de la agenda del día alimenta el resumen y la tabla.
        $appointments = $this->Appointment_model->for_date($today);

        // Contadores por estado para las tarjetas de la pantalla.
        $counts = array(
            'total'    => count($appointments),
            'attended' => 0,
            'reserved' => 0,
            'cancelled'=> 0,
            'no_show'  => 0,
        );

        foreach ($appointments as $appointment) {
            if (isset($counts[$appointment['status']])) {
                $counts[$appointment['status']]++;
            }
        }

        $data = array();
        $data['appointments'] = $appointments;
        $data['counts']       = $counts;
        $data['recent']       = $appointments;
        $data['income']       = $this->Payment_model->total_between($today, $today);

        // El resultado del local solo se calcula para el Encargado.
        if (($this->current_user['role_code'] ?? '') === 'encargado') {
            $data['expenses']     = $this->Expense_model->total_between($today, $today);
            $data['commissions']  = $this->Payment_model->commissions_between($today, $today);
            $data['local_result'] = $data['income'] - $data['expenses'] - $data['commissions'];
        }

        $this->render('dashboard/index', $data, 'Inicio');
    }
}
