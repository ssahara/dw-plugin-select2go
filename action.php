<?php
/**
 * DokuWiki Select2 plugin (Action Component)
 *
 * Page/link menu select box enhanced by jQuery Select2
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Ikuo Obataya <I.Obataya@gmail.com>
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * DokuWiki Select2 plugin is based on Select plugin by Ikuo Obataya
 * @see also https://www.dokuwiki.org/plugin:select
 *
 * Select2 is a jQuery replacement for select boxes,
 * which is Licenced under the MIT License (MIT)
 * @see also https://select2.github.io/
 *
 */
if(!defined('DOKU_INC')) die();

class action_plugin_select2 extends DokuWiki_Action_Plugin {

    // register hook
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'load_select2');
    }

    /**
     * register Select2 script and css
     */
    function load_select2(Doku_Event $event, $params) {
    
        $event->data['script'][] = array(
            'type'    => 'text/javascript',
            'charset' => 'utf-8',
            'src'     => DOKU_REL.'lib/plugins/select2/select2/js/select2.min.js',
            '_data'   => '',
        );
        $event->data['link'][] = array(
            'rel'     => 'stylesheet',
            'type'    => 'text/css',
            'href'    => DOKU_REL.'lib/plugins/select2/select2/css/select2.min.css',
        );
    }

}
