<?php
require_once APPPATH . 'controllers/BaseController.php';
require_once APPPATH . 'libraries/Mras/QuestionnaireMras.php';
class MrasConverter {

    private $dataTypeDisplayTypeMap = array(
        'DATE_PARTIAL' => 'date',
        'DATE' => 'text',
        'STRING_TEXTBOX_SINGLE' => 'text',
        //'STRING_TEXTBOX_MULTI'              =>  'text',
        'INTEGER_TEXTBOX' => 'number',
        'DROPDOWN' => 'dropdown',
        'MULTISELECT_CHECKBOXES_VERTICAL' => 'checkbox',
        'MULTISELECT_CHECKBOXES_HORIZONTAL' => 'checkbox',
        'MULTISELECT_LABELLED_BUTTONS' => 'checkbox',
        'STRING_TEXTBOX_MULTI' => 'text_area',
        'STRING_DICTIONARY' => 'singleselection',
        'DROPDOWN_RADIO_BUTTONS_HORIZONTAL' => 'radio',
        'DROPDOWN_RADIO_BUTTONS_VERTICAL' => 'radio',
        'DROPDOWN_LABELLED_BUTTONS' => 'radio_button',
        'DROPDOWN_COMBOBOX' => 'dropdown',
        'DROPDOWN_ICONIC_BUTTONS' => 'radio_button',
        'MULTISELECT_DROPDOWN' => 'dropdown',
        'INTEGER_SPINNER' => 'number',
        'DECIMAL_TEXTBOX' => 'number',
        'INTEGER_SLIDER' => 'slider',
        'YES_NO' => 'radio_button',
        'BOOLEAN' => 'radio_button',
        'HEADING' => 'label',
        'CASE_DATA' => 'text',
        'SELECTION' => 'dropdown',
        'SEARCH' => 'dropdown_search',
        'TEXT' => 'text',
        'NUMBER'=>'number',
        'PICKLIST' => 'checkbox',
        'ENTER_DETAILS' => 'dropdown',
        'PICK_LIST' => 'checkbox',
    );

    private $orientationMap = array(
        'DROPDOWN_ICONIC_BUTTONS' => 'rows',
        'DROPDOWN_LABELLED_BUTTONS' => 'rows',
        'MULTISELECT_CHECKBOXES_HORIZONTAL' => 'rows',
        'DROPDOWN_RADIO_BUTTONS_HORIZONTAL' => 'rows',
        'MULTISELECT_CHECKBOXES_VERTICAL' => 'columns',
        'DROPDOWN_RADIO_BUTTONS_VERTICAL' => 'columns',
    );
    private $sequence_num=100;
    private $questionTypeMap = array(
        'YES_NO' => 'singleselection',
        'BOOLEAN' => 'singleselection',
        'PICKLIST' => 'multiselection',
        'radio' => 'singleselection',
        'default_radio' => 'singleselection',
        'radio_button' => 'singleselection',
        'checkbox' => 'multiselection',
        'PICK_LIST' => 'multiselection',
        'MULTISELECT_DROPDOWN' => 'multiselection',
        'STRING_DICTIONARY' => 'autocomplete-text',
        'INTEGER_SLIDER' => 'number',
        'HEADING' => 'label',
        'CASE_DATA' => 'text',
        'SELECTION' => 'singleselection',
        'SEARCH' => 'singleselection',
        'TEXT' => 'text',
        'NUMBER'=>'text',
        'DATE' => 'date',
        'ENTER_DETAILS' => 'singleselection',
    );

    private $regexMap = array(
        //'STRING_TEXTBOX_MULTI'  =>  "^(?! )[A-Za-z\\d'\\-\\. ]{1,4000}$",
        'STRING_TEXTBOX_MULTI' => "^(?!\s*$).{1,4000}",
        'STRING_TEXTBOX_SINGLE' => "^(?!\s*$).{1,40}$",
        //'DECIMAL_TEXTBOX' => '^(\\d*\\.)?\\d{1,5}$',
        //'INTEGER_TEXTBOX' => '^[0-9]{1,9}$',
    );

