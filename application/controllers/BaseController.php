<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BaseController extends CI_Controller {

    public function thirdPartyMedicalQuestionnaire()
    {
        $type = $this->input->get('type');
        if (empty($type)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
        $payload = json_decode(file_get_contents('php://input'), true);
        if (empty($payload)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'invalid payload']));
        }
        $this->load->helper('converter');
        $questionnaire = get_medical_questinnaire($type,$payload);
        if (!$questionnaire) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
        $result = $questionnaire->execute();
        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }
}
