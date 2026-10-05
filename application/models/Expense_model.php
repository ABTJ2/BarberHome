<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modelo de egresos del local y sus categorías.
 *
 * Un egreso anulado se conserva (voided_at) pero deja de sumar.
 */
class Expense_model extends CI_Model {

    /**
     * Categorías activas (las que se pueden elegir al registrar un egreso).
     */
    public function categories()
    {
        return $this->db->where('active', 1)
            ->order_by('name')
            ->get('expense_categories')
            ->result_array();
    }

    /**
     * ¿La categoría existe y está activa?
     */
    public function valid_category($id)
    {
        return $id > 0 && $this->db->where('id', $id)
            ->where('active', 1)
            ->count_all_results('expense_categories') > 0;
    }

    /**
     * Consulta base del listado de egresos: une categoría y autor, acota el
     * período y aplica el filtro de texto.
     *
     * La usan tanto el listado como su conteo para la paginación.
     *
     * @param string $from     fecha inicial
     * @param string $to       fecha final
     * @param string $q        texto para filtrar por concepto o categoría
     * @param bool   $archived true = anulados; false = vigentes
     */
    private function consulta_egresos($from, $to, $q, $archived)
    {
        $this->db->select('e.*, ec.name category_name, u.full_name created_by_name')
            ->from('expenses e')
            ->join('expense_categories ec', 'ec.id = e.category_id')
            ->join('users u', 'u.id = e.created_by')
            ->where($archived ? 'e.voided_at IS NOT NULL' : 'e.voided_at IS NULL', NULL, FALSE)
            ->where('e.expense_date >=', $from)
            ->where('e.expense_date <=', $to);

        if ($q !== '') {
            $this->db->group_start()
                ->like('e.concept', $q)
                ->or_like('ec.name', $q)
                ->group_end();
        }
    }

    /**
     * Egresos de un período.
     *
     * @param string $from     fecha inicial
     * @param string $to       fecha final
     * @param string $q        texto para filtrar por concepto o categoría
     * @param bool   $archived true = anulados; false = vigentes
     * @param int    $limite   registros por página (0 = todos)
     * @param int    $offset   cuántos registros se saltean
     */
    public function all_between($from, $to, $q = '', $archived = FALSE, $limite = 0, $offset = 0)
    {
        $this->consulta_egresos($from, $to, $q, $archived);

        if ($limite > 0) {
            $this->db->limit($limite, $offset);
        }

        return $this->db->order_by('e.expense_date', 'DESC')
            ->order_by('e.id', 'DESC')
            ->get()
            ->result_array();
    }

    /**
     * Cuántos egresos hay con los mismos filtros del listado.
     */
    public function count_all_between($from, $to, $q = '', $archived = FALSE)
    {
        $this->consulta_egresos($from, $to, $q, $archived);

        return $this->db->count_all_results();
    }

    /**
     * Un egreso por su id.
     */
    public function find($id)
    {
        return $this->db->where('id', $id)->get('expenses')->row_array();
    }

    /**
     * Alta de egreso. Devuelve el id generado.
     */
    public function create($data)
    {
        $this->db->insert('expenses', $data);

        return $this->db->insert_id();
    }

    /**
     * Actualización de egreso. Solo mientras no esté anulado.
     */
    public function update_expense($id, $data)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NULL', NULL, FALSE)
            ->update('expenses', $data);
    }

    /**
     * Anula un egreso: conserva la fila y su autor, pero no suma.
     */
    public function void_expense($id, $user_id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NULL', NULL, FALSE)
            ->update('expenses', array(
                'voided_at' => date('Y-m-d H:i:s'),
                'voided_by' => $user_id,
            ));
    }

    /**
     * Restaura un egreso anulado.
     */
    public function restore_expense($id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NOT NULL', NULL, FALSE)
            ->update('expenses', array(
                'voided_at' => NULL,
                'voided_by' => NULL,
            ));
    }

    /**
     * Total de egresos del período, sin anulados.
     */
    public function total_between($from, $to)
    {
        $row = $this->db->select_sum('amount', 'total')
            ->where('voided_at IS NULL', NULL, FALSE)
            ->where('expense_date >=', $from)
            ->where('expense_date <=', $to)
            ->get('expenses')
            ->row_array();

        return (float) ($row['total'] ?: 0);
    }
}