    private $autoFmtMap = array(
        'DATE' => "MM/DD/YYYY",
        'DATE_PARTIAL' => "MM/YYYY",
        'YEAR' => "YYYY"
    );

    private $labelImage = array(
        'SMOKER' => 'https://ya-webdesign.com/transparent250_/smoke-icon-png.png',
        'NON_SMOKER' => 'https://ya-webdesign.com/transparent250_/no-smoking-icon-png-8.png',

    );

    private $section_config = NULL;
    private $redis;
    private $api_id=null;
    private $application_section=null;
    private $questionnaire_mras;
    private $case_id = null;
    public function __construct($case_id=null,int $id = 4, $application_section = [] ) {  

        $this->CI = &get_instance(); // Get CI instance
        $this->CI->load->library('Mras/QuestionnaireMras'); // Load the questionnaire_mras library
        $this->case_id=$case_id;
        $this->questionnaire_mras = $this->CI->questionnairemras; 
        $this->api_id = $id;
        $this->application_section = $application_section;
        $this->section_config = \json_decode("", true); ////
        ////$this->redis = &get_instance()->cache->redis;
    }
    public function _convert($data = [], $error_map = [], $provided = [], $displayed = []) {
        $breadcrumbs = [];
        $updated_sections = $data['pages'];
        $this->_convertFormList($updated_sections, $breadcrumbs);
        $updated_sections = array_values($updated_sections ?? []);
        $parent_questions = [];
        $type="breadcrumb";
        $row_align = false;
        $questions = $this->_convertResultingForm($updated_sections, "", "", $parent_questions, $error_map, $provided, $type, $breadcrumbs, $row_align);
        $breadcrumbs = array_values($breadcrumbs);
        unset($questions['hidden_flag']);
        return [
            "breadcrumbs" => $breadcrumbs,
            "data" => [
                "questionnarie" => [
                    "questions" => $questions,
                ],
            ],
        ];
    }

    /* FormList is equivalent to breadcrumbs in sureify format */
    private function _convertFormList(&$form_list, &$result) {
        foreach ($form_list as $key => $b) {
            if($b['text']=='Clarifying Questions')
            {
                unset($form_list[$key]);
                continue;
            }
            $breadcrumb_id = preg_replace('/\s+/', '_', $b['code']);
            $result[] = [
                "breadcrumb_id" => $breadcrumb_id,
                "section_id" => 4,////$this->application_section['id'],
                "title" => $b['text'],
                "state" => $b['current'] ?? null == true ? 'active' : ($b['complete'] ?? null ? 'completed' : 'incomplete'),
                "total_questions" => $b['totalQuestionsCount'] ?? null,
                "answered_questions" => $b['answeredQuestionsCount']?? null,
                "type" => "questions",
                "source_id" => 1,
                "childBreadcrumbs" => [],
                "image_url" => "https://acqcdn.s3.amazonaws.com/brighthouse/breadcrumb/Stethoscope.png",
                "active_img_url" => "https://acqcdn.s3.amazonaws.com/brighthouse/breadcrumb/Stethoscope.png",
                "inactive_img_url" => "https://acqcdn.s3.amazonaws.com/brighthouse/breadcrumb/Stethoscope.png"
            ];
            if (!empty($b['childNodes']?? null)) {
                //Need to be uncommented once front end implements child breadcrumbs
                //$this->_convertFormList($b['childNodes'], $result['childBreadcrumbs']);
                //Need to be removed
                $this->_convertFormList($b['childNodes']?? null, $result);
            }
        }
    }

    

