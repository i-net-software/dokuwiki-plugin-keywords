<?php
/**
 * DokuWiki Plugin keywords (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Gerry Weißbach <tools@inetsoftware.de>
 */
use dokuwiki\HTTP\DokuHTTPClient;
use \dokuwiki\Logger;

class action_plugin_keywords_keywords extends \dokuwiki\Extension\ActionPlugin
{
    private $CHATGPT_API_URL = "https://api.openai.com/v1/chat/completions";

    /** @inheritDoc */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'handleFormEditOutput');
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handleCommonWikipageSave');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handleMetaheaderOutput');
    }
    
    /**
     * Event handler for TPL_METAHEADER_OUTPUT
     *
     * @see https://www.dokuwiki.org/devel:event:TPL_METAHEADER_OUTPUT
     * @param Doku_Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleFormEditOutput(Doku_Event $event, $param) {
        global $lang;

        // if no API key is set, do not show the option
        if ( empty( $this->getConf('APIKey') ) ) {
            return;
        }

        /** @var Doku_Form||\dokuwiki\Form\Form $form */
        $form =& $event->data;

        if (is_a($form, \dokuwiki\Form\Form::class) ) {
            $form->addHTML(' ');
            $form->addCheckbox('updateKeywords', $this->getLang('updateKeywords'))->id('edit__updateKeywords')->addClass('nowrap')->val('1');
        }

        if (is_a($form, Doku_Form::class)  ) {
            $form->addElement( form_makeCheckboxField( 'updateKeywords', '1', $this->getLang('updateKeywords'), 'edit__updateKeywords', 'nowrap' ) );
        }
    }

    /**
     * Event handler for COMMON_WIKIPAGE_SAVE
     *
     * @see https://www.dokuwiki.org/devel:event:COMMON_WIKIPAGE_SAVE
     * @param Doku_Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleCommonWikipageSave(Doku_Event $event, $param) {
        global $INPUT;

        $hasUpdateKeywords = $INPUT->bool( 'updateKeywords', false );
        if ( !$hasUpdateKeywords ) {
            return;
        }

        $requestContent = $event->data['newContent'];
        $requestContent = trim( preg_replace( '/\{\{keywords>.*?\}\}/is' , '', $requestContent ) );
        if ( empty( $requestContent ) ) {
            $event->data['contentChanged'] = 1;
            $event->data['newContent'] = '';
            return;
        }

//*     // Remove one slash to have a sample of keywords saved instead of checking with chatGPT
        $httpClient = new DokuHTTPClient();
        // $httpClient->debug = true;
        $httpClient->headers = [
            'Authorization' => 'Bearer ' . $this->getConf('APIKey'),
            'Content-Type' => 'application/json',
        ];
        $status = $httpClient->sendRequest($this->CHATGPT_API_URL, json_encode( [
            'model' => $this->getConf('APIModel'),
            'messages' => [
                [ "role" => "system", "content" => $this->getConf('APIRole')  ],
                [ "role" => "user", "content" => $requestContent ]
            ],
            "functions" => [[
                "name" => "keywords_for_text",
                "description" => "Use this function to sent the list of keywords back to the dokuwiki. The Input is the array of keywords",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "keywords" => [
                            "type" => "array",
                            "items" => [
                                "type" => "string"
                            ],
                            "description" => "This is the list of keywords generated from the input. Each keyword is given as string."
                        ]
                    ]
                ]
            ],[
                "name" => "no_content",
                "description" => "Use this function to indicate that no kexwords could be generated.",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "empty" => [
                            "type" => "string",
                            "description" => "This parameter must always be NULL."
                        ]
                    ]
                ]
            ]]
        ] ), 'POST');

        $data = json_decode( $httpClient->resp_body );

        if ( $status === false ) {
            Logger::error( "An error occurred during the Chat GPT call", $httpClient->error, __FILE__, __LINE__ );
            return;
        } else if ( $data->error ) {
            Logger::error( "An error occurred during the Chat GPT call", $data->error->message, __FILE__, __LINE__ );
            return;
        }

        if ( $data->choices[0]->message->function_call->name == "keywords_for_text" ) {
            $arguments = json_decode( $data->choices[0]->message->function_call->arguments );
            $keywords = $arguments->keywords;
            Logger::debug( "Chat GPT response", $keywords, __FILE__, __LINE__ );
        } else {
            Logger::debug( "INVALID Chat GPT response", $data->choices[0]->message, __FILE__, __LINE__ );
        }

        if ( empty( $keywords ) || !is_array( $keywords ) ) {
            // there is no content herre.
            if ( $requestContent == $event->data['newContent'] ) {
                // Nothing has changed, return
                return;
            }

            // The keywords are empty now.
            $event->data['contentChanged'] = 1;
            $event->data['newContent'] = $requestContent;
            return;
        }
/*/
        $keywords = [ "word1", "word2", "word3", "word4" ];
//*/

        $event->data['contentChanged'] = 1;
        $event->data['newContent'] = '{{keywords>' . implode( ", ", $keywords ) . '}}' . "\n\n" . $requestContent;
    }

    /**
     * Prints keywords to the meta header
     * Event handler for TPL_METAHEADER_OUTPUT
     *
     * @see https://www.dokuwiki.org/devel:event:TPL_METAHEADER_OUTPUT
     * @param Doku_Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     * @author Ilya Lebedev <ilya@lebedev.net>
     */
    public function handleMetaheaderOutput(Doku_Event $event, $param) {
        global $ID;
        global $conf;

        if (empty($event->data) || empty($event->data['meta'])) return; // nothing to do for us
        
        //Check if keywords are exists
        $kw = p_get_metadata($ID,'keywords');
        if (empty($kw)) return;

        for ($i=0;$i<sizeof($event->data['meta']);$i++) {
            $h = &$event->data['meta'][$i];
            if ('keywords' == $h['name']) {
                $h['content'] .= $kw;
                break;
            }
        }
    }
}

