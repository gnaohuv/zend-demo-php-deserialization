<?php

class RceController extends Zend_Controller_Action
{
    // Ham nay duoc goi khi truy cap vao /rce
    public function indexAction()
    {
        // Lay du lieu POST tu form gui len
        $payload = $this->getRequest()->getPost('notes');
        $this->view->result = '';

        // Neu co du lieu payload thi xu ly
        if (!empty($payload)) {
            // Giai ma chuoi base64
            $decoded = base64_decode($payload);

            // Bat dau ghi ket qua ra bo dem (de khong bi in truc tiep ra man hinh)
            ob_start();

            // Goi unserialize, neu payload hop le thi se thuc thi o day
            @unserialize($decoded);

            // Lay ket qua da in ra va gan vao bien de hien thi
            $this->view->result = ob_get_clean();
        }
    }
}