    private function _convertResultingForm($input, $parent_qid, $parent_lid, &$parent_questions, $error_map , $provided , $type, &$breadcrumbs, &$row_align) {
        $result = [];
        $is_hidden = true;
        foreach ($input as $i => $q) {
            if($q['text']=='Clarifying Questions' ) continue;
            $question = $this->convert($q, $parent_qid, $parent_lid, $parent_questions, $error_map, $provided,$type);
            $this->updateQuestionAndDisplayType($question,$type);
            $is_hidden = ($is_hidden && $question['is_hidden']);
            switch($type)
            {
                case 'breadcrumb' : 
                    $group_questions = $this->_convertResultingForm($q['sections'], $question['question_id'], "", $parent_questions, $error_map, $provided,$type='group', $breadcrumbs, $row_align);
                    if(!$group_questions['hidden_flag'])
                    {
                        unset($group_questions['hidden_flag']);
                        if(!empty($group_questions))
                        {
                            $question['questions'] = $group_questions;
                        }
                        else
                        {
                            unset($breadcrumbs[$i]);
                            $question = [];
                        }
                    }
                    else
                    {
                        unset($breadcrumbs[$i]);
                        $question = [];
                    }
                    $type = 'breadcrumb';
                    break;
                case 'group':
                    $base_questions = $this->_convertResultingForm($q['baseQuestions'], $question['question_id'], "", $parent_questions, $error_map, $provided,$type='question', $breadcrumbs, $row_align);
                    if($row_align)
                    {
                        $question['properties']['orientation']['type'] = 'rows';
                        $question['properties']['orientation']['value'] = 2;
                        $row_align = false;
                    }
                    if(!$base_questions['hidden_flag'])
                    {
                        unset($base_questions['hidden_flag']);
                        $question['questions'] = $base_questions;
                    }
                    else
                    {
                        $is_hidden = true;
                        $question = [];
                    }
                    $type = 'group';
                    break;
                case 'question':
                    if(empty($question['questions']))
                        $question['questions']=[];
                    if(isset($question['row_align']) && $question['row_align'])
                    {
                        $row_align = true;
                    }
                    break;
            }
            if($question['is_height_question'] || $question['question_id'] == 'SCNY_PT2_HEIGHT' || $question['question_id'] == 'PT2_HEIGHT' || $question['question_id'] == 'REIN_PT3_HEIGHT' || $question['question_id'] == 'PT3_HEIGHT_CA')
            {
                $question_dummy = $question;
                $question['question_id'] = $question['question_id'].'dummy';
                $question_dummy['questions'] = [];
                array_push($result, $question_dummy);
                $question['child_questions'] = false;
                $question['is_hidden'] = true;
            }
            if(!empty($question))
                array_push($result, $question);
        }
        if(!empty($question))
            $result['hidden_flag'] = $is_hidden;
        return $result;
    }
    public function isNYSectionReflexive($parent_qid)
    {
        $sections = ['28','28_2','28_1','31','13'];
        if(!in_array($parent_qid, $sections))
        {
            return true;
        }
        return false;

    }
    /** setting basic details  from mras question to sureify format  */
    public function convert($cq, $parent_qid, $parent_lid, &$parent_questions, $error_map, $provided, $type) {
        $question = array();

        $this->_defaults($question, $parent_qid, $parent_lid, $cq);
        $this->_boolVals($question, $cq);
        if($type=='question')
        {
            $questionType = $cq['questionType'];
            $question['question_type']=$this->questionTypeMap[$questionType];
            $question['display_type']=$this->dataTypeDisplayTypeMap[$questionType];
            if($provided['BH_OwnerResidenceState'] != 'StateNY' || $this->isNYSectionReflexive(($parent_qid)))
            {
                $question['child_questions']=true;
            }
            $question['mras_question_type']='Base';

            if($questionType=='STANDARD' || $questionType=='CONDITIONAL')
            {
                if($this->isNYSectionReflexive($parent_qid))
                {
                    $this->_displayType($question, $cq, $provided, $parent_qid, true);
                }
                else
                {
                    $this->_displayType($question, $cq, $provided, $parent_qid);
                }
            }
            if($questionType == 'CASE_DATA' && $cq['caseDataQuestionMeta']['dataType'] == 'SELECTION' && isset($cq['caseDataQuestionMeta']['list']['listName']))
            {
                $question['question_type'] = 'singleselection';
                $question['display_type'] = 'dropdown';
                if($provided['BH_OwnerResidenceState'] != 'StateNY' || $this->isNYSectionReflexive($parent_qid))
                {
                    $question['child_questions'] = true;
                }
                $case_id = $this->case_id;////RedisUtility::getQuoteInformation($_POST['uid'])['external_uuid'];
                $list_name= $cq['caseDataQuestionMeta']['list']['listName'];
                $url = "";////BRIGHTHOUSE_URL.$case_id. '/listDefinitions'.'/' ;
                $response_options = [];////$this->redis->get($case_id.":".$list_name);
                if(empty($response_options))
                {
                    $countries = $this->questionnaire_mras->getDisclosure($list_name,'',$url,'GET');
                }
                foreach($countries['listItems'] as $country)
                {
                    if($country['code'] != 'OLI_UNKNOWN')
                    {
                        $response_option['id'] = $country['code'];
                        $response_option['label'] = $country['description'];
                        $response_options[] = $response_option;
                        $question['child_questions_on'][] = $response_option['id'];
                    }
                }
                if(!empty($countries))
                {
                    ////$this->redis->save($case_id.":".$list_name, $response_options);
                }
                else
                {
                    foreach($response_options as $response_option)
                        $question['child_questions_on'][] = $response_option['id'];
                }
                if(!isset($response_options['errors']))
                {
                    $question['response_options'] = $response_options;
                }

            }
        }
        if(isset($cq['caseDataQuestionMeta']['dataType' ]) and $cq['caseDataQuestionMeta']['dataType'] == 'NUMERIC')
        {
            $question['validations']['pattern']['error_message'] = "Please enter valid input";
            $question['display_type'] = 'number';
            $question['question_type'] = 'text';
            $question['validations']['pattern']['value'] = "/^[0-9]*$/";
            $question['validations']['pattern']['error_message'] = 'Please enter valid input';
        }
        if(isset($cq['caseDataQuestionMeta']['minimum']))
        {
            $minimum = (int)$cq['caseDataQuestionMeta']['minimum'];
            $maximum = (int)$cq['caseDataQuestionMeta']['maximum'];
            $question['validations']['max_value']['value'] = "".$maximum;
            $question['validations']['max_value']['error_message'] = "Max value is ".$maximum;
            $question['validations']['min_value']['value'] = "".$minimum;
            $question['validations']['min_value']['error_message'] = "Min value is ".$minimum;
            $question['validations']['max_value']['is_exact_message'] = true;
            $question['validations']['min_value']['is_exact_message'] = true;
            if($question['question_id'] == 'PT2_HEIGHT' || $question['question_id'] == 'REIN_PT3_HEIGHT' || $question['question_id'] == 'PT3_HEIGHT_CA'||$question['question_id'] == 'SCNY_PT2_HEIGHT' || strtolower((string)$cq['caseDataQuestionMeta']['unit']) == strtolower('IN'))
            {
                $minimum = (int)$cq['caseDataQuestionMeta']['minimum'];
                $minimum = $minimum/12;
                $maximum = (int)$cq['caseDataQuestionMeta']['maximum'];
                $maximum = $maximum/12;
                $height_feet = $question;
                $height_feet['question_id'] = 'BH_Height_Feet'.$question['question_id'];
                $height_feet['units_root_question_id'] = $question['question_id'];
                $height_feet['properties']['size']['value'] = 'S';
                $height_feet['properties']['inline']['value'] = true;
                $height_feet['properties']['mobileDisplay']['inline']['value'] = true;
                $height_feet['properties']['mobileDisplay']['category'] = 'Height';
                if($provided['BH_OwnerResidenceState'] != 'StateNY')
                {
                    $height_feet['child_questions'] = true;
                }
                $height_feet['validations']['max_value']['value'] = "".$maximum;
                $height_feet['validations']['max_value']['error_message'] = "Max Height Feet value is ".$maximum;
                $height_feet['validations']['min_value']['value'] = "".$minimum;
                $height_feet['validations']['min_value']['error_message'] = "Min Height Feet value is ".$minimum;
                $height_feet['validations']['required']['value'] = true;
                $height_feet['validations']['required']['error_message'] = "This is a required field.";
                $height_in = $height_feet;
                $height_in['question_id'] = 'BH_Height_In'.$question['question_id'];
                $height_in['units_root_question_id'] = $question['question_id'];
                unset($height_feet['question_text']);
                unset($height_in['question_text']);
                $height_feet['question_text'] = '(ft)';
                $height_in['question_text'] = '(in)';
                $height_in['validations']['max_value']['value'] = "11";
                $height_in['validations']['max_value']['error_message'] = "Max Height Inches value is 11";
                $height_in['validations']['min_value']['value'] = "0";
                $height_in['validations']['min_value']['error_message'] = "Min Height Inches value is 0";
                $email = $provided['BH_EmailAgent'];
                if(empty($email))
                {
                    $email = $provided['BH_Email'];
                }
                if(strpos(strtolower($email),EXCLUDING_EMAIL_PATTERN) != false)
                {
                    $height_feet['response'] = "5";
                    $height_in['response'] = "10";
                }
                $question['questions'][] = $height_feet;
                $question['questions'][] = $height_in;
                $question['display_type'] = 'label';
                $question['question_type'] = 'label';
                $question['unit_conversion_data']['feet'] = 0;
                $question['unit_conversion_data']['inches'] = 0;
                $question['row_align'] = true;
                $question['is_height_question'] = true;
            }
        }
        $question['mras_answer_type'] = $cq['questionType'] ?? null;
        if($type == 'breadcrumb')
        {
            $question["image_url"] = "https://acqcdn.s3.amazonaws.com/brighthouse/breadcrumb/Stethoscope.png";
            $question["active_img_url"] = "https://acqcdn.s3.amazonaws.com/brighthouse/breadcrumb/Stethoscope.png";
        }
        if($cq['disclosureSource'] == 'SEARCH')
        {
            
            $question = $this->prepareListTypeBaseQuestion($cq,$question);
            

        }
        $this->addValidationsToQuestion($question);
        return $question;
    }
    
