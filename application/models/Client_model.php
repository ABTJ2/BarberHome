<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modelo de clientes.
 */
class Client_model extends CI_Model {

    /**
     * Filtros del listado de clientes.
     *
     * Viven en un solo lugar porque los usan tanto el listado como el
     * conteo de registros para la paginación.
     *
     * @param string $q        texto para filtrar por nombre, apellido o teléfono
     * @param bool   $archived true = eliminados; false = vigentes
     */
    private function filtros($q, $archived)
    {
        // Borrado lógico: los eliminados se conservan para el historial.
        $this->db->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL', NULL, FALSE);

        if ($q !== '') {
            $this->db->group_start()
                ->like('first_name', $q)
                ->or_like('last_name', $q)
                ->or_like('phone', $q)
                ->group_end();
        }
    }

    /**
     * Listado de clientes.
     *
     * @param string $q        texto para filtrar por nombre, apellido o teléfono
     * @param bool   $archived true = eliminados; false = vigentes
     * @param int    $limite   registros por página (0 = todos)
     * @param int    $offset   cuántos registros se saltean
     */
    public function search($q = '', $archived = FALSE, $limite = 0, $offset = 0)
    {
        $this->db->from('clients');

        $this->filtros($q, $archived);

        if ($limite > 0) {
            $this->db->limit($limite, $offset);
        }

        return $this->db->order_by('last_name')->order_by('first_name')->get()->result_array();
    }

    /**
     * Cuántos clientes hay con los mismos filtros del listado.
     *
     * @param string $q        texto para filtrar por nombre, apellido o teléfono
     * @param bool   $archived true = eliminados; false = vigentes
     */
    public function count_search($q = '', $archived = FALSE)
    {
        $this->db->from('clients');

        $this->filtros($q, $archived);

        return $this->db->count_all_results();
    }

    /**
     * Un cliente por su id, o NULL si no existe o fue eliminado.
     */
    public function find($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('clients')
            ->row_array();
    }

    /**
     * Un cliente eliminado por su id, o NULL si no está en la papelera.
     *
     * Se usa al restaurarlo: hacen falta sus datos para comprobar que no
     * quede duplicado con otro cliente ya activo.
     */
    public function find_archived($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->get('clients')
            ->row_array();
    }

    /**
     * Busca otro cliente activo con el mismo nombre, apellido y teléfono.
     *
     * Sirve para avisar que el cliente ya existe en lugar de duplicarlo.
     *
     * @param mixed $exclude_id cliente a ignorar (en edición)
     */
    public function duplicate($first, $last, $phone, $exclude_id = NULL)
    {
        $this->db->where('first_name', $first)
            ->where('last_name', $last)
            ->where('phone', $phone)
            ->where('deleted_at IS NULL', NULL, FALSE);

        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }

        return $this->db->get('clients')->row_array();
    }

    /**
     * Alta de cliente. Devuelve el id generado.
     */
    public function create($data)
    {
        $this->db->insert('clients', $data);

        return $this->db->insert_id();
    }

    /**
     * Actualización de cliente.
     */
    public function update_client($id, $data)
    {
        return $this->db->where('id', $id)->update('clients', $data);
    }

    /**
     * Baja lógica: sale de las listas, pero sus turnos se conservan.
     */
    public function archive($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('clients', array('deleted_at' => date('Y-m-d H:i:s')));
    }

    /**
     * Restaura un cliente eliminado.
     */
    public function restore($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('clients', array('deleted_at' => NULL));
    }

    /**
     * ¿El cliente tiene turnos futuros reservados?
     *
     * Si los tiene, hay que cancelarlos antes de eliminar al cliente.
     */
    public function has_future_reservations($id)
    {
        return $this->db->where('client_id', $id)
            ->where('status', 'reserved')
            ->where('start_at >=', date('Y-m-d H:i:s'))
            ->count_all_results('appointments') > 0;
    }

    /**
     * Historial de turnos del cliente, del más nuevo al más viejo.
     *
     * Incluye todos los estados (cancelado, ausente, etc.): es el
     * historial completo de la relación.
     */
    public function history($client_id)
    {
        return $this->db->select("
                a.*,
                b.full_name barber_name,
                (SELECT GROUP_CONCAT(s2.name ORDER BY s2.name SEPARATOR ', ')
                   FROM appointment_services aps2
                   JOIN services s2 ON s2.id = aps2.service_id
                  WHERE aps2.appointment_id = a.id) services", FALSE)
            ->from('appointments a')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('a.client_id', $client_id)
            ->order_by('a.start_at', 'DESC')
            ->get()
            ->result_array();
    }
}
