<?php
/**
 * DokuWiki Select2Go plugin (Syntax Component)
 *
 * Page/link menu select box enhanced by jQuery Select2
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Ikuo Obataya <I.Obataya@gmail.com>
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * DokuWiki Select2Go plugin is based on Select plugin by Ikuo Obataya
 * @see also https://www.dokuwiki.org/plugin:select
 * 
 * Select2 is a jQuery-based replacement for select boxes,
 * which is Licenced under the MIT License (MIT)
 * @see also https://select2.github.io/
 *
 * SYNTAX:
 *   <select width msg="message, please choose">
 *     * [[.:page0|title0]]
 *   group A
 *     *![[.:pageA1|titleA1]]     (default selection)
 *     * [[.:pageA2|titleA2]]
 *     * titleA3
 *   </select>
 *
 * OUTPUT:
 *   <select onchange="javascript:plugin_select2_jump(this)">
 *     <option>message, please choose</option>
 *     <option>title0</option>
 *     <optgroup label="group A">
 *       <option selected="selected">titleA1</option>
 *       <option>titleA2</option>
 *       <option disabled="disabled">titleA3</option>
 *     </optgroup>
 *   </select>
 *
 */

use dokuwiki\File\PageResolver;

class syntax_plugin_select2go extends DokuWiki_Syntax_Plugin
{
    protected $pattern = '<select\b.+?</select>';
    protected $mode = 'plugin_select2go';

    public function getType() { return 'substition';}
    public function getPType() { return 'block';}
    public function getSort(){return 168;}

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern($this->pattern, $mode, $this->mode);
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $ID;

        $match = substr($match, 7, -9);  // strip markup
        list($params, $match) = explode('>', $match, 2);

        // parameters for select tag
        $param = $this->getArguments($params);
        if (!array_key_exists('useSelect2', $param)) {
            $param['useSelect2'] = $this->getConf('force_select2');
        }
        if (array_key_exists('size', $param) && !array_key_exists('width', $param)) {
            $param['width'] = $param['size'];
        }
        $param['size'] = 1; // <select size="1"></select>
        if (array_key_exists('height', $param)) {
            $param['width_onFocus'] = $param['height'];
            unset($param['height']);
        }
        if (!array_key_exists('msg', $param)) {
            $param['msg'] = $this->getLang('guidance_msg');
        }
        // register message as first option
        $optgroup = 0;
        if ($param['msg'] !== false) {
            $entry[] = array(
                    'tag'      => 'option',
                    'group'    => $optgroup,   // group 0 memeber will not grouped.
                    'id'       => '',
                    'title'    => $param['msg'],
                    'selected' => false,
                    'disabled' => false,
            );
        }

        // options in select box
        $items = explode("\n", trim($match,"\n"));
        $pattern = '/( {2,}|\t{1,})\*/';
        $is_legacy_syntax = (!preg_match($pattern, $match)) ? true : false;