    public function addValidationsToQuestion(&$question)
    {
        if($question['question_type'] != 'label')
        {
            $question['validations']['required']['value'] = true;
            $question['validations']['required']['error_message'] = "This is a required field.";
        }
        else
        {
            $question['question_status'] = 'valid';
        }
    }

    public function convertChildQuestion($cq,$ques,$provided,$is_original_question = false)
    {
        $question = array();
        $parent_qid=[];
        $parent_lid=[];
        $locator_map=[];
        $cq['visible']=true;
        $this->_defaults($question, $parent_qid, $parent_lid, $cq);
        $this->_boolVals($question, $cq);
        $questionType = $cq['answerType'];
        $question['display_type'] = $this->dataTypeDisplayTypeMap[$questionType];
        $question['question_type'] = $this->questionTypeMap[$questionType];
        if($question['question_type'] == 'text')
        {
            $question['question_type'] = 'text_area';
        }
        if($questionType == 'PICKLIST'|| $questionType == 'PICK_LIST' || $questionType=='SELECTION' || $questionType=='SEARCH' || $questionType=='BOOLEAN')
        {
            if($ques['child_questions'])
            {
                $this->_displayType($question, $cq,$provided, true);
            }
            else
            {
                $this->_displayType($question, $cq,$provided);
            }
        }
        if($questionType == 'DATE')
        {
            if($cq['dateGranularity'] == 'MONTH')
            {
                $question['validations']['format']['value'] = $this->autoFmtMap['DATE_PARTIAL'];
                $question['validations']['format']['error_message'] = 'Invalid Format';
                $question['validations']['placeholder_text']['value'] = $this->autoFmtMap['DATE_PARTIAL'];
                $question['validations']['placeholder_text']['error_message'] = '';
                $question['validations']['auto_format']['type'] = '';
                $question['validations']['auto_format']['value'] = '00/0000';
                $question['validations']['auto_format']['precision'] = '';
            }
            else if($cq['dateGranularity'] == 'YEAR')
            {
                $question['validations']['format']['value'] = $this->autoFmtMap['YEAR'];
                $question['validations']['format']['error_message'] = 'Invalid Format';
                $question['validations']['placeholder_text']['value'] = $this->autoFmtMap['YEAR'];
                $question['validations']['placeholder_text']['error_message'] = '';
                $question['validations']['auto_format']['type'] = '';
                $question['validations']['auto_format']['value'] = '0000';
                $question['validations']['auto_format']['precision'] = '';
            }
            else
            {
                $question['validations']['format']['value'] = $this->autoFmtMap['DATE'];
                $question['validations']['format']['error_message'] = 'Invalid Format';
                $question['validations']['placeholder_text']['value'] = $this->autoFmtMap['DATE'];
                $question['validations']['placeholder_text']['error_message'] = '';
                $question['validations']['auto_format']['type'] = '';
                $question['validations']['auto_format']['value'] = '00/0000';
                $question['validations']['auto_format']['precision'] = '';
            }
        }
        if($questionType == 'NUMBER')
        {
            $question['validations']['pattern']['value'] = "/^[0-9]*$/";
            $question['validations']['pattern']['error_message'] = 'Please enter valid input';
        }
        $this->addValidationsToQuestion($question);
        if($provided['BH_OwnerResidenceState'] != 'StateNY' || $is_original_question || $ques['child_questions'])
        {
            $question['child_questions'] = true;
        }
        $question['sequence_number'] = $ques['sequence_number'] + 1;
        $question['mras_question_type'] = 'Disclosure';
        if(isset($cq['triggeredCondAlias']))
        {
            $question['triggeredCondAlias'] = $cq['triggeredCondAlias'];
        }
        $question['parent_id'] = $ques['question_id'];
        if(!empty($ques['parent_disclosure_id']))
        {
            $question['parent_disclosure_id'] = $ques['parent_disclosure_id'];
        }
        $question['question_status'] = 'invalid';
        
        return $question;
    }

