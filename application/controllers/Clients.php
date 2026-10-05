<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador de clientes.
 *
 * Alta, edición, baja lógica y ficha con historial de turnos.
 * Las bajas son lógicas: el cliente sale de las listas pero sus
 * turnos y cobros se conservan.
 */
class Clients extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->require_login();

        $this->load->model('Client_model');
    }

    /**
     * Listado con buscador, filtro de eliminados y paginación.
     */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        $paginacion = $this->paginacion($this->Client_model->count_search($q, $archived));

        $this->render('clients/index', array(
            'clients'    => $this->Client_model->search(
                $q,
                $archived,
                $paginacion['limit'],
                $paginacion['offset']
            ),
            'q'          => $q,
            'archived'   => $archived,
            'paginacion' => $paginacion,
            'ruta_paginacion' => 'clientes',
            // Los filtros se repiten en los enlaces de paginación.
            'filtros_paginacion' => array(
                'q'        => $q,
                'archived' => $archived ? '1' : '',
            ),
        ), 'Clientes');
    }

    public function create()
    {
        $this->render('clients/form', array(
            'client' => NULL,
            'errors' => array(),
        ), 'Nuevo cliente');
    }

    /**
     * Validación del formulario.
     *
     * @param mixed $exclude_id id a ignorar al buscar duplicados (en edición)
     */
    private function validate($exclude_id = NULL)
    {
        $errors = array();

        $first = trim($this->posted('first_name', ''));
        $last  = trim($this->posted('last_name', ''));
        $phone = trim($this->posted('phone', ''));

        if ($first === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        if ($last === '') {
            $errors[] = 'El apellido es obligatorio.';
        }

        if ($phone === '') {
            $errors[] = 'El teléfono es obligatorio.';
        }

        // Mismo nombre, apellido y teléfono: se avisa para reutilizar
        // el cliente existente en lugar de duplicarlo.
        if (!$errors && $this->Client_model->duplicate($first, $last, $phone, $exclude_id)) {
            $errors[] = 'Ya existe un cliente con estos datos. Buscalo para reutilizarlo.';
        }

        return $errors;
    }

    /**
     * Datos del cliente que van a la tabla clients.
     */
    private function data()
    {
        return array(
            'first_name' => trim($this->posted('first_name', '')),
            'last_name'  => trim($this->posted('last_name', '')),
            'phone'      => trim($this->posted('phone', '')),
            'notes'      => trim($this->posted('notes', '')),
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    /**
     * Alta de cliente.
     */
    public function store()
    {
        $this->require_post();

        $errors = $this->validate();

        if ($errors) {
            // Se vuelve al formulario con lo que el usuario había tipeado.
            $this->render('clients/form', array(
                'client' => $this->input->post(NULL, TRUE),
                'errors' => $errors,
            ), 'Nuevo cliente');
            return;
        }

        $data = $this->data();
        $data['created_at'] = date('Y-m-d H:i:s');

        $id = $this->Client_model->create($data);

        $this->session->set_flashdata('success', 'Cliente registrado.');
        redirect('clientes/ver/' . $id);
    }

    public function edit($id)
    {
        $client = $this->Client_model->find($id);

        if (!$client) {
            show_404();
        }

        $this->render('clients/form', array(
            'client' => $client,
            'errors' => array(),
        ), 'Editar cliente');
    }

    /**
     * Edición de cliente.
     */
    public function update($id)
    {
        $this->require_post();

        $client = $this->Client_model->find($id);

        if (!$client) {
            show_404();
        }

        $errors = $this->validate($id);

        if ($errors) {
            $this->render('clients/form', array(
                'client'=> array_merge($client, $this->input->post(NULL, TRUE)),
                'errors'=> $errors,
            ), 'Editar cliente');
            return;
        }

        $this->Client_model->update_client($id, $this->data());

        $this->session->set_flashdata('success', 'Cliente actualizado.');
        redirect('clientes/ver/' . $id);
    }

    /**
     * Ficha del cliente con su historial de turnos.
     */
    public function show($id)
    {
        $client = $this->Client_model->find($id);

        if (!$client) {
            show_404();
        }

        $this->render('clients/show', array(
            'client'  => $client,
            'history' => $this->Client_model->history($id),
        ), 'Cliente');
    }

    /**
     * Baja lógica de cliente.
     */
    public function delete($id)
    {
        $this->require_post();

        if (!$this->Client_model->find($id)) {
            show_404();
        }

        // Con turnos futuros sin resolver no se elimina: primero se cancelan.
        if ($this->Client_model->has_future_reservations($id)) {
            $this->session->set_flashdata('error', 'Este cliente tiene turnos futuros. Cancelalos antes de eliminarlo.');
        } else {
            $this->Client_model->archive($id);
            $this->session->set_flashdata('success', 'Cliente eliminado de la lista. Su historial se conservó.');
        }

        redirect('clientes');
    }

    /**
     * Restaura un cliente eliminado.
     */
    public function restore($id)
    {
        $this->require_post();

        // Solo tiene sentido restaurar si estaba eliminado.
        $client = $this->Client_model->find_archived($id);

        if (!$client) {
            show_404();
        }

        // Si ya hay un cliente activo con los mismos datos no se puede
        // restaurar: quedaría duplicado.
        if ($this->Client_model->duplicate($client['first_name'], $client['last_name'], $client['phone'])) {
            $this->session->set_flashdata('error', 'Ya existe un cliente activo con esos datos.');
        } else {
            $this->Client_model->restore($id);
            $this->session->set_flashdata('success', 'Cliente restaurado.');
        }

        redirect('clientes?archived=1');
    }
}
