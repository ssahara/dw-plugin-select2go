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
 * @see also http://ivaynberg.github.io/select2/
 *
 * 1. allow list style for option items
 * 2. cosmetics: add css class for internal links
 * 3. new parameter to specify initial selected item
 * 4. new parameter for combobox width

  SYNTAX:
   <select width guidetext>
     * [[.:page0|title0]]
   group1
     * [[.:page1a|title1a]]
     * [[.:page2b|title1b]]
   </select>

   OUTPUT:
   <select>
     <option >...</option>
     <optgroup label="group1">
       <option >...</option>
       <option >...</option>
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

        $match = substr($match, 7, -9);  // strip markup
        list($params, $match) = explode('>', $match, 2);
        $items = explode("\n", trim($match,"\n"));

        // parameters for select tag
        $tokens = preg_split('/\s+/', $params);
        $params = array();
        $params['size'] = 1; // <select size="1"></select>
        foreach ($tokens as $token) {

            if (preg_match('/^s=(\d+)/',$token,$matches)){
            // default selection
                if ($matches[1] < count($items)-1)
                    $params['default'] = $matches[1];
                continue;
            } elseif (preg_match('/^2/',$token,$matches)) {
                // markup was <select2 ...>  </select>
                $params['useSelect2'] = true;
                continue;
            } elseif (preg_match('/^(\d+(px)?)\s*([,]\s*(\d+(px)?))?/',$token,$matches)){
            // width and width_Blur
                if ($matches[4]) {
                    // width and width_Blur was given
                    $params['width'] = $matches[1];
                    if (!$matches[2]) $params['width'].= 'px';
                    $params['width_Blur'] = $matches[4];
                    if (!$matches[5]) $params['width_Blur'].= 'px';
                    continue;
                } elseif ($matches[2]) {
                    // only width was given
                    $params['width'] = $matches[1];
                    if (!$matches[2]) $params['width'].= 'px';
                    continue;
                }
            }
            // unmatched tokens constitute message
            $message.= (empty($message) ? '' : ' ').$token;
        }
        // register message as option
        $optgroup_id = 0;
        $option[] = array(
                'optgroup_id' => $optgroup_id,   // group 0 memeber will not grouped.
                'id'    => '',
                'title' => empty($message) ? $this->getLang('guidance_msg') : $message,
                'attr'  => empty($params['default']) ? '' : 'disabled',
            );

        // options to be selected
        for ($i = 0; $i < count($items); $i++) {
            if (empty($items[$i])) continue;
//msg('handle optgroup='.$optgroup_id, 0);
            // check whether item is list
            if ( !preg_match('/( {2,}|\t{1,})\*/', $items[$i])) {
                // optgroup
                // リストでないものが出てきたら、グループをつくる。グループ0はグループ化しない。
                $optgroup_id++;
                $optgroup_label[$optgroup_id] = trim($items[$i]);
            } else {
                // option
                if (preg_match('/\[\[(.+?)\]\]/', $items[$i], $match)) {
                    // link option
                    list($id,$title) = explode('|', $match[1], 2);
                    $attr  = '';
                } else {
                    // disabled option (it is text, not link item)
                    $id = '';
                    $title = explode('*', $items[$i])[1];
                    $attr  = 'disabled';
                }
                $option[] = array(
                    'optgroup_id' => $optgroup_id,
                    'id'    => $id,
                    'title' => $title,
                    'attr'  => $attr,
                );
            }
        }
        return array($params, $optgroup_label, $option);
    }

    /**
     * Create output
     */
    public function render($mode, &$renderer, $data) {
        global $ID, $conf;

        list($params, $optgroup_label, $options) = $data;

        if($mode == 'xhtml'){
            $html = '<form class="dw_pl_select">'.NL;
            $html.= '<select';
            $html.= ($params['useSelect2']) ? ' class="select_menu"' : '';
            $html.= ' onChange="javascript:plugin_select_jump(this)"';
            if (!empty($params['width'])) {
                $html.= ' style="width:'.$params['width'].';"';
                $html.= ' onFocus="this.style.width='."'".$params['width']."'".'"';
            }
            if (!empty($params['width_onBlur'])) {
                $html.= ' onBlur="this.style.width='."'".$params['width_onBlur']."'".'"';
            }
            $html.= '>'.NL;

            // loop for each option item
            $optgroup_id = 0;
            foreach($options as $option){
                // optgroup
//msg('i='.$optgroup_id.' option[optgroup]='.$option['optgroup_id'], 0);
                if ($option['optgroup_id'] !== $optgroup_id) {
                    // グループが変わったら…
                    if ($option['optgroup_id'] > 1) {
                        //グループ0はグループかされていない。グループ1の場合は前のグループを閉じる必要はない
                        $html.= '</optgroup>'.NL;
//msg('closed',0);
                    }
                    $optgroup_id = $option['optgroup_id'];
                    $html.= '<optgroup label="'.$optgroup_label[$option['optgroup_id']].'">'.NL;
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
                $html.= '<option'. (empty($option['attr']) ? '' : ' '.$option['attr']);
                $html.= ' value="'.$target.'|'.hsc($url).'"';
                $html.= ' title="'.(isset($exists) ? $option['id'] : hsc($url)).'"';
                $html.= ($exists === true)  ? ' class="wikilink1"' : '';
                $html.= ($exists === false) ? ' class="wikilink2"' : '';
                $html.= '>';
                $html.= empty($option['title']) ? $option['id'] : hsc($option['title']);
                $html.= '</option>'.NL;
                unset($exists);
            }
            if ($optgroup_id > 1) $html.= '</optgroup>'.NL;
            $html.= '</select>'.NL;
            $html.= '</form>'.NL;

            $renderer->doc.=$html;
            return true;
        }
        return false;
    }

}