    public function prepareListTypeQuestion($cq,$ques, $provided)
    {
        $listQuestion = array();
        $listQuestion['question_text'] = $cq['questionText'];
        $listQuestion['display_type'] = 'list';
        $listQuestion['question_type'] = 'group';
        $cq['questionText'] = '';
        $listQuestion["validations"]["required"]["value"] = true;
        $listQuestion["validations"]["required"]["error_message"] = "";
        $original_questions = $this->convertChildQuestion($cq,$ques,$provided, true);
        $original_questions['disclosure_source'] = 'SEARCH';
        $original_questions['disclosure_id'] = $cq['disclosure_id']; 
        $listQuestion['original_questions'][] = $original_questions;
        $listQuestion["add_button_text"] = "Add Another";
        $listQuestion['question_id'] = $ques['question_id'].'_list';
        $listQuestion["is_edit_icon_visible"] = false;
        $listQuestion["is_hidden"] = false;
        $listQuestion['is_mras_question'] = true;
        $listQuestion['child_questions_completed_flag'] = false;
        $listQuestion['question_status'] = 'invalid';
        //unset($listQuestion['questions']);
        return $listQuestion;
    }

    public function prepareListTypeBaseQuestion($cq,$ques)
    {
        $listQuestion = array();
        $listQuestion['question_text'] = $cq['questionText'];
        $listQuestion['display_type'] = 'list';
        $listQuestion['question_type'] = 'group';
        $cq['questionText'] = '';
        $listQuestion["validations"]["required"]["value"] = true;
        $listQuestion["validations"]["required"]["error_message"] = "";
        unset($ques['question_text']);
        //unset($ques['response_options']);
        $original_questions=$ques;
        $original_questions['question_id'] = $ques['question_id'].'_original_ques';
        $original_questions['parent_list_id'] = $ques['question_id'];
        //$this->_displayType($original_questions,$cq);//this->convertChildQuestion($cq,$ques);
        $original_questions['disclosure_source'] = 'SEARCH';
        $original_questions['disclosure_id'] = $cq['disclosure_id']; 
        $original_questions["validations"]["required"]["value"] = true;
        $original_questions["validations"]["required"]["error_message"] = "";
        $original_questions['is_hidden'] = false;
        $listQuestion['original_questions'][] = $original_questions;
        $listQuestion["add_button_text"] = "Add Another";
        $listQuestion['question_id'] = $ques['question_id'];
        $listQuestion["is_edit_icon_visible"] = false;
        $listQuestion["is_hidden"] = true;
        $listQuestion['is_mras_question'] = true;
        return $listQuestion;
    }
    private function updateQuestionAndDisplayType(&$cq,$type)
    {
        if($type == 'breadcrumb')
        {
            $cq['question_type']='group';
            $cq['display_type']='breadcrumb';
        }
        else if($type == 'group')
        {
            $cq['question_type']='group';
            $cq['display_type']='questions_group';
        }
    }

