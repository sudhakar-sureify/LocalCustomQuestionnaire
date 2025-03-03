<?php
require_once APPPATH . 'libraries/Mras/MrasConverter.php';
require_once APPPATH . 'libraries/Mras/QuestionnaireMras.php';
class Mras {
    public $method = null;
    public $question_id = null;
    public $url = null;
    public $case_id = null;
    public $payload = null;
    public $questionnaire = null;
    public function __construct($payload) {
        $this->CI =& get_instance();
        $this->questionnaire = new QuestionnaireMras();
        $this->converter = new MrasConverter($payload['case_id']);
        $this->method = $payload['method'];
        $this->question_id = $payload['question_id'];
        $this->url = $payload['url'];
        $this->case_id = $payload['case_id'];
        $this->payload = $payload['payload'];
        $this->function = $payload['function'];
        $this->case_data = $payload['case_data'];
        $this->provided_data = $payload['provided_data'];
        $this->child_question = $payload['cq'];
        $this->question = $payload['ques'];
    }
    public function execute()
    {
        switch($this->function){
            case 'convert':
                $result = $this->converter->_convert($this->case_data,[],$this->provided_data,[]);
                break;
            case 'createcase':
                $result = $this->questionnaire->createcase($this->payload);
                break;
            case 'disclosure':
                $result = $this->questionnaire->getDisclosure($this->question_id,$this->payload,$this->url,$this->method);
                break;
            case 'convert_childquestions':
                $result = $this->converter->convertChildQuestion($this->child_question,$this->question,$this->provided_data);
                break;
            case 'prepare_list_type_question':
                $result = $this->converter->prepareListTypeQuestion($this->child_question,$this->question,$this->provided_data);
                break;
            default:
                $result = [];
        }
        return $result;
    }

}
