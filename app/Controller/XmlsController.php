<?php

class XmlsController extends AppController {
    public $helpers = array('Html', 'Form', 'Session');
    public $components = array('Session');

    public function index() {
        $this->set('xmls', $this->Xml->find('all'));
    }

    public function view($id = null) {
        if (!$id) {
            throw new NotFoundException(__('Invalid xml'));
        }

        $xml = $this->Xml->findById($id);
        if (!$xml) {
            throw new NotFoundException(__('Invalid xml'));
        }
        $this->set('xml', $xml);
    }

    public function add() {
        if ($this->request->is('xml')) {
            $this->Xml->create();
            if ($this->Xml->save($this->request->data)) {
                $this->Session->setFlash(__('Your xml has been saved.'));
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash(__('Unable to add your xml.'));
        }
    }

    public function edit($id = null) {
        if (!$id) {
            throw new NotFoundException(__('Invalid xml'));
        }

        $xml = $this->Xml->findById($id);
        if (!$xml) {
            throw new NotFoundException(__('Invalid xml'));
        }

        if ($this->request->is(array('xml', 'put'))) {
            $this->Xml->id = $id;
            if ($this->Xml->save($this->request->data)) {
                $this->Session->setFlash(__('Your xml has been updated.'));
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash(__('Unable to update your xml.'));
        }

        if (!$this->request->data) {
            $this->request->data = $xml;
        }
    }

    public function delete($id) {
        if ($this->request->is('get')) {
            throw new MethodNotAllowedException();
        }
        if ($this->Xml->delete($id)) {
            $this->Session->setFlash(
                __('Le xml avec id : %s a été supprimé.', h($id))
            );
            return $this->redirect(array('action' => 'index'));
        }
    }

}