    private function _defaults(&$question, $parent_qid, $parent_lid, $cq) {
        $question['question_id'] = $cq['code'] ;
        $question['question_text'] = $cq['text'] ?? $cq['questionText'];
        //$question['question_status'] = 'missing';
        $question['question_type'] = '';
        $question['display_type'] = '';
        $question['response_options'] = array();
        $question['response'] = '';
        $question['validations'] = array();
        $question['properties'] = [];
        //$question['validations']['required']['value'] = 'false';
        $question['child_questions'] = false;
        $question['index'] = '';
        $question['presentation_type'] = '';
        $question['sequence_number'] = $this->sequence_num;
        $this->sequence_num=$this->sequence_num+5;
        $question['hint_text'] = '';
        $question['sureify_meta']['source_id'] = 1;
        $question['is_mras_question'] = true;
    }

    private function _boolVals(&$question, $cq) {
        $question['is_editable'] = true;
        $question['is_readonly'] = false;
        $question['is_reviewable'] = true;
        $question['is_edit_icon_visible'] = false;
        if(isset($cq['properties']['naheader']))
        {
            $question['is_hidden'] = true;
        }
        else if(isset($cq['visible']))
        {
            $question['is_hidden'] = !$cq['visible'];
        }
        else
        {
            $question['is_hidden'] = false;
        }
    }

