<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BaseController extends CI_Controller {
    public function createcase() {
        $type = $this->input->get('type');
        if (empty($type)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
        $payload = json_decode(file_get_contents('php://input'), true);
        file_put_contents(APPPATH . 'logs/createcase.json', json_encode($payload, JSON_PRETTY_PRINT));
        // Validate the payload
        if (empty($payload)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'invalid payload']));
        }
        $this->load->helper('converter');
        $questionnaire = get_questionnaire($type);
        if (!$questionnaire) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
        $result =$questionnaire->createCase($payload);
        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }
    public function getDisclosures() {
        $type = $this->input->get('type');
        if (empty($type)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
        $payload = json_decode(file_get_contents('php://input'), true);
        file_put_contents(APPPATH . 'logs/disclosure.json', json_encode($payload, JSON_PRETTY_PRINT));
        // Validate the payload
        $this->load->helper('converter');
        $questionnaire = get_questionnaire($type);
        if (!$questionnaire) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
        $result =$questionnaire->getDisclosure($payload['question_id'],$payload['payload'],$payload['url'],$payload['method']);
        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }
    public function convert() {
        
        $type = $this->input->get('type');
    
        if (empty($type)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
    
        $payload = json_decode(file_get_contents('php://input'), true);
        file_put_contents(APPPATH . 'logs/convert_payload.json', json_encode($payload, JSON_PRETTY_PRINT));
        // Validate the payload
        if (empty($payload) || !isset($payload['case_data']) || !isset($payload['provided_data'])) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'invalid payload']));
        }
    
        $this->load->helper('converter');
        $converter = get_converter($type);
    
        if (!$converter) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'not a valid type']));
        }
    
        $result = $converter->_convert($payload['case_data'], [], $payload['provided_data'], []);
        
        $this->output->set_status_header(200)->set_content_type('application/json')->set_output(json_encode($result, JSON_PRETTY_PRINT));  
        return;
    }
}
