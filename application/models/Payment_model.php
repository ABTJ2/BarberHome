<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modelo de cobros.
 *
 * Cada cobro es a la vez el ingreso del local y el pago del peluquero:
 * por eso guarda el porcentaje de comisión congelado al cobrar
 * (commission_percent_snapshot) y el monto de comisión.
 *
 * Un cobro anulado se conserva (voided_at) pero deja de sumar.
 */
class Payment_model extends CI_Model {

    /**
     * Formas de pago disponibles.
     *
     * @param bool $active_only solo las activas (las que se pueden usar)
     */
    public function methods($active_only = TRUE)
    {
        if ($active_only) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('name')->get('payment_methods')->result_array();
    }

    /**
     * ¿Ya existe una forma de pago con ese nombre?
     *
     * El nombre es único: se avisa antes de dar de alta un duplicado.
     */
    public function method_name_exists($name)
    {
        return $this->db->where('name', $name)
            ->count_all_results('payment_methods') > 0;
    }

    /**
     * Alta de una forma de pago.
     */
    public function create_method($name)
    {
        return $this->db->insert('payment_methods', array(
            'name'  => $name,
            'active'=> 1,
        ));
    }

    /**
     * Filtro común de los listados: tipo de cobro y búsqueda por texto.
     */
    private function filter_list($q, $archived)
    {
        // Los anulados se ven aparte, con el casillero "Anulados".
        $this->db->where($archived ? 'p.voided_at IS NOT NULL' : 'p.voided_at IS NULL', NULL, FALSE);

        if ($q !== '') {
            $this->db->group_start()
                ->like('c.first_name', $q)
                ->or_like('c.last_name', $q)
                ->or_like('b.full_name', $q)
                ->or_like('pm.name', $q)
                ->group_end();
        }
    }

    /**
     * Un cobro por su id, con los datos del turno asociado.
     */
    public function find($id)
    {
        return $this->db->select('p.*, a.client_id, a.barber_id, a.start_at, pm.name payment_method')
            ->from('payments p')
            ->join('appointments a', 'a.id = p.appointment_id')
            ->join('payment_methods pm', 'pm.id = p.payment_method_id')
            ->where('p.id', $id)
            ->get()
            ->row_array();
    }

    /**
     * Cobro de un turno, si existe.
     */
    public function for_appointment($appointment_id)
    {
        return $this->db->where('appointment_id', $appointment_id)
            ->get('payments')
            ->row_array();
    }

    /**
     * Anula un cobro: deja de sumar a ingresos y comisiones, pero se conserva.
     */
    public function void_payment($id, $user_id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NULL', NULL, FALSE)
            ->update('payments', array(
                'voided_at' => date('Y-m-d H:i:s'),
                'voided_by' => $user_id,
            ));
    }

    /**
     * Restaura un cobro anulado.
     */
    public function restore_payment($id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NOT NULL', NULL, FALSE)
            ->update('payments', array(
                'voided_at' => NULL,
                'voided_by' => NULL,
            ));
    }