    private function _responseOptions(&$question, $cq, $provided, $parent_qid='', $childQuestions = false) {
        $yes = array(
            'id' => $cq['code'] . '_Yes',
            'label' => 'Yes',
        );
        $no = array(
            'id' => $cq['code'] . '_No',
            'label' => 'No',
        );
        $question['response_options'][] = $yes;
        $question['response_options'][] = $no;
        if($provided['BH_OwnerResidenceState'] != 'StateNY' || $childQuestions)
        {
            $question['child_questions_on'][]=$cq['code'] . '_Yes';
            $question['child_questions_on'][]=$cq['code'] . '_No';
        }
    }

    public function _displayType(&$question, $cq, $provided, $parent_qid = '', $childQuestions = false) {
        $answerType = $cq['answerType'];
        $question['display_type'] = $this->dataTypeDisplayTypeMap[$answerType];
        $question['question_type'] = $this->questionTypeMap[$answerType];
        if($answerType == 'YES_NO' || $answerType=='BOOLEAN'){
            $this->_responseOptions($question, $cq, $provided, $parent_qid, $childQuestions);
        }
        else {
            if( $answerType == 'PICKLIST' || $answerType == 'PICK_LIST')
            {
                $picklist = $cq['picklistQuestionMeta']['picklistItems'];
                if(!empty($picklist) && $question['mras_question_type'] == 'Base')
                {
                    $picklist[] = 'None of the Above';
                }
            }
            else if ($answerType == 'SELECTION')
            {
                $picklist = $cq['options'];
            }
            else if ($answerType == 'SEARCH')
            {
                $picklist = $cq['conditions'];
            }
            else if ($answerType == 'ENTER_DETAILS')
            {

                $case_id = $this->case_id;////RedisUtility::getQuoteInformation($_POST['uid'])['external_uuid'];
                $url=BRIGHTHOUSE_URL.$case_id.'/lives/1/interviews/TI/conditions/';
                $disclosure = $cq;
                $picklist =$this->questionnaire_mras->getDisclosure($cq['category']['code'],'',$url,'GET')['conditions'];
                
            }
            if(empty($picklist))
            {
                $question['is_hidden'] = true;
            }
            foreach( $picklist as $picklistItem)
            {
                $item = array(
                    'id' => $picklistItem,
                    'label' => $picklistItem,
                );
                $question['response_options'][] = $item;
                $question['child_questions_on'][]=$picklistItem;
            }
        }
    }
}