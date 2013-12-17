<?php
/**
 * DokuWiki select2 plugin (Syntax Component)
 *
 * Page menu select box enhanced by jQuery Select2
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Select2 is a jQuery based replacement for select boxes
 * coded by Igor Vaynberg.
 * Licenced under the Apache Software Foundation License v2.0 and GPL 2.
 * @see also http://ivaynberg.github.io/select2/
 *
  SYNTAX:
   <select width guidetext>
     * [[.:page0|title0]]
   group A
     *![[.:pageA1|titleA1]]     (default selection)
     * [[.:pageA2|titleA2]]
     * titleA3
   </select>

   OUTPUT:
   <select>
     <option>guidetext</option>
     <option>title0</option>
     <optgroup label="group A">
       <option selected="selected">titleA1</option>
       <option>titleA2</option>
       <option disabled="disabled">titleA3</option>
     </optgroup>
   </select>

 */
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_select2 extends DokuWiki_Syntax_Plugin {
    public function getType() { return 'substition';}
    public function getPType() { return 'block';}
    public function getSort(){return 168;}
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<select2?.+?</select2?>', $mode, 'plugin_select2');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos) {
        global $ID;

        $param = array();
        if (preg_match('/^.select2 /',$match)) {
            // markup was <select2 ...>...</select2>
            $param['useSelect2'] = true;
            $match = substr($match, 9, -10); // strip markup
        } else {
            // markup was <select ...>...</select>
            $match = substr($match, 8, -9);  // strip markup
        }
        list($params, $match) = explode('>', $match, 2);
        $items = explode("\n", trim($match,"\n"));

        // parameters for select tag
        $tokens = preg_split('/\s+/', $params);
        $param['size'] = 1; // <select size="1"></select>
        if (empty($param['useSelect2'])) {
            $param['useSelect2'] = $this->getConf('force_select2');
        }

        foreach ($tokens as $token) {

            if (preg_match('/^(\d+(px|%)?)\s*([,]\s*(\d+(px|%)?))?/',$token,$matches)){
            // width and width_Blur
                if ($matches[4]) {
                    // width and width_Blur was given
                    $param['width'] = $matches[1];
                    if (!$matches[2]) $param['width'].= 'px';
                    $param['width_Blur'] = $matches[4];
                    if (!$matches[5]) $param['width_Blur'].= 'px';
                    continue;
                } elseif ($matches[2]) {
                    // only width was given
                    $param['width'] = $matches[1];
                    if (!$matches[2]) $param['width'].= 'px';
                    continue;
                }
            }
            // unmatched tokens constitute message
            $message.= (empty($message) ? '' : ' ').$token;
        }
        // register message as first option
        $optgroup = 0;
        $option[] = array(
                'group'    => $optgroup,   // group 0 memeber will not grouped.
                'id'       => '',
                'title'    => empty($message) ? $this->getLang('guidance_msg') : $message,
                'selected' => false,
                'disabled' => false,
            );

        // options to be selected
        for ($i = 0; $i < count($items); $i++) {
            $selected = false;
            $disabled = false;
            if (empty($items[$i])) continue;

            // check whether item is list
            if ( !preg_match('/( {2,}|\t{1,})\*/', $items[$i])) {
                // new optgroup
                // optgroup 0 member will not grouped
                $optgroup++;
                $optgroup_label[$optgroup] = trim($items[$i]);
            } else {
                // option
                if (preg_match('/(.)\[\[(.+?)\]\]/', $items[$i], $match)) {
                    // link option
                    list($id, $title) = explode('|', $match[2], 2);
                    if($match[1] == '!') $selected = true;
                } else {
                    // disabled option (it is text, not link item)
                    $id = '';
                    $title = explode('*', $items[$i])[1];
                    $disabled = true;
                }
                $option[] = array(
                    'group'    => $optgroup,
                    'id'       => $id,
                    'title'    => $title,
                    'selected' => $selected,
                    'disabled' => $disabled,
                );
            }
        }
        return array($param, $optgroup_label, $option);
    }

    /**
     * Create output
     */
    public function render($mode, &$renderer, $data) {
        global $ID, $conf;

        list($param, $optgroup_label, $options) = $data;

        if($mode == 'xhtml'){
            $html = '<form class="dw_pl_select">'.NL;
            $html.= '<select';
            $html.= ($param['useSelect2']) ? ' class="select_menu"' : '';
            $html.= ' onChange="javascript:plugin_select_jump(this)"';
            if (!empty($param['width'])) {
                $html.= ' style="width:'.$param['width'].';"';
                $html.= ' onFocus="this.style.width='."'".$param['width']."'".'"';
            }
            if (!empty($param['width_onBlur'])) {
                $html.= ' onBlur="this.style.width='."'".$param['width_onBlur']."'".'"';
            }
            $html.= '>'.NL;

            // loop for each option item
            $optgroup = 0;
            foreach($options as $option){
                // optgroup
                if ($option['group'] !== $optgroup) { // optgroup changed
                    // optgroup 0 member will not grouped
                    // do not need to close optgroup tag if new group is 1
                    $html.= ($option['group'] > 1) ? '</optgroup>'.NL : '';
                    $html.= '<optgroup label="'.$optgroup_label[$option['group']].'">'.NL;
                    $optgroup = $option['group'];
                }

                // The following code is partly identical to
                // internallink function in /inc/parser/handler.php

                //decide which kind of link it is
                if (empty($option['id'])) { // disabled option
                    $url = '';
                    $target = '';
                } elseif ( preg_match('/^[a-zA-Z\.]+>{1}.*$/u',$option['id']) ) {
                // Interwiki
                    $interwiki = explode('>',$option['id'],2);
                    $url = $renderer->_resolveInterWiki($interwiki[0],$interwiki[1]);
                    $target = $conf['target']['interwiki'];
                } elseif ( preg_match('/^\\\\\\\\[^\\\\]+?\\\\/u',$option['id']) ){
                // Windows Share
                    $url = $arg1;
                    $target = $conf['target']['windows'];
                } elseif ( preg_match('#^([a-z0-9\-\.+]+?)://#i',$option['id']) ){
                // external link (accepts all protocols)
                    $url = $option['id'];
                    $target = $conf['target']['extern'];
                } elseif ( preg_match('!^#.+!',$option['id']) ){
                // local link
                    $url = substr($option['id'],1); // strip #
                    $target = $conf['target']['wiki'];
                }else{
                // internal link
                    resolve_pageid(getNS($ID),$option['id'],$exists);
                    $url = wl($option['id']);
                    $target = $conf['target']['wiki'];
                }

                // output option element
                $html.= '<option';
                $html.= ($option['selected']) ? ' selected="selected"' : '';
                $html.= ($option['disabled']) ? ' disabled="disabled"' : '';
                $html.= ' value="'.$target.'|'.hsc($url).'"';
                $html.= ' title="'.(isset($exists) ? $option['id'] : hsc($url)).'"';
                $html.= ($exists === true)  ? ' class="wikilink1"' : '';
                $html.= ($exists === false) ? ' class="wikilink2"' : '';
                $html.= '>';
                $html.= empty($option['title']) ? $option['id'] : hsc($option['title']);
                $html.= '</option>'.NL;
                unset($exists);
            }
            if ($optgroup > 1) $html.= '</optgroup>'.NL;
            $html.= '</select>'.NL;
            $html.= '</form>'.NL;

            $renderer->doc.=$html;
            return true;
        }
        return false;
    }

}