    /**
     * Registra el cobro de un turno.
     *
     * Devuelve array($ok, $mensaje). El cobro:
     * - pasa el turno a "atendido";
     * - congela el porcentaje de comisión de ese momento;
     * - genera el ingreso del local.
     */
    public function create_for_appointment($appointment_id, $amount, $method_id, $notes, $user_id)
    {
        $this->db->trans_begin();

        // Bloquear el turno evita que dos personas lo cobren al mismo tiempo
        // y que una se lleve por delante el estado que otra está por leer.
        $appointment = $this->db->query(
            'SELECT a.*, b.commission_percent
               FROM appointments a
               JOIN barbers b ON b.id = a.barber_id
              WHERE a.id = ?
              FOR UPDATE',
            array($appointment_id)
        )->row_array();

        // No se cobra un turno cancelado, ausente, inexistente
        // ni uno que ya tiene un cobro.
        if (!$appointment
            || in_array($appointment['status'], array('cancelled', 'no_show'), TRUE)
            || $this->for_appointment($appointment_id)) {
            $this->db->trans_rollback();
            return array(FALSE, 'El turno no se puede cobrar en su estado actual o ya tiene un cobro.');
        }

        if (!$this->active_method($method_id)) {
            $this->db->trans_rollback();
            return array(FALSE, 'Seleccioná una forma de pago activa.');
        }

        // Comisión del peluquero: se congela el porcentaje para que un
        // cambio de comisión no altere liquidaciones ya cobradas.
        $percent = (float) $appointment['commission_percent'];
        $commission = round((float) $amount * $percent / 100, 2);

        $this->db->insert('payments', array(
            'appointment_id'           => $appointment_id,
            'payment_method_id'        => $method_id,
            'amount'                   => $amount,
            'commission_percent_snapshot'=> $percent,
            'commission_amount'        => $commission,
            'notes'                    => $notes,
            'paid_at'                  => date('Y-m-d H:i:s'),
            'created_by'               => $user_id,
        ));

        // El turno queda atendido: el cobro es la confirmación de la atención.
        $this->db->where('id', $appointment_id)->update('appointments', array(
            'status'     => 'attended',
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return array(FALSE, 'No se pudo registrar el cobro.');
        }

        $this->db->trans_commit();

        return array(TRUE, 'Cobro registrado e ingreso generado.');
    }

    /**
     * ¿La forma de pago existe y está activa?
     */
    private function active_method($id)
    {
        return $id > 0 && $this->db->where('id', $id)
            ->where('active', 1)
            ->count_all_results('payment_methods') > 0;
    }

    /**
     * Corrige un cobro existente (solo Encargado).
     *
     * La comisión se recalcula con el porcentaje que quedó congelado
     * en el cobro, no con el porcentaje actual del peluquero.
     */
    public function update_payment($id, $amount, $method_id, $notes)
    {
        $payment = $this->find($id);

        // Un cobro anulado no se corrige: primero hay que restaurarlo.
        if (!$payment || $payment['voided_at'] || !$this->active_method($method_id)) {
            return FALSE;
        }

        $commission = round((float) $amount * (float) $payment['commission_percent_snapshot'] / 100, 2);

        return $this->db->where('id', $id)->update('payments', array(
            'amount'          => $amount,
            'payment_method_id'=> $method_id,
            'commission_amount'=> $commission,
            'notes'           => $notes,
            'updated_at'      => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * Total cobrado en un período, sin cobros anulados.
     */
    public function total_between($from, $to)
    {
        $row = $this->db->select_sum('amount', 'total')
            ->where('voided_at IS NULL', NULL, FALSE)
            ->where('paid_at >=', $from . ' 00:00:00')
            ->where('paid_at <=', $to . ' 23:59:59')
            ->get('payments')
            ->row_array();

        return (float) ($row['total'] ?: 0);
    }

    /**
     * Total de comisiones del período, sin cobros anulados.
     */
    public function commissions_between($from, $to)
    {
        $row = $this->db->select_sum('commission_amount', 'total')
            ->where('voided_at IS NULL', NULL, FALSE)
            ->where('paid_at >=', $from . ' 00:00:00')
            ->where('paid_at <=', $to . ' 23:59:59')
            ->get('payments')
            ->row_array();

        return (float) ($row['total'] ?: 0);
    }

    /**
     * Liquidación por peluquero: cuántos cobros hizo, producción y a pagar.
     */
    public function liquidation_between($from, $to)
    {
        return $this->db->select('
                b.id,
                b.full_name,
                COUNT(p.id) attentions,
                SUM(p.amount) production,
                SUM(p.commission_amount) payout')
            ->from('payments p')
            ->join('appointments a', 'a.id = p.appointment_id')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('p.voided_at IS NULL', NULL, FALSE)
            ->where('p.paid_at >=', $from . ' 00:00:00')
            ->where('p.paid_at <=', $to . ' 23:59:59')
            ->group_by(array('b.id', 'b.full_name'))
            ->order_by('b.full_name')
            ->get()
            ->result_array();
    }

    /**
     * Consulta base de los listados de cobros: une turno, cliente, peluquero
     * y forma de pago, y acota el período.
     *
     * La usan tanto el listado como su conteo para la paginación.
     */
    private function consulta_cobros($from, $to)
    {
        $this->db->select("
                p.*,
                pm.name payment_method,
                CONCAT(c.first_name, ' ', c.last_name) client_name,
                b.full_name barber_name", FALSE)
            ->from('payments p')
            ->join('payment_methods pm', 'pm.id = p.payment_method_id')
            ->join('appointments a', 'a.id = p.appointment_id')
            ->join('clients c', 'c.id = a.client_id')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('p.paid_at >=', $from . ' 00:00:00')
            ->where('p.paid_at <=', $to . ' 23:59:59');
    }

    /**
     * Cobros de un período, con los datos del turno y del cliente.
     *
     * @param string $from     fecha inicial
     * @param string $to       fecha final
     * @param string $q        texto para filtrar por cliente, peluquero o forma de pago
     * @param bool   $archived true = anulados; false = vigentes
     * @param int    $limite   registros por página (0 = todos)
     * @param int    $offset   cuántos registros se saltean
     */
    public function between($from, $to, $q = '', $archived = FALSE, $limite = 0, $offset = 0)
    {
        $this->consulta_cobros($from, $to);

        $this->filter_list($q, $archived);

        if ($limite > 0) {
            $this->db->limit($limite, $offset);
        }

        return $this->db->order_by('p.paid_at', 'DESC')->get()->result_array();
    }

    /**
     * Cuántos cobros hay con los mismos filtros del listado.
     */
    public function count_between($from, $to, $q = '', $archived = FALSE)
    {
        $this->consulta_cobros($from, $to);

        $this->filter_list($q, $archived);

        return $this->db->count_all_results();
    }
}