        for ($i = 0; $i < count($items); $i++) {
            $selected = false;
            $disabled = false;
            if (empty($items[$i])) continue;

            // check whether item is list
            if (!preg_match('/( {2,}|\t{1,})\*/', $items[$i])) {
                if ($is_legacy_syntax) {
                // option given in legacy syntax
                    list($id, $title) = array_pad(explode('|', trim($items[$i]), 2), 2, '');
                    if (empty($title)) $title = $id;
                    $entry[] = array( 'tag'=>'option', 'group'=>$optgroup,
                                      'id'=>$id, 'title'=>$title);
                    continue;
                }
                // new optgroup: group 0 member will not grouped
                $optgroup++;
                $entry[] = array(
                    'tag'      => 'optgroup',
                    'group'    => $optgroup,
                    'title'    => trim($items[$i]),
                );
            } else {
                // option
                if (preg_match('/(.)\[\[(.+?)\]\]/', $items[$i], $match)) {
                    // link item
                    list($id, $title) = array_pad(explode('|', $match[2], 2), 2, '');
                    if ($match[1] == '!') $selected = true;
                } else {
                    // text item (disabled option)
                    $id = '';
                    $title = explode('*', $items[$i])[1];
                    $disabled = true;
                }
                $entry[] = array(
                    'tag'      => 'option',
                    'group'    => $optgroup,
                    'id'       => $id,
                    'title'    => $title,
                    'selected' => $selected,
                    'disabled' => $disabled,
                );
            }
        }
        return array($param, $entry);
    }

    /**
     * Create output
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        global $ID, $conf;

        list($param, $items) = $data;

        if ($format == 'xhtml') {
            $html  = '<select';
            $html .= ($param['useSelect2']) ? ' class="select_menu"' : '';
            if (array_key_exists('width',$param)) {
                $html .= ' style="width:'.$param['width'].';"';
            }
            if (array_key_exists('width_onFocus',$param)) {
                $html .= ' onFocus="this.style.width='."'".$param['width_onFocus']."'".'"';
                $html .= ' onBlur="this.style.width='."'".$param['width']."'".'"';
            }
            $html .= '>'.DOKU_LF;

            // loop for each option item
            $optgroup = 0;
            foreach ($items as $entry) {
                // optgroup tag
                if ($entry['tag'] == 'optgroup') { // optgroup changed
                    // optgroup 0 member will not grouped
                    // do not need to close optgroup tag if new group is 1
                    $html .= ($entry['group'] > 1) ? '</optgroup>'.DOKU_LF : '';
                    $html .= '<optgroup label="'.$entry['title'].'">'.DOKU_LF;
                    $optgroup = $entry['group'];
                    continue;
                }

                // option tag
                // The following code is partly identical to
                // internallink function in /inc/parser/handler.php

                //decide which kind of link it is
                if (empty($entry['id'])) { // disabled option
                    $url = '';
                    $target = '';
                } elseif (preg_match('/^[a-zA-Z\.]+>{1}.*$/u',$entry['id'])) {
                // Interwiki
                    $interwiki = explode('>',$entry['id'],2);
                    $url = $renderer->_resolveInterWiki($interwiki[0],$interwiki[1]);
                    $target = $conf['target']['interwiki'];
                } elseif (preg_match('/^\\\\\\\\[^\\\\]+?\\\\/u',$entry['id'])) {
                // Windows Share
                    $url = $arg1;
                    $target = $conf['target']['windows'];
                } elseif (preg_match('#^([a-z0-9\-\.+]+?)://#i',$entry['id'])) {
                // external link (accepts all protocols)
                    $url = $entry['id'];
                    $target = $conf['target']['extern'];
                } elseif (preg_match('!^#.+!',$entry['id'])) {
                // local link
                    //$url = substr($entry['id'],1); // strip #
                    $url = $entry['id'];
                    $target = $conf['target']['wiki'];
                } else {
                // internal link
                    if (class_exists('dokuwiki\File\PageResolver')) {
                        // DW 2022-07-31 and later
                        $Resolver = new PageResolver(getNS($ID));
                        $entry['id'] = $Resolver->resolveId($entry['id']);
                        $exists = page_exists($entry['id']);
                    } else {
                        resolve_pageid(getNS($ID),$entry['id'],$exists);
                    }
                    $url = wl($entry['id']);
                    $target = $conf['target']['wiki'];
                }

                // output option element
                $html .= '<option';
                $html .= (!empty($entry['selected'])) ? ' selected="selected"' : '';
                $html .= (!empty($entry['disabled'])) ? ' disabled="disabled"' : '';
                $html .= ' value="'.$target.'|'.hsc($url).'"';
                $html .= ' title="'.(isset($exists) ? $entry['id'] : hsc($url)).'"';
                if (isset($exists)) {
                    $html .= ' class="';
                    $html .= ($exists) ? 'wikilink1' : 'wikilink2';
                    $html .= '"';
                }
                $html .= '>';
                $html .= empty($entry['title']) ? $entry['id'] : hsc($entry['title']);
                $html .= '</option>'.DOKU_LF;
                unset($exists);
            }
            if ($optgroup > 1) $html .= '</optgroup>'.DOKU_LF;
            $html .= '</select>'.DOKU_LF;

            $renderer->doc .=$html;
            return true;
        }
        return false;
    }


    /* ---------------------------------------------------------
     * get each named/non-named arguments as array variable
     *
     * Named arguments is to be given as key="value" (quoted).
     * Non-named arguments is assumed as boolean.
     *
     * @param string $args   arguments
     * @return array     parsed arguments in $arg['key']=value
     * ---------------------------------------------------------
     */
    public function getArguments($args = '')
    {
        $arg = array();
        if (empty($args)) return $arg;

        // get named arguments (key="value"), ex: width="100"
        // value must be quoted in argument string.
        $val = "([\"'`])(?:[^\\\\\"'`]|\\\\.)*\g{-1}";
        $pattern = "/\b(\w+)=($val) ?/";
        preg_match_all($pattern, $args, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $arg[$match[1]] = substr($match[2], 1, -1); // drop quates from value string
            $args = str_replace($match[0], '', $args); // remove parsed substring
        }

        // get named numeric value argument, ex width=100
        // numeric value may not be quoted in argument string.
        $val = '\d+';
        $pattern = "/\b(\w+)=($val) ?/";
        preg_match_all($pattern, $args, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $arg[$match[1]] = (int)$match[2];
            $args = str_replace($match[0], '', $args); // remove parsed substring
        }

        // get non-named arguments
        $tokens = preg_split('/\s+/', $args);
        foreach ($tokens as $token) {

            // get size parameters specified as non-named arguments
            // assume as single size or eles width and height pair
            //  ex: 85% |  256x256 | 800,600px | 85%,300px
            $pattern = '/(\d+(\%|em|pt|px)?)(?:[,xX]?(\d+(\%|em|pt|px)?))?$/';
            if (preg_match($pattern, $token, $matches)) {
                //error_log('helper matches: '.count($matches).' '.var_export($matches, 1));
                if ((count($matches) > 4) && empty($matches[2])) {
                    $matches[2] = $matches[4];
                    $matches[1] = $matches[1].$matches[4];
                }
                if (count($matches) > 3) {
                    $arg['width']  = $matches[1];
                    $arg['height'] = $matches[3];
                } else {
                    $arg['size'] = $matches[1];
                }
            }

            // get flags, ex: showdate, noshowfooter
            if (preg_match('/^(?:!|not?)(.+)/', $token, $matches)) {
                // denyed/negative prefixed token
                $arg[$matches[1]] = false;
            } elseif (preg_match('/^[A-Za-z]/', $token)) {
                $arg[$token] = true;
            }
        }
        return $arg;
    }

}
